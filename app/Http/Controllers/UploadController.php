<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller as BaseController;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class UploadController extends BaseController
{
    /**
     * Allowed file extensions for database backup uploads.
     * Organized by database type for clarity.
     */
    private const ALLOWED_EXTENSIONS = [
        // SQL-based databases (PostgreSQL, MySQL, MariaDB)
        'sql',
        'dump',
        'backup',
        // Compressed formats
        'gz',
        'tar',
        'zip',
        'bz2',
        // MongoDB
        'archive',
        'bson',
        'json',
        // Redis
        'rdb',
        'aof',
    ];

    /**
     * Allowed MIME types for database backup uploads.
     */
    private const ALLOWED_MIME_TYPES = [
        'application/sql',
        'application/x-sql',
        'text/plain',
        'text/x-sql',
        'application/gzip',
        'application/x-gzip',
        'application/x-tar',
        'application/x-bzip2',
        'application/zip',
        'application/x-compressed',
        'application/octet-stream', // Generic binary (required for some backup formats)
        'application/json',
    ];

    /**
     * Maximum file size in bytes (2GB).
     */
    private const MAX_FILE_SIZE = 2147483648;

    /**
     * Magic bytes for validating binary files when MIME is octet-stream.
     * Format: [extension => [magic_bytes_hex, offset]]
     */
    private const MAGIC_BYTES = [
        'gz' => ['1f8b', 0],           // Gzip
        'bz2' => ['425a68', 0],        // Bzip2
        'zip' => ['504b0304', 0],      // ZIP (PK..)
        'tar' => ['7573746172', 257],  // tar (ustar at offset 257)
        'rdb' => ['52454449', 0],      // Redis RDB (REDI)
    ];

    public function upload(Request $request)
    {
        $resource = getResourceByUuid(request()->route('databaseUuid'), data_get(auth()->user()->currentTeam(), 'id'));
        if (is_null($resource)) {
            return response()->json(['error' => 'You do not have permission for this database'], 403);
        }
        $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));

        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException;
        }

        $save = $receiver->receive();

        if ($save->isFinished()) {
            $file = $save->getFile();

            // Validate file before saving
            $validationError = $this->validateUploadedFile($file);
            if ($validationError !== null) {
                // Clean up the temporary file
                if (file_exists($file->getPathname())) {
                    unlink($file->getPathname());
                }

                return response()->json(['error' => $validationError], 400);
            }

            return $this->saveFile($file, $resource);
        }

        $handler = $save->handler();

        return response()->json([
            'done' => $handler->getPercentageDone(),
            'status' => true,
        ]);
    }

    /**
     * Validate the uploaded file for security.
     *
     * @return string|null Error message if validation fails, null if valid
     */
    protected function validateUploadedFile(UploadedFile $file): ?string
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return 'File size exceeds maximum allowed size of 2GB';
        }

        // Get and validate extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (empty($extension) || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return 'Invalid file extension. Allowed extensions: '.implode(', ', self::ALLOWED_EXTENSIONS);
        }

        // Validate MIME type
        $mimeType = $file->getMimeType();
        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return 'Invalid file type. This does not appear to be a valid database backup file.';
        }

        // SECURITY: Additional validation for octet-stream files using magic bytes
        // This prevents uploading arbitrary binaries disguised as backup files
        if ($mimeType === 'application/octet-stream') {
            if (! $this->validateMagicBytes($file, $extension)) {
                return 'Invalid file content. The file does not match expected format for extension: '.$extension;
            }
        }

        // Check for double extensions (e.g., file.php.sql)
        $originalName = $file->getClientOriginalName();
        if (preg_match('/\.(php|phtml|phar|sh|bash|exe|bat|cmd|ps1|py|rb|pl|cgi|asp|aspx|jsp|htaccess)\./i', $originalName)) {
            return 'Invalid filename. Suspicious file extension detected.';
        }

        // Validate filename doesn't contain path traversal attempts
        if (preg_match('/[\/\\\\]|\.\./', $originalName)) {
            return 'Invalid filename. Path traversal characters detected.';
        }

        return null;
    }

    /**
     * Validate file content using magic bytes for binary files.
     * Returns true if valid or if no magic bytes check is defined for the extension.
     */
    protected function validateMagicBytes(UploadedFile $file, string $extension): bool
    {
        // If no magic bytes defined for this extension, allow it
        // (e.g., .sql, .dump, .json are text files)
        if (! isset(self::MAGIC_BYTES[$extension])) {
            return true;
        }

        [$expectedHex, $offset] = self::MAGIC_BYTES[$extension];
        $expectedLength = strlen($expectedHex) / 2;

        // Read bytes from file
        $handle = fopen($file->getPathname(), 'rb');
        if (! $handle) {
            return false;
        }

        // Seek to offset if needed
        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $bytes = fread($handle, $expectedLength);
        fclose($handle);

        if ($bytes === false || strlen($bytes) < $expectedLength) {
            return false;
        }

        // Compare magic bytes
        $actualHex = bin2hex($bytes);

        return strtolower($actualHex) === strtolower($expectedHex);
    }

    // protected function saveFileToS3($file)
    // {
    //     $fileName = $this->createFilename($file);

    //     $disk = Storage::disk('s3');
    //     // It's better to use streaming Streaming (laravel 5.4+)
    //     $disk->putFileAs('photos', $file, $fileName);

    //     // for older laravel
    //     // $disk->put($fileName, file_get_contents($file), 'public');
    //     $mime = str_replace('/', '-', $file->getMimeType());

    //     // We need to delete the file when uploaded to s3
    //     unlink($file->getPathname());

    //     return response()->json([
    //         'path' => $disk->url($fileName),
    //         'name' => $fileName,
    //         'mime_type' => $mime
    //     ]);
    // }
    protected function saveFile(UploadedFile $file, $resource)
    {
        $mime = str_replace('/', '-', $file->getMimeType());
        $restoreDir = "upload/{$resource->uuid}/restore";
        $finalPath = storage_path('app/'.$restoreDir);

        // Clean up any previous uploads in the restore directory
        if (is_dir($finalPath)) {
            array_map('unlink', glob("{$finalPath}/*"));
        }

        // Save with original filename so the restore endpoint can find it
        $file->move($finalPath, $file->getClientOriginalName());

        return response()->json([
            'mime_type' => $mime,
        ]);
    }

    protected function createFilename(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = str_replace('.'.$extension, '', $file->getClientOriginalName()); // Filename without extension

        $filename .= '_'.md5((string) time()).'.'.$extension;

        return $filename;
    }
}
