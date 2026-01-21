// API helper functions
import { router } from '@inertiajs/react';

/**
 * Make a DELETE request using Inertia
 */
export function deleteResource(url: string, options = {}) {
    return router.delete(url, options);
}

/**
 * Make a POST request using Inertia
 */
export function createResource(url: string, data: any, options = {}) {
    return router.post(url, data, options);
}

/**
 * Make a PUT request using Inertia
 */
export function updateResource(url: string, data: any, options = {}) {
    return router.put(url, data, options);
}

/**
 * Make a PATCH request using Inertia
 */
export function patchResource(url: string, data: any, options = {}) {
    return router.patch(url, data, options);
}
