<?php

namespace FLAIRUK\Uber\Resources\Identity;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Consumer Identity (Login with Uber) and organisation administration.
 *
 * For the OAuth flows themselves (authorize, token, PAR, revoke, JWKS) use Uber::oauth().
 *
 * @see https://developer.uber.com/docs/consumer-identity/introduction
 */
class Identity extends Resource
{
    /**
     * The signed-in user's OpenID profile (user token, scope profile; needs Uber's approval).
     */
    public function me(): Response
    {
        return $this->get('v3/me', scopes: []);
    }

    /**
     * Link the signed-in Uber user to your user id (user token, scope identity.link-account).
     */
    public function linkAccount(string $thirdPartyUserId, ?string $accountType = null): Response
    {
        return $this->post('v1/identity/link-account', [
            'thirdPartyUserID' => $thirdPartyUserId,
            'accountType' => $accountType,
        ], []);
    }

    /**
     * Unlink one of your users (app token, scope identity.unlink-account).
     */
    public function unlinkAccount(string $thirdPartyUserId, ?string $accountType = null): Response
    {
        return $this->post('v1/identity/unlink-account', [
            'thirdPartyUserID' => $thirdPartyUserId,
            'clientID' => $this->client->config('client_id'),
            'accountType' => $accountType,
        ], ['identity.unlink-account']);
    }

    /**
     * Register an OAuth client for a partner (scope oauth.dcr or oauth.dcr.b2b, granted by your Uber partner engineer).
     *
     * @param  array<string, mixed>  $client  ['client_name', 'redirect_uris', 'jwks', 'organization_uuid', ...]
     */
    public function registerClient(array $client, bool $b2b = true): Response
    {
        return $this->post('v2/oauth/register', $client, $b2b ? ['oauth.dcr.b2b'] : []);
    }

    /**
     * Org units, org users and role assignments under a root organisation.
     */
    public function organization(?string $organizationId = null): Organization
    {
        return new Organization($this->client, $organizationId ?? (string) $this->client->config('business.organization_id'));
    }
}
