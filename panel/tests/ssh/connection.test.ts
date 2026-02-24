import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { EventEmitter } from 'events';

// ---------------------------------------------------------------------------
// Hoisted fakes — declared before vi.mock() calls to avoid reference errors
// ---------------------------------------------------------------------------

// Minimal fake ClientChannel used in exec / execStream tests
class FakeChannel extends EventEmitter {
  stderr = new EventEmitter();

  emitData(chunk: string): void {
    this.emit('data', Buffer.from(chunk));
  }

  emitStderr(chunk: string): void {
    this.stderr.emit('data', Buffer.from(chunk));
  }

  emitClose(code: number = 0): void {
    this.emit('close', code);
  }
}

// Fake ssh2 Client — synchronously emits 'ready' so no timer is needed
class FakeClient extends EventEmitter {
  static lastInstance: FakeClient | null = null;

  private _fakeChannel: FakeChannel | null = null;

  constructor() {
    super();
    FakeClient.lastInstance = this;
  }

  connect(_config: unknown): this {
    // Emit synchronously — avoids any dependency on timers or micro-task queues
    Promise.resolve().then(() => this.emit('ready'));
    return this;
  }

  end(): void {
    Promise.resolve().then(() => this.emit('close'));
  }

  setFakeChannel(ch: FakeChannel): void {
    this._fakeChannel = ch;
  }

  exec(
    _command: string,
    callback: (err: Error | null, stream: FakeChannel) => void,
  ): this {
    const ch = this._fakeChannel;
    if (!ch) {
      // Call synchronously so the caller can set up listeners before emitting events
      callback(new Error('No fake channel configured'), null as unknown as FakeChannel);
      return this;
    }
    // Call synchronously so the caller's stream listeners are attached before
    // any test code emits data or close events on the channel
    callback(null, ch);
    return this;
  }
}

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

vi.mock('fs', () => ({
  readFileSync: vi.fn(() => Buffer.from('mock-private-key')),
}));

// Use a getter so the reference resolves after class declaration, not at hoist time
vi.mock('ssh2', () => ({
  get Client() {
    return FakeClient;
  },
}));

// ---------------------------------------------------------------------------
// Import the module under test AFTER all mocks are registered
// ---------------------------------------------------------------------------

import { SSHConnectionManager } from '../../src/ssh/connection.js';

// ---------------------------------------------------------------------------
// Shared config
// ---------------------------------------------------------------------------

const defaultConfig = {
  host: '127.0.0.1',
  port: 22,
  username: 'root',
  privateKeyPath: '/home/test/.ssh/id_rsa',
};

// ---------------------------------------------------------------------------
// Test suite — no fake timers needed for exec / execStream groups
// ---------------------------------------------------------------------------

describe('SSHConnectionManager — singleton', () => {
  beforeEach(() => {
    SSHConnectionManager.resetInstance();
    FakeClient.lastInstance = null;
  });

  afterEach(() => {
    SSHConnectionManager.resetInstance();
  });

  it('returns the same instance on repeated calls', () => {
    const a = SSHConnectionManager.getInstance();
    const b = SSHConnectionManager.getInstance();
    expect(a).toBe(b);
  });

  it('creates a fresh instance after resetInstance()', () => {
    const a = SSHConnectionManager.getInstance();
    SSHConnectionManager.resetInstance();
    const b = SSHConnectionManager.getInstance();
    expect(a).not.toBe(b);
  });
});

describe('SSHConnectionManager — connect / disconnect / status', () => {
  beforeEach(() => {
    SSHConnectionManager.resetInstance();
    FakeClient.lastInstance = null;
  });

  afterEach(async () => {
    await SSHConnectionManager.getInstance().disconnect();
    SSHConnectionManager.resetInstance();
    vi.restoreAllMocks();
  });

  it('resolves and marks connected=true on ready', async () => {
    const manager = SSHConnectionManager.getInstance();
    await manager.connect(defaultConfig);
    expect(manager.isConnected()).toBe(true);
  });

  it('notifies status listeners when connected', async () => {
    const manager = SSHConnectionManager.getInstance();
    const listener = vi.fn();
    manager.onStatusChange(listener);

    await manager.connect(defaultConfig);

    expect(listener).toHaveBeenCalledWith(true);
  });

  it('rejects when the client emits error before ready', async () => {
    vi.spyOn(FakeClient.prototype, 'connect').mockImplementation(function (
      this: FakeClient,
    ) {
      Promise.resolve().then(() =>
        this.emit(
          'error',
          Object.assign(new Error('auth failed'), { level: 'client-ssh' }),
        ),
      );
      return this;
    });

    const manager = SSHConnectionManager.getInstance();
    await expect(manager.connect(defaultConfig)).rejects.toThrow('client-ssh');
  });

  it('marks connected=false after disconnect', async () => {
    const manager = SSHConnectionManager.getInstance();
    await manager.connect(defaultConfig);

    await manager.disconnect();
    expect(manager.isConnected()).toBe(false);
  });

  it('notifies listeners on disconnect', async () => {
    const manager = SSHConnectionManager.getInstance();
    await manager.connect(defaultConfig);

    const listener = vi.fn();
    manager.onStatusChange(listener);

    await manager.disconnect();
    expect(listener).toHaveBeenCalledWith(false);
  });

  it('stops notifying after unsubscribing', async () => {
    const manager = SSHConnectionManager.getInstance();
    const listener = vi.fn();
    const unsub = manager.onStatusChange(listener);
    unsub();

    await manager.connect(defaultConfig);
    expect(listener).not.toHaveBeenCalled();
  });
});

describe('SSHConnectionManager — exec', () => {
  beforeEach(() => {
    SSHConnectionManager.resetInstance();
    FakeClient.lastInstance = null;
  });

  afterEach(async () => {
    await SSHConnectionManager.getInstance().disconnect();
    SSHConnectionManager.resetInstance();
    vi.restoreAllMocks();
  });

  it('rejects exec when not connected', async () => {
    const manager = SSHConnectionManager.getInstance();
    await expect(manager.exec('ls')).rejects.toThrow('not connected');
  });

  it('resolves with stdout from exec', async () => {
    const channel = new FakeChannel();

    vi.spyOn(FakeClient.prototype, 'connect').mockImplementation(function (
      this: FakeClient,
    ) {
      this.setFakeChannel(channel);
      Promise.resolve().then(() => this.emit('ready'));
      return this;
    });

    const manager = SSHConnectionManager.getInstance();
    await manager.connect(defaultConfig);

    // Emit data asynchronously so that exec() can attach listeners first
    // (exec awaits getChannel which suspends on a microtask boundary)
    const resultPromise = manager.exec('uptime');
    await Promise.resolve(); // let exec() resume and attach stream listeners
    channel.emitData('12:00 up 1 day\n');
    channel.emitClose(0);

    expect(await resultPromise).toBe('12:00 up 1 day\n');
  });

  it('rejects when command exits with non-zero code and stderr', async () => {
    const channel = new FakeChannel();

    vi.spyOn(FakeClient.prototype, 'connect').mockImplementation(function (
      this: FakeClient,
    ) {
      this.setFakeChannel(channel);
      Promise.resolve().then(() => this.emit('ready'));
      return this;
    });

    const manager = SSHConnectionManager.getInstance();
    await manager.connect(defaultConfig);

    const resultPromise = manager.exec('bad-command');
    await Promise.resolve(); // let exec() resume and attach stream listeners
    channel.emitStderr('command not found');
    channel.emitClose(127);

    await expect(resultPromise).rejects.toThrow('command not found');
  });
});

describe('SSHConnectionManager — execStream', () => {
  beforeEach(() => {
    SSHConnectionManager.resetInstance();
    FakeClient.lastInstance = null;
  });

  afterEach(async () => {
    await SSHConnectionManager.getInstance().disconnect();
    SSHConnectionManager.resetInstance();
    vi.restoreAllMocks();
  });

  async function setupConnectedManager(): Promise<{
    manager: SSHConnectionManager;
    channel: FakeChannel;
  }> {
    const channel = new FakeChannel();

    vi.spyOn(FakeClient.prototype, 'connect').mockImplementation(function (
      this: FakeClient,
    ) {
      this.setFakeChannel(channel);
      Promise.resolve().then(() => this.emit('ready'));
      return this;
    });

    const manager = SSHConnectionManager.getInstance();
    await manager.connect(defaultConfig);
    return { manager, channel };
  }

  it('yields stdout lines one at a time', async () => {
    const { manager, channel } = await setupConnectedManager();

    const gen = manager.execStream('tail -f /dev/null');
    const collected: string[] = [];

    // Prime the generator to the first yield-point so it attaches stream listeners.
    // gen.next() starts execution up to the first `yield` or `await new Promise(...)`.
    // We need to let it reach the point where stream listeners are attached before
    // emitting events on the channel.
    const firstNext = gen.next();
    await Promise.resolve(); // let execStream() resume past `await this.getChannel()`

    channel.emitData('line1\nline2\n');
    channel.emitData('line3\n');
    channel.emitClose(0);

    // Collect — firstNext resolves to the first line
    const first = await firstNext;
    if (!first.done) collected.push(first.value);
    for await (const line of gen) {
      collected.push(line);
    }

    expect(collected).toEqual(['line1', 'line2', 'line3']);
  });

  it('handles partial lines split across chunks', async () => {
    const { manager, channel } = await setupConnectedManager();

    const gen = manager.execStream('echo hello');
    const collected: string[] = [];

    const firstNext = gen.next();
    await Promise.resolve(); // let execStream() attach stream listeners

    channel.emitData('hel');
    channel.emitData('lo\n');
    channel.emitClose(0);

    const first = await firstNext;
    if (!first.done) collected.push(first.value);
    for await (const line of gen) {
      collected.push(line);
    }

    expect(collected).toEqual(['hello']);
  });

  it('flushes remaining buffer without trailing newline on close', async () => {
    const { manager, channel } = await setupConnectedManager();

    const gen = manager.execStream('printf no-newline');
    const collected: string[] = [];

    const firstNext = gen.next();
    await Promise.resolve(); // let execStream() attach stream listeners

    channel.emitData('no-newline'); // no trailing \n
    channel.emitClose(0);

    const first = await firstNext;
    if (!first.done) collected.push(first.value);
    for await (const line of gen) {
      collected.push(line);
    }

    expect(collected).toEqual(['no-newline']);
  });
});

describe('SSHConnectionManager — auto-reconnect (fake timers)', () => {
  beforeEach(() => {
    // Fake only the timer APIs — Promise resolution is unaffected
    vi.useFakeTimers({
      toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval'],
    });
    SSHConnectionManager.resetInstance();
    FakeClient.lastInstance = null;
  });

  afterEach(async () => {
    SSHConnectionManager.getInstance().disconnect().catch(() => undefined);
    SSHConnectionManager.resetInstance();
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it('does NOT auto-reconnect after intentional disconnect', async () => {
    const manager = SSHConnectionManager.getInstance();
    await manager.connect(defaultConfig);
    await manager.disconnect();

    const connectSpy = vi.spyOn(FakeClient.prototype, 'connect');
    await vi.advanceTimersByTimeAsync(60_000);

    expect(connectSpy).not.toHaveBeenCalled();
  });

  it('auto-reconnects after unexpected close using backoff', async () => {
    const manager = SSHConnectionManager.getInstance();
    await manager.connect(defaultConfig);

    const connectSpy = vi.spyOn(FakeClient.prototype, 'connect');

    // Simulate an unexpected server-side close
    FakeClient.lastInstance!.emit('close');

    // Drain the microtask queue so the reconnect() async function starts
    await Promise.resolve();

    // First backoff delay is 1 000 ms
    await vi.advanceTimersByTimeAsync(1500);

    // Allow any Promises scheduled during the timer callback to settle
    await Promise.resolve();
    await Promise.resolve();

    expect(connectSpy.mock.calls.length).toBeGreaterThanOrEqual(1);

    await manager.disconnect();
  });

  it('reconnect attempts use increasing backoff delays', async () => {
    // Every connect attempt fires 'close' immediately — simulates repeated failure
    vi.spyOn(FakeClient.prototype, 'connect').mockImplementation(function (
      this: FakeClient,
    ) {
      Promise.resolve().then(() => this.emit('close'));
      return this;
    });

    const manager = SSHConnectionManager.getInstance();

    // Initial connect will fail (close fires before ready)
    try {
      await manager.connect(defaultConfig);
    } catch {
      // Expected
    }

    const connectSpy = vi.spyOn(FakeClient.prototype, 'connect');

    // Attempt #1: backoff[0] = 1 000 ms
    await vi.advanceTimersByTimeAsync(1500);
    await Promise.resolve();
    await Promise.resolve();
    expect(connectSpy.mock.calls.length).toBeGreaterThanOrEqual(1);

    // Attempt #2: backoff[1] = 2 000 ms
    await vi.advanceTimersByTimeAsync(2500);
    await Promise.resolve();
    await Promise.resolve();
    expect(connectSpy.mock.calls.length).toBeGreaterThanOrEqual(2);

    await manager.disconnect();
  });
});
