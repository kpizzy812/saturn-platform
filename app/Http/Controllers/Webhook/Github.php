<?php

namespace App\Http\Controllers\Webhook;

use App\Actions\Application\CleanupPreviewDeployment;
use App\Enums\ProcessStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ApplicationPullRequestUpdateJob;
use App\Jobs\GithubAppPermissionJob;
use App\Models\Application;
use App\Models\ApplicationPreview;
use App\Models\GithubApp;
use App\Models\PrivateKey;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Visus\Cuid2\Cuid2;

class Github extends Controller
{
    public function manual(Request $request)
    {
        try {
            $return_payloads = collect([]);
            $x_github_delivery = request()->header('X-GitHub-Delivery');
            $x_github_event = Str::lower($request->header('X-GitHub-Event'));
            $x_hub_signature_256 = Str::after($request->header('X-Hub-Signature-256'), 'sha256=');
            $content_type = $request->header('Content-Type');
            $payload = $request->collect();
            if ($x_github_event === 'ping') {
                // Just pong
                return response('pong');
            }

            if ($content_type !== 'application/json') {
                $payload = json_decode(data_get($payload, 'payload'), true);
            }
            $branch = null;
            $full_name = null;
            $action = null;
            $base_branch = null;
            $pull_request_id = null;
            $pull_request_html_url = null;
            $author_association = null;
            $changed_files = collect();
            if ($x_github_event === 'push') {
                $branch = data_get($payload, 'ref');
                $full_name = data_get($payload, 'repository.full_name');
                if (Str::isMatch('/refs\/heads\/*/', $branch)) {
                    $branch = Str::after($branch, 'refs/heads/');
                }
                $added_files = data_get($payload, 'commits.*.added');
                $removed_files = data_get($payload, 'commits.*.removed');
                $modified_files = data_get($payload, 'commits.*.modified');
                $changed_files = collect($added_files)->concat($removed_files)->concat($modified_files)->unique()->flatten();
            }
            if ($x_github_event === 'pull_request') {
                $action = data_get($payload, 'action');
                $full_name = data_get($payload, 'repository.full_name');
                $pull_request_id = data_get($payload, 'number');
                $pull_request_html_url = data_get($payload, 'pull_request.html_url');
                $branch = data_get($payload, 'pull_request.head.ref');
                $base_branch = data_get($payload, 'pull_request.base.ref');
                $author_association = data_get($payload, 'pull_request.author_association');
            }
            if (! $branch) {
                return response('Nothing to do. No branch found in the request.');
            }
            $applicationsQuery = Application::where('git_repository', 'like', "%$full_name%");
            if ($x_github_event === 'push') {
                $applications = $applicationsQuery->where('git_branch', $branch)->get();
                if ($applications->isEmpty()) {
                    return response("Nothing to do. No applications found with deploy key set, branch is '$branch' and Git Repository name has $full_name.");
                }
            } elseif ($x_github_event === 'pull_request') {
                $applications = $applicationsQuery->where('git_branch', $base_branch)->get();
                if ($applications->isEmpty()) {
                    return response("Nothing to do. No applications found with branch '$base_branch'.");
                }
            } else {
                return response('Nothing to do. Unsupported event type.');
            }
            $applicationsByServer = $applications->groupBy(function ($app) {
                return $app->destination->server_id;
            });

            foreach ($applicationsByServer as $serverId => $serverApplications) {
                foreach ($serverApplications as $application) {
                    $webhook_secret = data_get($application, 'manual_webhook_secret_github');
                    $hmac = hash_hmac('sha256', $request->getContent(), $webhook_secret);
                    // Security: Always validate signature - never skip in dev mode
                    if (! hash_equals($x_hub_signature_256, $hmac)) {
                        $return_payloads->push([
                            'application' => $application->name,
                            'status' => 'failed',
                            'message' => 'Invalid signature.',
                        ]);

                        continue;
                    }
                    $isFunctional = $application->destination->server->isFunctional();
                    if (! $isFunctional) {
                        $return_payloads->push([
                            'application' => $application->name,
                            'status' => 'failed',
                            'message' => 'Server is not functional.',
                        ]);

                        continue;
                    }
                    if ($x_github_event === 'push') {
                        if ($application->isDeployable()) {
                            $is_watch_path_triggered = $application->isWatchPathsTriggered($changed_files);
                            if ($is_watch_path_triggered || is_null($application->watch_paths)) {
                                $deployment_uuid = new Cuid2;
                                $result = queue_application_deployment(
                                    application: $application,
                                    deployment_uuid: $deployment_uuid,
                                    force_rebuild: false,
                                    commit: data_get($payload, 'after', 'HEAD'),
                                    is_webhook: true,
                                );
                                if ($result['status'] === 'queue_full') {
                                    return response($result['message'], 429)->header('Retry-After', '60');
                                } elseif ($result['status'] === 'skipped') {
                                    $return_payloads->push([
                                        'application' => $application->name,
                                        'status' => 'skipped',
                                        'message' => $result['message'],
                                    ]);
                                } else {
                                    $return_payloads->push([
                                        'application' => $application->name,
                                        'status' => 'success',
                                        'message' => 'Deployment queued.',
                                        'application_uuid' => $application->uuid,
                                        'application_name' => $application->name,
                                        'deployment_uuid' => $result['deployment_uuid'],
                                    ]);
                                }
                            } else {
                                $paths = str($application->watch_paths)->explode("\n");
                                $return_payloads->push([
                                    'status' => 'failed',
                                    'message' => 'Changed files do not match watch paths. Ignoring deployment.',
                                    'application_uuid' => $application->uuid,
                                    'application_name' => $application->name,
                                    'details' => [
                                        'changed_files' => $changed_files,
                                        'watch_paths' => $paths,
                                    ],
                                ]);
                            }
                        } else {
                            $return_payloads->push([
                                'status' => 'failed',
                                'message' => 'Deployments disabled.',
                                'application_uuid' => $application->uuid,
                                'application_name' => $application->name,
                            ]);
                        }
                    }
                    if ($x_github_event === 'pull_request') {
                        if ($action === 'opened' || $action === 'synchronize' || $action === 'reopened') {
                            if ($application->isPRDeployable()) {
                                // Check if PR deployments from public contributors are restricted
                                if (! $application->settings->is_pr_deployments_public_enabled) {
                                    $trustedAssociations = ['OWNER', 'MEMBER', 'COLLABORATOR', 'CONTRIBUTOR'];
                                    if (! in_array($author_association, $trustedAssociations)) {
                                        $return_payloads->push([
                                            'application' => $application->name,
                                            'status' => 'failed',
                                            'message' => 'PR deployments are restricted to repository members and contributors. Author association: '.$author_association,
                                        ]);

                                        continue;
                                    }
                                }

                                // Get all apps to deploy (for monorepos, deploy all apps in the group)
                                $appsToDeploy = $application->isPartOfMonorepo()
                                    ? $application->getMonorepoGroup()
                                    : collect([$application]);

                                foreach ($appsToDeploy as $appToDeploy) {
                                    // Skip apps that don't have PR deployments enabled
                                    if (! $appToDeploy->isPRDeployable()) {
                                        continue;
                                    }

                                    $deployment_uuid = new Cuid2;
                                    $found = ApplicationPreview::where('application_id', $appToDeploy->id)->where('pull_request_id', $pull_request_id)->first();
                                    if (! $found) {
                                        if ($appToDeploy->build_pack === 'dockercompose') {
                                            $pr_app = ApplicationPreview::create([
                                                'git_type' => 'github',
                                                'application_id' => $appToDeploy->id,
                                                'pull_request_id' => $pull_request_id,
                                                'pull_request_html_url' => $pull_request_html_url,
                                                'docker_compose_domains' => $appToDeploy->docker_compose_domains,
                                            ]);
                                            $pr_app->generate_preview_fqdn_compose();
                                        } else {
                                            $pr_app = ApplicationPreview::create([
                                                'git_type' => 'github',
                                                'application_id' => $appToDeploy->id,
                                                'pull_request_id' => $pull_request_id,
                                                'pull_request_html_url' => $pull_request_html_url,
                                            ]);
                                            $pr_app->generate_preview_fqdn();
                                        }
                                    }

                                    $result = queue_application_deployment(
                                        application: $appToDeploy,
                                        pull_request_id: $pull_request_id,
                                        deployment_uuid: $deployment_uuid,
                                        force_rebuild: false,
                                        commit: data_get($payload, 'head.sha', 'HEAD'),
                                        is_webhook: true,
                                        git_type: 'github'
                                    );
                                    if ($result['status'] === 'queue_full') {
                                        return response($result['message'], 429)->header('Retry-After', '60');
                                    } elseif ($result['status'] === 'skipped') {
                                        $return_payloads->push([
                                            'application' => $appToDeploy->name,
                                            'status' => 'skipped',
                                            'message' => $result['message'],
                                        ]);
                                    } else {
                                        $return_payloads->push([
                                            'application' => $appToDeploy->name,
                                            'status' => 'success',
                                            'message' => 'Preview deployment queued.',
                                        ]);
                                    }
                                }
                            } else {
                                $return_payloads->push([
                                    'application' => $application->name,
                                    'status' => 'failed',
                                    'message' => 'Preview deployments disabled.',
                                ]);
                            }
                        }
                        if ($action === 'closed') {
                            // For monorepos, clean up all apps in the group
                            $appsToCleanup = $application->isPartOfMonorepo()
                                ? $application->getMonorepoGroup()
                                : collect([$application]);

                            foreach ($appsToCleanup as $appToCleanup) {
                                $found = ApplicationPreview::where('application_id', $appToCleanup->id)->where('pull_request_id', $pull_request_id)->first();
                                if ($found) {
                                    // Use comprehensive cleanup that cancels active deployments,
                                    // kills helper containers, and removes all PR containers
                                    CleanupPreviewDeployment::run($appToCleanup, $pull_request_id, $found);

                                    $return_payloads->push([
                                        'application' => $appToCleanup->name,
                                        'status' => 'success',
                                        'message' => 'Preview deployment closed.',
                                    ]);
                                }
                            }

                            // If no previews were found for any app in the group
                            if ($return_payloads->where('status', 'success')->isEmpty()) {
                                $return_payloads->push([
                                    'application' => $application->name,
                                    'status' => 'failed',
                                    'message' => 'No preview deployment found.',
                                ]);
                            }
                        }
                    }
                }
            }

            return response($return_payloads);
        } catch (Exception $e) {
            return handleError($e);
        }
    }

    public function normal(Request $request)
    {
        try {
            $return_payloads = collect([]);
            $id = null;
            $x_github_delivery = $request->header('X-GitHub-Delivery');
            $x_github_event = Str::lower($request->header('X-GitHub-Event'));
            $x_github_hook_installation_target_id = $request->header('X-GitHub-Hook-Installation-Target-Id');
            $x_hub_signature_256 = Str::after($request->header('X-Hub-Signature-256'), 'sha256=');
            $payload = $request->collect();
            if ($x_github_event === 'ping') {
                // Just pong
                return response('pong');
            }
            $github_app = GithubApp::where('app_id', $x_github_hook_installation_target_id)->first();
            if (is_null($github_app)) {
                return response('Nothing to do. No GitHub App found.');
            }
            $webhook_secret = data_get($github_app, 'webhook_secret');
            $hmac = hash_hmac('sha256', $request->getContent(), $webhook_secret);
            // Security: Always validate signature - never skip in any environment
            if (! hash_equals($x_hub_signature_256, $hmac)) {
                return response('Invalid signature.');
            }
            if ($x_github_event === 'installation' || $x_github_event === 'installation_repositories') {
                // Installation handled by setup redirect url. Repositories queried on-demand.
                $action = data_get($payload, 'action');
                if ($action === 'new_permissions_accepted') {
                    GithubAppPermissionJob::dispatch($github_app);
                }

                return response('cool');
            }
            $branch = null;
            $action = null;
            $base_branch = null;
            $pull_request_id = null;
            $pull_request_html_url = null;
            $author_association = null;
            $changed_files = collect();
            if ($x_github_event === 'push') {
                $id = data_get($payload, 'repository.id');
                $branch = data_get($payload, 'ref');
                if (Str::isMatch('/refs\/heads\/*/', $branch)) {
                    $branch = Str::after($branch, 'refs/heads/');
                }
                $added_files = data_get($payload, 'commits.*.added');
                $removed_files = data_get($payload, 'commits.*.removed');
                $modified_files = data_get($payload, 'commits.*.modified');
                $changed_files = collect($added_files)->concat($removed_files)->concat($modified_files)->unique()->flatten();
            }
            if ($x_github_event === 'pull_request') {
                $action = data_get($payload, 'action');
                $id = data_get($payload, 'repository.id');
                $pull_request_id = data_get($payload, 'number');
                $pull_request_html_url = data_get($payload, 'pull_request.html_url');
                $branch = data_get($payload, 'pull_request.head.ref');
                $base_branch = data_get($payload, 'pull_request.base.ref');
                $author_association = data_get($payload, 'pull_request.author_association');
            }
            if (! $id || ! $branch) {
                return response('Nothing to do. No id or branch found.');
            }
            $applicationsQuery = Application::where('repository_project_id', $id)
                ->where('source_id', $github_app->id)
                ->whereRelation('source', 'is_public', false);
            if ($x_github_event === 'push') {
                $applications = $applicationsQuery->where('git_branch', $branch)->get();
                if ($applications->isEmpty()) {
                    return response("Nothing to do. No applications found with branch '$branch'.");
                }
            } elseif ($x_github_event === 'pull_request') {
                $applications = $applicationsQuery->where('git_branch', $base_branch)->get();
                if ($applications->isEmpty()) {
                    return response("Nothing to do. No applications found with branch '$base_branch'.");
                }
            } else {
                return response('Nothing to do. Unsupported event type.');
            }
            $applicationsByServer = $applications->groupBy(function ($app) {
                return $app->destination->server_id;
            });

            foreach ($applicationsByServer as $serverId => $serverApplications) {
                foreach ($serverApplications as $application) {
                    $isFunctional = $application->destination->server->isFunctional();
                    if (! $isFunctional) {
                        $return_payloads->push([
                            'status' => 'failed',
                            'message' => 'Server is not functional.',
                            'application_uuid' => $application->uuid,
                            'application_name' => $application->name,
                        ]);

                        continue;
                    }
                    if ($x_github_event === 'push') {
                        if ($application->isDeployable()) {
                            $is_watch_path_triggered = $application->isWatchPathsTriggered($changed_files);
                            if ($is_watch_path_triggered || is_null($application->watch_paths)) {
                                $deployment_uuid = new Cuid2;
                                $result = queue_application_deployment(
                                    application: $application,
                                    deployment_uuid: $deployment_uuid,
                                    commit: data_get($payload, 'after', 'HEAD'),
                                    force_rebuild: false,
                                    is_webhook: true,
                                );
                                if ($result['status'] === 'queue_full') {
                                    return response($result['message'], 429)->header('Retry-After', '60');
                                }
                                $return_payloads->push([
                                    'status' => $result['status'],
                                    'message' => $result['message'],
                                    'application_uuid' => $application->uuid,
                                    'application_name' => $application->name,
                                    'deployment_uuid' => $result['deployment_uuid'] ?? null,
                                ]);
                            } else {
                                $paths = str($application->watch_paths)->explode("\n");
                                $return_payloads->push([
                                    'status' => 'failed',
                                    'message' => 'Changed files do not match watch paths. Ignoring deployment.',
                                    'application_uuid' => $application->uuid,
                                    'application_name' => $application->name,
                                    'details' => [
                                        'changed_files' => $changed_files,
                                        'watch_paths' => $paths,
                                    ],
                                ]);
                            }
                        } else {
                            $return_payloads->push([
                                'status' => 'failed',
                                'message' => 'Deployments disabled.',
                                'application_uuid' => $application->uuid,
                                'application_name' => $application->name,
                            ]);
                        }
                    }
                    if ($x_github_event === 'pull_request') {
                        if ($action === 'opened' || $action === 'synchronize' || $action === 'reopened') {
                            if ($application->isPRDeployable()) {
                                // Check if PR deployments from public contributors are restricted
                                if (! $application->settings->is_pr_deployments_public_enabled) {
                                    $trustedAssociations = ['OWNER', 'MEMBER', 'COLLABORATOR', 'CONTRIBUTOR'];
                                    if (! in_array($author_association, $trustedAssociations)) {
                                        $return_payloads->push([
                                            'application' => $application->name,
                                            'status' => 'failed',
                                            'message' => 'PR deployments are restricted to repository members and contributors. Author association: '.$author_association,
                                        ]);

                                        continue;
                                    }
                                }

                                // Get all apps to deploy (for monorepos, deploy all apps in the group)
                                $appsToDeploy = $application->isPartOfMonorepo()
                                    ? $application->getMonorepoGroup()
                                    : collect([$application]);

                                foreach ($appsToDeploy as $appToDeploy) {
                                    // Skip apps that don't have PR deployments enabled
                                    if (! $appToDeploy->isPRDeployable()) {
                                        continue;
                                    }

                                    $deployment_uuid = new Cuid2;
                                    $found = ApplicationPreview::where('application_id', $appToDeploy->id)->where('pull_request_id', $pull_request_id)->first();
                                    if (! $found) {
                                        ApplicationPreview::create([
                                            'git_type' => 'github',
                                            'application_id' => $appToDeploy->id,
                                            'pull_request_id' => $pull_request_id,
                                            'pull_request_html_url' => $pull_request_html_url,
                                        ]);
                                    }
                                    $result = queue_application_deployment(
                                        application: $appToDeploy,
                                        pull_request_id: $pull_request_id,
                                        deployment_uuid: $deployment_uuid,
                                        force_rebuild: false,
                                        commit: data_get($payload, 'head.sha', 'HEAD'),
                                        is_webhook: true,
                                        git_type: 'github'
                                    );
                                    if ($result['status'] === 'queue_full') {
                                        return response($result['message'], 429)->header('Retry-After', '60');
                                    } elseif ($result['status'] === 'skipped') {
                                        $return_payloads->push([
                                            'application' => $appToDeploy->name,
                                            'status' => 'skipped',
                                            'message' => $result['message'],
                                        ]);
                                    } else {
                                        $return_payloads->push([
                                            'application' => $appToDeploy->name,
                                            'status' => 'success',
                                            'message' => 'Preview deployment queued.',
                                        ]);
                                    }
                                }
                            } else {
                                $return_payloads->push([
                                    'application' => $application->name,
                                    'status' => 'failed',
                                    'message' => 'Preview deployments disabled.',
                                ]);
                            }
                        }
                        if ($action === 'closed' || $action === 'close') {
                            // For monorepos, clean up all apps in the group
                            $appsToCleanup = $application->isPartOfMonorepo()
                                ? $application->getMonorepoGroup()
                                : collect([$application]);

                            foreach ($appsToCleanup as $appToCleanup) {
                                $found = ApplicationPreview::where('application_id', $appToCleanup->id)->where('pull_request_id', $pull_request_id)->first();
                                if ($found) {
                                    // Delete the PR comment on GitHub (GitHub-specific feature)
                                    ApplicationPullRequestUpdateJob::dispatchSync(application: $appToCleanup, preview: $found, status: ProcessStatus::CLOSED);

                                    // Use comprehensive cleanup that cancels active deployments,
                                    // kills helper containers, and removes all PR containers
                                    CleanupPreviewDeployment::run($appToCleanup, $pull_request_id, $found);

                                    $return_payloads->push([
                                        'application' => $appToCleanup->name,
                                        'status' => 'success',
                                        'message' => 'Preview deployment closed.',
                                    ]);
                                }
                            }

                            // If no previews were found for any app in the group
                            if ($return_payloads->where('status', 'success')->isEmpty()) {
                                $return_payloads->push([
                                    'application' => $application->name,
                                    'status' => 'failed',
                                    'message' => 'No preview deployment found.',
                                ]);
                            }
                        }
                    }
                }
            }

            return response($return_payloads);
        } catch (Exception $e) {
            return handleError($e);
        }
    }

    public function redirect(Request $request)
    {
        try {
            $code = $request->get('code');
            $state = $request->get('state');

            if (! $code || ! $state) {
                \Log::error('GitHub App redirect: missing code or state', [
                    'code' => $code,
                    'state' => $state,
                ]);

                return redirect()->route('sources.github.index')
                    ->with('error', 'Invalid GitHub callback — missing parameters.');
            }

            $github_app = GithubApp::where('uuid', $state)
                ->where('team_id', currentTeam()->id)
                ->first();
            if (! $github_app) {
                \Log::error('GitHub App redirect: GithubApp not found or unauthorized', ['state' => $state]);

                return redirect()->route('sources.github.index')
                    ->with('error', 'GitHub App record not found. Please try creating again.');
            }

            $api_url = data_get($github_app, 'api_url');
            // Use send() instead of post() to avoid sending [] as JSON body.
            // Laravel's post() defaults to json_encode([]) which GitHub rejects with 422.
            $response = Http::timeout(15)
                ->accept('application/vnd.github+json')
                ->send('POST', "$api_url/app-manifests/$code/conversions");

            if ($response->failed()) {
                \Log::error('GitHub App redirect: manifest conversion failed', [
                    'github_app_id' => $github_app->id,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);

                return redirect()->route('sources.github.index')
                    ->with('error', 'Failed to complete GitHub App setup (HTTP '.$response->status().'). Please try again.');
            }

            $data = $response->json();
            $id = data_get($data, 'id');
            $slug = data_get($data, 'slug');
            $client_id = data_get($data, 'client_id');
            $client_secret = data_get($data, 'client_secret');
            $pem = data_get($data, 'pem');
            $webhook_secret = data_get($data, 'webhook_secret');

            $private_key = PrivateKey::create([
                'name' => "github-app-{$slug}",
                'private_key' => $pem,
                'team_id' => $github_app->team_id,
                'is_git_related' => true,
            ]);

            $github_app->name = $slug;
            $github_app->app_id = $id;
            $github_app->client_id = $client_id;
            $github_app->client_secret = $client_secret;
            $github_app->webhook_secret = $webhook_secret;
            $github_app->private_key_id = $private_key->id;
            $github_app->save();

            \Log::info('GitHub App created successfully', [
                'github_app_id' => $github_app->id,
                'app_id' => $id,
                'slug' => $slug,
            ]);

            // Redirect directly to GitHub install page to streamline the flow
            $installPath = getInstallationPath($github_app);

            return redirect()->away($installPath);
        } catch (Exception $e) {
            \Log::error('GitHub App redirect error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('sources.github.index')
                ->with('error', 'GitHub App setup failed: '.$e->getMessage());
        }
    }

    public function install(Request $request)
    {
        try {
            $installation_id = $request->get('installation_id');
            $source = $request->get('source');
            $setup_action = $request->get('setup_action');

            if (! $installation_id) {
                \Log::error('GitHub App install: missing installation_id');

                return redirect()->route('sources.github.index')
                    ->with('error', 'Invalid GitHub install callback — missing installation_id.');
            }

            // Find GithubApp by UUID if source is provided (team-scoped)
            $github_app = null;
            $teamId = currentTeam()->id;
            if ($source) {
                $github_app = GithubApp::where('uuid', $source)
                    ->where('team_id', $teamId)
                    ->first();
            }

            // Fallback: find by checking which app owns this installation via GitHub API (team-scoped)
            if (! $github_app) {
                $candidates = GithubApp::whereNotNull('app_id')
                    ->whereNull('installation_id')
                    ->whereNotNull('private_key_id')
                    ->where('team_id', $teamId)
                    ->get();

                foreach ($candidates as $candidate) {
                    try {
                        $jwt = generateGithubJwt($candidate);
                        $response = Http::withHeaders([
                            'Authorization' => "Bearer $jwt",
                            'Accept' => 'application/vnd.github+json',
                        ])->get("{$candidate->api_url}/app/installations/{$installation_id}");

                        if ($response->successful()) {
                            $github_app = $candidate;
                            break;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            if (! $github_app) {
                \Log::error('GitHub App install: GithubApp not found', [
                    'source' => $source,
                    'installation_id' => $installation_id,
                ]);

                return redirect()->route('sources.github.index')
                    ->with('error', 'GitHub App record not found.');
            }

            if ($setup_action === 'install') {
                $github_app->installation_id = $installation_id;
                $github_app->save();

                \Log::info('GitHub App installed', [
                    'github_app_id' => $github_app->id,
                    'installation_id' => $installation_id,
                ]);
            }

            return redirect()->route('sources.github.show', ['id' => $github_app->id]);
        } catch (Exception $e) {
            \Log::error('GitHub App install error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('sources.github.index')
                ->with('error', 'GitHub App installation failed: '.$e->getMessage());
        }
    }
}
