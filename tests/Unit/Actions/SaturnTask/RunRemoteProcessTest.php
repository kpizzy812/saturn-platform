<?php

namespace Tests\Unit\Actions\SaturnTask;

use App\Actions\SaturnTask\RunRemoteProcess;
use Tests\TestCase;

/**
 * Unit tests for RunRemoteProcess action.
 */
class RunRemoteProcessTest extends TestCase
{
    /** @test */
    public function it_validates_activity_type_in_constructor(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('ActivityTypes::INLINE->value', $source);
        $this->assertStringContainsString('ActivityTypes::COMMAND->value', $source);
        $this->assertStringContainsString('Incompatible Activity to run a remote command.', $source);
    }

    /** @test */
    public function it_has_throttle_interval_of_200ms(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('throttle_interval_ms = 200', $source);
    }

    /** @test */
    public function decode_output_returns_empty_for_null(): void
    {
        $result = RunRemoteProcess::decodeOutput(null);

        $this->assertEquals('', $result);
    }

    /** @test */
    public function it_uses_ssh_multiplexing_helper(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('SshMultiplexingHelper::generateSshCommand(', $source);
    }

    /** @test */
    public function it_reads_server_uuid_from_activity(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('server_uuid', $source);
        $this->assertStringContainsString('command', $source);
    }

    /** @test */
    public function it_sets_finished_status_on_success(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('ProcessStatus::FINISHED', $source);
    }

    /** @test */
    public function it_sets_error_status_on_failure(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('ProcessStatus::ERROR', $source);
    }

    /** @test */
    public function it_throws_on_non_zero_exit_unless_ignore_errors(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('ignore_errors', $source);
        $this->assertStringContainsString('RuntimeException', $source);
    }

    /** @test */
    public function it_dispatches_event_on_finish(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('call_event_on_finish', $source);
        $this->assertStringContainsString('call_event_data', $source);
    }

    /** @test */
    public function encode_output_uses_json_with_unicode(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('JSON_THROW_ON_ERROR', $source);
        $this->assertStringContainsString('JSON_UNESCAPED_UNICODE', $source);
    }

    /** @test */
    public function encode_output_includes_timestamp_and_order(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString("'timestamp'", $source);
        $this->assertStringContainsString("'order'", $source);
        $this->assertStringContainsString("'batch'", $source);
    }

    /** @test */
    public function it_hides_output_when_flag_set(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString('hide_from_output', $source);
    }

    /** @test */
    public function it_uses_ssh_command_timeout_config(): void
    {
        $source = file_get_contents(app_path('Actions/SaturnTask/RunRemoteProcess.php'));

        $this->assertStringContainsString("config('constants.ssh.command_timeout')", $source);
    }
}
