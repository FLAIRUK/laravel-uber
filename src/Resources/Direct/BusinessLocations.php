<?php

namespace FLAIRUK\Uber\Resources\Direct;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;

/**
 * Store / Business Location Management: an organisation's stores, whose
 * external_business_location_id is the external_store_id on quotes and deliveries.
 *
 * @see https://developer.uber.com/docs/deliveries/api-reference/business-location-management
 */
class BusinessLocations extends Resource
{
    protected array $scopes = ['direct.organizations'];

    protected string $api = 'direct';

    public function __construct(Uber $client, protected string $organizationId)
    {
        parent::__construct($client);
    }

    /**
     * Call ->lazy() on the result to walk every page.
     */
    public function list(?int $limit = null, ?string $pageToken = null): Response
    {
        return $this->get($this->path(), ['limit' => $limit, 'pagetoken' => $pageToken])
            ->paginate('business_locations', function (Response $page) use ($limit) {
                $next = $page->get('next_page_token');

                return filled($next) ? $this->list($limit, (string) $next) : null;
            });
    }

    public function find(string $businessLocationId): Response
    {
        return $this->get($this->path($businessLocationId));
    }

    /**
     * @param  array<string, mixed>  $changes  ['name', 'phone_number', 'detailed_address', 'location' => ['lat', 'lng'], 'external_business_location_id']
     */
    public function update(string $businessLocationId, array $changes): Response
    {
        return $this->patch($this->path($businessLocationId), $changes);
    }

    protected function path(?string $id = null): string
    {
        $path = 'v1/direct/organizations/'.$this->segment($this->organizationId).'/business_locations';

        return $id === null ? $path : $path.'/'.$this->segment($id);
    }
}
