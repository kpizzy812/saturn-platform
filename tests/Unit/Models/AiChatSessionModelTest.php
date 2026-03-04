<?php

/**
 * Unit tests for AiChatSession model.
 *
 * Tests cover:
 * - isActive() / isArchived() — pure status checks
 * - getContextModelClass() — resolves context_type to model class
 */

use App\Models\AiChatSession;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandalonePostgresql;

// ─── isActive() ───────────────────────────────────────────────────────────────

test('isActive returns true when status is active', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['status' => 'active']);
    expect($session->isActive())->toBeTrue();
});

test('isActive returns false when status is archived', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['status' => 'archived']);
    expect($session->isActive())->toBeFalse();
});

test('isActive returns false when status is unknown', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['status' => 'closed']);
    expect($session->isActive())->toBeFalse();
});

// ─── isArchived() ─────────────────────────────────────────────────────────────

test('isArchived returns true when status is archived', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['status' => 'archived']);
    expect($session->isArchived())->toBeTrue();
});

test('isArchived returns false when status is active', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['status' => 'active']);
    expect($session->isArchived())->toBeFalse();
});

// ─── Mutual exclusion ────────────────────────────────────────────────────────

test('isActive and isArchived are mutually exclusive for active status', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['status' => 'active']);

    expect($session->isActive())->toBeTrue();
    expect($session->isArchived())->toBeFalse();
});

test('isActive and isArchived are mutually exclusive for archived status', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['status' => 'archived']);

    expect($session->isActive())->toBeFalse();
    expect($session->isArchived())->toBeTrue();
});

// ─── getContextModelClass() ───────────────────────────────────────────────────

test('getContextModelClass returns Application class for application context', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['context_type' => 'application']);
    expect($session->getContextModelClass())->toBe(Application::class);
});

test('getContextModelClass returns Server class for server context', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['context_type' => 'server']);
    expect($session->getContextModelClass())->toBe(Server::class);
});

test('getContextModelClass returns StandalonePostgresql class for database context', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['context_type' => 'database']);
    expect($session->getContextModelClass())->toBe(StandalonePostgresql::class);
});

test('getContextModelClass returns Service class for service context', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['context_type' => 'service']);
    expect($session->getContextModelClass())->toBe(Service::class);
});

test('getContextModelClass returns Project class for project context', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['context_type' => 'project']);
    expect($session->getContextModelClass())->toBe(Project::class);
});

test('getContextModelClass returns Environment class for environment context', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['context_type' => 'environment']);
    expect($session->getContextModelClass())->toBe(Environment::class);
});

test('getContextModelClass returns null for unknown context_type', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['context_type' => 'unknown_type']);
    expect($session->getContextModelClass())->toBeNull();
});

test('getContextModelClass returns null when context_type is null', function () {
    $session = new AiChatSession;
    $session->setRawAttributes(['context_type' => null]);
    expect($session->getContextModelClass())->toBeNull();
});
