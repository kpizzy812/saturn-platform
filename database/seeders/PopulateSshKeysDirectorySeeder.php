<?php

namespace Database\Seeders;

use App\Models\PrivateKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class PopulateSshKeysDirectorySeeder extends Seeder
{
    public function run()
    {
        try {
            // Safety check: verify all keys in DB have valid private_key data
            // before deleting files from disk. The encrypted cast may return empty
            // if DB values were corrupted (e.g., plaintext stored before encrypted cast was added).
            $allKeys = PrivateKey::all();
            $hasEmptyKeys = false;

            foreach ($allKeys as $key) {
                if (empty($key->private_key) || ! str_contains($key->private_key, 'PRIVATE KEY')) {
                    echo "  WARNING: PrivateKey id={$key->id} ({$key->name}) has empty/invalid private_key in DB. Skipping full re-sync.\n";
                    $hasEmptyKeys = true;
                }
            }

            if ($hasEmptyKeys) {
                // Don't delete existing files — just ensure any keys WITH valid DB data are written
                echo "  Partial sync: writing only keys with valid DB data (preserving existing files).\n";
                foreach ($allKeys as $key) {
                    if (! empty($key->private_key) && str_contains($key->private_key, 'PRIVATE KEY')) {
                        $key->storeInFileSystem();
                    }
                }
            } else {
                // All keys valid in DB — safe to do full re-sync
                Storage::disk('ssh-keys')->deleteDirectory('');
                Storage::disk('ssh-keys')->makeDirectory('');
                Storage::disk('ssh-mux')->deleteDirectory('');
                Storage::disk('ssh-mux')->makeDirectory('');

                PrivateKey::chunk(100, function ($keys) {
                    foreach ($keys as $key) {
                        $key->storeInFileSystem();
                    }
                });
            }

            // Fix ownership for container user (www-data = uid 9999)
            Process::run('chown -R 9999:9999 '.storage_path('app/ssh/keys'));
            Process::run('chown -R 9999:9999 '.storage_path('app/ssh/mux'));

        } catch (\Throwable $e) {
            echo "Error populating SSH keys: {$e->getMessage()}\n";
        }
    }
}
