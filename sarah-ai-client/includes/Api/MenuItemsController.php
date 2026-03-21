<?php

declare(strict_types=1);

namespace SarahAiClient\Api;

use SarahAiClient\Infrastructure\MenuRepository;
use WP_REST_Request;
use WP_REST_Response;

class MenuItemsController
{
    private MenuRepository $menu;

    public function __construct(MenuRepository $menu)
    {
        $this->menu = $menu;
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-client/v1', '/menu-items', [
            ['methods' => 'GET',  'callback' => [$this, 'index'], 'permission_callback' => [$this, 'can']],
            ['methods' => 'POST', 'callback' => [$this, 'store'], 'permission_callback' => [$this, 'can']],
        ]);
        register_rest_route('sarah-ai-client/v1', '/menu-items/(?P<key>[a-z0-9\-]+)/toggle', [
            'methods' => 'POST', 'callback' => [$this, 'toggle'], 'permission_callback' => [$this, 'can'],
        ]);
        register_rest_route('sarah-ai-client/v1', '/menu-items/(?P<key>[a-z0-9\-]+)/move', [
            'methods' => 'POST', 'callback' => [$this, 'move'], 'permission_callback' => [$this, 'can'],
        ]);
        register_rest_route('sarah-ai-client/v1', '/menu-items/(?P<key>[a-z0-9\-]+)', [
            'methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => [$this, 'can'],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): WP_REST_Response
    {
        $parents = $this->menu->allParents();
        $result  = array_map(function (array $parent): array {
            $parent['children'] = $this->menu->childrenOf((string) $parent['item_key']);
            return $parent;
        }, $parents);
        return $this->ok(['parents' => $result]);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $itemKey   = sanitize_key((string) ($request['item_key'] ?? ''));
        $label     = sanitize_text_field((string) ($request['label'] ?? ''));
        $viewKey   = sanitize_key((string) ($request['view_key'] ?? ''));
        $parentKey = isset($request['parent_key']) && $request['parent_key'] !== ''
            ? sanitize_key((string) $request['parent_key'])
            : null;
        if ($itemKey === '' || $label === '' || $viewKey === '') {
            return $this->fail('Missing required fields.');
        }
        if ($parentKey !== null && ! $this->menu->allowsChildren($parentKey)) {
            return $this->fail('This item does not allow children.');
        }
        $this->menu->create($itemKey, $label, $viewKey, true, $parentKey);
        return $this->ok([]);
    }

    public function toggle(WP_REST_Request $request): WP_REST_Response
    {
        $key  = sanitize_key((string) ($request['key'] ?? ''));
        $item = $this->menu->getByKey($key);
        if ($item === null) {
            return $this->fail('Not found.', 404);
        }
        $this->menu->updateStatus($key, (int) $item['is_enabled'] !== 1);
        return $this->ok([]);
    }

    public function move(WP_REST_Request $request): WP_REST_Response
    {
        $key       = sanitize_key((string) ($request['key'] ?? ''));
        $direction = sanitize_key((string) ($request->get_param('direction') ?? ''));
        if ($direction === 'up') {
            $this->menu->moveUp($key);
        } elseif ($direction === 'down') {
            $this->menu->moveDown($key);
        } else {
            return $this->fail('Invalid direction.');
        }
        return $this->ok([]);
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $key = sanitize_key((string) ($request['key'] ?? ''));
        if (! $this->menu->isDeletable($key)) {
            return $this->fail('This item cannot be deleted.');
        }
        $this->menu->deleteChildrenOf($key);
        $this->menu->delete($key);
        return $this->ok([]);
    }

    private function ok(array $data): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true, 'data' => $data, 'message' => ''], 200);
    }

    private function fail(string $message, int $status = 400): WP_REST_Response
    {
        return new WP_REST_Response(['success' => false, 'data' => [], 'message' => $message], $status);
    }
}
