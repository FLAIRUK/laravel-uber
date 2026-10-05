<?php

namespace FLAIRUK\Uber\Resources\Business;

use FLAIRUK\Uber\Response;

/**
 * An organisation's employees and groups (scope business.employees, privileged).
 * Employees are keyed by email.
 *
 * @see https://developer.uber.com/docs/employees/introduction
 */
class Employees extends OrganizationResource
{
    protected array $scopes = ['business.employees'];

    /**
     * @param  array<string, mixed>  $employees
     */
    public function create(array $employees): Response
    {
        return $this->post($this->path('employees'), $employees);
    }

    /**
     * @param  array<string, mixed>  $employees  matched by email
     */
    public function update(array $employees): Response
    {
        return $this->post($this->path('employees/update'), $employees);
    }

    public function find(string $email): Response
    {
        return $this->get($this->path('employee'), ['email' => $email]);
    }

    /**
     * Call ->lazy() on the result to walk every page.
     */
    public function list(int $limit = 1000, ?string $startKey = null): Response
    {
        return $this->get($this->path('employees'), ['limit' => $limit, 'start_key' => $startKey])
            ->paginate('employees', function (Response $page) use ($limit) {
                $next = $page->get('next_key') ?? $page->get('start_key');

                return filled($next) ? $this->list($limit, (string) $next) : null;
            });
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function search(array $query): Response
    {
        return $this->get($this->path('employees/search'), $query);
    }

    /**
     * Send invitations (restricted).
     *
     * @param  array<string, mixed>  $invitation
     */
    public function invite(array $invitation): Response
    {
        return $this->post($this->path('employees/invite'), $invitation);
    }

    public function remove(string $email): Response
    {
        return $this->send('DELETE', $this->path('employees'), ['query' => ['email' => $email]], retry: false);
    }

    public function groups(): Response
    {
        return $this->get($this->path('groups'));
    }

    /**
     * Create or update a group. A 206 means some members could not be added.
     *
     * @param  array<string, mixed>  $group
     */
    public function saveGroup(array $group): Response
    {
        return $this->post($this->path('group'), $group);
    }

    public function deleteGroup(string $groupId): Response
    {
        return $this->send('DELETE', $this->path('group'), ['query' => ['group_id' => $groupId]], retry: false);
    }

    protected function path(string $path): string
    {
        return 'v1/business/organizations/'.$this->segment($this->organizationId()).'/'.$path;
    }

    protected function headers(): array
    {
        return [];
    }
}
