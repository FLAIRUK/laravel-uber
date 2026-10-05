<?php

namespace FLAIRUK\Uber\Resources\Identity;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;

/**
 * Identity organisation management: the units, users and roles of a root organisation.
 * Scopes identity.organizations, identity.employees and identity.roleassignments.
 *
 * @see https://developer.uber.com/docs/identity/introduction
 */
class Organization extends Resource
{
    public function __construct(Uber $client, protected string $organizationId)
    {
        parent::__construct($client);
    }

    // Org units

    /**
     * @param  array<string, mixed>  $unit  ['orgUnitInfo' => ['name', 'hierarchyInfo' => ['parentOrganizationId']]]
     */
    public function createUnit(array $unit): Response
    {
        return $this->post($this->path('orgunits'), ['organization' => $unit], ['identity.organizations']);
    }

    public function units(?int $limit = null, ?string $pageToken = null): Response
    {
        return $this->get($this->path('orgunits'), ['limit' => $limit, 'pageToken' => $pageToken], ['identity.organizations'])
            ->paginate('organizations', function (Response $page) use ($limit, $pageToken) {
                $next = $page->get('pageToken');

                return filled($next) && $next !== $pageToken ? $this->units($limit, (string) $next) : null;
            });
    }

    public function unit(string $unitId): Response
    {
        return $this->get($this->path('orgunits/'.$this->segment($unitId)), scopes: ['identity.organizations']);
    }

    /**
     * The parent can't be changed: delete the unit and create it again.
     *
     * @param  array<string, mixed>  $unit
     */
    public function updateUnit(string $unitId, array $unit): Response
    {
        return $this->put($this->path('orgunits/'.$this->segment($unitId)), ['organization' => $unit], ['identity.organizations']);
    }

    /**
     * Fails with 412 while the unit has child units or users.
     */
    public function deleteUnit(string $unitId): Response
    {
        return $this->delete($this->path('orgunits/'.$this->segment($unitId)), scopes: ['identity.organizations']);
    }

    // Org users

    /**
     * @param  array<string, mixed>  $user  ['firstName', 'lastName', 'email', 'phoneNumber', 'externalId', 'organizationId']
     */
    public function createUser(array $user): Response
    {
        return $this->post($this->path('orgusers'), ['orguser' => $user], ['identity.employees']);
    }

    public function users(?int $limit = null, ?string $pageToken = null, ?string $criteria = null): Response
    {
        return $this->get($this->path('orgusers'), ['limit' => $limit, 'pageToken' => $pageToken, 'criteria' => $criteria], ['identity.employees'])
            ->paginate('orgusers', function (Response $page) use ($limit, $pageToken, $criteria) {
                $next = $page->get('pageToken');

                return filled($next) && $next !== $pageToken ? $this->users($limit, (string) $next, $criteria) : null;
            });
    }

    public function user(string $userId): Response
    {
        return $this->get($this->path('orguser/'.$this->segment($userId)), scopes: ['identity.employees']);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function updateUser(string $userId, array $user): Response
    {
        return $this->put($this->path('orguser/'.$this->segment($userId)), ['orguser' => $user], ['identity.employees']);
    }

    public function deleteUser(string $userId): Response
    {
        return $this->delete($this->path('orguser/'.$this->segment($userId)), scopes: ['identity.employees']);
    }

    // Role assignments

    /**
     * @param  array<string, mixed>  $assignment  ['entityId' => org user id, 'roleName', 'scopeId', 'domain']
     */
    public function assignRole(array $assignment): Response
    {
        return $this->post($this->path('roleassignments'), ['roleassignment' => $assignment], ['identity.roleassignments']);
    }

    public function roleAssignments(?string $userId = null, ?string $scope = null): Response
    {
        return $this->get($this->path('roleassignments'), ['orguser' => $userId, 'scope' => $scope], ['identity.roleassignments'])
            ->paginate('roleassignments');
    }

    /**
     * Uber documents this path as /orguser/{roleassignment_id}; it is sent as documented.
     */
    public function removeRoleAssignment(string $assignmentId): Response
    {
        return $this->delete($this->path('orguser/'.$this->segment($assignmentId)), scopes: ['identity.roleassignments']);
    }

    protected function path(string $path): string
    {
        return 'v1/identity/organizations/'.$this->segment($this->organizationId).'/'.$path;
    }
}
