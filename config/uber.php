<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Your app's client id and secret from the Uber developer dashboard. App
    | (client credentials) tokens are fetched with them for each set of scopes
    | an endpoint needs, and cached until shortly before they expire; Uber
    | allows 100 token requests an hour and the 101st invalidates the oldest.
    |
    | @see https://developer.uber.com/docs/consumer-identity/introduction
    |
    */

    'client_id' => env('UBER_CLIENT_ID'),

    'client_secret' => env('UBER_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The sandbox is sandbox-api.uber.com for rides, guest rides, health and
    | business, and test-api.uber.com (with sandbox-login.uber.com tokens) for
    | Uber Eats. Uber Direct has no sandbox host: use your Direct test account's
    | credentials and customer id. Set base_url to send everything elsewhere.
    |
    */

    'sandbox' => (bool) env('UBER_SANDBOX', false),

    'base_url' => env('UBER_BASE_URL'),

    // Accept-Language for responses, e.g. "en_GB". Null leaves it to Uber.
    'locale' => env('UBER_LOCALE'),

    /*
    |--------------------------------------------------------------------------
    | OAuth (acting for a user)
    |--------------------------------------------------------------------------
    |
    | For riders, drivers, store owners provisioning Uber Eats and Ads users.
    | "url" overrides the OAuth host (auth.uber.com); Uber Ads and Uber Pay
    | document login.uber.com.
    |
    */

    'oauth' => [
        'url' => env('UBER_OAUTH_URL'),
        'redirect_uri' => env('UBER_REDIRECT_URI'),
        'scopes' => array_values(array_filter(explode(' ', (string) env('UBER_SCOPES', 'profile')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product settings
    |--------------------------------------------------------------------------
    */

    'direct' => [
        'customer_id' => env('UBER_DIRECT_CUSTOMER_ID'),
    ],

    'business' => [
        // Sent as x-uber-organizationuuid by apps acting for another organisation.
        'organization_id' => env('UBER_ORGANIZATION_ID'),
        'vouchers_scope' => env('UBER_VOUCHERS_SCOPE', 'organizations.voucher_programs'),
    ],

    'health' => [
        // "health.sandbox" in the sandbox, if your app was set up that way.
        'scope' => env('UBER_HEALTH_SCOPE', 'health'),
    ],

    'ads' => [
        'account_id' => env('UBER_ADS_ACCOUNT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Uber signs webhooks with an HMAC of the body: Riders, Eats and Vehicle
    | Suppliers with your client secret, Guest Rides, Health, Business and
    | Direct with the signing key shown per webhook in the dashboard. List
    | every signing key you use (comma-separated); the client secret is always
    | tried too.
    |
    | Set "path" to register the package's route (POST, no CSRF), or null to
    | register your own with the "uber.webhook" middleware.
    |
    */

    'webhooks' => [
        'path' => env('UBER_WEBHOOK_PATH', 'uber/webhook'),
        'signing_keys' => array_values(array_filter(explode(',', (string) env('UBER_WEBHOOK_SIGNING_KEY', '')))),
        'deduplicate' => true,
        'deduplicate_hours' => 48,
        'cache_store' => env('UBER_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('UBER_TIMEOUT', 30),

    // Reads are retried on connection errors and 5xx responses: [times, sleep milliseconds].
    // Requests, deliveries, orders, charges and cancellations are never retried automatically.
    'retry' => [2, 500],

    // Where app tokens and JWKS keys are cached. Null uses the default store.
    'cache_store' => env('UBER_CACHE_STORE'),

];
