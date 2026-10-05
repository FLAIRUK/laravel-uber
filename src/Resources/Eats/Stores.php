<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Stores: details, status, prep time, holiday hours and fulfilment.
 *
 * @see https://developer.uber.com/docs/eats/references/api/store_suite
 */
class Stores extends Resource
{
    protected array $scopes = ['eats.store'];

    protected string $api = 'eats';

    /**
     * The stores your app (or, with a merchant token, the merchant) can manage.
     * Call ->lazy() on the result to walk every page.
     */
    public function list(?int $pageSize = null, ?string $pageToken = null): Response
    {
        return $this->get('v1/delivery/stores', ['page_size' => $pageSize, 'next_page_token' => $pageToken])
            ->paginate('data', function (Response $page) use ($pageSize) {
                $next = $page->get('pagination_data.next_page_token');

                return filled($next) ? $this->list($pageSize, (string) $next) : null;
            });
    }

    /**
     * @param  list<string>  $expand  extra sections, e.g. ['MENU_INFO']
     */
    public function find(string $storeId, array $expand = []): Response
    {
        return $this->get('v1/delivery/store/'.$this->segment($storeId), ['expand' => $expand ? implode(',', $expand) : null]);
    }

    /**
     * @param  array<string, mixed>  $changes  ['contact', 'location', 'pickup_instructions']
     */
    public function update(string $storeId, array $changes): Response
    {
        return $this->post('v1/delivery/store/'.$this->segment($storeId), $changes);
    }

    public function status(string $storeId): Response
    {
        return $this->get('v1/delivery/store/'.$this->segment($storeId).'/status');
    }

    /**
     * Take a store online or offline without changing its hours.
     *
     * @param  string  $status  ONLINE or OFFLINE
     */
    public function setStatus(string $storeId, string $status, ?string $reason = null, \DateTimeInterface|string|null $offlineUntil = null): Response
    {
        return $this->post('v1/delivery/store/'.$this->segment($storeId).'/update-store-status', [
            'status' => $status,
            'reason' => $reason,
            'is_offline_until' => $offlineUntil,
        ]);
    }

    /**
     * @param  array<string, mixed>  $prepTime  e.g. ['default_prep_time' => 900] (seconds)
     */
    public function setPrepTime(string $storeId, array $prepTime): Response
    {
        return $this->post('v1/delivery/store/'.$this->segment($storeId).'/update-store-prep-time', $prepTime);
    }

    /**
     * Bring-your-own-courier fulfilment settings (scope eats.byoc.fulfillment.config).
     *
     * @param  array<string, mixed>  $overrideConfig
     */
    public function setFulfillmentConfiguration(string $storeId, array $overrideConfig): Response
    {
        return $this->post(
            'v1/delivery/store/'.$this->segment($storeId).'/update-fulfillment-configuration',
            ['override_config' => $overrideConfig],
            ['eats.byoc.fulfillment.config'],
        );
    }

    /**
     * Opening-hour exceptions by date.
     */
    public function holidayHours(string $storeId): Response
    {
        return $this->get('v1/eats/stores/'.$this->segment($storeId).'/holiday-hours');
    }

    /**
     * @param  array<string, mixed>  $holidayHours  ['2026-12-25' => ['open_time_periods' => []]] (no periods = closed)
     */
    public function setHolidayHours(string $storeId, array $holidayHours): Response
    {
        return $this->post('v1/eats/stores/'.$this->segment($storeId).'/holiday-hours', ['holiday_hours' => $holidayHours]);
    }
}
