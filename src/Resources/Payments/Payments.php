<?php

namespace FLAIRUK\Uber\Resources\Payments;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;
use Illuminate\Support\Str;

/**
 * Uber Pay, for payment providers Uber has onboarded: report the outcome of
 * deposits, refunds, charges and payouts that Uber asked you to process.
 *
 * App token, scope payments.deposits (payments.payouts for payouts). Every write
 * carries an X-Idempotency-Key; one is generated if you don't pass it.
 * Amounts are E5 integers (the amount × 100,000).
 *
 * The endpoints Uber calls on your side (/charge, /refund, /payout...) are yours to build.
 *
 * @see https://uberpay.uber.com
 */
class Payments extends Resource
{
    protected array $scopes = ['payments.deposits'];

    public function deposit(string $depositId): Response
    {
        return $this->get('v1/payments/deposits/'.$this->segment($depositId));
    }

    /**
     * @param  array<string, mixed>  $deposit
     */
    public function updateDeposit(string $depositId, array $deposit, ?string $idempotencyKey = null): Response
    {
        return $this->write('v1/payments/deposits/'.$this->segment($depositId), $deposit, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $confirmation
     */
    public function confirmDeposit(string $depositId, array $confirmation = [], ?string $idempotencyKey = null): Response
    {
        return $this->write('v1/payments/deposits/'.$this->segment($depositId).'/confirm', $confirmation, $idempotencyKey);
    }

    public function cancelDeposit(string $depositId, string $reason, ?string $idempotencyKey = null): Response
    {
        return $this->write('v1/payments/deposits/'.$this->segment($depositId).'/cancel', ['cancel_reason' => $reason], $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $refund  e.g. ['status' => 'CONFIRMED'|'REJECTED', ...]
     */
    public function updateRefund(string $refundId, array $refund, ?string $idempotencyKey = null): Response
    {
        return $this->write('v1/payments/refunds/'.$this->segment($refundId), $refund, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    public function finalizeCharge(string $chargeId, array $charge, ?string $idempotencyKey = null): Response
    {
        return $this->write('v1/payments/charges/'.$this->segment($chargeId), $charge, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $payout
     */
    public function finalizePayout(string $payoutId, array $payout, ?string $idempotencyKey = null): Response
    {
        return $this->write('v1/payments/payouts/'.$this->segment($payoutId), $payout, $idempotencyKey, ['payments.payouts']);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<string>|null  $scopes
     */
    protected function write(string $path, array $body, ?string $idempotencyKey, ?array $scopes = null): Response
    {
        return $this->send('POST', $path, [
            'json' => $body,
            'headers' => ['X-Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid()],
        ], $scopes, retry: false);
    }
}
