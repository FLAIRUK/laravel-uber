<?php

namespace FLAIRUK\Uber\Resources\Business;

use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Response;

/**
 * Business receipts for rides and Eats orders (scope business.receipts, Enterprise only).
 * The business_order.receipt webhook tells you when one is created or updated.
 *
 * @see https://developer.uber.com/docs/businesses/receipts/introduction
 */
class Receipts extends OrganizationResource
{
    protected array $scopes = ['business.receipts'];

    /**
     * The receipt for a ride or Eats order: fares, charges, taxes and documents.
     */
    public function find(string $orderId): Response
    {
        $this->organizationId();

        return $this->get('v1/business/orders/'.$this->segment($orderId).'/receipt');
    }

    /**
     * @deprecated use find() with the order id
     */
    public function trip(string $tripId): Response
    {
        return $this->get('v1/business/trips/'.$this->segment($tripId).'/receipt');
    }

    /**
     * @deprecated use find() and its payment_detail documents
     */
    public function tripPdfUrl(string $tripId): Response
    {
        return $this->get('v1/business/trips/'.$this->segment($tripId).'/receipt/pdf_url');
    }

    /**
     * @deprecated use find() and its payment_detail documents
     */
    public function tripInvoiceUrls(string $tripId): Response
    {
        return $this->get('v1/business/trips/'.$this->segment($tripId).'/invoice_urls');
    }

    /**
     * Sandbox: create a completed test ride (scenario COMPLETED_STATE_TRIP) or Eats
     * order (COMPLETED_STATE_DELIVERY_ORDER), then poll sandboxRun() for its order id.
     *
     * @param  array<string, mixed>  $run
     */
    public function createSandboxRun(array $run, bool $eats = false): Response
    {
        if (! $this->client->isSandbox()) {
            throw new ConfigurationException('Uber sandbox runs need the sandbox: use Uber::sandbox() or set UBER_SANDBOX=true.');
        }

        return $this->post($eats ? 'v1/sandbox/terminal-state-eats-run' : 'v1/sandbox/terminal-state-trip-run', $run);
    }

    public function sandboxRun(string $runId): Response
    {
        return $this->get('v1/sandbox/run/'.$this->segment($runId));
    }
}
