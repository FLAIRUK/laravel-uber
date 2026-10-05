<?php

namespace FLAIRUK\Uber\Resources\Business;

use FLAIRUK\Uber\Response;

/**
 * Billing statements (scope business.statements, privileged). There is no list:
 * the statement id arrives in the business_trips.statement_ready webhook.
 *
 * @see https://developer.uber.com/docs/statements/introduction
 */
class Statements extends OrganizationResource
{
    protected array $scopes = ['business.statements'];

    public function find(string $statementId): Response
    {
        return $this->get('v1/business/statements/'.$this->segment($statementId), [
            'organization_uuid' => $this->organizationId(),
        ]);
    }

    protected function headers(): array
    {
        return [];
    }
}
