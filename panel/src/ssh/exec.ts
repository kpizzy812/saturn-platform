import { SSHConnectionManager } from './connection.js';

/**
 * Execute a remote command and return the full stdout output as a string.
 * Throws if the command exits with a non-zero code or if the connection is lost.
 */
export async function exec(command: string): Promise<string> {
  return SSHConnectionManager.getInstance().exec(command);
}

/**
 * Execute a remote command and yield stdout lines one at a time as they arrive.
 * Each yielded value is a single line with the trailing newline stripped.
 */
export async function* execStream(
  command: string,
): AsyncGenerator<string, void, unknown> {
  yield* SSHConnectionManager.getInstance().execStream(command);
}
