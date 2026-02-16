<?php

namespace App\Jobs;

use App\Enums\ProcessStatus;
use App\Models\Application;
use App\Models\ApplicationPreview;
use App\Models\GithubApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplicationPullRequestUpdateJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $build_logs_url;

    public string $body;

    public function __construct(
        public Application $application,
        public ApplicationPreview $preview,
        public ProcessStatus $status,
        public ?string $deployment_uuid = null
    ) {
        $this->onQueue('high');
    }

    public function handle()
    {
        try {
            if ($this->application->is_public_repository()) {
                return;
            }

            $serviceName = $this->application->name;

            if ($this->status === ProcessStatus::CLOSED) {
                $this->delete_comment();

                return;
            }

            match ($this->status) {
                ProcessStatus::QUEUED => $this->body = "The preview deployment for **{$serviceName}** is queued. ⏳\n\n",
                ProcessStatus::IN_PROGRESS => $this->body = "The preview deployment for **{$serviceName}** is in progress. 🟡\n\n",
                ProcessStatus::FINISHED => $this->body = "The preview deployment for **{$serviceName}** is ready. 🟢\n\n".($this->preview->fqdn ? "[Open Preview]({$this->preview->fqdn}) | " : ''),
                ProcessStatus::ERROR => $this->body = "The preview deployment for **{$serviceName}** failed. 🔴\n\n",
                ProcessStatus::KILLED => $this->body = "The preview deployment for **{$serviceName}** was killed. ⚫\n\n",
                ProcessStatus::CANCELLED => $this->body = "The preview deployment for **{$serviceName}** was cancelled. 🚫\n\n",
            };
            $this->build_logs_url = base_url()."/deployments/{$this->deployment_uuid}";

            $this->body .= '[Open Build Logs]('.$this->build_logs_url.")\n\n\n";
            $this->body .= 'Last updated at: '.now()->toDateTimeString().' CET';
            if ($this->preview->pull_request_issue_comment_id) {
                $this->update_comment();
            } else {
                $this->create_comment();
            }
        } catch (\Throwable $e) {
            return $e;
        }
    }

    private function getGithubSource(): ?GithubApp
    {
        $source = $this->application->source;

        return $source instanceof GithubApp ? $source : null;
    }

    private function update_comment()
    {
        ['data' => $data] = githubApi(source: $this->getGithubSource(), endpoint: "/repos/{$this->application->git_repository}/issues/comments/{$this->preview->pull_request_issue_comment_id}", method: 'patch', data: [
            'body' => $this->body,
        ], throwError: false);
        if (data_get($data, 'message') === 'Not Found') {
            $this->create_comment();
        }
    }

    private function create_comment()
    {
        ['data' => $data] = githubApi(source: $this->getGithubSource(), endpoint: "/repos/{$this->application->git_repository}/issues/{$this->preview->pull_request_id}/comments", method: 'post', data: [
            'body' => $this->body,
        ]);
        $this->preview->pull_request_issue_comment_id = $data['id'];
        $this->preview->save();
    }

    private function delete_comment()
    {
        githubApi(source: $this->getGithubSource(), endpoint: "/repos/{$this->application->git_repository}/issues/comments/{$this->preview->pull_request_issue_comment_id}", method: 'delete');
    }
}
