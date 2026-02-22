import React, { useState, useEffect, useCallback } from 'react';
import { Box, Text, useInput } from 'ink';
import { Spinner } from '../components/shared/Spinner.js';
import { Table } from '../components/shared/Table.js';
import { Badge } from '../components/shared/Badge.js';
import { ConfirmDialog } from '../components/shared/ConfirmDialog.js';
import {
  listPRs,
  listWorkflowRuns,
  listCommits,
  createPR,
  mergePR,
  isGhAvailable,
} from '../github/client.js';
import type { PullRequest, WorkflowRun, Commit } from '../github/types.js';

// GitScreen does not receive env props — it operates against the local gh CLI
export function GitScreen() {
  type Tab = 'prs' | 'actions' | 'commits';
  const TABS: Tab[] = ['prs', 'actions', 'commits'];
  const TAB_LABELS: Record<Tab, string> = {
    prs: 'PRs',
    actions: 'Actions',
    commits: 'Commits',
  };

  const [activeTab, setActiveTab] = useState<Tab>('prs');
  const [ghAvailable, setGhAvailable] = useState<boolean | null>(null);

  // PR state
  const [prs, setPRs] = useState<PullRequest[]>([]);
  const [prsLoading, setPrsLoading] = useState(false);
  const [prsError, setPrsError] = useState<string | null>(null);
  const [selectedPr, setSelectedPr] = useState(0);

  // Actions state
  const [runs, setRuns] = useState<WorkflowRun[]>([]);
  const [runsLoading, setRunsLoading] = useState(false);
  const [runsError, setRunsError] = useState<string | null>(null);

  // Commits state
  const [commits, setCommits] = useState<Commit[]>([]);
  const [commitsLoading, setCommitsLoading] = useState(false);
  const [commitsError, setCommitsError] = useState<string | null>(null);

  // Confirm dialog state
  type Dialog = 'create-dev-staging' | 'create-staging-main' | 'merge' | null;
  const [dialog, setDialog] = useState<Dialog>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionMessage, setActionMessage] = useState<{ text: string; success: boolean } | null>(null);

  // Check gh availability once on mount
  useEffect(() => {
    void isGhAvailable().then(setGhAvailable);
  }, []);

  const loadPRs = useCallback(async () => {
    setPrsLoading(true);
    setPrsError(null);
    try {
      const result = await listPRs('open');
      setPRs(result);
      setSelectedPr(0);
    } catch (err) {
      setPrsError(err instanceof Error ? err.message : String(err));
    } finally {
      setPrsLoading(false);
    }
  }, []);

  const loadRuns = useCallback(async () => {
    setRunsLoading(true);
    setRunsError(null);
    try {
      const result = await listWorkflowRuns(15);
      setRuns(result);
    } catch (err) {
      setRunsError(err instanceof Error ? err.message : String(err));
    } finally {
      setRunsLoading(false);
    }
  }, []);

  const loadCommits = useCallback(async () => {
    setCommitsLoading(true);
    setCommitsError(null);
    try {
      const result = await listCommits('dev', 20);
      setCommits(result);
    } catch (err) {
      setCommitsError(err instanceof Error ? err.message : String(err));
    } finally {
      setCommitsLoading(false);
    }
  }, []);

  // Load data when the active tab changes
  useEffect(() => {
    if (ghAvailable === false) return;
    if (activeTab === 'prs') void loadPRs();
    else if (activeTab === 'actions') void loadRuns();
    else if (activeTab === 'commits') void loadCommits();
  }, [activeTab, ghAvailable, loadPRs, loadRuns, loadCommits]);

  // Keyboard handling — suspended while a dialog or action is in progress
  useInput((_input, key) => {
    if (dialog !== null || actionLoading) return;

    if (key.tab) {
      const currentIdx = TABS.indexOf(activeTab);
      setActiveTab(TABS[(currentIdx + 1) % TABS.length] ?? 'prs');
      return;
    }

    if (activeTab === 'prs') {
      if (key.upArrow && selectedPr > 0) {
        setSelectedPr((p) => p - 1);
        return;
      }
      if (key.downArrow && selectedPr < prs.length - 1) {
        setSelectedPr((p) => p + 1);
        return;
      }
      if (_input === 'c') { setDialog('create-dev-staging'); return; }
      if (_input === 'C') { setDialog('create-staging-main'); return; }
      if (_input === 'm' && prs.length > 0) { setDialog('merge'); return; }
      if (_input === 'r') { void loadPRs(); return; }
    }

    if (activeTab === 'actions' && _input === 'r') { void loadRuns(); return; }
    if (activeTab === 'commits' && _input === 'r') { void loadCommits(); return; }
  });

  // PR creation action
  async function handleCreatePR(head: string, base: string) {
    setDialog(null);
    setActionLoading(true);
    setActionMessage(null);
    try {
      const pr = await createPR(
        `chore: promote ${head} → ${base}`,
        `Automated promotion PR from ${head} to ${base}.`,
        base,
        head,
      );
      setActionMessage({ text: `PR #${pr.number} created: ${pr.title}`, success: true });
      await loadPRs();
    } catch (err) {
      setActionMessage({
        text: `Error creating PR: ${err instanceof Error ? err.message : String(err)}`,
        success: false,
      });
    } finally {
      setActionLoading(false);
    }
  }

  // PR merge action
  async function handleMergePR() {
    setDialog(null);
    const pr = prs[selectedPr];
    if (!pr) return;
    setActionLoading(true);
    setActionMessage(null);
    try {
      await mergePR(pr.number, 'squash');
      setActionMessage({ text: `PR #${pr.number} merged successfully.`, success: true });
      await loadPRs();
    } catch (err) {
      setActionMessage({
        text: `Merge failed: ${err instanceof Error ? err.message : String(err)}`,
        success: false,
      });
    } finally {
      setActionLoading(false);
    }
  }

  // Color helpers
  function runStatusColor(run: WorkflowRun): string {
    if (run.conclusion === 'success') return 'green';
    if (run.conclusion === 'failure' || run.conclusion === 'timed_out') return 'red';
    if (run.status === 'in_progress' || run.status === 'queued') return 'yellow';
    return 'gray';
  }

  function runStatusSymbol(run: WorkflowRun): string {
    if (run.conclusion === 'success') return '✔';
    if (run.conclusion === 'failure' || run.conclusion === 'timed_out') return '✘';
    if (run.status === 'in_progress') return '◌';
    return '·';
  }

  function checksColor(checks: PullRequest['checks']): string {
    if (checks === 'pass') return 'green';
    if (checks === 'fail') return 'red';
    if (checks === 'pending') return 'yellow';
    return 'gray';
  }

  function formatRelativeTime(isoString: string): string {
    const diffMs = Date.now() - new Date(isoString).getTime();
    const mins = Math.floor(diffMs / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
  }

  function truncate(str: string, max: number): string {
    return str.length > max ? str.slice(0, max - 1) + '\u2026' : str;
  }

  // gh CLI not installed or not authed
  if (ghAvailable === null) {
    return (
      <Box flexDirection="column" padding={1}>
        <Spinner label="Checking gh CLI…" />
      </Box>
    );
  }

  if (ghAvailable === false) {
    return (
      <Box flexDirection="column" padding={1}>
        <Text bold color="red">gh CLI not available</Text>
        <Text dimColor>Install gh and authenticate: gh auth login</Text>
        <Text dimColor>https://cli.github.com/</Text>
      </Box>
    );
  }

  // Tab bar component (inline)
  const TabBar = () => (
    <Box gap={2} marginBottom={1}>
      {TABS.map((tab) => (
        <Box key={tab}>
          {activeTab === tab ? (
            <Text bold color="cyan" inverse>{` ${TAB_LABELS[tab]} `}</Text>
          ) : (
            <Text dimColor>{` ${TAB_LABELS[tab]} `}</Text>
          )}
        </Box>
      ))}
      <Text dimColor>  Tab:switch  r:refresh</Text>
    </Box>
  );

  // Confirm dialogs render in place of the main view
  if (dialog === 'create-dev-staging') {
    return (
      <Box flexDirection="column" padding={1}>
        <TabBar />
        <ConfirmDialog
          message="Create PR: dev → staging?"
          onConfirm={() => void handleCreatePR('dev', 'staging')}
          onCancel={() => setDialog(null)}
        />
      </Box>
    );
  }

  if (dialog === 'create-staging-main') {
    return (
      <Box flexDirection="column" padding={1}>
        <TabBar />
        <ConfirmDialog
          message="Create PR: staging → main?"
          onConfirm={() => void handleCreatePR('staging', 'main')}
          onCancel={() => setDialog(null)}
        />
      </Box>
    );
  }

  const selectedPrObj = prs[selectedPr];
  if (dialog === 'merge' && selectedPrObj) {
    return (
      <Box flexDirection="column" padding={1}>
        <TabBar />
        <ConfirmDialog
          message={`Squash-merge PR #${selectedPrObj.number}: ${truncate(selectedPrObj.title, 40)}?`}
          destructive
          onConfirm={() => void handleMergePR()}
          onCancel={() => setDialog(null)}
        />
      </Box>
    );
  }

  // Column definitions for PRs table
  type PRRow = { num: string; title: string; author: string; route: string; checks: string; review: string };
  const prColumns = [
    { header: '#', key: 'num' as keyof PRRow, width: 6 },
    { header: 'Title', key: 'title' as keyof PRRow, width: 40 },
    { header: 'Author', key: 'author' as keyof PRRow, width: 14 },
    { header: 'Route', key: 'route' as keyof PRRow, width: 24 },
    { header: 'Checks', key: 'checks' as keyof PRRow, width: 8 },
    { header: 'Review', key: 'review' as keyof PRRow, width: 18 },
  ];
  const prRows: PRRow[] = prs.map((pr) => ({
    num: `#${pr.number}`,
    title: truncate(pr.title, 39),
    author: pr.author,
    route: `${pr.branch} → ${pr.baseBranch}`,
    checks: pr.checks,
    review: pr.reviewDecision || '—',
  }));

  // Column definitions for commits table
  type CommitRow = { sha: string; message: string; author: string; date: string };
  const commitColumns = [
    { header: 'SHA', key: 'sha' as keyof CommitRow, width: 8 },
    { header: 'Message', key: 'message' as keyof CommitRow, width: 52 },
    { header: 'Author', key: 'author' as keyof CommitRow, width: 18 },
    { header: 'Date', key: 'date' as keyof CommitRow, width: 10 },
  ];
  const commitRows: CommitRow[] = commits.map((c) => ({
    sha: c.sha.slice(0, 7),
    message: truncate(c.message, 51),
    author: c.author,
    date: formatRelativeTime(c.date),
  }));

  return (
    <Box flexDirection="column" padding={1}>
      <TabBar />

      {/* Action spinner / feedback */}
      {actionLoading && <Spinner label="Working…" />}
      {actionMessage && (
        <Box marginBottom={1}>
          <Text color={actionMessage.success ? 'green' : 'red'}>
            {actionMessage.success ? '✔ ' : '✘ '}
            {actionMessage.text}
          </Text>
        </Box>
      )}

      {/* PRs tab */}
      {activeTab === 'prs' && (
        <Box flexDirection="column">
          <Box marginBottom={1}>
            <Text dimColor>c: dev→staging  C: staging→main  m: merge selected  ↑↓: select</Text>
          </Box>
          {prsLoading && <Spinner label="Loading pull requests…" />}
          {prsError && <Text color="red">{prsError}</Text>}
          {!prsLoading && !prsError && prs.length === 0 && (
            <Text dimColor>No open pull requests.</Text>
          )}
          {!prsLoading && !prsError && prs.length > 0 && (
            <Box flexDirection="column">
              <Table columns={prColumns} data={prRows} highlightRow={selectedPr} />
              {selectedPrObj && (
                <Box marginTop={1} gap={2}>
                  <Text dimColor>Selected:</Text>
                  <Text bold color="cyan">#{selectedPrObj.number}</Text>
                  <Badge
                    label={selectedPrObj.checks}
                    color={checksColor(selectedPrObj.checks)}
                  />
                  {selectedPrObj.isDraft && <Badge label="draft" color="gray" />}
                  {!selectedPrObj.mergeable && (
                    <Badge label="conflict" color="red" />
                  )}
                </Box>
              )}
            </Box>
          )}
        </Box>
      )}

      {/* Actions tab */}
      {activeTab === 'actions' && (
        <Box flexDirection="column">
          {runsLoading && <Spinner label="Loading workflow runs…" />}
          {runsError && <Text color="red">{runsError}</Text>}
          {!runsLoading && !runsError && runs.length === 0 && (
            <Text dimColor>No workflow runs found.</Text>
          )}
          {!runsLoading && !runsError && runs.length > 0 && (
            <Box flexDirection="column" gap={1}>
              {runs.map((run) => (
                <Box key={run.id} gap={2}>
                  <Text color={runStatusColor(run)}>{runStatusSymbol(run)}</Text>
                  <Box width={32}>
                    <Text bold>{truncate(run.name, 31)}</Text>
                  </Box>
                  <Box width={18}>
                    <Text dimColor>{run.branch}</Text>
                  </Box>
                  <Box width={12}>
                    <Text color={runStatusColor(run)}>
                      {run.conclusion ?? run.status}
                    </Text>
                  </Box>
                  <Text dimColor>{formatRelativeTime(run.createdAt)}</Text>
                </Box>
              ))}
            </Box>
          )}
        </Box>
      )}

      {/* Commits tab */}
      {activeTab === 'commits' && (
        <Box flexDirection="column">
          {commitsLoading && <Spinner label="Loading commits…" />}
          {commitsError && <Text color="red">{commitsError}</Text>}
          {!commitsLoading && !commitsError && commits.length === 0 && (
            <Text dimColor>No commits found on dev branch.</Text>
          )}
          {!commitsLoading && !commitsError && commits.length > 0 && (
            <Table columns={commitColumns} data={commitRows} />
          )}
        </Box>
      )}
    </Box>
  );
}
