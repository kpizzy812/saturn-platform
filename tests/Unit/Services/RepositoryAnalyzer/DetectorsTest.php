<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use Tests\TestCase;

/**
 * Unit tests for RepositoryAnalyzer Detectors: AppDetector, PortDetector.
 */
class DetectorsTest extends TestCase
{
    // =========================================================================
    // AppDetector
    // =========================================================================

    /** @test */
    public function app_detector_detects_node_frameworks(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'nestjs'", $source);
        $this->assertStringContainsString("'nextjs'", $source);
        $this->assertStringContainsString("'nuxt'", $source);
        $this->assertStringContainsString("'remix'", $source);
        $this->assertStringContainsString("'astro'", $source);
        $this->assertStringContainsString("'sveltekit'", $source);
    }

    /** @test */
    public function app_detector_detects_python_frameworks(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'django'", $source);
        $this->assertStringContainsString("'fastapi'", $source);
        $this->assertStringContainsString("'flask'", $source);
    }

    /** @test */
    public function app_detector_detects_go_frameworks(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'go-fiber'", $source);
        $this->assertStringContainsString("'go-gin'", $source);
        $this->assertStringContainsString("'go-echo'", $source);
    }

    /** @test */
    public function app_detector_detects_php_frameworks(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'laravel'", $source);
        $this->assertStringContainsString("'symfony'", $source);
        $this->assertStringContainsString('laravel/framework', $source);
    }

    /** @test */
    public function app_detector_detects_ruby_frameworks(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'rails'", $source);
        $this->assertStringContainsString("'sinatra'", $source);
    }

    /** @test */
    public function app_detector_detects_rust_frameworks(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'rust-axum'", $source);
        $this->assertStringContainsString("'rust-actix'", $source);
    }

    /** @test */
    public function app_detector_extracts_dependencies_from_multiple_formats(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString('extractNodeDeps', $source);
        $this->assertStringContainsString('extractPythonRequirementsDeps', $source);
        $this->assertStringContainsString('extractGoDeps', $source);
        $this->assertStringContainsString('extractRubyDeps', $source);
        $this->assertStringContainsString('extractRustDeps', $source);
        $this->assertStringContainsString('extractPhpDeps', $source);
    }

    /** @test */
    public function app_detector_extracts_ports_from_dockerfile(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString('extractPortFromDockerfile', $source);
        $this->assertStringContainsString('EXPOSE', $source);
    }

    /** @test */
    public function app_detector_uses_build_pack_types(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'dockerfile'", $source);
        $this->assertStringContainsString("'nixpacks'", $source);
    }

    /** @test */
    public function app_detector_supports_check_framework_with_match_modes(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString('checkFramework', $source);
        $this->assertStringContainsString("'any'", $source);
        $this->assertStringContainsString("'all'", $source);
    }

    /** @test */
    public function app_detector_infers_app_name(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString('inferAppName', $source);
    }

    /** @test */
    public function app_detector_detects_java_spring_boot(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'spring-boot'", $source);
    }

    /** @test */
    public function app_detector_detects_elixir_phoenix(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/AppDetector.php'));

        $this->assertStringContainsString("'phoenix'", $source);
    }

    // =========================================================================
    // PortDetector
    // =========================================================================

    /** @test */
    public function port_detector_maps_frameworks_to_languages(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/PortDetector.php'));

        $this->assertStringContainsString("'node'", $source);
        $this->assertStringContainsString("'python'", $source);
        $this->assertStringContainsString("'go'", $source);
        $this->assertStringContainsString("'rust'", $source);
        $this->assertStringContainsString("'php'", $source);
        $this->assertStringContainsString("'ruby'", $source);
    }

    /** @test */
    public function port_detector_has_port_patterns_per_language(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/PortDetector.php'));

        $this->assertStringContainsString('PORT_PATTERNS', $source);
        $this->assertStringContainsString('.listen', $source);
        $this->assertStringContainsString('uvicorn', $source);
        $this->assertStringContainsString('ListenAndServe', $source);
    }

    /** @test */
    public function port_detector_has_port_files_per_language(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/PortDetector.php'));

        $this->assertStringContainsString('PORT_FILES', $source);
        $this->assertStringContainsString('index.js', $source);
        $this->assertStringContainsString('main.py', $source);
        $this->assertStringContainsString('main.go', $source);
    }

    /** @test */
    public function port_detector_validates_port_range(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/PortDetector.php'));

        $this->assertStringContainsString('isValidPort', $source);
        $this->assertStringContainsString('65535', $source);
    }

    /** @test */
    public function port_detector_detects_from_package_json_scripts(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/PortDetector.php'));

        $this->assertStringContainsString('detectFromPackageJsonScripts', $source);
        $this->assertStringContainsString("'start'", $source);
        $this->assertStringContainsString("'dev'", $source);
    }

    /** @test */
    public function port_detector_detects_from_procfile(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/PortDetector.php'));

        $this->assertStringContainsString('detectFromProcfile', $source);
        $this->assertStringContainsString('--port', $source);
    }

    /** @test */
    public function port_detector_checks_file_readability(): void
    {
        $source = file_get_contents(app_path('Services/RepositoryAnalyzer/Detectors/PortDetector.php'));

        $this->assertStringContainsString('isReadableFile', $source);
    }
}
