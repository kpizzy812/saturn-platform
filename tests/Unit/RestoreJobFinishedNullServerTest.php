<?php

/**
 * Tests for RestoreJobFinished and S3RestoreJobFinished events to ensure they handle
 * null server scenarios gracefully (when server is deleted during operation).
 *
 * These tests verify the code structure since the events require database access
 * which is not available in Unit tests.
 */
describe('RestoreJobFinished null server handling', function () {
    it('handles null server gracefully in RestoreJobFinished event', function () {
        // Verify the event checks for null server before executing commands
        $eventFile = file_get_contents(__DIR__.'/../../app/Events/RestoreJobFinished.php');

        expect($eventFile)
            ->toContain('$server = Server::find($serverId)')
            ->toContain('if ($server)')
            ->toContain('instant_remote_process($commands, $server');
    });

    it('handles null server gracefully in S3RestoreJobFinished event', function () {
        // Verify the event checks for null server before executing commands
        $eventFile = file_get_contents(__DIR__.'/../../app/Events/S3RestoreJobFinished.php');

        expect($eventFile)
            ->toContain('$server = Server::find($serverId)')
            ->toContain('if ($server)')
            ->toContain('instant_remote_process(');
    });

    it('only executes commands when serverId and container are filled', function () {
        $eventFile = file_get_contents(__DIR__.'/../../app/Events/RestoreJobFinished.php');

        // Verify the guard clause for filled container and serverId
        expect($eventFile)
            ->toContain('if (filled($container) && filled($serverId))');
    });

    it('S3RestoreJobFinished only executes when serverId is filled', function () {
        $eventFile = file_get_contents(__DIR__.'/../../app/Events/S3RestoreJobFinished.php');

        // Verify the guard clauses for filled values
        expect($eventFile)
            ->toContain('if (filled($serverId))')
            ->toContain('if (filled($containerName))')
            ->toContain('if (filled($container))');
    });

    it('uses isSafeTmpPath to validate paths in RestoreJobFinished', function () {
        $eventFile = file_get_contents(__DIR__.'/../../app/Events/RestoreJobFinished.php');

        // Verify security validation for paths
        expect($eventFile)
            ->toContain('isSafeTmpPath($scriptPath)')
            ->toContain('isSafeTmpPath($tmpPath)');
    });

    it('uses isSafeTmpPath to validate paths in S3RestoreJobFinished', function () {
        $eventFile = file_get_contents(__DIR__.'/../../app/Events/S3RestoreJobFinished.php');

        // Verify security validation for paths
        expect($eventFile)
            ->toContain('isSafeTmpPath(');
    });
});
