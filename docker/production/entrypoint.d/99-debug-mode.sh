# Debug mode — install dev dependencies for debugging tools (telescope, debugbar, etc.)
#
# --no-scripts is required because package:discover needs a fully booted Laravel app,
# which is impossible in the entrypoint (no DB connection yet).
#
# After installing, we rebuild bootstrap/cache/packages.php directly via
# PackageManifest::build() — reads vendor/composer/installed.json, writes the manifest,
# NO Laravel bootstrap required. This breaks the deadlock where:
#   - with --no-scripts: stale packages.php → "make() on null"
#   - without --no-scripts: package:discover needs app boot → crash
if [ "$APP_DEBUG" = "true" ]; then
    echo "Debug mode is enabled"
    echo "Installing development dependencies..."
    composer install --dev --no-interaction --no-progress --no-scripts

    # Rebuild package manifest without booting Laravel
    echo "Rebuilding package manifest..."
    php -r "
        require 'vendor/autoload.php';
        \$basePath = getcwd();
        (new Illuminate\Foundation\PackageManifest(
            new Illuminate\Filesystem\Filesystem,
            \$basePath,
            \$basePath . '/bootstrap/cache/packages.php'
        ))->build();
    "

    # Delete stale services cache — Laravel will rebuild it on first boot
    rm -f bootstrap/cache/services.php
fi
