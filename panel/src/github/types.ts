export interface PullRequest {
  number: number;
  title: string;
  state: 'open' | 'closed' | 'merged';
  author: string;
  branch: string;
  baseBranch: string;
  createdAt: string;
  updatedAt: string;
  url: string;
  isDraft: boolean;
  mergeable: boolean;
  reviewDecision: string; // APPROVED, CHANGES_REQUESTED, REVIEW_REQUIRED, etc.
  checks: 'pass' | 'fail' | 'pending' | 'none';
}

export interface WorkflowRun {
  id: number;
  name: string;
  status: 'completed' | 'in_progress' | 'queued' | 'waiting';
  conclusion: 'success' | 'failure' | 'cancelled' | 'skipped' | 'timed_out' | null;
  branch: string;
  event: string;
  createdAt: string;
  updatedAt: string;
  url: string;
  headSha: string;
}

export interface Commit {
  sha: string;
  message: string;
  author: string;
  date: string;
}
