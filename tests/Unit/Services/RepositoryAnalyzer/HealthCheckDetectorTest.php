<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\HealthCheckDetector;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/health-check-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->detector = new HealthCheckDetector;
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        exec('rm -rf '.escapeshellarg($this->tempDir));
    }
});

test('detects health check from dockerfile', function () {
    file_put_contents($this->tempDir.'/Dockerfile', <<<'DOCKER'
FROM node:18-alpine
WORKDIR /app
COPY . .
RUN npm install
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost:3000/health || exit 1
EXPOSE 3000
CMD ["npm", "start"]
DOCKER);

    $healthCheck = $this->detector->detect($this->tempDir, 'express');

    expect($healthCheck)->not->toBeNull();
    expect($healthCheck->path)->toBe('/health');
    expect($healthCheck->intervalSeconds)->toBe(30);
    expect($healthCheck->timeoutSeconds)->toBe(3);
    expect($healthCheck->detectedVia)->toContain('Dockerfile');
});

test('detects health check from express routes', function () {
    mkdir($this->tempDir.'/src', 0755, true);
    file_put_contents($this->tempDir.'/src/app.js', <<<'JS'
const express = require('express');
const app = express();

app.get('/health', (req, res) => {
    res.json({ status: 'ok' });
});

app.get('/api/users', (req, res) => {
    res.json([]);
});

module.exports = app;
JS);

    $healthCheck = $this->detector->detect($this->tempDir, 'express');

    expect($healthCheck)->not->toBeNull();
    expect($healthCheck->path)->toBe('/health');
    expect($healthCheck->detectedVia)->toContain('source code');
});

test('detects healthz endpoint in kubernetes style', function () {
    mkdir($this->tempDir.'/src', 0755, true);
    file_put_contents($this->tempDir.'/src/server.ts', <<<'TS'
import express from 'express';

const app = express();

app.get('/healthz', (req, res) => res.send('OK'));
app.get('/readyz', (req, res) => res.send('OK'));

export default app;
TS);

    $healthCheck = $this->detector->detect($this->tempDir, 'express');

    expect($healthCheck)->not->toBeNull();
    expect($healthCheck->path)->toBe('/healthz');
});

test('detects health check from docker compose', function () {
    file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  app:
    build: .
    ports:
      - "3000:3000"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:3000/api/health"]
      interval: 10s
      timeout: 5s
      retries: 5
YAML);

    $healthCheck = $this->detector->detect($this->tempDir, 'express');

    expect($healthCheck)->not->toBeNull();
    expect($healthCheck->path)->toBe('/api/health');
    expect($healthCheck->detectedVia)->toContain('docker-compose');
});

test('returns null when no health check found', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'name' => 'test-app',
    ]));

    $healthCheck = $this->detector->detect($this->tempDir, 'express');

    expect($healthCheck)->toBeNull();
});

test('detects python fastapi health endpoint', function () {
    file_put_contents($this->tempDir.'/main.py', <<<'PYTHON'
from fastapi import FastAPI

app = FastAPI()

@app.get("/health")
def health():
    return {"status": "healthy"}

@app.get("/api/users")
def get_users():
    return []
PYTHON);

    $healthCheck = $this->detector->detect($this->tempDir, 'fastapi');

    expect($healthCheck)->not->toBeNull();
    expect($healthCheck->path)->toBe('/health');
});
