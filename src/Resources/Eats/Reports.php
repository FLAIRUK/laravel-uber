<?php

namespace FLAIRUK\Uber\Resources\Eats;

use DateTimeInterface;
use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Reports are generated asynchronously: create() returns a workflow_id, and the
 * eats.report.success webhook brings the download links.
 *
 * 60 requests a minute, up to 50 stores a request. Data can take 72 hours to settle.
 *
 * @see https://developer.uber.com/docs/eats/guides/reporting
 */
class Reports extends Resource
{
    public const TYPES = [
        'PAYMENT_DETAILS_REPORT', 'ORDER_ERRORS_MENU_ITEM_REPORT', 'ORDER_ERRORS_TRANSACTION_REPORT',
        'ORDER_HISTORY_REPORT', 'DOWNTIME_REPORT', 'CUSTOMER_AND_DELIVERY_FEEDBACK_REPORT',
        'MENU_ITEM_FEEDBACK_REPORT', 'BILLING_DETAILS_REPORT', 'ORDERS_AND_ITEMS_REPORT', 'FINANCE_SUMMARY_REPORT',
    ];

    protected array $scopes = ['eats.report'];

    protected string $api = 'eats';

    /**
     * @param  list<string>  $storeIds
     * @param  list<string>  $groupIds
     */
    public function create(string $type, DateTimeInterface|string $start, DateTimeInterface|string $end, array $storeIds = [], array $groupIds = []): Response
    {
        $format = $type === 'PAYMENT_DETAILS_REPORT' ? 'Y-m-d\TH:i:s' : 'Y-m-d';

        return $this->post('v1/eats/report', array_filter([
            'report_type' => $type,
            'store_uuids' => array_values($storeIds),
            'group_uuids' => array_values($groupIds),
            'start_date' => $start instanceof DateTimeInterface ? $start->format($format) : $start,
            'end_date' => $end instanceof DateTimeInterface ? $end->format($format) : $end,
        ], fn ($value) => $value !== []));
    }
}
