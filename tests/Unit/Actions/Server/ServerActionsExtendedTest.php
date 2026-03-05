<?php

namespace Tests\Unit\Actions\Server;

use App\Actions\Server\CheckUpdates;
use Tests\TestCase;

/**
 * Unit tests for Server Actions: RunCommand, RestartContainer, ResourcesCheck,
 * DeleteServer, CheckUpdates, StartLogDrain.
 */
class ServerActionsExtendedTest extends TestCase
{
    // =========================================================================
    // RunCommand
    // =========================================================================

    /** @test */
    public function run_command_uses_remote_process_with_command_type(): void
    {
        $source = file_get_contents(app_path('Actions/Server/RunCommand.php'));

        $this->assertStringContainsString('remote_process(', $source);
        $this->assertStringContainsString('ActivityTypes::COMMAND->value', $source);
        $this->assertStringContainsString('ignore_errors: true', $source);
    }

    // =========================================================================
    // RestartContainer
    // =========================================================================

    /** @test */
    public function restart_container_delegates_to_server_model(): void
    {
        $source = file_get_contents(app_path('Actions/Server/RestartContainer.php'));

        $this->assertStringContainsString('$server->restartContainer($containerName)', $source);
    }

    // =========================================================================
    // ResourcesCheck
    // =========================================================================

    /** @test */
    public function resources_check_marks_stale_resources_as_exited(): void
    {
        $source = file_get_contents(app_path('Actions/Server/ResourcesCheck.php'));

        $this->assertStringContainsString("update(['status' => 'exited'])", $source);
        $this->assertStringContainsString("'last_online_at', '<', now()->subSeconds(\$seconds)", $source);
    }

    /** @test */
    public function resources_check_covers_all_resource_types(): void
    {
        $source = file_get_contents(app_path('Actions/Server/ResourcesCheck.php'));

        $expectedModels = [
            'Application',
            'ServiceApplication',
            'ServiceDatabase',
            'StandalonePostgresql',
            'StandaloneRedis',
            'StandaloneMongodb',
            'StandaloneMysql',
            'StandaloneMariadb',
            'StandaloneKeydb',
            'StandaloneDragonfly',
            'StandaloneClickhouse',
        ];

        foreach ($expectedModels as $model) {
            $this->assertStringContainsString($model, $source, "Missing resource type: $model");
        }
    }

    /** @test */
    public function resources_check_uses_60_second_threshold(): void
    {
        $source = file_get_contents(app_path('Actions/Server/ResourcesCheck.php'));

        $this->assertStringContainsString('$seconds = 60', $source);
    }

    // =========================================================================
    // DeleteServer
    // =========================================================================

    /** @test */
    public function delete_server_uses_with_trashed(): void
    {
        $source = file_get_contents(app_path('Actions/Server/DeleteServer.php'));

        $this->assertStringContainsString('Server::withTrashed()->find($serverId)', $source);
    }

    /** @test */
    public function delete_server_supports_hetzner_deletion(): void
    {
        $source = file_get_contents(app_path('Actions/Server/DeleteServer.php'));

        $this->assertStringContainsString('$deleteFromHetzner', $source);
        $this->assertStringContainsString('HetznerService', $source);
        $this->assertStringContainsString('$hetznerService->deleteServer(', $source);
    }

    /** @test */
    public function delete_server_finds_hetzner_token_by_team(): void
    {
        $source = file_get_contents(app_path('Actions/Server/DeleteServer.php'));

        $this->assertStringContainsString('CloudProviderToken::find(', $source);
        $this->assertStringContainsString("where('team_id', \$teamId)", $source);
        $this->assertStringContainsString("where('provider', 'hetzner')", $source);
    }

    /** @test */
    public function delete_server_notifies_team_on_hetzner_failure(): void
    {
        $source = file_get_contents(app_path('Actions/Server/DeleteServer.php'));

        $this->assertStringContainsString('HetznerDeletionFailed', $source);
        $this->assertStringContainsString('$team?->notify(', $source);
    }

    /** @test */
    public function delete_server_force_deletes_from_platform(): void
    {
        $source = file_get_contents(app_path('Actions/Server/DeleteServer.php'));

        $this->assertStringContainsString('$server->forceDelete()', $source);
    }

    /** @test */
    public function delete_server_handles_already_deleted_server(): void
    {
        $source = file_get_contents(app_path('Actions/Server/DeleteServer.php'));

        $this->assertStringContainsString('if (! $server)', $source);
        $this->assertStringContainsString('Server already deleted', $source);
    }

    // =========================================================================
    // CheckUpdates
    // =========================================================================

    /** @test */
    public function check_updates_detects_os_type(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CheckUpdates.php'));

        $this->assertStringContainsString('cat /etc/os-release', $source);
        $this->assertStringContainsString("osInfo['ID']", $source);
    }

    /** @test */
    public function check_updates_normalizes_os_variants(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CheckUpdates.php'));

        $this->assertStringContainsString("'manjaro'", $source);
        $this->assertStringContainsString("'pop'", $source);
        $this->assertStringContainsString("'linuxmint'", $source);
        $this->assertStringContainsString("'fedora-asahi-remix'", $source);
    }

    /** @test */
    public function check_updates_supports_all_package_managers(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CheckUpdates.php'));

        $this->assertStringContainsString("'pacman'", $source);
        $this->assertStringContainsString("'apk'", $source);
        $this->assertStringContainsString("'apt'", $source);
        $this->assertStringContainsString("'dnf'", $source);
        $this->assertStringContainsString("'zypper'", $source);
    }

    /** @test */
    public function check_updates_returns_error_when_server_unreachable(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CheckUpdates.php'));

        $this->assertStringContainsString('$server->serverStatus()', $source);
        $this->assertStringContainsString('Server is not reachable or not ready', $source);
    }

    /** @test */
    public function check_updates_parses_apt_output_correctly(): void
    {
        $action = new CheckUpdates;

        // Use reflection to call private method
        $method = new \ReflectionMethod($action, 'parseAptOutput');
        $method->setAccessible(true);

        $aptOutput = "Listing... Done\nlibssl3/jammy-updates 3.0.13-0ubuntu0.22.04.1 amd64 [upgradable from: 3.0.2-0ubuntu1.15]";

        $result = $method->invoke($action, $aptOutput);

        $this->assertEquals(1, $result['total_updates']);
        $this->assertEquals('libssl3', $result['updates'][0]['package']);
        $this->assertEquals('3.0.13-0ubuntu0.22.04.1', $result['updates'][0]['new_version']);
        $this->assertEquals('3.0.2-0ubuntu1.15', $result['updates'][0]['current_version']);
    }

    /** @test */
    public function check_updates_parses_empty_apt_output(): void
    {
        $action = new CheckUpdates;

        $method = new \ReflectionMethod($action, 'parseAptOutput');
        $method->setAccessible(true);

        $result = $method->invoke($action, "Listing... Done\n");

        $this->assertEquals(0, $result['total_updates']);
        $this->assertEmpty($result['updates']);
    }

    /** @test */
    public function check_updates_parses_dnf_output_correctly(): void
    {
        $action = new CheckUpdates;

        $method = new \ReflectionMethod($action, 'parseDnfOutput');
        $method->setAccessible(true);

        $dnfOutput = 'cloud-init.noarch    24.1-1.el9    updates';

        $result = $method->invoke($action, $dnfOutput);

        $this->assertEquals(1, $result['total_updates']);
        $this->assertEquals('cloud-init.noarch', $result['updates'][0]['package']);
        $this->assertEquals('24.1-1.el9', $result['updates'][0]['new_version']);
        $this->assertEquals('updates', $result['updates'][0]['repository']);
        $this->assertEquals('noarch', $result['updates'][0]['architecture']);
    }

    /** @test */
    public function check_updates_parses_pacman_output_correctly(): void
    {
        $action = new CheckUpdates;

        $method = new \ReflectionMethod($action, 'parsePacmanOutput');
        $method->setAccessible(true);

        $pacmanOutput = 'linux 6.7.0.arch3-1 -> 6.7.1.arch1-1';

        $result = $method->invoke($action, $pacmanOutput);

        $this->assertEquals(1, $result['total_updates']);
        $this->assertEquals('linux', $result['updates'][0]['package']);
        $this->assertEquals('6.7.0.arch3-1', $result['updates'][0]['current_version']);
        $this->assertEquals('6.7.1.arch1-1', $result['updates'][0]['new_version']);
    }

    /** @test */
    public function check_updates_handles_empty_pacman_output(): void
    {
        $action = new CheckUpdates;

        $method = new \ReflectionMethod($action, 'parsePacmanOutput');
        $method->setAccessible(true);

        $result = $method->invoke($action, '');

        $this->assertEquals(0, $result['total_updates']);
        $this->assertEmpty($result['updates']);
    }

    // =========================================================================
    // StartLogDrain
    // =========================================================================

    /** @test */
    public function start_log_drain_supports_all_provider_types(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString('is_logdrain_newrelic_enabled', $source);
        $this->assertStringContainsString('is_logdrain_highlight_enabled', $source);
        $this->assertStringContainsString('is_logdrain_axiom_enabled', $source);
        $this->assertStringContainsString('is_logdrain_custom_enabled', $source);
    }

    /** @test */
    public function start_log_drain_stops_existing_drain_before_starting(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString('StopLogDrain::run($server)', $source);
    }

    /** @test */
    public function start_log_drain_uses_fluent_bit(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString('fluent-bit', $source);
        $this->assertStringContainsString('cr.fluentbit.io/fluent/fluent-bit:2.0', $source);
    }

    /** @test */
    public function start_log_drain_supports_newrelic_config(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString('nrlogs', $source);
        $this->assertStringContainsString('LICENSE_KEY', $source);
        $this->assertStringContainsString('BASE_URI', $source);
    }

    /** @test */
    public function start_log_drain_supports_axiom_config(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString('api.axiom.co', $source);
        $this->assertStringContainsString('AXIOM_DATASET_NAME', $source);
        $this->assertStringContainsString('AXIOM_API_KEY', $source);
    }

    /** @test */
    public function start_log_drain_supports_highlight_config(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString('otel.highlight.io', $source);
        $this->assertStringContainsString('HIGHLIGHT_PROJECT_ID', $source);
    }

    /** @test */
    public function start_log_drain_supports_custom_config(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString('logdrain_custom_config', $source);
        $this->assertStringContainsString('logdrain_custom_config_parser', $source);
    }

    /** @test */
    public function start_log_drain_returns_message_when_no_drain_enabled(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString("'No log drain is enabled.'", $source);
    }

    /** @test */
    public function start_log_drain_includes_saturn_metadata_in_config(): void
    {
        $source = file_get_contents(app_path('Actions/Server/StartLogDrain.php'));

        $this->assertStringContainsString('saturn.server_name', $source);
        $this->assertStringContainsString('saturn.app_name', $source);
        $this->assertStringContainsString('saturn.project_name', $source);
        $this->assertStringContainsString('saturn.server_ip', $source);
        $this->assertStringContainsString('saturn.environment_name', $source);
    }
}
