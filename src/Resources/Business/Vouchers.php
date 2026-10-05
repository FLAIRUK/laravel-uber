<?php

namespace FLAIRUK\Uber\Resources\Business;

use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;

/**
 * Voucher programmes: rides and meals your organisation pays for.
 * Scope organizations.voucher_programs (or the .delegated / .aggregator variants:
 * set UBER_VOUCHERS_SCOPE).
 *
 * @see https://developer.uber.com/docs/vouchers/introduction
 */
class Vouchers extends OrganizationResource
{
    public function __construct(Uber $client, ?string $organizationId = null)
    {
        parent::__construct($client, $organizationId);

        $this->scopes = [(string) $client->config('business.vouchers_scope', 'organizations.voucher_programs')];
    }

    // Templates

    /**
     * @param  array<string, mixed>  $template  ['template_name', 'name', 'voucher_type' => 'PERSONAL_TRANSPORT'|'EATS'|'GENERIC_VOUCHER', 'creator_email', ...]
     */
    public function createTemplate(array $template): Response
    {
        return $this->post($this->path('voucher-program-templates'), $template);
    }

    public function templates(?int $limit = null, ?string $pageToken = null): Response
    {
        return $this->get($this->path('voucher-program-templates'), ['limit' => $limit, 'page_token' => $pageToken]);
    }

    public function template(string $templateId): Response
    {
        return $this->get($this->path('voucher-program-templates/'.$this->segment($templateId)));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updateTemplate(string $templateId, array $changes): Response
    {
        return $this->patch($this->path('voucher-program-templates/'.$this->segment($templateId)), $changes);
    }

    // Programmes

    /**
     * Returns voucher_program_id, code_text and code_link. A 402 means the organisation has no payment account.
     *
     * @param  array<string, mixed>  $program  ['name', 'currency_code', 'starts_at', 'ends_at' (epoch ms), 'code_scheme', 'creator_email', value caps...]
     */
    public function create(array $program): Response
    {
        return $this->post($this->path('voucher-programs'), $program);
    }

    /**
     * @param  array<string, mixed>  $program
     */
    public function createFromTemplate(array $program): Response
    {
        return $this->post($this->path('voucher-programs/create-from-template'), $program);
    }

    /**
     * A single-guest programme. Not available with the .delegated scope.
     *
     * @param  array<string, mixed>  $program
     */
    public function createIndividual(array $program): Response
    {
        return $this->post($this->path('individual_voucher_program/create'), $program);
    }

    public function find(string $programId, bool $includeGuests = false): Response
    {
        return $this->get($this->path('voucher-programs/'.$this->segment($programId)), ['include_guests' => $includeGuests ?: null]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(string $programId, array $changes): Response
    {
        return $this->patch($this->path('voucher-programs/'.$this->segment($programId)), $changes);
    }

    public function cancel(string $programId): Response
    {
        return $this->post($this->path('voucher-programs/'.$this->segment($programId).'/cancel'));
    }

    /**
     * @param  string|null  $status  Upcoming, Active, Complete or Canceled
     */
    public function search(?string $status = null, int $limit = 50, int $offset = 0): Response
    {
        return $this->post($this->path('voucher-programs/search'), [
            'program_status' => $status,
            'limit' => $limit,
            'offset' => $offset,
        ], retry: true);
    }

    // Guests

    public function guests(string $programId): Response
    {
        return $this->get($this->path('voucher-programs/'.$this->segment($programId).'/guests'));
    }

    /**
     * @param  list<array{email?: string, phone?: string}>  $guests
     */
    public function addGuests(string $programId, array $guests, bool $sendNotifications = true): Response
    {
        return $this->post($this->path('voucher-programs/'.$this->segment($programId).'/add-guests'), [
            'guests' => $guests,
            'send_notifications' => $sendNotifications,
        ]);
    }

    // Codes

    public function generateCodes(string $programId, int $count): Response
    {
        return $this->post($this->path('voucher-programs/'.$this->segment($programId).'/codes/generate'), ['number_of_codes' => $count]);
    }

    public function codes(string $programId, bool $includeUsage = false): Response
    {
        return $this->get($this->path('voucher-programs/'.$this->segment($programId).'/codes'), ['include_usage' => $includeUsage ?: null]);
    }

    /**
     * @param  list<string>  $codes
     */
    public function cancelCodes(string $programId, array $codes): Response
    {
        return $this->post($this->path('voucher-programs/'.$this->segment($programId).'/codes/cancel'), ['codes' => array_values($codes)]);
    }

    /**
     * Send codes to people. Asynchronous: the voucher_program_code_distributed webhook reports the outcome.
     *
     * @param  array<string, mixed>  $distribution
     */
    public function distributeCodes(string $programId, array $distribution): Response
    {
        return $this->post($this->path('voucher-programs/'.$this->segment($programId).'/codes/distribute'), $distribution);
    }

    /**
     * Redeem a voucher code for the signed-in rider (user token, scope vouchers.redeem).
     * Uber recommends deep links over this endpoint.
     */
    public function redeem(string $codeText): Response
    {
        return $this->post('v1.2/me/vouchers/redeem', ['code_text' => $codeText], []);
    }

    protected function path(string $path): string
    {
        return 'v1/organizations/'.$this->segment($this->organizationId()).'/'.$path;
    }

    protected function headers(): array
    {
        return [];
    }
}
