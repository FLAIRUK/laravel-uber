<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Store promotions. Lists only return promotions created through the API.
 *
 * @see https://developer.uber.com/docs/eats/references/api/promotions_suite
 */
class Promotions extends Resource
{
    protected array $scopes = ['eats.store.promotion.read'];

    protected string $api = 'eats';

    /**
     * @param  array<string, mixed>  $promotion
     */
    public function create(string $storeId, array $promotion): Response
    {
        return $this->post('v1/delivery/stores/'.$this->segment($storeId).'/promotion', $promotion, ['eats.store.promotion.write']);
    }

    public function find(string $promotionId): Response
    {
        return $this->get('v1/delivery/promotions/'.$this->segment($promotionId));
    }

    /**
     * @param  array<string, mixed>|null  $timeRange
     */
    public function forStore(string $storeId, ?string $state = null, ?array $timeRange = null): Response
    {
        return $this->get('v1/delivery/stores/'.$this->segment($storeId).'/promotions', [
            'state' => $state,
            'time_range' => $timeRange === null ? null : json_encode($timeRange),
        ]);
    }

    public function revoke(string $promotionId): Response
    {
        return $this->post('v1/delivery/promotions/'.$this->segment($promotionId).'/revoke', [], ['eats.store.promotion.write']);
    }
}
