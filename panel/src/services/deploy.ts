import { exec, execStream } from '../ssh/exec.js';
import { SOURCE_DIR, DEPLOY_SCRIPT } from '../config/constants.js';
import type { SaturnEnv } from '../config/types.js';

/**
 * Stream a full deployment for the given environment.
 * Runs the canonical deploy script from inside the source directory.
 * Merges stderr into stdout so the caller receives a unified log stream.
 *
 * Each yielded string is a single line of deploy output with the trailing
 * newline already stripped (handled by execStream).
 */
export async function* deploy(env: SaturnEnv): AsyncGenerator<string> {
  yield* execStream(
    `cd ${SOURCE_DIR(env)} && SATURN_ENV=${env} ./${DEPLOY_SCRIPT} 2>&1`,
  );
}

/**
 * Stream a rollback for the given environment.
 * Passes --rollback to the deploy script, which re-applies the previous
 * Docker image or git revision depending on the script implementation.
 * Merges stderr into stdout.
 */
export async function* rollback(env: SaturnEnv): AsyncGenerator<string> {
  yield* execStream(
    `cd ${SOURCE_DIR(env)} && SATURN_ENV=${env} ./${DEPLOY_SCRIPT} --rollback 2>&1`,
  );
}

/**
 * List recent deploy backup entries created by the deploy script.
 * Each deploy typically archives the previous state before swapping.
 *
 * Returns a raw `ls -lt` table (one entry per line) or an empty string.
 *
 * @param limit  Maximum number of backup entries to show (excluding ls header)
 */
export async function getDeployHistory(
  env: SaturnEnv,
  limit: number = 10,
): Promise<string> {
  // head -<limit+1> because ls -lt prints a "total N" header line first
  return exec(
    `ls -lt ${SOURCE_DIR(env)}/deploy/backups/ 2>/dev/null | head -${limit + 1}`,
  );
}

/**
 * Return the last <limit> git commits from the source directory in short form.
 * Format: "<short-hash> <subject>"
 */
export async function getGitLog(
  env: SaturnEnv,
  limit: number = 10,
): Promise<string> {
  return exec(`cd ${SOURCE_DIR(env)} && git log --oneline -${limit}`);
}

/**
 * Return the name of the currently checked-out git branch.
 * Strips the trailing newline that git rev-parse emits.
 */
export async function getCurrentBranch(env: SaturnEnv): Promise<string> {
  return exec(`cd ${SOURCE_DIR(env)} && git rev-parse --abbrev-ref HEAD`);
}

/**
 * Return the most recent commit in short oneline format.
 * Example output: "f7e237f feat: auto-populated status page"
 */
export async function getCurrentCommit(env: SaturnEnv): Promise<string> {
  return exec(`cd ${SOURCE_DIR(env)} && git log --oneline -1`);
}
