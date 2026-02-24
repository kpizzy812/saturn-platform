import { exec } from '../ssh/exec.js';
import { ENV_FILE } from '../config/constants.js';
import type { SaturnEnv } from '../config/types.js';

/**
 * Read the raw contents of the .env file for the given environment.
 * Returns the file content as-is including comments and blank lines.
 * Throws if the file does not exist or cannot be read.
 */
export async function readEnvFile(env: SaturnEnv): Promise<string> {
  return exec(`cat ${ENV_FILE(env)}`);
}

/**
 * Fetch the value of a single .env key from the remote file.
 * Uses grep to locate the assignment and cut to strip the key= prefix.
 * Multi-line values are NOT supported — only the first matching line is returned.
 *
 * @param key  Environment variable name — must match [A-Z_][A-Z0-9_]* (case-insensitive)
 * @throws     If the key contains characters that could break the shell command
 */
export async function getEnvValue(env: SaturnEnv, key: string): Promise<string> {
  if (!/^[A-Z_][A-Z0-9_]*$/i.test(key)) {
    throw new Error(`Invalid env key: ${key}`);
  }

  // grep -E '^KEY=' matches only exact-name assignments at the start of a line.
  // cut -d'=' -f2- preserves values that themselves contain '=' signs.
  return exec(`grep -E '^${key}=' ${ENV_FILE(env)} | cut -d'=' -f2-`);
}

/**
 * Show a side-by-side diff between the .env files of two environments.
 *
 * diff exits with code 1 when the files differ (which is the normal case).
 * The underlying exec() helper throws on non-zero exit codes, so we catch
 * that specific case and re-run with shell-level exit-code suppression via
 * `diff ... || true` to get the diff output without an exception.
 *
 * Returns the raw diff output, or a message when files are identical.
 */
export async function diffEnvFiles(env1: SaturnEnv, env2: SaturnEnv): Promise<string> {
  // `|| true` prevents the shell from returning a non-zero exit code when
  // diff finds differences (exit 1), while genuine errors (exit 2) still
  // surface as a non-empty stderr that exec() will propagate.
  const output = await exec(
    `diff --side-by-side ${ENV_FILE(env1)} ${ENV_FILE(env2)} || true`,
  );

  if (!output.trim()) {
    return 'Files are identical';
  }

  return output;
}

/**
 * Parse a raw .env file string into a key-value map.
 *
 * Rules applied:
 *  - Blank lines and lines starting with # are skipped
 *  - Lines without '=' are skipped
 *  - The key is everything before the first '='
 *  - The value is everything after the first '=' with optional surrounding quotes stripped
 *  - Only simple single or double quotes wrapping the entire value are removed
 */
export function parseEnvString(content: string): Record<string, string> {
  const env: Record<string, string> = {};

  for (const line of content.split('\n')) {
    const trimmed = line.trim();

    // Skip blank lines and comments
    if (!trimmed || trimmed.startsWith('#')) continue;

    const eqIndex = trimmed.indexOf('=');
    if (eqIndex === -1) continue;

    const key = trimmed.slice(0, eqIndex);
    const raw = trimmed.slice(eqIndex + 1);

    // Strip a single layer of surrounding quotes (handles "value" or 'value')
    const value = raw.replace(/^["']|["']$/g, '');

    env[key] = value;
  }

  return env;
}
