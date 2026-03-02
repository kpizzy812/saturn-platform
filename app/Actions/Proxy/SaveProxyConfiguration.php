<?php

namespace App\Actions\Proxy;

use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveProxyConfiguration
{
    use AsAction;

    public function handle(Server $server, string $configuration): void
    {
        $proxy_path = $server->proxyPath();
        $docker_compose_yml_base64 = base64_encode($configuration);

        // Update the saved settings hash
        $server->proxy->set('last_saved_settings', str($docker_compose_yml_base64)->pipe('md5')->toString());
        $server->save();

        // Transfer the configuration file to the server
        $escaped_path = escapeshellarg($proxy_path);
        instant_remote_process([
            "mkdir -p $escaped_path",
            'echo '.escapeshellarg($docker_compose_yml_base64)." | base64 -d | tee $escaped_path/docker-compose.yml > /dev/null",
        ], $server);
    }
}
