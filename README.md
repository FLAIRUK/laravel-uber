<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/logo-dark.svg">
    <img src="art/logo-light.svg" alt="Laravel Uber" width="420">
  </picture>
</p>

<h2 align="center">
  <a href="https://www.php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white" alt="PHP 8.2+"></a>&nbsp;
  <a href="https://laravel.com/docs/" target="_blank"><img src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel 12 or 13"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-uber/actions/workflows/tests.yml" target="_blank"><img src="https://img.shields.io/badge/Lint-%E2%9C%93-2EA043?style=flat&logo=githubactions&logoColor=white" alt="Lint"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-uber/actions/workflows/tests.yml" target="_blank"><img src="https://img.shields.io/badge/Tests-%E2%9C%93-2EA043?style=flat&logo=githubactions&logoColor=white" alt="Tests"></a>&nbsp;
  <a href="https://packagist.org/packages/flairuk/laravel-uber" target="_blank"><img src="https://img.shields.io/packagist/dt/flairuk/laravel-uber?style=flat&logo=packagist&logoColor=white&label=Downloads&color=F28D1A" alt="Downloads on Packagist"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-uber/blob/main/LICENSE" target="_blank"><img src="https://img.shields.io/github/license/FLAIRUK/laravel-uber?style=flat&label=License&color=3DA639" alt="MIT licence"></a>&nbsp;
  <a href="https://developer.uber.com/docs" target="_blank"><img src="https://img.shields.io/badge/Uber%20APIs-12-111827?style=flat" alt="12 Uber APIs"></a>&nbsp;
  <br>&nbsp;
</h2>

**Laravel Uber** — A Laravel 12 and 13 client for the [Uber developer platform](https://developer.uber.com/docs): rides, guest and health rides, Uber Direct deliveries, the Uber Eats Marketplace, Uber for Business, Login with Uber, drivers, vehicle suppliers, Ads, AI Solutions and Uber Pay, behind one facade.

- **Every documented API.** 12 APIs and about 250 endpoints, mapped from Uber's published OpenAPI specs and reference pages. Each one has a test that checks its method, path and scope.
- **Tokens handled.** App tokens are fetched per scope with the client-credentials grant, cached until shortly before they expire, and replaced if Uber rejects them. User tokens from the OAuth flow (with PKCE or pushed authorization requests) are passed with `withToken()`.
- **The right host.** Production is `api.uber.com`. Each API's sandbox is chosen for you: `sandbox-api` for rides, `test-api` with `sandbox-login` tokens for Eats.
- **Verified webhooks.** One route verifies Uber's HMAC signatures, whichever key the API signs with. It de-duplicates retries and dispatches Laravel events.
- **Safe retries.** Reads are retried on connection errors and 5xx responses. Ride requests, deliveries, orders, charges and cancellations never are.
- **Real errors.** Uber's error shapes are turned into typed exceptions that carry the error code, metadata and the surge-confirmation link.

> This is an unofficial package. It is not affiliated with or endorsed by Uber. Most Uber APIs need approval from Uber for your app and its scopes.

<p align="center">
  📦&nbsp;<a href="#-installation">Installation</a> ·
  🔑&nbsp;<a href="#-authentication">Authentication</a> ·
  🚗&nbsp;<a href="#-rides">Rides</a> ·
  📦&nbsp;<a href="#-uber-direct">Direct</a> ·
  🍔&nbsp;<a href="#-uber-eats">Eats</a> ·
  💼&nbsp;<a href="#-uber-for-business">Business</a> ·
  🧩&nbsp;<a href="#-more-apis">More</a> ·
  🔔&nbsp;<a href="#-webhooks">Webhooks</a> ·
  ⚠️&nbsp;<a href="#%EF%B8%8F-errors">Errors</a> ·
  ⚙️&nbsp;<a href="#%EF%B8%8F-configuration">Configuration</a>
</p>

<br><br>

## 📦 Installation

```bash
composer require flairuk/laravel-uber
php artisan uber:install
```

`uber:install` publishes `config/uber.php` and adds these keys to `.env` and `.env.example`:

```dotenv
UBER_CLIENT_ID=              # from the Uber developer dashboard
UBER_CLIENT_SECRET=
UBER_SANDBOX=true            # use the sandbox until you go live
UBER_REDIRECT_URI=           # for Login with Uber / acting for a user
UBER_DIRECT_CUSTOMER_ID=     # Uber Direct only
UBER_ORGANIZATION_ID=        # Uber for Business third-party apps only
UBER_WEBHOOK_SIGNING_KEY=    # webhook signing keys, comma-separated
```

Then check that Uber issues a token for a scope your app is approved for:

```bash
php artisan uber:status --scope=eats.deliveries
```

<br><br>

## 🔑 Authentication

```php
use FLAIRUK\Uber\Facades\Uber;
```

You can also type-hint `FLAIRUK\Uber\Uber` to have it injected.

**App endpoints** need no setup. These include Direct, Eats, Guest Rides, Health, Business, Vehicle Suppliers and AI Solutions. The package asks `auth.uber.com` for a client-credentials token with the scope each endpoint needs, and caches it. Uber allows only 100 token requests an hour, and each new token past that invalidates the oldest, so the cache matters.

**User endpoints** act for a person: a rider, a driver, a store owner activating your Eats integration, or an Ads user. Send them through Uber's OAuth flow, then pass their token:

```php
// 1. Redirect the user to Uber
$pkce = Uber::oauth()::pkce();                         // optional; S256
session(['uber_state' => $state = Str::random(40), 'uber_verifier' => $pkce['verifier']]);

return redirect(Uber::oauth()->authorizeUrl(['profile', 'request', 'offline_access'], $state, codeChallenge: $pkce['challenge']));

// 2. On the callback
abort_unless(hash_equals(session('uber_state'), $request->state), 403);

$token = Uber::oauth()->exchangeCode($request->code, codeVerifier: session('uber_verifier'));
$user->update(['uber_token' => $token->toArray()]);    // store it, encrypted

// 3. Use it
$uber = Uber::withToken($user->uber_token);            // a string, an AccessToken or the stored array

if ($uber->token()->isExpired()) {
    $user->update(['uber_token' => ($fresh = Uber::oauth()->refresh($uber->token()))->toArray()]);
    $uber = Uber::withToken($fresh);
}
```

More OAuth:

```php
Uber::oauth()->pushAuthorizationRequest($scopes, $state, loginHint: ['email' => $user->email]);   // PAR
Uber::oauth()->authorizeUrlFor($requestUri);
Uber::oauth()->revoke($token);
Uber::oauth()->certs();                     // JWKS for verifying ID tokens (cached)
Uber::identity()->me();                     // OpenID profile (/v3/me), with a user token
```

<br><br>

## 🚗 Rides

### Riders API (acting for a rider)

```php
$rides = Uber::withToken($token)->rides();

$rides->products(51.5074, -0.1278);
$rides->priceEstimates(51.5074, -0.1278, 51.4700, -0.4543);
$rides->timeEstimates(51.5074, -0.1278);

$fare = $rides->estimate(['product_id' => $productId, 'start_latitude' => 51.5074, /* ... */]);
$ride = $rides->request(['fare_id' => $fare['fare']['fare_id'], 'product_id' => $productId, /* ... */]);

$rides->find($ride['request_id']);
$rides->current();
$rides->cancel($ride['request_id']);
$rides->receipt($ride['request_id']);
$rides->me();
$rides->paymentMethods();
$rides->history()->lazy();                  // every past trip, page by page
```

Ride requests need the privileged `request` scope. If the product is surging, `request()` throws a `ConflictException` whose `surgeConfirmationUrl()` you send the rider to. Then request again with the `surge_confirmation_id`.

Uber's current reference page documents only products, estimates, me, payment methods and creating a request. The other endpoints (current trip, history, places, map and receipt) still work for existing integrations but no longer have reference pages.

In the sandbox, step a trip through its states yourself:

```php
Uber::sandbox()->withToken($token)->rides()->sandbox()->setStatus($requestId, 'accepted');
Uber::sandbox()->withToken($token)->rides()->sandbox()->setProduct($productId, surgeMultiplier: 2.2);
```

### Guest Rides and Uber Health

These order rides for people who don't need an Uber account, billed to your organisation. Guest Rides uses scope `guests.trips` and Health uses scope `health`; both share the same API shape.

```php
$estimate = Uber::guestRides()->estimates([
    'pickup' => ['latitude' => 51.5074, 'longitude' => -0.1278],
    'dropoff' => ['latitude' => 51.4700, 'longitude' => -0.4543],
]);

$trip = Uber::guestRides()->create([
    'guest' => ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone_number' => '+447700900000'],
    'pickup' => ['latitude' => 51.5074, 'longitude' => -0.1278],
    'dropoff' => ['latitude' => 51.4700, 'longitude' => -0.4543],
    'product_id' => $estimate[0]['product']['product_id'],
    'fare_id' => $estimate[0]['estimate_info']['fare_id'],
]);

Uber::guestRides()->find($trip['request_id']);
Uber::guestRides()->trips('ACTIVE')->lazy();
Uber::guestRides()->message($trip['request_id'], 'At the main entrance');
Uber::guestRides()->cancel($trip['request_id']);
Uber::guestRides()->zones($lat, $lng);
Uber::guestRides()->autocomplete('Heathrow Terminal 5');

Uber::health()->create($trip);              // identical calls under /v1/health
Uber::health()->widgetToken($userId);       // for Uber's booking and tracking widgets
```

Third-party apps acting for another organisation call `forOrganization($orgId)` or set `UBER_ORGANIZATION_ID`. Either way, the package sends the `x-uber-organizationuuid` header.

In the sandbox, create a run with test drivers and drive the trip through its states:

```php
$run = Uber::sandbox()->guestRides()->sandbox()->createRun([...]);
$guests = Uber::sandbox()->guestRides()->inSandboxRun($run['run_id']);
$guests->create($trip);
Uber::sandbox()->guestRides()->sandbox()->driverState($run['run_id'], $driverId, 'ACCEPT');
```

<br><br>

## 📦 Uber Direct

Courier delivery from your store to your customer. It uses scope `eats.deliveries` and `UBER_DIRECT_CUSTOMER_ID`.

```php
$store = ['street_address' => ['20 W 34th St'], 'city' => 'New York', 'state' => 'NY', 'zip_code' => '10001', 'country' => 'US'];

$quote = Uber::direct()->quote(['pickup_address' => $store, 'dropoff_address' => $customer]);   // arrays are JSON-encoded for you

$delivery = Uber::direct()->create([
    'quote_id' => $quote['id'],
    'pickup_name' => 'Store', 'pickup_address' => $store, 'pickup_phone_number' => '+15555555555',
    'dropoff_name' => 'Jane', 'dropoff_address' => $customer, 'dropoff_phone_number' => '+15555555556',
    'manifest_items' => [['name' => 'Bouquet', 'quantity' => 1, 'size' => 'medium']],
    'idempotency_key' => $order->uuid,
]);

Uber::direct()->find($delivery['id']);
Uber::direct()->list(['filter' => 'ongoing'])->lazy();
Uber::direct()->update($delivery['id'], ['tip_by_customer' => 300]);
Uber::direct()->cancel($delivery['id'], 'customer_called_to_cancel');
Uber::direct()->proofOfDelivery($delivery['id']);   // base64 image in ['document']
Uber::direct()->refund([...]);
Uber::direct()->stores($lat, $lng);

Uber::direct()->createTest($delivery);              // the robot courier completes it by itself
Uber::forCustomer($otherCustomerId)->direct()->find($id);
```

Merchant and store management uses scope `direct.organizations`:

```php
Uber::direct()->organizations()->create([...]);
Uber::direct()->organizations()->invite($orgId, [...]);
Uber::direct()->businessLocations($orgId)->list()->lazy();
Uber::direct()->businessLocations($orgId)->update($locationId, ['name' => 'Soho']);
```

Uber Direct Japan uses the same API, so this client covers it too.

<br><br>

## 🍔 Uber Eats

For point-of-sale and order-management systems.

```php
// Stores
Uber::eats()->stores()->list()->lazy();
Uber::eats()->stores()->find($storeId);
Uber::eats()->stores()->setStatus($storeId, 'OFFLINE', reason: 'Kitchen closed');
Uber::eats()->stores()->setHolidayHours($storeId, ['2026-12-25' => ['open_time_periods' => []]]);

// Menus (sent gzip-compressed)
Uber::eats()->menus()->replace($storeId, $menu);
Uber::eats()->menus()->updateItem($storeId, $itemId, ['suspension_info' => [...]]);

// Orders: accept or deny within 11.5 minutes of orders.notification
Uber::eats()->orders()->find($orderId, expand: ['carts', 'deliveries', 'payment']);
Uber::eats()->orders()->accept($orderId, ['ready_for_pickup_time' => now()->addMinutes(15)]);
Uber::eats()->orders()->deny($orderId, ['type' => 'STORE_CLOSED', 'info' => 'Closed early']);
Uber::eats()->orders()->ready($orderId);
Uber::eats()->orders()->forStore($storeId, ['state' => 'OFFERED'])->lazy();

// Stores still on the previous order API
Uber::eats()->legacyOrders()->accept($orderId);

// Promotions, reports, Paymentless Checkout, your own couriers
Uber::eats()->promotions()->create($storeId, [...]);
Uber::eats()->reports()->create('ORDER_HISTORY_REPORT', now()->subWeek(), now(), [$storeId]);   // link arrives by webhook
Uber::eats()->payments()->charge([...]);                                                     // an idempotency key is added
Uber::eats()->couriers()->location([...]);
```

**Activating a store.** The merchant authorizes your app with `eats.pos_provisioning`. Then, with their token:

```php
$merchant = Uber::withToken($merchantToken)->eats();

$merchant->stores()->list();
$merchant->integrations()->activate($storeId, ['is_order_manager' => true, 'integrator_store_id' => $ourId]);
```

Uber then sends `store.provisioned`, and from then on app tokens work for that store.

<br><br>

## 💼 Uber for Business

```php
Uber::business()->receipts()->find($orderId);                     // business.receipts
Uber::business()->vouchers()->create([...]);                       // organizations.voucher_programs
Uber::business()->vouchers()->generateCodes($programId, 50);
Uber::business()->employees()->create([...]);                      // business.employees
Uber::business()->employeeTrips()->create([...]);                  // business.trips
Uber::business()->organizations()->create([...]);                  // business.organizations
Uber::business()->statements()->find($statementId);                // business.statements

Uber::business()->consentUrl($scopes, $redirectUri, 'Acme Travel'); // third-party consent
```

Identity administration (org units, users and role assignments):

```php
Uber::identity()->organization($rootOrgId)->createUser([...]);
Uber::identity()->organization($rootOrgId)->users()->lazy();
```

<br><br>

## 🧩 More APIs

```php
Uber::withToken($driverToken)->drivers()->trips()->lazy();         // Drivers API
Uber::vehicleSuppliers()->vehicles()->create([...]);               // Uber for Suppliers
Uber::vehicleSuppliers()->shifts()->save($earnerId, $start, $end);
Uber::withToken($adsToken)->ads($accountId)->campaigns()->lazy();  // Uber Ads (login.uber.com OAuth)
Uber::aiSolutions()->translate('en_US', 'fr_FR', 'Your driver is here');
Uber::payments()->confirmDeposit($depositId);                      // Uber Pay (payment providers)
Uber::identity()->linkAccount($yourUserId);                        // account linking
```

### Anything else

For endpoints without a wrapper, call them directly. The package still handles authentication, error mapping and retries:

```php
Uber::get('v1/some/endpoint', ['q' => 1], scopes: ['some.scope']);
Uber::post('v1/some/endpoint', $body, scopes: ['some.scope']);
Uber::withToken($token)->send('PUT', 'v1/thing', ['json' => $body, 'headers' => [...]]);
```

**Not wrapped:**
- Third-party Demand Rides and the `/docs/secured` APIs: partner-only, with no documented scopes.
- Uber Freight: it has a separate portal.
- SCIM: it needs a token issued through your identity provider.
- Consumer Delivery: no endpoints are published.

<br><br>

## 🔔 Webhooks

The package registers `POST /uber/webhook`, which has no CSRF check. Point every Uber dashboard at it, then listen:

```php
use FLAIRUK\Uber\Events\WebhookReceived;

Event::listen('uber.orders.notification', function (WebhookEvent $event) {
    AcceptOrder::dispatch($event->resourceId());       // queue it: answer Uber fast
});

Event::listen('uber.event.delivery_status', fn (WebhookEvent $event) => $event->status());   // Direct
Event::listen(WebhookReceived::class, fn (WebhookReceived $received) => logger($received->event->type));
```

- **Signatures.** Every request must carry a valid `X-Uber-Signature`; Direct also sends `X-Postmates-Signature`, which is accepted too. Riders, Eats and Vehicle Suppliers sign with your client secret. Guest Rides, Health, Business and Direct sign with each webhook's signing key, so add those keys to `UBER_WEBHOOK_SIGNING_KEY`.
- **Retries and order.** Uber retries failed deliveries and doesn't guarantee order. Events are de-duplicated by id for 48 hours. If a listener throws, the event is forgotten, so Uber's retry is processed. Fetch `$event->resourceHref` for the current state rather than trusting arrival order.
- **Your own route.** Set `UBER_WEBHOOK_PATH=` (empty) and use the `uber.webhook` middleware on a route of your own.

<br><br>

## ⚠️ Errors

```php
use FLAIRUK\Uber\Exceptions\{AuthenticationException, ConflictException, NotFoundException, RateLimitException, UberException, ValidationException};

try {
    Uber::direct()->create($delivery);
} catch (ValidationException $e) {      // 400 / 422: $e->errorCode, e.g. "address_undeliverable"
} catch (ConflictException $e) {        // 409: duplicate_delivery, surge, current_trip_exists…
    $e->metadata['delivery_id'] ?? $e->surgeConfirmationUrl();
} catch (RateLimitException $e) {       // 429
    $this->release($e->retryAfter());
} catch (AuthenticationException $e) {  // 401 / 403, or a refused token request
} catch (NotFoundException $e) {        // 404
} catch (UberException $e) {            // anything else; $e->response for the raw response
}
```

A `ConfigurationException` (also an `UberException`) means a credential or id is missing. It is thrown before anything is sent.

<br><br>

## ⚙️ Configuration

| Key | Env | Default |
| --- | --- | --- |
| `client_id`, `client_secret` | `UBER_CLIENT_ID`, `UBER_CLIENT_SECRET` | |
| `sandbox` | `UBER_SANDBOX` | `false` |
| `base_url` | `UBER_BASE_URL` | chosen per API |
| `locale` | `UBER_LOCALE` | Uber's default (sent as `Accept-Language`) |
| `oauth.url` | `UBER_OAUTH_URL` | `https://auth.uber.com` |
| `oauth.redirect_uri` | `UBER_REDIRECT_URI` | |
| `oauth.scopes` | `UBER_SCOPES` | `profile` |
| `direct.customer_id` | `UBER_DIRECT_CUSTOMER_ID` | |
| `business.organization_id` | `UBER_ORGANIZATION_ID` | |
| `business.vouchers_scope` | `UBER_VOUCHERS_SCOPE` | `organizations.voucher_programs` |
| `health.scope` | `UBER_HEALTH_SCOPE` | `health` |
| `ads.account_id` | `UBER_ADS_ACCOUNT_ID` | |
| `webhooks.path` | `UBER_WEBHOOK_PATH` | `uber/webhook` |
| `webhooks.signing_keys` | `UBER_WEBHOOK_SIGNING_KEY` | |
| `timeout` | `UBER_TIMEOUT` | `30` seconds |
| `retry` | | 2 retries, 500 ms apart (reads only) |
| `cache_store` | `UBER_CACHE_STORE` | the default store |

Scope one call differently without changing the defaults:

```php
Uber::sandbox()->guestRides()->estimates($route);
Uber::locale('fr_FR')->withToken($token)->rides()->products($lat, $lng);
Uber::forCustomer($customerId)->direct()->list();
Uber::guestRides()->forOrganization($orgId)->trips();
```

<br><br>

## 🧪 Testing

```bash
composer test
```

The client uses Laravel's HTTP client, so `Http::fake()` works in your own tests. Fake the token endpoint too:

```php
Http::fake([
    'auth.uber.com/oauth/v2/token' => Http::response(['access_token' => 'test', 'expires_in' => 3600]),
    'api.uber.com/v1/customers/*/deliveries' => Http::response(['id' => 'del_test', 'status' => 'pending']),
]);
```

<br><br>

## 📄 License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

Uber is a trademark of Uber Technologies, Inc. This package is not affiliated with or endorsed by Uber.
