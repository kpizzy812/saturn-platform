import { exec, execStream } from '../ssh/exec.js';
import { containerName, BACKUP_DIR } from '../config/constants.js';
import type { SaturnEnv } from '../config/types.js';

// Filename pattern for valid backup files: only safe characters, must end with .sql
const SAFE_BACKUP_FILENAME_RE = /^[a-zA-Z0-9_\-.]+\.sql$/;

/**
 * Create a pg_dump backup of the saturn database and write it to the env backup dir.
 * The filename encodes an ISO 8601 timestamp with colons and dots replaced by hyphens
 * so the name is safe for use in shell commands and file systems.
 *
 * Returns a success message containing the generated filename.
 * Throws if pg_dump fails or the backup directory is not writable.
 */
export async function createBackup(env: SaturnEnv): Promise<string> {
  const dbContainer = containerName('db', env);

  // Build a filename that is shell-safe and sorts chronologically
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const filename = `backup_${timestamp}.sql`;
  const destPath = `${BACKUP_DIR(env)}/${filename}`;

  // pg_dump writes to stdout; redirect into the host-mounted backup directory.
  // The echo is only reached when the dump succeeds (shell && semantics).
  return exec(
    `docker exec ${dbContainer} pg_dump -U saturn -d saturn > ${destPath} && echo "Backup created: ${filename}"`,
  );
}

/**
 * List all .sql backup files in the env backup directory.
 * Output is sorted by modification time, newest first (-t flag from ls -lth).
 *
 * Returns a human-readable ls table, or "No backups found" when the directory
 * is empty or does not exist.
 */
export async function listBackups(env: SaturnEnv): Promise<string> {
  return exec(
    `ls -lth ${BACKUP_DIR(env)}/*.sql 2>/dev/null || echo "No backups found"`,
  );
}

/**
 * Restore a previously created SQL backup into the saturn database.
 * Streams psql output line-by-line so the caller can display live progress.
 *
 * @param filename  Base filename only (no path component) — must match [a-zA-Z0-9_-.]+.sql
 * @throws          If the filename contains path traversal characters or invalid characters
 */
export async function* restoreBackup(
  env: SaturnEnv,
  filename: string,
): AsyncGenerator<string> {
  // Validate before building the shell command to prevent path traversal
  if (!SAFE_BACKUP_FILENAME_RE.test(filename)) {
    throw new Error(`Invalid backup filename: ${filename}`);
  }

  const dbContainer = containerName('db', env);
  const backupPath = `${BACKUP_DIR(env)}/${filename}`;

  // cat reads from the host path; docker exec -i accepts stdin from the pipe.
  // 2>&1 merges psql notices/warnings into the yielded stream.
  yield* execStream(
    `cat ${backupPath} | docker exec -i ${dbContainer} psql -U saturn -d saturn 2>&1`,
  );
}

/**
 * Return the total disk usage of the backup directory for the given environment.
 * Uses du -sh for a human-readable summary (e.g. "1.2G").
 *
 * Returns "0B" when the directory does not exist.
 */
export async function getBackupSize(env: SaturnEnv): Promise<string> {
  return exec(`du -sh ${BACKUP_DIR(env)} 2>/dev/null || echo "0B"`);
}
