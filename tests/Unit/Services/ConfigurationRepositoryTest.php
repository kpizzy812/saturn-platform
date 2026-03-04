<?php

/**
 * Unit tests for ConfigurationRepository service.
 *
 * Tests cover:
 * - updateMailConfig() with resend_enabled — sets resend mail driver
 * - updateMailConfig() with smtp_enabled — sets smtp mail driver
 * - SMTP encryption mapping: starttls/none/unknown → null, tls → 'tls'
 * - disableSshMux() — sets constants.ssh.mux_enabled to false
 */

use App\Services\ConfigurationRepository;

// ─── updateMailConfig() with Resend ──────────────────────────────────────────

test('updateMailConfig sets mail.default to resend when resend_enabled is true', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = true;
    $settings->resend_api_key = 're_test_key';
    $settings->smtp_from_address = 'hello@example.com';
    $settings->smtp_from_name = 'Example';
    $settings->smtp_enabled = false;

    $repo->updateMailConfig($settings);

    expect(config('mail.default'))->toBe('resend');
});

test('updateMailConfig sets resend.api_key when resend_enabled', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = true;
    $settings->resend_api_key = 're_secret_123';
    $settings->smtp_from_address = null;
    $settings->smtp_from_name = null;
    $settings->smtp_enabled = false;

    $repo->updateMailConfig($settings);

    expect(config('resend.api_key'))->toBe('re_secret_123');
});

test('updateMailConfig uses default from address when smtp_from_address is null for resend', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = true;
    $settings->resend_api_key = 're_key';
    $settings->smtp_from_address = null;
    $settings->smtp_from_name = null;
    $settings->smtp_enabled = false;

    $repo->updateMailConfig($settings);

    expect(config('mail.from.address'))->toBe('test@example.com');
});

test('updateMailConfig uses provided from address for resend', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = true;
    $settings->resend_api_key = 're_key';
    $settings->smtp_from_address = 'custom@company.com';
    $settings->smtp_from_name = 'Custom';
    $settings->smtp_enabled = false;

    $repo->updateMailConfig($settings);

    expect(config('mail.from.address'))->toBe('custom@company.com');
    expect(config('mail.from.name'))->toBe('Custom');
});

// ─── updateMailConfig() with SMTP ────────────────────────────────────────────

test('updateMailConfig sets mail.default to smtp when smtp_enabled is true', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = false;
    $settings->smtp_enabled = true;
    $settings->smtp_encryption = 'tls';
    $settings->smtp_from_address = 'smtp@example.com';
    $settings->smtp_from_name = 'SMTP Test';
    $settings->smtp_host = 'smtp.example.com';
    $settings->smtp_port = 587;
    $settings->smtp_username = 'user';
    $settings->smtp_password = 'pass';
    $settings->smtp_timeout = 30;

    $repo->updateMailConfig($settings);

    expect(config('mail.default'))->toBe('smtp');
});

test('updateMailConfig sets smtp mailer host when smtp_enabled', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = false;
    $settings->smtp_enabled = true;
    $settings->smtp_encryption = 'none';
    $settings->smtp_from_address = null;
    $settings->smtp_from_name = null;
    $settings->smtp_host = 'mail.example.net';
    $settings->smtp_port = 465;
    $settings->smtp_username = 'user';
    $settings->smtp_password = 'pass';
    $settings->smtp_timeout = 60;

    $repo->updateMailConfig($settings);

    expect(config('mail.mailers.smtp.host'))->toBe('mail.example.net');
    expect(config('mail.mailers.smtp.port'))->toBe(465);
});

// ─── SMTP encryption mapping ──────────────────────────────────────────────────

test('updateMailConfig maps tls encryption to tls', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = false;
    $settings->smtp_enabled = true;
    $settings->smtp_encryption = 'TLS';
    $settings->smtp_from_address = null;
    $settings->smtp_from_name = null;
    $settings->smtp_host = 'smtp.host';
    $settings->smtp_port = 465;
    $settings->smtp_username = '';
    $settings->smtp_password = '';
    $settings->smtp_timeout = 30;

    $repo->updateMailConfig($settings);

    expect(config('mail.mailers.smtp.encryption'))->toBe('tls');
});

test('updateMailConfig maps starttls encryption to null', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = false;
    $settings->smtp_enabled = true;
    $settings->smtp_encryption = 'starttls';
    $settings->smtp_from_address = null;
    $settings->smtp_from_name = null;
    $settings->smtp_host = 'smtp.host';
    $settings->smtp_port = 587;
    $settings->smtp_username = '';
    $settings->smtp_password = '';
    $settings->smtp_timeout = 30;

    $repo->updateMailConfig($settings);

    expect(config('mail.mailers.smtp.encryption'))->toBeNull();
});

test('updateMailConfig maps none encryption to null', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = false;
    $settings->smtp_enabled = true;
    $settings->smtp_encryption = 'none';
    $settings->smtp_from_address = null;
    $settings->smtp_from_name = null;
    $settings->smtp_host = 'smtp.host';
    $settings->smtp_port = 25;
    $settings->smtp_username = '';
    $settings->smtp_password = '';
    $settings->smtp_timeout = 30;

    $repo->updateMailConfig($settings);

    expect(config('mail.mailers.smtp.encryption'))->toBeNull();
});

test('updateMailConfig maps unknown encryption to null', function () {
    $repo = new ConfigurationRepository(app('config'));

    $settings = new stdClass;
    $settings->resend_enabled = false;
    $settings->smtp_enabled = true;
    $settings->smtp_encryption = 'ssl';
    $settings->smtp_from_address = null;
    $settings->smtp_from_name = null;
    $settings->smtp_host = 'smtp.host';
    $settings->smtp_port = 465;
    $settings->smtp_username = '';
    $settings->smtp_password = '';
    $settings->smtp_timeout = 30;

    $repo->updateMailConfig($settings);

    expect(config('mail.mailers.smtp.encryption'))->toBeNull();
});

// ─── disableSshMux() ──────────────────────────────────────────────────────────

test('disableSshMux sets constants.ssh.mux_enabled to false', function () {
    $repo = new ConfigurationRepository(app('config'));

    // Ensure it is true first
    config(['constants.ssh.mux_enabled' => true]);
    expect(config('constants.ssh.mux_enabled'))->toBeTrue();

    $repo->disableSshMux();

    expect(config('constants.ssh.mux_enabled'))->toBeFalse();
});

test('disableSshMux is idempotent when already false', function () {
    $repo = new ConfigurationRepository(app('config'));

    config(['constants.ssh.mux_enabled' => false]);
    $repo->disableSshMux();

    expect(config('constants.ssh.mux_enabled'))->toBeFalse();
});
