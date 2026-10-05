<?php

namespace FLAIRUK\Uber\Resources\Ads;

use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;

/**
 * Uber Ads: campaigns, ad groups, ads, creatives and reporting for an ad account.
 *
 * Ads uses the authorization code flow on login.uber.com (tokens last 24 hours,
 * refresh tokens don't expire): Uber::withToken($token)->ads($accountId).
 * Bulk writes return per-item results, so a 200 can still carry failures.
 *
 * @see https://developer.uber.com/docs/ads/introduction
 */
class Ads extends Resource
{
    public function __construct(Uber $client, protected ?string $accountId = null)
    {
        parent::__construct($client);
    }

    /**
     * The ad accounts the user can manage (scope ads.ad-accounts.read).
     */
    public function accounts(): Response
    {
        return $this->get('v1/ads/ad-accounts')->paginate('ad_accounts');
    }

    public function stores(?string $pageToken = null, ?int $pageLimit = null): Response
    {
        return $this->paged('stores', [], $pageToken, $pageLimit);
    }

    // Campaigns

    /**
     * @param  list<string>  $campaignIds
     */
    public function campaigns(array $campaignIds = [], ?string $pageToken = null, ?int $pageLimit = null): Response
    {
        return $this->paged('campaigns', ['campaign_id' => $this->ids($campaignIds)], $pageToken, $pageLimit);
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     */
    public function createCampaigns(array $campaigns): Response
    {
        return $this->post($this->path('campaigns'), ['campaigns' => $campaigns]);
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns  each with its campaign_id
     */
    public function updateCampaigns(array $campaigns): Response
    {
        return $this->patch($this->path('campaigns'), ['campaigns' => $campaigns]);
    }

    /**
     * @param  list<string>  $campaignIds
     */
    public function archiveCampaigns(array $campaignIds): Response
    {
        return $this->post($this->path('campaigns/archive'), ['campaign_ids' => array_values($campaignIds)]);
    }

    // Ad groups

    /**
     * @param  list<string>  $adGroupIds
     */
    public function adGroups(string $campaignId, array $adGroupIds = [], ?string $pageToken = null, ?int $pageLimit = null): Response
    {
        return $this->paged('campaigns/'.$this->segment($campaignId).'/ad-groups', ['ad_group_id' => $this->ids($adGroupIds)], $pageToken, $pageLimit);
    }

    /**
     * @param  list<array<string, mixed>>  $adGroups
     */
    public function createAdGroups(string $campaignId, array $adGroups): Response
    {
        return $this->post($this->path('campaigns/'.$this->segment($campaignId).'/ad-groups'), ['ad_groups' => $adGroups]);
    }

    /**
     * @param  list<array<string, mixed>>  $adGroups
     */
    public function updateAdGroups(string $campaignId, array $adGroups): Response
    {
        return $this->patch($this->path('campaigns/'.$this->segment($campaignId).'/ad-groups'), ['ad_groups' => $adGroups]);
    }

    // Ads

    /**
     * @param  list<string>  $adIds
     */
    public function ads(string $campaignId, string $adGroupId, array $adIds = [], ?string $pageToken = null, ?int $pageLimit = null): Response
    {
        return $this->paged($this->adsPath($campaignId, $adGroupId), ['ad_id' => $this->ids($adIds)], $pageToken, $pageLimit);
    }

    /**
     * @param  list<array<string, mixed>>  $ads
     */
    public function createAds(string $campaignId, string $adGroupId, array $ads): Response
    {
        return $this->post($this->path($this->adsPath($campaignId, $adGroupId)), ['ads' => $ads]);
    }

    /**
     * @param  list<array<string, mixed>>  $ads
     */
    public function updateAds(string $campaignId, string $adGroupId, array $ads): Response
    {
        return $this->patch($this->path($this->adsPath($campaignId, $adGroupId)), ['ads' => $ads]);
    }

    // Creative (beta)

    /**
     * @param  list<string>  $assetIds
     */
    public function assets(array $assetIds = [], ?string $pageToken = null, ?int $pageLimit = null): Response
    {
        return $this->paged('assets', ['asset_id' => $this->ids($assetIds)], $pageToken, $pageLimit);
    }

    public function createAsset(string $name, string $fileUrl): Response
    {
        return $this->post($this->path('assets'), ['name' => $name, 'file_url' => $fileUrl]);
    }

    /**
     * @param  list<string>  $creativeIds
     */
    public function creatives(array $creativeIds = [], ?string $pageToken = null, ?int $pageLimit = null): Response
    {
        return $this->paged('creatives', ['creative_id' => $this->ids($creativeIds)], $pageToken, $pageLimit);
    }

    /**
     * @param  list<array<string, mixed>>  $creatives
     */
    public function createCreatives(array $creatives): Response
    {
        return $this->post($this->path('creatives'), ['creatives' => $creatives]);
    }

    /**
     * Products you can advertise. Limited to 100 requests a day.
     */
    public function products(?string $pageToken = null, ?int $pageLimit = null): Response
    {
        return $this->paged('products', [], $pageToken, $pageLimit);
    }

    // Reporting

    /**
     * Start an asynchronous report; poll report() for its status and report_url.
     *
     * @param  array<string, mixed>  $report  ['report_type', 'time_range', 'columns', 'time_unit', 'file_format', 'filters'?, 'include_headers'?]
     */
    public function createReport(array $report): Response
    {
        return $this->post($this->path('reporting/report'), $report);
    }

    public function report(string $reportId): Response
    {
        return $this->get($this->path('reporting/'.$this->segment($reportId)));
    }

    /**
     * A small report, returned in the response (scope ads.sync.reporting).
     *
     * @param  array<string, mixed>  $report  ['report_type', 'time_range', 'columns', 'time_unit', 'filters'?]
     */
    public function syncReport(array $report): Response
    {
        return $this->post($this->path('reporting/sync'), $report, retry: true);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function paged(string $path, array $query, ?string $pageToken, ?int $pageLimit): Response
    {
        $items = str_contains($path, '/') ? basename(str_replace('-', '_', $path)) : str_replace('-', '_', $path);

        return $this->get($this->path($path), $query + ['page_token' => $pageToken, 'page_limit' => $pageLimit])
            ->paginate($items, function (Response $page) use ($path, $query, $pageLimit) {
                $next = $page->get('next_page_token');
                $next = is_array($next) ? ($next['value'] ?? null) : $next;   // documented as {"value": "..."}

                return filled($next) ? $this->paged($path, $query, (string) $next, $pageLimit) : null;
            });
    }

    protected function adsPath(string $campaignId, string $adGroupId): string
    {
        return 'campaigns/'.$this->segment($campaignId).'/ad-groups/'.$this->segment($adGroupId).'/ads';
    }

    protected function path(string $path): string
    {
        $account = filled($this->accountId)
            ? $this->accountId
            : throw new ConfigurationException('No Uber Ads account: pass it to Uber::ads($accountId) or set UBER_ADS_ACCOUNT_ID.');

        return 'v1/ads/'.$this->segment($account).'/'.$path;
    }

    /**
     * @param  list<string>  $ids
     */
    protected function ids(array $ids): ?string
    {
        return $ids === [] ? null : implode(',', $ids);
    }
}
