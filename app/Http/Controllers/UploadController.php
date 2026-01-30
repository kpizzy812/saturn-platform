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
        $filePath = "upload/{$resource->uuid}";
        $finalPath = storage_path('app/'.$filePath);
        $file->move($finalPath, 'restore');

        return response()->json([
            'mime_type' => $mime,
        ]);
    }

    protected function createFilename(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = str_replace('.'.$extension, '', $file->getClientOriginalName()); // Filename without extension

        $filename .= '_'.md5(time()).'.'.$extension;

        return $filename;
    }
}
