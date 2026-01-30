<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\DockerfileAnalyzer;
use App\Services\RepositoryAnalyzer\DTOs\DockerfileInfo;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DockerfileAnalyzerTest extends TestCase
{
    private DockerfileAnalyzer $analyzer;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new DockerfileAnalyzer;
        $this->tempDir = sys_get_temp_dir().'/dockerfile-analyzer-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    private function createDockerfile(string $content, string $filename = 'Dockerfile'): void
    {
        file_put_contents($this->tempDir.'/'.$filename, $content);
    }

    public function test_returns_null_when_no_dockerfile(): void
    {
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertNull($result);
    }

    public function test_detects_dockerfile_variants(): void
    {
        // Test lowercase dockerfile
        $this->createDockerfile('FROM node:18', 'dockerfile');
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertInstanceOf(DockerfileInfo::class, $result);
        $this->assertEquals('node:18', $result->baseImage);
    }

    public function test_extracts_base_image(): void
    {
        $this->createDockerfile('FROM python:3.11-slim');
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('python:3.11-slim', $result->baseImage);
    }

    public function test_extracts_base_image_with_platform(): void
    {
        $this->createDockerfile('FROM --platform=linux/amd64 node:18-alpine');
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('node:18-alpine', $result->baseImage);
    }

    public function test_extracts_env_variables_key_value_format(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
ENV NODE_ENV=production
ENV PORT=3000
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('production', $result->envVariables['NODE_ENV']);
        $this->assertEquals('3000', $result->envVariables['PORT']);
    }

    public function test_extracts_env_variables_space_format(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
ENV NODE_ENV production
ENV PORT 3000
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('production', $result->envVariables['NODE_ENV']);
        $this->assertEquals('3000', $result->envVariables['PORT']);
    }

    public function test_extracts_multiple_env_on_one_line(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
ENV NODE_ENV=production PORT=3000 DEBUG=false
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('production', $result->envVariables['NODE_ENV']);
        $this->assertEquals('3000', $result->envVariables['PORT']);
        $this->assertEquals('false', $result->envVariables['DEBUG']);
    }

    public function test_extracts_env_with_quotes(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
ENV MESSAGE="Hello World"
ENV GREETING='Hi there'
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('Hello World', $result->envVariables['MESSAGE']);
        $this->assertEquals('Hi there', $result->envVariables['GREETING']);
    }

    public function test_extracts_exposed_ports(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
EXPOSE 3000
EXPOSE 8080
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertContains(3000, $result->exposedPorts);
        $this->assertContains(8080, $result->exposedPorts);
    }

    public function test_extracts_multiple_ports_on_one_line(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM nginx
EXPOSE 80 443
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertContains(80, $result->exposedPorts);
        $this->assertContains(443, $result->exposedPorts);
    }

    public function test_extracts_ports_with_protocol(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
EXPOSE 3000/tcp
EXPOSE 5000/udp
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertContains(3000, $result->exposedPorts);
        $this->assertContains(5000, $result->exposedPorts);
    }

    public function test_extracts_build_args(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
ARG NODE_VERSION=18
ARG BUILD_DATE
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('18', $result->buildArgs['NODE_VERSION']);
        $this->assertNull($result->buildArgs['BUILD_DATE']);
    }

    public function test_extracts_workdir(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
WORKDIR /app
WORKDIR /app/src
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        // Should get the last WORKDIR
        $this->assertEquals('/app/src', $result->workdir);
    }

    public function test_extracts_healthcheck(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
HEALTHCHECK --interval=30s --timeout=5s CMD curl -f http://localhost:3000/health || exit 1
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertStringContainsString('curl', $result->healthcheck);
        $this->assertStringContainsString('localhost:3000/health', $result->healthcheck);
    }

    public function test_extracts_entrypoint_json_format(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
ENTRYPOINT ["node", "server.js"]
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('node server.js', $result->entrypoint);
    }

    public function test_extracts_entrypoint_shell_format(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
ENTRYPOINT node server.js
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('node server.js', $result->entrypoint);
    }

    public function test_extracts_cmd_json_format(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
CMD ["npm", "start"]
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('npm start', $result->cmd);
    }

    public function test_extracts_cmd_shell_format(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
CMD npm run start:prod
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('npm run start:prod', $result->cmd);
    }

    public function test_extracts_labels(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
LABEL version="1.0"
LABEL maintainer="admin@example.com"
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('1.0', $result->labels['version']);
        $this->assertEquals('admin@example.com', $result->labels['maintainer']);
    }

    public function test_get_primary_port(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
EXPOSE 3000
EXPOSE 8080
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals(3000, $result->getPrimaryPort());
    }

    public function test_get_primary_port_returns_null_when_no_ports(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertNull($result->getPrimaryPort());
    }

    public function test_get_node_version(): void
    {
        $this->createDockerfile('FROM node:18.17.1-alpine');
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('18.17.1', $result->getNodeVersion());
    }

    public function test_get_python_version(): void
    {
        $this->createDockerfile('FROM python:3.11-slim');
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('3.11', $result->getPythonVersion());
    }

    public function test_get_go_version(): void
    {
        $this->createDockerfile('FROM golang:1.21-alpine');
        $result = $this->analyzer->analyze($this->tempDir);

        $this->assertEquals('1.21', $result->getGoVersion());
    }

    public function test_to_array(): void
    {
        $dockerfile = <<<'DOCKERFILE'
FROM node:18-alpine
ENV NODE_ENV=production
EXPOSE 3000
ARG VERSION=1.0
WORKDIR /app
CMD ["npm", "start"]
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        $array = $result->toArray();

        $this->assertEquals('node:18-alpine', $array['base_image']);
        $this->assertEquals(['NODE_ENV' => 'production'], $array['env_variables']);
        $this->assertContains(3000, $array['exposed_ports']);
        $this->assertEquals(['VERSION' => '1.0'], $array['build_args']);
        $this->assertEquals('/app', $array['workdir']);
        $this->assertEquals('npm start', $array['cmd']);
        $this->assertEquals('18', $array['node_version']);
    }

    public function test_complex_dockerfile(): void
    {
        $dockerfile = <<<'DOCKERFILE'
# Build stage
FROM node:18-alpine AS builder
WORKDIR /build
ARG NODE_ENV=production
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Production stage
FROM node:18-alpine
WORKDIR /app
ENV NODE_ENV=production
ENV PORT=3000
COPY --from=builder /build/dist ./dist
COPY --from=builder /build/node_modules ./node_modules
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s CMD wget -q --spider http://localhost:3000/health || exit 1
CMD ["node", "dist/server.js"]
LABEL version="1.0" maintainer="team@example.com"
DOCKERFILE;

        $this->createDockerfile($dockerfile);
        $result = $this->analyzer->analyze($this->tempDir);

        // Should get first FROM
        $this->assertEquals('node:18-alpine', $result->baseImage);
        $this->assertEquals('production', $result->envVariables['NODE_ENV']);
        $this->assertEquals('3000', $result->envVariables['PORT']);
        $this->assertContains(3000, $result->exposedPorts);
        $this->assertEquals('/app', $result->workdir);
        $this->assertStringContainsString('wget', $result->healthcheck);
        $this->assertEquals('node dist/server.js', $result->cmd);
        $this->assertEquals('18', $result->getNodeVersion());
    }
}
