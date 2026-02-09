<?php

namespace App\Traits;

/**
 * Defense-in-depth: Validates public_port uniqueness on the same server
 * before saving any database model that uses TCP proxy.
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

            if (isPublicPortAlreadyUsed($server, (int) $port, $database->id)) {
                throw new \RuntimeException(
                    "Port {$port} is already in use by another database on server {$server->name}."
                );
            }
        });
    }
}
