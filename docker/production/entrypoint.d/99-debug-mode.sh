# Debug mode — install dev dependencies for debugging tools (telescope, debugbar, etc.)
#
# Note: symfony/console is pinned to <7.4 in composer.json because
# laravel/framework v12.21.0 is incompatible with Symfony Console 7.4+
# (see https://github.com/laravel/framework/issues/57955).
# Once Laravel is updated to >=12.38.0, the pin can be removed.
#
# --no-scripts is needed because post-install-cmd tries to copy git hooks
# (hooks/ → .git/hooks/) which don't exist in the Docker container.
# composer dump-autoload (without --no-scripts) then triggers only
# post-autoload-dump → package:discover, which works fine.
if [ "$APP_DEBUG" = "true" ]; then
    echo "Debug mode is enabled"
    echo "Installing development dependencies..."
    composer install --no-interaction --no-progress --no-scripts
    composer dump-autoload
fi
