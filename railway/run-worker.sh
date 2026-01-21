#!/bin/bash
# Worker service for background jobs
php artisan queue:work --tries=3 --timeout=90
