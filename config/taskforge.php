<?php

declare(strict_types=1);
use App\Models\Tenant;
use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of the Tenant model.
    |
    */
    'tenant_model' => Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of the User model.
    |
    */
    'user_model' => User::class,

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'retry_attempts' => env('WEBHOOK_RETRY_ATTEMPTS', 3),
        'retry_backoff' => explode(',', env('WEBHOOK_RETRY_BACKOFF', '60,300,900')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'per_minute' => env('RATE_LIMIT_PER_MINUTE', 60),
        'login_per_minute' => env('RATE_LIMIT_LOGIN_PER_MINUTE', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (in seconds)
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => [
        'default' => 600, // 10 minutes
        'short' => 300,   // 5 minutes
        'long' => 3600,   // 1 hour
    ],
];
