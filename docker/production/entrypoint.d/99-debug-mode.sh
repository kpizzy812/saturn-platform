# Debug mode — install dev dependencies for debugging tools (telescope, debugbar, etc.)
#
# --no-scripts is required because package:discover needs a fully booted Laravel app,
# which is impossible in the entrypoint (no DB connection yet).
#
# After installing dev deps, we MUST clear the stale bootstrap caches that were
# generated during the Docker build with production-only packages. Without this,
# Laravel loads the old packages.php (missing dev providers) while the autoloader
# already includes dev classes — causing "make() on null" crashes.
if [ "$APP_DEBUG" = "true" ]; then
    echo "Debug mode is enabled"
    echo "Installing development dependencies..."
    composer install --dev --no-interaction --no-progress --no-scripts

    # Clear stale provider caches so Laravel re-discovers all packages at runtime
    echo "Clearing stale bootstrap caches..."
    rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
fi
