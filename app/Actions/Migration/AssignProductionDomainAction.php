<?php

namespace App\Actions\Migration;

use App\Models\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Assigns a production domain (FQDN) to a cloned application.
 *
 * Supports two modes:
 * - Subdomain of platform: e.g., myapp.saturn.ac
 * - Custom domain: e.g., app.company.com
 *
 * Also updates Traefik/Caddy proxy labels to match the new domain.
 */
class AssignProductionDomainAction
{
    use AsAction;

    /**
     * Assign production domain to a resource.
     *
     * @param  string  $fqdn  The domain to assign (e.g., "https://myapp.saturn.ac" or "https://app.company.com")
     * @return array{success: bool, fqdn?: string, error?: string}
     */
    public function handle(Model $resource, string $fqdn): array
    {
        if (! ($resource instanceof Application)) {
            return [
                'success' => false,
                'error' => 'Domain assignment is only supported for applications.',
            ];
        }

        // Normalize FQDN: ensure https:// prefix
        $fqdn = $this->normalizeFqdn($fqdn);

        if (! $this->isValidFqdn($fqdn)) {
            return [
                'success' => false,
                'error' => "Invalid domain format: {$fqdn}",
            ];
        }

        try {
            // Update the application FQDN
            $oldFqdn = $resource->fqdn;
            $resource->update(['fqdn' => $fqdn]);

            // Update proxy labels if custom labels exist
            $this->updateProxyLabels($resource, $oldFqdn, $fqdn);

            Log::info('Production domain assigned', [
                'application_id' => $resource->getKey(),
                'fqdn' => $fqdn,
                'old_fqdn' => $oldFqdn,
            ]);

            return [
                'success' => true,
                'fqdn' => $fqdn,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to assign production domain', [
                'application_id' => $resource->getKey(),
                'fqdn' => $fqdn,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Normalize FQDN to include https:// prefix.
     */
    protected function normalizeFqdn(string $fqdn): string
    {
        $fqdn = trim($fqdn);

        // Remove trailing slashes
        $fqdn = rtrim($fqdn, '/');

        // Add https:// if no scheme present
        if (! preg_match('#^https?://#i', $fqdn)) {
            $fqdn = 'https://'.$fqdn;
        }

        return $fqdn;
    }

    /**
     * Validate FQDN format.
     */
    protected function isValidFqdn(string $fqdn): bool
    {
        // Must start with http:// or https://
        if (! preg_match('#^https?://#i', $fqdn)) {
            return false;
        }

        // Extract host
        $host = parse_url($fqdn, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        // Must be a valid domain (no IP addresses for production)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Basic domain validation: at least one dot, valid characters
        return (bool) preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $host);
    }

    /**
     * Update proxy custom labels to reflect new domain.
     * Replaces old FQDN host with new FQDN host in Traefik/Caddy labels.
     */
    protected function updateProxyLabels(Application $resource, ?string $oldFqdn, string $newFqdn): void
    {
        $customLabels = $resource->custom_labels ?? '';
        if (empty($customLabels)) {
            return;
        }

        $oldHost = $oldFqdn ? parse_url($oldFqdn, PHP_URL_HOST) : null;
        $newHost = parse_url($newFqdn, PHP_URL_HOST);

        if (! $newHost) {
            return;
        }

        // If old FQDN exists, replace host references in labels
        if ($oldHost && $oldHost !== $newHost) {
            $updatedLabels = str_replace($oldHost, $newHost, $customLabels);
            $resource->update(['custom_labels' => $updatedLabels]);
        }
    }
}
