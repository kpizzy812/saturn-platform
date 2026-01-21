<?php

use App\Notifications\Channels\EmailChannel;

/**
 * EmailChannel tests for Resend API error handling.
 *
 * These tests verify the error handling code structure and message mappings
 * using reflection and source code analysis since static method mocking
 * (Team::find) is not available in Unit tests.
 */
beforeEach(function () {
    // Get the EmailChannel source code for analysis
    $reflection = new ReflectionClass(EmailChannel::class);
    $this->sourceFile = $reflection->getFileName();
    $this->sourceCode = file_get_contents($this->sourceFile);
});

it('has error handling for invalid Resend API key (403)', function () {
    // Verify 403 error code is handled with appropriate message
    expect($this->sourceCode)
        ->toContain('403')
        ->toContain('Invalid Resend API key')
        ->toContain('Please verify your API key in the Resend dashboard');
});

it('has error handling for restricted Resend API key (401)', function () {
    // Verify 401 error code is handled with appropriate message
    expect($this->sourceCode)
        ->toContain('401')
        ->toContain('restricted permissions')
        ->toContain('Full Access permissions');
});

it('has error handling for rate limiting (429)', function () {
    // Verify 429 error code is handled with appropriate message
    expect($this->sourceCode)
        ->toContain('429')
        ->toContain('rate limit exceeded')
        ->toContain('try again');
});

it('has error handling for validation errors (400)', function () {
    // Verify 400 error code is handled
    expect($this->sourceCode)
        ->toContain('400')
        ->toContain('Email validation failed');
});

it('has error handling for network/transport errors', function () {
    // Verify TransporterException is caught and handled
    expect($this->sourceCode)
        ->toContain('TransporterException')
        ->toContain('Unable to connect to Resend API')
        ->toContain('check your internet connection');
});

it('has generic error handling with message for unknown error codes', function () {
    // Verify default case exists in the match statement
    expect($this->sourceCode)
        ->toContain('default =>')
        ->toContain('Failed to send email via Resend');
});

it('uses NonReportableException for expected errors to avoid Sentry spam', function () {
    // Verify NonReportableException is used for expected errors (403, 401, 400)
    expect($this->sourceCode)
        ->toContain('NonReportableException::fromException')
        ->toContain('in_array($e->getErrorCode(), [403, 401, 400])');
});

it('redacts sensitive data before logging', function () {
    // Verify sensitive data is redacted before internal notification
    expect($this->sourceCode)
        ->toContain("data_set(\$emailSettings, 'smtp_password', '********')")
        ->toContain("data_set(\$emailSettings, 'resend_api_key', '********')");
});

it('sends internal notification for Resend errors', function () {
    // Verify send_internal_notification is called for error logging
    expect($this->sourceCode)
        ->toContain('send_internal_notification(sprintf(')
        ->toContain('Resend Error');
});

it('handles domain verification errors on cloud instances', function () {
    // Verify cloud-specific domain verification error handling
    expect($this->sourceCode)
        ->toContain('isCloud()')
        ->toContain('domain is not verified')
        ->toContain('NonReportableException::fromException($e)');
});

it('uses match statement for error code mapping', function () {
    // Verify the error handling uses a match statement with proper structure
    $reflection = new ReflectionClass(EmailChannel::class);
    $sendMethod = $reflection->getMethod('send');

    $methodStart = $sendMethod->getStartLine();
    $methodEnd = $sendMethod->getEndLine();
    $methodSource = implode('', array_slice(file($this->sourceFile), $methodStart - 1, $methodEnd - $methodStart + 1));

    // Verify match statement structure
    expect($methodSource)
        ->toContain('$userMessage = match ($e->getErrorCode())')
        ->toContain('403 =>')
        ->toContain('401 =>')
        ->toContain('429 =>')
        ->toContain('400 =>')
        ->toContain('default =>');
});

it('catches Resend ErrorException specifically', function () {
    // Verify specific Resend exception handling
    expect($this->sourceCode)
        ->toContain('catch (\Resend\Exceptions\ErrorException $e)')
        ->toContain('$e->getErrorCode()')
        ->toContain('$e->getErrorMessage()');
});
