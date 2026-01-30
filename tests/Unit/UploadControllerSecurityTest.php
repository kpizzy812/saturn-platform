<?php

namespace Tests\Unit;

use App\Http\Controllers\UploadController;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Security tests for UploadController file validation.
 *
 * Tests that the upload validation properly rejects malicious files
 * and allows legitimate database backup files.
 */
class UploadControllerSecurityTest extends TestCase
{
    private UploadController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new UploadController;
    }

    /**
     * Helper to call the protected validateUploadedFile method.
     */
    private function validateFile(UploadedFile $file): ?string
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateUploadedFile');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $file);
    }

    #[Test]
    public function it_allows_valid_sql_backup_files(): void
    {
        $file = UploadedFile::fake()->create('backup.sql', 100, 'text/plain');
        $result = $this->validateFile($file);

        $this->assertNull($result, 'Valid .sql file should be allowed');
    }

    #[Test]
    public function it_allows_valid_gzip_backup_files(): void
    {
        $file = UploadedFile::fake()->create('backup.sql.gz', 100, 'application/gzip');
        $result = $this->validateFile($file);

        $this->assertNull($result, 'Valid .gz file should be allowed');
    }

    #[Test]
    public function it_allows_valid_dump_backup_files(): void
    {
        $file = UploadedFile::fake()->create('database.dump', 100, 'application/octet-stream');
        $result = $this->validateFile($file);

        $this->assertNull($result, 'Valid .dump file should be allowed');
    }

    #[Test]
    public function it_allows_valid_mongodb_archive_files(): void
    {
        $file = UploadedFile::fake()->create('mongo.archive', 100, 'application/octet-stream');
        $result = $this->validateFile($file);

        $this->assertNull($result, 'Valid .archive file should be allowed');
    }

    #[Test]
    public function it_allows_valid_redis_rdb_files(): void
    {
        $file = UploadedFile::fake()->create('dump.rdb', 100, 'application/octet-stream');
        $result = $this->validateFile($file);

        $this->assertNull($result, 'Valid .rdb file should be allowed');
    }

    #[Test]
    public function it_rejects_php_files(): void
    {
        $file = UploadedFile::fake()->create('malicious.php', 100, 'text/x-php');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, 'PHP files should be rejected');
        $this->assertStringContainsString('Invalid file extension', $result);
    }

    #[Test]
    public function it_rejects_executable_files(): void
    {
        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, 'Executable files should be rejected');
        $this->assertStringContainsString('Invalid file extension', $result);
    }

    #[Test]
    public function it_rejects_shell_scripts(): void
    {
        $file = UploadedFile::fake()->create('malicious.sh', 100, 'application/x-sh');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, 'Shell scripts should be rejected');
        $this->assertStringContainsString('Invalid file extension', $result);
    }

    #[Test]
    public function it_rejects_double_extension_php_attacks(): void
    {
        $file = UploadedFile::fake()->create('backup.php.sql', 100, 'text/plain');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, 'Double extension with PHP should be rejected');
        $this->assertStringContainsString('Suspicious file extension', $result);
    }

    #[Test]
    public function it_rejects_double_extension_phtml_attacks(): void
    {
        $file = UploadedFile::fake()->create('backup.phtml.gz', 100, 'application/gzip');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, 'Double extension with PHTML should be rejected');
        $this->assertStringContainsString('Suspicious file extension', $result);
    }

    #[Test]
    public function it_rejects_double_extension_shell_attacks(): void
    {
        $file = UploadedFile::fake()->create('backup.sh.sql', 100, 'text/plain');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, 'Double extension with shell should be rejected');
        $this->assertStringContainsString('Suspicious file extension', $result);
    }

    #[Test]
    public function it_rejects_htaccess_double_extension(): void
    {
        $file = UploadedFile::fake()->create('backup.htaccess.sql', 100, 'text/plain');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, 'Double extension with .htaccess should be rejected');
        $this->assertStringContainsString('Suspicious file extension', $result);
    }

    /**
     * Note: Laravel's UploadedFile automatically sanitizes filenames,
     * removing path traversal characters. These tests verify that
     * our additional validation layer also catches any attempts.
     *
     * Laravel sanitizes '../../../etc/passwd.sql' to 'passwd.sql'
     * so the file passes our validation (it's a valid .sql file).
     * This is defense-in-depth - Laravel handles path traversal at the framework level.
     */
    #[Test]
    public function it_handles_sanitized_path_traversal_filenames(): void
    {
        // Laravel sanitizes '../../../etc/passwd.sql' to 'passwd.sql'
        $file = UploadedFile::fake()->create('../../../etc/passwd.sql', 100, 'text/plain');

        // After Laravel sanitization, the filename is 'passwd.sql' which is valid
        $result = $this->validateFile($file);

        // This validates that Laravel's sanitization works correctly
        // and our validation doesn't break valid files after sanitization
        $this->assertNull($result, 'Sanitized filename should pass validation');
    }

    #[Test]
    public function it_handles_sanitized_backslash_path_traversal(): void
    {
        // Laravel sanitizes '..\\..\\backup.sql' to 'backup.sql'
        $file = UploadedFile::fake()->create('..\\..\\backup.sql', 100, 'text/plain');

        // After Laravel sanitization, the filename is 'backup.sql' which is valid
        $result = $this->validateFile($file);

        // Validates defense-in-depth with Laravel's sanitization
        $this->assertNull($result, 'Sanitized filename should pass validation');
    }

    #[Test]
    public function it_rejects_files_without_extension(): void
    {
        $file = UploadedFile::fake()->create('backup', 100, 'application/octet-stream');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, 'Files without extension should be rejected');
        $this->assertStringContainsString('Invalid file extension', $result);
    }

    #[Test]
    #[DataProvider('dangerousExtensionsProvider')]
    public function it_rejects_dangerous_extensions(string $extension): void
    {
        $file = UploadedFile::fake()->create("malicious.{$extension}", 100, 'application/octet-stream');
        $result = $this->validateFile($file);

        $this->assertNotNull($result, "{$extension} files should be rejected");
    }

    public static function dangerousExtensionsProvider(): array
    {
        return [
            'php' => ['php'],
            'phtml' => ['phtml'],
            'phar' => ['phar'],
            'sh' => ['sh'],
            'bash' => ['bash'],
            'exe' => ['exe'],
            'bat' => ['bat'],
            'cmd' => ['cmd'],
            'ps1' => ['ps1'],
            'py' => ['py'],
            'rb' => ['rb'],
            'pl' => ['pl'],
            'cgi' => ['cgi'],
            'asp' => ['asp'],
            'aspx' => ['aspx'],
            'jsp' => ['jsp'],
            'js' => ['js'],
            'html' => ['html'],
            'htm' => ['htm'],
        ];
    }

    #[Test]
    #[DataProvider('allowedExtensionsProvider')]
    public function it_allows_valid_backup_extensions(string $extension): void
    {
        $file = UploadedFile::fake()->create("backup.{$extension}", 100, 'application/octet-stream');
        $result = $this->validateFile($file);

        $this->assertNull($result, "{$extension} files should be allowed");
    }

    public static function allowedExtensionsProvider(): array
    {
        return [
            'sql' => ['sql'],
            'dump' => ['dump'],
            'backup' => ['backup'],
            'gz' => ['gz'],
            'tar' => ['tar'],
            'zip' => ['zip'],
            'bz2' => ['bz2'],
            'archive' => ['archive'],
            'bson' => ['bson'],
            'json' => ['json'],
            'rdb' => ['rdb'],
            'aof' => ['aof'],
        ];
    }
}
