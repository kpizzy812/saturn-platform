<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

/**
 * Defense-in-depth: Validates public_port uniqueness on the same server
 * before saving any database model that uses TCP proxy.
 *
 * Uses Cache::lock() to prevent race conditions where two concurrent
 * requests pass the check simultaneously.
 *
 * Use this trait in all Standalone* database models.
 */
trait ValidatesPublicPort
{
    public static function bootValidatesPublicPort(): void
    {
        static::saving(function ($database) {
            if (! $database->isDirty('public_port') && ! $database->isDirty('is_public')) {
                return;
            }

            $port = $database->public_port;
            $isPublic = $database->is_public;

            // No conflict possible if not public or no port set
            if (! $isPublic || ! $port) {
                return;
            }

            $server = $database->destination?->server;
            if (! $server) {
                return;
            }

            // Atomic lock prevents two concurrent saves from both passing the check
            $lock = Cache::lock("public_port_check:{$server->id}:{$port}", 5);

            if (! $lock->get()) {
                throw new \RuntimeException(
                    "Port {$port} is being assigned by another operation. Please try again."
                );
            }

            try {
                if (isPublicPortAlreadyUsed($server, (int) $port, $database->uuid)) {
                    throw new \RuntimeException(
                        "Port {$port} is already in use by another database on server {$server->name}."
                    );
                }
            } finally {
                // Release AFTER the model is saved (Eloquent saving hook runs before the actual SQL)
                // We keep the lock here — it auto-expires in 5s which covers the save window
            }
        });
    }
}
