<?php

/**
 * Configuration paths helper functions.
 *
 * Contains functions for generating standard directory paths
 * used throughout the Saturn platform.
 */

/**
 * Get the base configuration directory for Saturn.
 */
function base_configuration_dir(): string
{
    return '/data/saturn';
}

/**
 * Get the applications configuration directory.
 */
function application_configuration_dir(): string
{
    return base_configuration_dir().'/applications';
}

/**
 * Get the services configuration directory.
 */
function service_configuration_dir(): string
{
    return base_configuration_dir().'/services';
}

/**
 * Get the databases configuration directory.
 */
function database_configuration_dir(): string
{
    return base_configuration_dir().'/databases';
}

/**
 * Get the database proxy directory for a specific UUID.
 */
function database_proxy_dir($uuid): string
{
    return base_configuration_dir()."/databases/$uuid/proxy";
}

/**
 * Get the backups directory.
 */
function backup_dir(): string
{
    return base_configuration_dir().'/backups';
}

/**
 * Get the metrics directory.
 */
function metrics_dir(): string
{
    return base_configuration_dir().'/metrics';
}
