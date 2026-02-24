import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// vi.hoisted — must be declared before vi.mock so the factory can reference it
// ---------------------------------------------------------------------------
const { mockExecFile } = vi.hoisted(() => {
  return { mockExecFile: vi.fn() };
});

// ---------------------------------------------------------------------------
// Mock node:child_process before importing client — prevents real gh calls.
// promisify wraps execFile so we mock the raw callback-style function.
// ---------------------------------------------------------------------------
vi.mock('node:child_process', () => ({
  execFile: mockExecFile,
}));

// Import after mocks are registered
import {
  listPRs,
  getPR,
  createPR,
  mergePR,
  listWorkflowRuns,
  getWorkflowRun,
  listCommits,
  isGhAvailable,
} from '../../src/github/client.js';

// ---------------------------------------------------------------------------
// Test fixtures — raw shapes as returned by gh --json
// ---------------------------------------------------------------------------

const RAW_PR_OPEN = {
  number: 42,
  title: 'feat: add something cool',
  state: 'OPEN',
  author: { login: 'alice' },
  headRefName: 'feat/cool',
  baseRefName: 'dev',
  createdAt: '2026-02-20T10:00:00Z',
  updatedAt: '2026-02-21T12:00:00Z',
  url: 'https://github.com/AgazadeAV/coolify-Saturn/pull/42',
  isDraft: false,
  mergeable: 'MERGEABLE',
  reviewDecision: 'APPROVED',
  statusCheckRollup: [{ state: 'SUCCESS' }, { state: 'SUCCESS' }],
};

const RAW_PR_DRAFT = {
  ...RAW_PR_OPEN,
  number: 99,
  isDraft: true,
  mergeable: 'CONFLICTING',
  reviewDecision: 'CHANGES_REQUESTED',
  statusCheckRollup: [{ state: 'FAILURE' }],
};

const RAW_PR_PENDING = {
  ...RAW_PR_OPEN,
  number: 55,
  statusCheckRollup: [{ state: 'IN_PROGRESS' }, { state: 'SUCCESS' }],
};

const RAW_PR_NO_CHECKS = {
  ...RAW_PR_OPEN,
  number: 11,
  statusCheckRollup: null,
};

const RAW_RUN = {
  databaseId: 9876543,
  name: 'Deploy to VPS',
  status: 'completed',
  conclusion: 'success',
  headBranch: 'dev',
  event: 'push',
  createdAt: '2026-02-22T08:00:00Z',
  updatedAt: '2026-02-22T08:05:00Z',
  url: 'https://github.com/AgazadeAV/coolify-Saturn/actions/runs/9876543',
  headSha: 'abc123def456',
};

const RAW_RUN_IN_PROGRESS = {
  ...RAW_RUN,
  databaseId: 1111111,
  status: 'in_progress',
  conclusion: null,
};

const RAW_COMMITS = [
  {
    sha: 'aabbcc112233',
    commit: {
      message: 'fix: correct typo\n\nDetailed description here',
      author: { name: 'Bob', date: '2026-02-22T09:00:00Z' },
    },
  },
  {
    sha: 'ddeeff445566',
    commit: {
      message: 'feat: new feature',
      author: { name: 'Alice', date: '2026-02-21T15:00:00Z' },
    },
  },
];

// ---------------------------------------------------------------------------
// Helpers: configure mock to resolve or reject
// ---------------------------------------------------------------------------

/**
 * Make mockExecFile invoke its callback with the given stdout string.
 * promisify converts this to a resolved Promise<{ stdout }>.
 */
function mockGhSuccess(stdout: string): void {
  mockExecFile.mockImplementation(
    (
      _bin: string,
      _args: string[],
      _opts: unknown,
      cb: (err: null, result: { stdout: string; stderr: string }) => void,
    ) => {
      cb(null, { stdout, stderr: '' });
    },
  );
}

/**
 * Make mockExecFile invoke its callback with an error.
 * promisify converts this to a rejected Promise.
 */
function mockGhError(message: string, extra: Record<string, unknown> = {}): void {
  mockExecFile.mockImplementation(
    (
      _bin: string,
      _args: string[],
      _opts: unknown,
      cb: (err: Error) => void,
    ) => {
      const err = Object.assign(new Error(message), extra);
      cb(err);
    },
  );
}

// ---------------------------------------------------------------------------
// isGhAvailable()
// ---------------------------------------------------------------------------
describe('isGhAvailable()', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('returns true when gh auth status exits 0', async () => {
    mockGhSuccess('Logged in to github.com as alice');
    const result = await isGhAvailable();
    expect(result).toBe(true);
  });

  it('returns false when gh exits with a non-zero error', async () => {
    mockGhError('not logged into any hosts');
    const result = await isGhAvailable();
    expect(result).toBe(false);
  });

  it('returns false when gh binary is not found (ENOENT)', async () => {
    mockGhError('spawn gh ENOENT', { code: 'ENOENT' });
    const result = await isGhAvailable();
    expect(result).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// listPRs()
// ---------------------------------------------------------------------------
describe('listPRs()', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('returns mapped PullRequest array for open PRs', async () => {
    mockGhSuccess(JSON.stringify([RAW_PR_OPEN]));
    const prs = await listPRs('open');

    expect(prs).toHaveLength(1);
    const pr = prs[0]!;
    expect(pr.number).toBe(42);
    expect(pr.title).toBe('feat: add something cool');
    expect(pr.state).toBe('open');
    expect(pr.author).toBe('alice');
    expect(pr.branch).toBe('feat/cool');
    expect(pr.baseBranch).toBe('dev');
    expect(pr.isDraft).toBe(false);
    expect(pr.mergeable).toBe(true);
    expect(pr.reviewDecision).toBe('APPROVED');
    expect(pr.checks).toBe('pass');
    expect(pr.url).toContain('pull/42');
  });

  it('maps CONFLICTING mergeable to false', async () => {
    mockGhSuccess(JSON.stringify([RAW_PR_DRAFT]));
    const prs = await listPRs('open');
    expect(prs[0]!.mergeable).toBe(false);
  });

  it('maps UNKNOWN mergeable to false', async () => {
    mockGhSuccess(JSON.stringify([{ ...RAW_PR_OPEN, mergeable: 'UNKNOWN' }]));
    const prs = await listPRs('open');
    expect(prs[0]!.mergeable).toBe(false);
  });

  it('returns empty array when gh returns []', async () => {
    mockGhSuccess('[]');
    const prs = await listPRs('open');
    expect(prs).toEqual([]);
  });

  it('passes --state all to gh for all state', async () => {
    mockGhSuccess('[]');
    await listPRs('all');
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('all');
  });

  it('uses -R flag with default repo', async () => {
    mockGhSuccess('[]');
    await listPRs('open');
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('-R');
    expect(args).toContain('AgazadeAV/coolify-Saturn');
  });

  it('uses -R flag with custom repo override', async () => {
    mockGhSuccess('[]');
    await listPRs('open', 'other/repo');
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('other/repo');
  });

  it('throws meaningful error when gh is not installed (ENOENT)', async () => {
    mockGhError('spawn gh ENOENT', { code: 'ENOENT' });
    await expect(listPRs()).rejects.toThrow('gh CLI not found');
  });

  it('throws meaningful error when gh timed out', async () => {
    mockGhError('timeout', { code: 'ETIMEDOUT' });
    await expect(listPRs()).rejects.toThrow('timed out');
  });

  it('throws meaningful error when gh is not authenticated', async () => {
    mockGhError('not logged into any hosts', { stderr: 'not logged into any hosts' });
    await expect(listPRs()).rejects.toThrow('not authenticated');
  });

  it('throws on malformed JSON output', async () => {
    mockGhSuccess('not-json');
    await expect(listPRs()).rejects.toThrow('Failed to parse gh JSON output');
  });

  it('throws on empty stdout', async () => {
    mockGhSuccess('');
    await expect(listPRs()).rejects.toThrow('empty output');
  });
});

// ---------------------------------------------------------------------------
// checks field mapping (statusCheckRollup → 'pass'|'fail'|'pending'|'none')
// ---------------------------------------------------------------------------
describe('listPRs() — checks mapping', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('maps all SUCCESS to pass', async () => {
    mockGhSuccess(JSON.stringify([RAW_PR_OPEN]));
    const prs = await listPRs();
    expect(prs[0]!.checks).toBe('pass');
  });

  it('maps any FAILURE to fail', async () => {
    mockGhSuccess(JSON.stringify([RAW_PR_DRAFT]));
    const prs = await listPRs();
    expect(prs[0]!.checks).toBe('fail');
  });

  it('maps IN_PROGRESS to pending when no failures', async () => {
    mockGhSuccess(JSON.stringify([RAW_PR_PENDING]));
    const prs = await listPRs();
    expect(prs[0]!.checks).toBe('pending');
  });

  it('maps null statusCheckRollup to none', async () => {
    mockGhSuccess(JSON.stringify([RAW_PR_NO_CHECKS]));
    const prs = await listPRs();
    expect(prs[0]!.checks).toBe('none');
  });

  it('maps empty statusCheckRollup array to none', async () => {
    mockGhSuccess(JSON.stringify([{ ...RAW_PR_OPEN, statusCheckRollup: [] }]));
    const prs = await listPRs();
    expect(prs[0]!.checks).toBe('none');
  });

  it('fail takes priority over pending', async () => {
    mockGhSuccess(JSON.stringify([{
      ...RAW_PR_OPEN,
      statusCheckRollup: [{ state: 'FAILURE' }, { state: 'IN_PROGRESS' }],
    }]));
    const prs = await listPRs();
    expect(prs[0]!.checks).toBe('fail');
  });

  it('maps TIMED_OUT conclusion to fail', async () => {
    mockGhSuccess(JSON.stringify([{
      ...RAW_PR_OPEN,
      statusCheckRollup: [{ conclusion: 'TIMED_OUT' }],
    }]));
    const prs = await listPRs();
    expect(prs[0]!.checks).toBe('fail');
  });

  it('maps QUEUED state to pending', async () => {
    mockGhSuccess(JSON.stringify([{
      ...RAW_PR_OPEN,
      statusCheckRollup: [{ state: 'QUEUED' }],
    }]));
    const prs = await listPRs();
    expect(prs[0]!.checks).toBe('pending');
  });
});

// ---------------------------------------------------------------------------
// getPR()
// ---------------------------------------------------------------------------
describe('getPR()', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('fetches and maps a single PR', async () => {
    mockGhSuccess(JSON.stringify(RAW_PR_OPEN));
    const pr = await getPR(42);

    expect(pr.number).toBe(42);
    expect(pr.state).toBe('open');
    expect(pr.author).toBe('alice');
  });

  it('passes the PR number to gh args', async () => {
    mockGhSuccess(JSON.stringify(RAW_PR_OPEN));
    await getPR(42);
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('42');
  });

  it('throws ENOENT error with helpful message', async () => {
    mockGhError('spawn gh ENOENT', { code: 'ENOENT' });
    await expect(getPR(1)).rejects.toThrow('gh CLI not found');
  });
});

// ---------------------------------------------------------------------------
// createPR()
// ---------------------------------------------------------------------------
describe('createPR()', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('returns mapped PullRequest on success', async () => {
    mockGhSuccess(JSON.stringify(RAW_PR_OPEN));
    const pr = await createPR(
      'feat: add something cool',
      'PR body text',
      'dev',
      'feat/cool',
    );

    expect(pr.number).toBe(42);
    expect(pr.title).toBe('feat: add something cool');
    expect(pr.branch).toBe('feat/cool');
    expect(pr.baseBranch).toBe('dev');
  });

  it('passes title, body, base, head to gh args', async () => {
    mockGhSuccess(JSON.stringify(RAW_PR_OPEN));
    await createPR('My Title', 'My Body', 'main', 'feature/x');

    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('--title');
    expect(args).toContain('My Title');
    expect(args).toContain('--body');
    expect(args).toContain('My Body');
    expect(args).toContain('--base');
    expect(args).toContain('main');
    expect(args).toContain('--head');
    expect(args).toContain('feature/x');
  });

  it('throws on gh failure', async () => {
    mockGhError('gh failed', { stderr: 'branch not found' });
    await expect(createPR('t', 'b', 'main', 'bad-branch')).rejects.toThrow('gh command failed');
  });
});

// ---------------------------------------------------------------------------
// mergePR()
// ---------------------------------------------------------------------------
describe('mergePR()', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('resolves without value on success', async () => {
    mockGhSuccess('');
    await expect(mergePR(42)).resolves.toBeUndefined();
  });

  it('uses --squash flag by default', async () => {
    mockGhSuccess('');
    await mergePR(42);
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('--squash');
  });

  it('uses --merge flag when method is merge', async () => {
    mockGhSuccess('');
    await mergePR(42, 'merge');
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('--merge');
  });

  it('uses --rebase flag when method is rebase', async () => {
    mockGhSuccess('');
    await mergePR(42, 'rebase');
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('--rebase');
  });

  it('always passes --delete-branch', async () => {
    mockGhSuccess('');
    await mergePR(42);
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('--delete-branch');
  });

  it('passes the PR number to args', async () => {
    mockGhSuccess('');
    await mergePR(77);
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('77');
  });

  it('throws on gh failure', async () => {
    mockGhError('gh failed', { stderr: 'PR not mergeable' });
    await expect(mergePR(42)).rejects.toThrow('gh command failed');
  });
});

// ---------------------------------------------------------------------------
// listWorkflowRuns()
// ---------------------------------------------------------------------------
describe('listWorkflowRuns()', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('returns mapped WorkflowRun array', async () => {
    mockGhSuccess(JSON.stringify([RAW_RUN]));
    const runs = await listWorkflowRuns();

    expect(runs).toHaveLength(1);
    const run = runs[0]!;
    expect(run.id).toBe(9876543);
    expect(run.name).toBe('Deploy to VPS');
    expect(run.status).toBe('completed');
    expect(run.conclusion).toBe('success');
    expect(run.branch).toBe('dev');
    expect(run.event).toBe('push');
    expect(run.headSha).toBe('abc123def456');
  });

  it('maps null conclusion for in_progress run', async () => {
    mockGhSuccess(JSON.stringify([RAW_RUN_IN_PROGRESS]));
    const runs = await listWorkflowRuns();
    expect(runs[0]!.conclusion).toBeNull();
    expect(runs[0]!.status).toBe('in_progress');
  });

  it('passes --limit to gh args', async () => {
    mockGhSuccess('[]');
    await listWorkflowRuns(25);
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('--limit');
    expect(args).toContain('25');
  });

  it('returns empty array when no runs', async () => {
    mockGhSuccess('[]');
    const runs = await listWorkflowRuns();
    expect(runs).toEqual([]);
  });

  it('throws on ENOENT', async () => {
    mockGhError('ENOENT', { code: 'ENOENT' });
    await expect(listWorkflowRuns()).rejects.toThrow('gh CLI not found');
  });
});

// ---------------------------------------------------------------------------
// getWorkflowRun()
// ---------------------------------------------------------------------------
describe('getWorkflowRun()', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('fetches and maps a single workflow run', async () => {
    mockGhSuccess(JSON.stringify(RAW_RUN));
    const run = await getWorkflowRun(9876543);

    expect(run.id).toBe(9876543);
    expect(run.name).toBe('Deploy to VPS');
    expect(run.conclusion).toBe('success');
  });

  it('passes run ID to gh args', async () => {
    mockGhSuccess(JSON.stringify(RAW_RUN));
    await getWorkflowRun(9876543);
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args).toContain('9876543');
  });

  it('throws on authentication error', async () => {
    mockGhError('gh not authenticated', { stderr: 'not logged into any hosts' });
    await expect(getWorkflowRun(1)).rejects.toThrow('not authenticated');
  });
});

// ---------------------------------------------------------------------------
// listCommits()
// ---------------------------------------------------------------------------
describe('listCommits()', () => {
  beforeEach(() => { mockExecFile.mockReset(); });

  it('returns mapped Commit array', async () => {
    mockGhSuccess(JSON.stringify(RAW_COMMITS));
    const commits = await listCommits('dev', 2);

    expect(commits).toHaveLength(2);

    const first = commits[0]!;
    expect(first.sha).toBe('aabbcc112233');
    expect(first.message).toBe('fix: correct typo'); // only first line
    expect(first.author).toBe('Bob');
    expect(first.date).toBe('2026-02-22T09:00:00Z');

    const second = commits[1]!;
    expect(second.sha).toBe('ddeeff445566');
    expect(second.message).toBe('feat: new feature');
    expect(second.author).toBe('Alice');
  });

  it('uses only the first line of multi-line commit messages', async () => {
    mockGhSuccess(JSON.stringify(RAW_COMMITS));
    const commits = await listCommits();
    // RAW_COMMITS[0] has "fix: correct typo\n\nDetailed description here"
    expect(commits[0]!.message).toBe('fix: correct typo');
  });

  it('uses `gh api` (not `gh -R`) for the endpoint', async () => {
    mockGhSuccess(JSON.stringify(RAW_COMMITS));
    await listCommits('dev');
    const [_bin, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args[0]).toBe('api');
  });

  it('encodes slashes in branch name in the API endpoint', async () => {
    mockGhSuccess(JSON.stringify(RAW_COMMITS));
    await listCommits('feature/my-branch');
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    const endpoint = args[1]!;
    expect(endpoint).toContain('feature%2Fmy-branch');
  });

  it('includes per_page in the API endpoint', async () => {
    mockGhSuccess(JSON.stringify(RAW_COMMITS));
    await listCommits('dev', 5);
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args[1]).toContain('per_page=5');
  });

  it('uses default branch dev when not specified', async () => {
    mockGhSuccess(JSON.stringify(RAW_COMMITS));
    await listCommits();
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args[1]).toContain('sha=dev');
  });

  it('includes the repo in the API endpoint', async () => {
    mockGhSuccess(JSON.stringify(RAW_COMMITS));
    await listCommits('dev', 10, 'other/repo');
    const [, args] = mockExecFile.mock.calls[0] as [string, string[]];
    expect(args[1]).toContain('repos/other/repo');
  });

  it('throws ENOENT with helpful message', async () => {
    mockGhError('ENOENT', { code: 'ENOENT' });
    await expect(listCommits()).rejects.toThrow('gh CLI not found');
  });

  it('throws ETIMEDOUT with helpful message', async () => {
    mockGhError('timeout', { code: 'ETIMEDOUT' });
    await expect(listCommits()).rejects.toThrow('timed out');
  });

  it('throws authentication error with helpful message', async () => {
    mockGhError('auth error', { stderr: 'not logged into any hosts' });
    await expect(listCommits()).rejects.toThrow('not authenticated');
  });

  it('throws on empty response', async () => {
    mockGhSuccess('');
    await expect(listCommits()).rejects.toThrow('empty output');
  });

  it('throws on malformed JSON', async () => {
    mockGhSuccess('not-json');
    await expect(listCommits()).rejects.toThrow('Failed to parse');
  });
});
