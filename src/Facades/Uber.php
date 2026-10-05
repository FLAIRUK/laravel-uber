<?php

namespace FLAIRUK\Uber\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \FLAIRUK\Uber\Resources\Rides\Rides rides()
 * @method static \FLAIRUK\Uber\Resources\GuestRides\GuestRides guestRides()
 * @method static \FLAIRUK\Uber\Resources\Health\Health health()
 * @method static \FLAIRUK\Uber\Resources\Direct\Direct direct()
 * @method static \FLAIRUK\Uber\Resources\Eats\Eats eats()
 * @method static \FLAIRUK\Uber\Resources\Business\Business business()
 * @method static \FLAIRUK\Uber\Resources\Identity\Identity identity()
 * @method static \FLAIRUK\Uber\Resources\Drivers\Drivers drivers()
 * @method static \FLAIRUK\Uber\Resources\VehicleSuppliers\VehicleSuppliers vehicleSuppliers()
 * @method static \FLAIRUK\Uber\Resources\Ads\Ads ads(?string $accountId = null)
 * @method static \FLAIRUK\Uber\Resources\AiSolutions\AiSolutions aiSolutions()
 * @method static \FLAIRUK\Uber\Resources\Payments\Payments payments()
 * @method static \FLAIRUK\Uber\OAuth\OAuth oauth()
 * @method static \FLAIRUK\Uber\Uber withToken(string|\FLAIRUK\Uber\OAuth\AccessToken|array $token)
 * @method static \FLAIRUK\Uber\Uber asApp()
 * @method static \FLAIRUK\Uber\OAuth\AccessToken|null token()
 * @method static \FLAIRUK\Uber\Uber sandbox(bool $sandbox = true)
 * @method static \FLAIRUK\Uber\Uber forCustomer(string $customerId)
 * @method static \FLAIRUK\Uber\Uber locale(string $locale)
 * @method static string baseUrl(?string $api = null)
 * @method static string authUrl(?string $api = null)
 * @method static bool isSandbox()
 * @method static mixed config(string $key, mixed $default = null)
 * @method static \FLAIRUK\Uber\Response get(string $path, array $query = [], array $scopes = [])
 * @method static \FLAIRUK\Uber\Response post(string $path, array $body = [], array $scopes = [], bool $retry = false)
 * @method static \FLAIRUK\Uber\Response put(string $path, array $body = [], array $scopes = [], bool $retry = false)
 * @method static \FLAIRUK\Uber\Response patch(string $path, array $body = [], array $scopes = [], bool $retry = false)
 * @method static \FLAIRUK\Uber\Response delete(string $path, array $body = [], array $scopes = [], bool $retry = false)
 * @method static \FLAIRUK\Uber\Response send(string $method, string $path, array $options = [], array $scopes = [], ?bool $retry = null)
 * @method static \Illuminate\Http\Client\PendingRequest request(array $scopes = [], ?string $authUrl = null)
 *
 * @see \FLAIRUK\Uber\Uber
 */
class Uber extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FLAIRUK\Uber\Uber::class;
    }
}
