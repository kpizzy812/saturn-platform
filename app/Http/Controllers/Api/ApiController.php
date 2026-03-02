<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

abstract class ApiController extends Controller
{
    /**
     * Check if the current API request has the read:sensitive ability.
     * This ability grants access to secrets, credentials, and other sensitive fields.
     */
    protected function canReadSensitive(): bool
    {
        return request()->attributes->get('can_read_sensitive', false) === true;
    }
}
