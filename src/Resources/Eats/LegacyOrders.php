<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * The previous Order API (/v1/eats/orders, /v2/eats/order). Uber labels it the
 * previous version but has not set an end date; use orders() for new work.
 *
 * @see https://developer.uber.com/docs/eats/references/api/v1/post-eats-order-orderid-acceptposorder
 */
class LegacyOrders extends Resource
{
    public const DENY_CODES = [
        'STORE_CLOSED', 'POS_NOT_READY', 'POS_OFFLINE', 'ITEM_AVAILABILITY', 'MISSING_ITEM', 'MISSING_INFO',
        'PRICING', 'CAPACITY', 'ADDRESS', 'SPECIAL_INSTRUCTIONS', 'OTHER',
    ];

    public const CANCEL_REASONS = [
        'OUT_OF_ITEMS', 'KITCHEN_CLOSED', 'CUSTOMER_CALLED_TO_CANCEL', 'RESTAURANT_TOO_BUSY',
        'CANNOT_COMPLETE_CUSTOMER_NOTE', 'OTHER',
    ];

    protected array $scopes = ['eats.order'];

    protected string $api = 'eats';

    /**
     * An order's eater, cart, payment and deliveries.
     */
    public function find(string $orderId): Response
    {
        return $this->get('v2/eats/order/'.$this->segment($orderId));
    }

    /**
     * Orders waiting to be accepted or denied (scope eats.store.orders.read).
     */
    public function created(string $storeId, ?int $limit = null): Response
    {
        return $this->get('v1/eats/stores/'.$this->segment($storeId).'/created-orders', ['limit' => $limit], ['eats.store.orders.read'])
            ->paginate('orders');
    }

    /**
     * Orders cancelled recently (scope eats.store.orders.read).
     */
    public function canceled(string $storeId, ?int $limit = null): Response
    {
        return $this->get('v1/eats/stores/'.$this->segment($storeId).'/canceled-orders', ['limit' => $limit], ['eats.store.orders.read'])
            ->paginate('orders');
    }

    /**
     * @param  array<string, mixed>  $details  ['reason'?, 'pickup_time'? (unix), 'external_reference_id'?]
     */
    public function accept(string $orderId, array $details = []): Response
    {
        return $this->post('v1/eats/orders/'.$this->segment($orderId).'/accept_pos_order', $details);
    }

    /**
     * @param  array<string, mixed>  $reason  ['explanation', 'code' => one of DENY_CODES, 'out_of_stock_items'?, 'invalid_items'?]
     */
    public function deny(string $orderId, array $reason): Response
    {
        return $this->post('v1/eats/orders/'.$this->segment($orderId).'/deny_pos_order', ['reason' => $reason]);
    }

    /**
     * @param  string  $reason  one of CANCEL_REASONS
     */
    public function cancel(string $orderId, string $reason, ?string $details = null, ?string $cancellingParty = null): Response
    {
        return $this->post('v1/eats/orders/'.$this->segment($orderId).'/cancel', [
            'reason' => $reason,
            'details' => $details,
            'cancelling_party' => $cancellingParty,
        ]);
    }

    /**
     * For stores that deliver with their own couriers: started, arriving or delivered.
     */
    public function restaurantDeliveryStatus(string $orderId, string $status): Response
    {
        return $this->post(
            'v1/eats/orders/'.$this->segment($orderId).'/restaurantdelivery/status',
            ['status' => $status],
            ['eats.store.orders.restaurantdelivery.status'],
        );
    }
}
