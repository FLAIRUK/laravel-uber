<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Order Fulfillment API (/v1/delivery). Accept or deny within 11.5 minutes of
 * the orders.notification webhook, or Uber cancels the order.
 *
 * Only the store's order manager app may accept, deny or cancel its orders.
 *
 * @see https://developer.uber.com/docs/eats/references/api/order_suite
 */
class Orders extends Resource
{
    protected array $scopes = ['eats.order'];

    protected string $api = 'eats';

    /**
     * @param  list<string>  $expand  e.g. ['carts', 'deliveries', 'payment']
     */
    public function find(string $orderId, array $expand = []): Response
    {
        return $this->get('v1/delivery/order/'.$this->segment($orderId), ['expand' => $expand ? implode(',', $expand) : null]);
    }

    /**
     * A store's orders. Call ->lazy() on the result to walk every page.
     *
     * @param  array<string, mixed>  $filters  ['state', 'status', 'start_time', 'end_time', 'page_size', 'expand']
     */
    public function forStore(string $storeId, array $filters = []): Response
    {
        if (is_array($filters['expand'] ?? null)) {
            $filters['expand'] = implode(',', $filters['expand']);
        }

        return $this->get('v1/delivery/store/'.$this->segment($storeId).'/orders', $filters)
            ->paginate('data', function (Response $page) use ($storeId, $filters) {
                $next = $page->get('pagination_data.next_page_token');

                return filled($next) ? $this->forStore($storeId, ['next_page_token' => $next] + $filters) : null;
            });
    }

    /**
     * @param  array<string, mixed>  $details  ['ready_for_pickup_time'?, 'external_reference_id'?, 'accepted_by'?, 'order_pickup_instructions'?]
     */
    public function accept(string $orderId, array $details = []): Response
    {
        return $this->post($this->path($orderId, 'accept'), $details);
    }

    /**
     * @param  array<string, mixed>  $reason  ['info' => ..., 'type' => 'STORE_CLOSED'|'ITEM_ISSUE'|..., 'item_ids'?]
     */
    public function deny(string $orderId, array $reason): Response
    {
        return $this->post($this->path($orderId, 'deny'), ['deny_reason' => $reason]);
    }

    /**
     * @param  array<string, mixed>  $reason  ['type' => ..., 'info' => ...]
     */
    public function cancel(string $orderId, array $reason = []): Response
    {
        return $this->post($this->path($orderId, 'cancel'), $reason ? ['cancellation_reason' => $reason] : []);
    }

    /**
     * The order is ready for pickup.
     */
    public function ready(string $orderId): Response
    {
        return $this->post($this->path($orderId, 'ready'));
    }

    public function updateReadyTime(string $orderId, \DateTimeInterface|string $readyForPickupTime): Response
    {
        return $this->post($this->path($orderId, 'update-ready-time'), ['ready_for_pickup_time' => $readyForPickupTime]);
    }

    /**
     * Adjust the order total, e.g. for weighed items. amount_e5 is the amount × 100,000.
     *
     * @param  array<string, mixed>  $adjustment  ['amount_e5', 'tax_rate'?, 'reason', 'custom_reason'?]
     */
    public function adjustPrice(string $orderId, array $adjustment): Response
    {
        return $this->post($this->path($orderId, 'adjust-price'), $adjustment);
    }

    /**
     * Check a substitution or quantity change with the customer before making it.
     *
     * @param  array<string, mixed>  $validation  ['issue_type', 'item', 'action_type'?, 'item_availability'?, 'item_substitute'?]
     */
    public function validateItemFulfillment(string $orderId, array $validation): Response
    {
        return $this->post($this->path($orderId, 'validate-item-fulfillment'), $validation);
    }

    /**
     * @param  array<string, mixed>  $resolution
     */
    public function resolveFulfillmentIssues(string $orderId, array $resolution): Response
    {
        return $this->post($this->path($orderId, 'resolve-fulfillment-issues'), $resolution);
    }

    /**
     * Uber's suggested replacements for an out-of-stock item.
     *
     * @param  array<string, mixed>  $query  ['id' => item id, 'order_id', 'store_id']
     */
    public function replacementRecommendations(array $query): Response
    {
        return $this->post('v1/delivery/get-replacement-recommendations', $query, retry: true);
    }

    /**
     * Ask for more than one courier for a large order (scope delivery.multiple.courier).
     */
    public function setCourierCount(string $orderId, int $count): Response
    {
        return $this->post($this->path($orderId, 'update-delivery-partner-count'), ['delivery_partner_count' => $count], ['delivery.multiple.courier']);
    }

    protected function path(string $orderId, string $action): string
    {
        return 'v1/delivery/order/'.$this->segment($orderId).'/'.$action;
    }
}
