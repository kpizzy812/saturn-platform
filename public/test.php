<?php

// Simple test - does PHP work at all?
echo "PHP works!\n";

// Test database connection
try {
    $db_url = getenv('DATABASE_URL');
    if ($db_url) {
        $parsed = parse_url($db_url);
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $parsed['host'],
            $parsed['port'] ?? 5432,
            ltrim($parsed['path'], '/')
        );
        $pdo = new PDO($dsn, $parsed['user'], $parsed['pass']);
        echo "Database connected!\n";
    } else {
        echo "DATABASE_URL not set\n";
    }
} catch (Exception $e) {
    echo 'Database error: '.$e->getMessage()."\n";
}

// Test Laravel bootstrap
echo "\nTrying to boot Laravel...\n";
try {
    require __DIR__.'/../vendor/autoload.php';
    echo "Autoload OK\n";

    $app = require_once __DIR__.'/../bootstrap/app.php';
    echo "App created\n";

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo "Kernel created\n";

    echo "\nLaravel boots successfully!\n";
} catch (Throwable $e) {
    echo "\n=== LARAVEL ERROR ===\n";
    echo 'Message: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
    echo "\nTrace:\n".$e->getTraceAsString()."\n";
}
