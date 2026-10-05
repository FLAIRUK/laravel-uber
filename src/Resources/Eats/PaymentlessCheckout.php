<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;
use Illuminate\Support\Str;

/**
 * Paymentless Checkout (PLC): charge an Uber app payment code at your till.
 * Version 2 (scope delivery.plc2) adds refunds; version 1 uses delivery.plc.
 *
 * @see https://developer.uber.com/docs/eats/references/api/paymentless_checkout_2
 */
class PaymentlessCheckout extends Resource
{
    protected string $api = 'eats';

    public function __construct(Uber $client, protected int $version = 2)
    {
        parent::__construct($client);

        $this->scopes = [$version === 1 ? 'delivery.plc' : 'delivery.plc2'];
    }

    public function status(): Response
    {
        return $this->version === 1
            ? $this->get('v1/plc/healthz')
            : $this->post('v2/plc/healthz', ['status' => 'CHECK'], retry: true);
    }

    /**
     * Check a payment code the customer shows you.
     */
    public function validate(string $paymentCode, string $acceptorCode): Response
    {
        return $this->version === 1
            ? $this->get('v1/plc/payment_codes/'.$this->segment($paymentCode), ['acceptor_code' => $acceptorCode])
            : $this->post('v2/plc/payment_codes', ['payment_code' => $paymentCode, 'acceptor_code' => $acceptorCode], retry: true);
    }

    /**
     * Charge a payment code. The idempotency key makes a retry safe; one is generated if you don't pass it.
     *
     * @param  array<string, mixed>  $charge  ['payment_code', 'amount_value', 'amount_currency', 'acceptor_code', 'reference_id', ...]
     */
    public function charge(array $charge, ?string $idempotencyKey = null): Response
    {
        return $this->send('POST', 'v'.$this->version.'/plc/charges', [
            'json' => $charge,
            'headers' => ['Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid()],
        ], retry: false);
    }

    /**
     * Version 1 only.
     *
     * @param  array<string, mixed>  $filters  ['reference_id', 'limit', 'starting_after', 'ending_before']
     */
    public function charges(array $filters = []): Response
    {
        return $this->get('v1/plc/charges', $filters);
    }

    /**
     * Version 1 only.
     */
    public function findCharge(string $chargeId): Response
    {
        return $this->get('v1/plc/charges/'.$this->segment($chargeId));
    }

    /**
     * Version 2 only.
     *
     * @param  array<string, mixed>  $refund  ['return_code', 'refund_amount', 'amount_currency', 'acceptor_code', 'refund_reference_id', ...]
     */
    public function refund(array $refund): Response
    {
        return $this->post('v2/plc/refunds', $refund);
    }
}
