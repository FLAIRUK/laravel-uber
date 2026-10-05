<?php

namespace FLAIRUK\Uber\Resources\Health;

use FLAIRUK\Uber\Resources\GuestRides\ManagedRides;
use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;

/**
 * Uber Health: HIPAA-compliant rides for patients and caregivers, ordered by a
 * healthcare organisation. The same shape as Guest Rides under /v1/health.
 *
 * App token, scope health (health.sandbox in the sandbox: set it with
 * UBER_HEALTH_SCOPE). Never log trip payloads: they can hold patient data.
 *
 * @see https://developer.uber.com/docs/health/introduction
 */
class Health extends ManagedRides
{
    protected string $prefix = 'health';

    protected array $scopes = ['health'];

    protected string $api = 'health';

    public function __construct(Uber $client)
    {
        parent::__construct($client);

        $this->scopes = [(string) $client->config('health.scope', 'health')];
    }

    /**
     * A proxy number that connects this phone number to the driver. Health takes the
     * request id in the body, not the path.
     */
    public function communication(string $requestId, string $phoneNumber): Response
    {
        return $this->put('v1/health/trips/communication', ['request_id' => $requestId, 'phone_number' => $phoneNumber]);
    }

    /**
     * A short-lived token for Uber's booking and tracking widgets, for one of your users.
     */
    public function widgetToken(string $externalUserId): Response
    {
        return $this->post('u4b/v1/widget/transienttoken', ['external_user_id' => $externalUserId]);
    }

    protected function phoneInfoPath(): string
    {
        return 'v1/health/guests/phone-info';
    }
}
