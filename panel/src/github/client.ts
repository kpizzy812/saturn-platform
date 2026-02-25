import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import type { PullRequest, WorkflowRun, Commit } from './types.js';
import { GITHUB_REPO } from '../config/constants.js';

const execFileAsync = promisify(execFile);

// Timeout for all gh CLI calls (milliseconds)
const GH_TIMEOUT_MS = 15_000;

// JSON fields requested from `gh pr list` / `gh pr view`
const PR_JSON_FIELDS = [
  'number',
  'title',
  'state',
  'author',
  'headRefName',
  'baseRefName',
  'createdAt',
  'updatedAt',
  'url',
  'isDraft',
  'mergeable',
  'reviewDecision',
  'statusCheckRollup',
].join(',');

// JSON fields requested from `gh run list` / `gh run view`
const RUN_JSON_FIELDS = [
  'databaseId',
  'name',
  'status',
  'conclusion',
  'headBranch',
  'event',
  'createdAt',
  'updatedAt',
  'url',
  'headSha',
].join(',');

// -------------------------------------------------------------------
// Internal helpers
// -------------------------------------------------------------------

/**
 * Run a `gh` subcommand against the given repo.
 * Returns trimmed stdout. Throws a descriptive error on non-zero exit.
 */
async function gh(args: string[], repo: string = GITHUB_REPO): Promise<string> {
  try {
    const { stdout } = await execFileAsync('gh', [...args, '-R', repo], {
      timeout: GH_TIMEOUT_MS,
    });
    return stdout.trim();
  } catch (err: unknown) {
    const error = err as NodeJS.ErrnoException & { code?: string; stderr?: string };

    // gh binary not found
    if (error.code === 'ENOENT') {
      throw new Error(
        'gh CLI not found. Install it: https://cli.github.com/',
      );
    }

    // Timed out
    if (error.code === 'ETIMEDOUT') {
      throw new Error(`gh command timed out after ${GH_TIMEOUT_MS / 1000}s: gh ${args.join(' ')}`);
    }

    // gh authentication / API errors surface via stderr
    const stderr = error.stderr ?? '';
    if (stderr.includes('not logged into') || stderr.includes('authentication')) {
      throw new Error(`gh is not authenticated. Run: gh auth login\nDetail: ${stderr.trim()}`);
    }

    throw new Error(`gh command failed: gh ${args.join(' ')}\n${stderr.trim()}`);
  }
}

/**
 * Parse JSON from gh output. Throws if output is empty or invalid JSON.
 */
function parseJson<T>(raw: string, context: string): T {
  if (!raw) {
    throw new Error(`gh returned empty output for: ${context}`);
  }
  try {
    return JSON.parse(raw) as T;
  } catch {
    throw new Error(`Failed to parse gh JSON output for ${context}: ${raw.slice(0, 200)}`);
  }
}

/**
 * Map the statusCheckRollup array returned by gh to our simplified checks field.
 * gh returns an array of check objects; we derive aggregate pass/fail/pending/none.
 */
function mapChecks(rollup: Array<{ state?: string; status?: string; conclusion?: string }> | null | undefined): PullRequest['checks'] {
  if (!rollup || rollup.length === 0) return 'none';

  let hasFail = false;
  let hasPending = false;

  for (const check of rollup) {
    // Each entry has either `state` (for check runs) or `conclusion`
    const state = (check.state ?? check.conclusion ?? check.status ?? '').toUpperCase();

    if (state === 'FAILURE' || state === 'ERROR' || state === 'TIMED_OUT' || state === 'CANCELLED') {
      hasFail = true;
    } else if (state === 'PENDING' || state === 'IN_PROGRESS' || state === 'QUEUED' || state === 'WAITING' || state === 'EXPECTED') {
      hasPending = true;
    }
  }

  if (hasFail) return 'fail';
  if (hasPending) return 'pending';
  return 'pass';
}

// Raw shape returned by `gh pr list/view --json`
interface RawPR {
  number: number;
  title: string;
  state: string;
  author: { login: string };
  headRefName: string;
  baseRefName: string;
  createdAt: string;
  updatedAt: string;
  url: string;
  isDraft: boolean;
  mergeable: string; // MERGEABLE | CONFLICTING | UNKNOWN
  reviewDecision: string | null;
  statusCheckRollup: Array<{ state?: string; status?: string; conclusion?: string }> | null;
}

function mapPR(raw: RawPR): PullRequest {
  return {
    number: raw.number,
    title: raw.title,
    state: raw.state.toLowerCase() as PullRequest['state'],
    author: raw.author?.login ?? 'unknown',
    branch: raw.headRefName,
    baseBranch: raw.baseRefName,
    createdAt: raw.createdAt,
    updatedAt: raw.updatedAt,
    url: raw.url,
    isDraft: raw.isDraft,
    mergeable: raw.mergeable === 'MERGEABLE',
    reviewDecision: raw.reviewDecision ?? '',
    checks: mapChecks(raw.statusCheckRollup),
  };
}

// Raw shape returned by `gh run list/view --json`
interface RawRun {
  databaseId: number;
  name: string;
  status: string;
  conclusion: string | null;
  headBranch: string;
  event: string;
  createdAt: string;
  updatedAt: string;
  url: string;
  headSha: string;
}

function mapRun(raw: RawRun): WorkflowRun {
  return {
    id: raw.databaseId,
    name: raw.name,
    status: raw.status as WorkflowRun['status'],
    conclusion: (raw.conclusion ?? null) as WorkflowRun['conclusion'],
    branch: raw.headBranch,
    event: raw.event,
    createdAt: raw.createdAt,
    updatedAt: raw.updatedAt,
    url: raw.url,
    headSha: raw.headSha,
  };
}

// -------------------------------------------------------------------
// Public API
// -------------------------------------------------------------------

/**
 * List pull requests for the repo.
 * State defaults to 'open'. Passing 'all' fetches open + closed + merged.
 */
export async function listPRs(
  state: 'open' | 'closed' | 'all' = 'open',
  repo?: string,
): Promise<PullRequest[]> {
  const raw = await gh(
    ['pr', 'list', '--json', PR_JSON_FIELDS, '--state', state, '--limit', '50'],
    repo,
  );
  const list = parseJson<RawPR[]>(raw, 'pr list');
  return list.map(mapPR);
}

/**
 * Get a single pull request by number.
 */
export async function getPR(prNumber: number, repo?: string): Promise<PullRequest> {
  const raw = await gh(
    ['pr', 'view', String(prNumber), '--json', PR_JSON_FIELDS],
    repo,
  );
  const data = parseJson<RawPR>(raw, `pr view #${prNumber}`);
  return mapPR(data);
}

/**
 * Create a new pull request and return the created PR object.
 */
export async function createPR(
  title: string,
  body: string,
  base: string,
  head: string,
  repo?: string,
): Promise<PullRequest> {
  // gh pr create returns only the PR URL by default; --json gives structured output
  const raw = await gh(
    [
      'pr', 'create',
      '--title', title,
      '--body', body,
      '--base', base,
      '--head', head,
      '--json', PR_JSON_FIELDS,
    ],
    repo,
  );
  const data = parseJson<RawPR>(raw, 'pr create');
  return mapPR(data);
}

/**
 * Merge a pull request.
 * Defaults to squash merge with branch deletion — matches Saturn's workflow.
 */
export async function mergePR(
  prNumber: number,
  method: 'merge' | 'squash' | 'rebase' = 'squash',
  repo?: string,
): Promise<void> {
  const methodFlag = `--${method}`;
  await gh(
    ['pr', 'merge', String(prNumber), methodFlag, '--delete-branch'],
    repo,
  );
}

/**
 * List recent workflow runs, newest first.
 */
export async function listWorkflowRuns(
  limit: number = 10,
  repo?: string,
): Promise<WorkflowRun[]> {
  const raw = await gh(
    ['run', 'list', '--json', RUN_JSON_FIELDS, '--limit', String(limit)],
    repo,
  );
  const list = parseJson<RawRun[]>(raw, 'run list');
  return list.map(mapRun);
}

/**
 * Get a single workflow run by its numeric run ID.
 */
export async function getWorkflowRun(runId: number, repo?: string): Promise<WorkflowRun> {
  const raw = await gh(
    ['run', 'view', String(runId), '--json', RUN_JSON_FIELDS],
    repo,
  );
  const data = parseJson<RawRun>(raw, `run view ${runId}`);
  return mapRun(data);
}

/**
 * List commits on a branch via the GitHub API (no HTML needed).
 * Uses `gh api` so authentication is handled by the gh keychain.
 */
export async function listCommits(
  branch: string = 'dev',
  limit: number = 10,
  repo?: string,
): Promise<Commit[]> {
  const repoSlug = repo ?? GITHUB_REPO;

  // gh api doesn't accept -R for the endpoint path — embed the repo directly
  const endpoint = `repos/${repoSlug}/commits?sha=${encodeURIComponent(branch)}&per_page=${limit}`;

  let raw: string;
  try {
    const { stdout } = await execFileAsync('gh', ['api', endpoint], {
      timeout: GH_TIMEOUT_MS,
    });
    raw = stdout.trim();
  } catch (err: unknown) {
    const error = err as NodeJS.ErrnoException & { code?: string; stderr?: string };

    if (error.code === 'ENOENT') {
      throw new Error('gh CLI not found. Install it: https://cli.github.com/');
    }
    if (error.code === 'ETIMEDOUT') {
      throw new Error(`gh api timed out after ${GH_TIMEOUT_MS / 1000}s`);
    }

    const stderr = error.stderr ?? '';
    if (stderr.includes('not logged into') || stderr.includes('authentication')) {
      throw new Error(`gh is not authenticated. Run: gh auth login\nDetail: ${stderr.trim()}`);
    }

    throw new Error(`gh api failed for ${endpoint}: ${stderr.trim()}`);
  }

  interface RawCommit {
    sha: string;
    commit: {
      message: string;
      author: {
        name: string;
        date: string;
      };
    };
  }

  const list = parseJson<RawCommit[]>(raw, `commits for ${branch}`);

  return list.map((c) => ({
    sha: c.sha,
    // Use only the first line of the commit message as the summary
    message: c.commit.message.split('\n')[0] ?? c.commit.message,
    author: c.commit.author.name,
    date: c.commit.author.date,
  }));
}

/**
 * Check whether gh CLI is installed and authenticated.
 * Returns true when `gh auth status` exits 0, false otherwise.
 */
export async function isGhAvailable(): Promise<boolean> {
  try {
    await execFileAsync('gh', ['auth', 'status'], { timeout: GH_TIMEOUT_MS });
    return true;
  } catch {
    return false;
  }
}
