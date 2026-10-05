<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Menu API (v2): the whole menu in one document, plus single-item updates.
 *
 * @see https://developer.uber.com/docs/eats/references/api/v2/put-eats-stores-storeid-menu
 */
class Menus extends Resource
{
    protected array $scopes = ['eats.store'];

    protected string $api = 'eats';

    public function find(string $storeId, ?string $menuType = null): Response
    {
        return $this->get('v2/eats/stores/'.$this->segment($storeId).'/menus', ['menu_type' => $menuType]);
    }

    /**
     * Replace the store's menu. Sent gzip-compressed, as Uber recommends for large menus.
     * Marking an item alcoholic can't be undone through the API.
     *
     * @param  array<string, mixed>  $menu  ['menus' => [...], 'categories' => [...], 'items' => [...], 'modifier_groups' => [...], 'menu_type'?]
     */
    public function replace(string $storeId, array $menu, bool $gzip = true): Response
    {
        $path = 'v2/eats/stores/'.$this->segment($storeId).'/menus';

        if (! $gzip) {
            return $this->put($path, $menu);
        }

        return $this->send('PUT', $path, [
            'body' => gzencode((string) json_encode($menu, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'headers' => ['Content-Type' => 'application/json', 'Content-Encoding' => 'gzip'],
        ], retry: false);
    }

    /**
     * Change one item: price, availability (suspension_info), product info and so on.
     *
     * @param  array<string, mixed>  $changes  e.g. ['suspension_info' => ['suspension' => ['suspend_until' => 0]]]
     */
    public function updateItem(string $storeId, string $itemId, array $changes): Response
    {
        return $this->post('v2/eats/stores/'.$this->segment($storeId).'/menus/items/'.$this->segment($itemId), $changes);
    }
}
