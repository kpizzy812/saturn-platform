import { exec } from '../ssh/exec.js';
import { containerName } from '../config/constants.js';
import type { SaturnEnv } from '../config/types.js';

/**
 * Run a php artisan command inside the app container for the given environment.
 * All output (stdout + stderr via 2>&1) is returned as a single string.
 * Throws if the command exits with a non-zero code.
 */
function artisan(env: SaturnEnv, command: string): Promise<string> {
  const container = containerName('app', env);
  return exec(`docker exec ${container} php artisan ${command}`);
}

/**
 * Run pending database migrations.
 * --force bypasses the production safety prompt.
 */
export async function migrate(env: SaturnEnv): Promise<string> {
  return artisan(env, 'migrate --force');
}

/**
 * Drop all tables and re-run all migrations from scratch.
 * --force bypasses the production safety prompt.
 * Use with caution — all data will be lost.
 */
export async function migrateFresh(env: SaturnEnv): Promise<string> {
  return artisan(env, 'migrate:fresh --force');
}

/**
 * Run a database seeder class.
 * Defaults to ProductionSeeder which seeds only safe reference data.
 *
 * @param seeder  Fully-qualified seeder class name (no namespace prefix required)
 */
export async function seed(
  env: SaturnEnv,
  seeder: string = 'ProductionSeeder',
): Promise<string> {
  return artisan(env, `db:seed --class=${seeder} --force`);
}

/**
 * Clear all Laravel application caches: cache store, config, route, and view.
 * Each command is run sequentially; output from all commands is joined with newlines.
 */
export async function clearCache(env: SaturnEnv): Promise<string> {
  const container = containerName('app', env);

  const commands = [
    `docker exec ${container} php artisan cache:clear`,
    `docker exec ${container} php artisan config:clear`,
    `docker exec ${container} php artisan route:clear`,
    `docker exec ${container} php artisan view:clear`,
  ];

  const results: string[] = [];
  for (const cmd of commands) {
    results.push(await exec(cmd));
  }

  return results.join('\n');
}

/**
 * Rebuild all Laravel bootstrap caches: config, route, and view.
 * Run this after clearing caches to warm them up again for production.
 * Each command is run sequentially; output from all commands is joined with newlines.
 */
export async function rebuildCache(env: SaturnEnv): Promise<string> {
  const container = containerName('app', env);

  const commands = [
    `docker exec ${container} php artisan config:cache`,
    `docker exec ${container} php artisan route:cache`,
    `docker exec ${container} php artisan view:cache`,
  ];

  const results: string[] = [];
  for (const cmd of commands) {
    results.push(await exec(cmd));
  }

  return results.join('\n');
}

/**
 * Show the current migration status table.
 * Useful for verifying which migrations have been run and which are pending.
 */
export async function migrationStatus(env: SaturnEnv): Promise<string> {
  return artisan(env, 'migrate:status');
}

/**
 * Run an arbitrary artisan command inside the app container.
 * The caller is responsible for ensuring the command string is safe.
 *
 * @param command  Artisan command and arguments (e.g. 'queue:restart')
 */
export async function runCommand(env: SaturnEnv, command: string): Promise<string> {
  return artisan(env, command);
}
