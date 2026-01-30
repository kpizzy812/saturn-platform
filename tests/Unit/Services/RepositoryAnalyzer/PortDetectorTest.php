<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\PortDetector;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/port-detector-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->detector = new PortDetector;
});

afterEach(function () {
    // Cleanup temp directory
    if (is_dir($this->tempDir)) {
        exec('rm -rf '.escapeshellarg($this->tempDir));
    }
});

test('detects port from nodejs express', function () {
    mkdir($this->tempDir.'/src', 0755, true);
    file_put_contents($this->tempDir.'/src/index.js', <<<'JS'
const express = require('express');
const app = express();

app.get('/', (req, res) => res.send('Hello'));

app.listen(4000, () => console.log('Server running'));
JS);

    $port = $this->detector->detect($this->tempDir, 'express');

    expect($port)->toBe(4000);
});

test('detects port from python fastapi', function () {
    file_put_contents($this->tempDir.'/main.py', <<<'PYTHON'
from fastapi import FastAPI
import uvicorn

app = FastAPI()

@app.get("/")
def read_root():
    return {"Hello": "World"}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8080)
PYTHON);

    $port = $this->detector->detect($this->tempDir, 'fastapi');

    expect($port)->toBe(8080);
});

test('detects port from package json scripts', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'name' => 'test-app',
        'scripts' => [
            'start' => 'next start -p 3001',
            'dev' => 'next dev',
        ],
    ]));

    $port = $this->detector->detect($this->tempDir, 'nextjs');

    expect($port)->toBe(3001);
});

test('detects port from procfile with port flag', function () {
    file_put_contents($this->tempDir.'/Procfile', <<<'PROC'
web: gunicorn app:app --port 9000
worker: celery -A tasks worker
PROC);

    $port = $this->detector->detect($this->tempDir, 'flask');

    expect($port)->toBe(9000);
});

test('detects port from procfile with PORT env var', function () {
    file_put_contents($this->tempDir.'/Procfile', <<<'PROC'
web: uvicorn app:app --host 0.0.0.0 --port $PORT
PROC);

    $port = $this->detector->detect($this->tempDir, 'fastapi');

    // Uses default for Python when $PORT is detected
    expect($port)->toBe(8000);
});

test('returns null when no port found', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'name' => 'test-app',
    ]));

    $port = $this->detector->detect($this->tempDir, 'express');

    expect($port)->toBeNull();
});

test('detects go port from main.go', function () {
    file_put_contents($this->tempDir.'/main.go', <<<'GO'
package main

import (
    "net/http"
)

func main() {
    http.ListenAndServe(":8081", nil)
}
GO);

    $port = $this->detector->detect($this->tempDir, 'go-fiber');

    expect($port)->toBe(8081);
});
