<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared Tokens for Internal/Admin/Bootstrap APIs
    |--------------------------------------------------------------------------
    |
    | These tokens are used by the RequireSharedToken middleware to authenticate
    | internal service-to-service calls (GeoDNS → Portal, Resolver → Console)
    | and bootstrap operations. Keep them secret and rotate periodically.
    |
    | Values should be set in .env, never hard-coded here.
    |
    */

    'bootstrap' => env('BOOTSTRAP_SHARED_TOKEN', 'bootstrap-local-token'),
    'internal' => env('INTERNAL_SHARED_TOKEN', 'internal-local-token'),
    'admin' => env('ADMIN_SHARED_TOKEN', 'admin-local-token'),
];
