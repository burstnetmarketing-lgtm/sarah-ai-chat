<?php

declare(strict_types=1);

namespace SarahAiClient\Infrastructure;

use SarahAiClient\DB\MenuTable;

class MenuRepository
{
    public function ensureCoreItems(): void
    {
        $this->insertIfMissing('dashboard',       'Dashboard',       'dashboard',       null, false, false);
        $this->insertIfMissing('knowledge-base',  'Knowledge Base',  'knowledge-base',  null, false, false);
        $this->insertIfMissing('quick-questions', 'Quick Questions', 'quick-questions', null, false, false);
        $this->insertIfMissing('appearance',      'Appearance',      'appearance',      null, false, false);
        $this->insertIfMissing('settings',        'Settings',        'settings',        null, false, false);
        $this->insertIfMissing('public-api',      'PHP API',         'public-api',      null, false, false);
    }

    public function seedDefaults(): void
    {
        $this->ensureCoreItems();
    }

    public function allEnabled(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $rows  = $wpdb->get_results(
            "SELECT item_key, label, view_key FROM {$table} WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function allParents(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE parent_key IS NULL ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function childrenOf(string $parentKey): array
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE parent_key = %s ORDER BY sort_order ASC, id ASC",
                $parentKey
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function all(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function getByKey(string $itemKey): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE item_key = %s", $itemKey),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Inserts item and sets sort_order = auto-increment ID (guarantees uniqueness). */
    public function create(string $itemKey, string $label, string $viewKey, bool $enabled, ?string $parentKey = null): void
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'parent_key'     => $parentKey,
            'item_key'       => $itemKey,
            'label'          => $label,
            'view_key'       => $viewKey,
            'sort_order'     => 0,
            'is_enabled'     => $enabled ? 1 : 0,
            'is_deletable'   => 1,
            'allow_children' => 1,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $newId = (int) $wpdb->insert_id;
        if ($newId > 0) {
            $wpdb->update($table, ['sort_order' => $newId], ['id' => $newId]);
        }
    }

    public function moveUp(string $itemKey): void
    {
        $item = $this->getByKey($itemKey);
        if ($item === null) {
            return;
        }
        $prev = $this->adjacentSibling($item, 'prev');
        if ($prev === null) {
            return;
        }
        $this->swapOrder($itemKey, (int) $item['sort_order'], (string) $prev['item_key'], (int) $prev['sort_order']);
    }

    public function moveDown(string $itemKey): void
    {
        $item = $this->getByKey($itemKey);
        if ($item === null) {
            return;
        }
        $next = $this->adjacentSibling($item, 'next');
        if ($next === null) {
            return;
        }
        $this->swapOrder($itemKey, (int) $item['sort_order'], (string) $next['item_key'], (int) $next['sort_order']);
    }

    public function isDeletable(string $itemKey): bool
    {
        $item = $this->getByKey($itemKey);
        return $item !== null && (int) $item['is_deletable'] === 1;
    }

    public function allowsChildren(string $itemKey): bool
    {
        $item = $this->getByKey($itemKey);
        return $item !== null && (int) $item['allow_children'] === 1;
    }

    public function delete(string $itemKey): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . MenuTable::TABLE, ['item_key' => $itemKey], ['%s']);
    }

    private function removeIfExists(string $itemKey): void
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $wpdb->delete($table, ['item_key' => $itemKey], ['%s']);
        $wpdb->delete($table, ['parent_key' => $itemKey], ['%s']);
    }

    public function deleteChildrenOf(string $parentKey): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . MenuTable::TABLE, ['parent_key' => $parentKey], ['%s']);
    }

    public function updateStatus(string $itemKey, bool $enabled): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . MenuTable::TABLE,
            ['is_enabled' => $enabled ? 1 : 0, 'updated_at' => current_time('mysql')],
            ['item_key'   => $itemKey],
            ['%d', '%s'],
            ['%s']
        );
    }

    private function adjacentSibling(array $item, string $direction): ?array
    {
        global $wpdb;
        $table        = $wpdb->prefix . MenuTable::TABLE;
        $currentOrder = (int) $item['sort_order'];
        $parentKey    = $item['parent_key'];
        $op           = $direction === 'prev' ? '<' : '>';
        $sort         = $direction === 'prev' ? 'DESC' : 'ASC';

        if ($parentKey !== null) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE parent_key = %s AND sort_order {$op} %d ORDER BY sort_order {$sort} LIMIT 1",
                $parentKey,
                $currentOrder
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE parent_key IS NULL AND sort_order {$op} %d ORDER BY sort_order {$sort} LIMIT 1",
                $currentOrder
            );
        }

        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    private function swapOrder(string $keyA, int $orderA, string $keyB, int $orderB): void
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->update($table, ['sort_order' => $orderB, 'updated_at' => $now], ['item_key' => $keyA]);
        $wpdb->update($table, ['sort_order' => $orderA, 'updated_at' => $now], ['item_key' => $keyB]);
    }

    private function fixParentKey(string $itemKey, string $parentKey): void
    {
        global $wpdb;
        $table = $wpdb->prefix . MenuTable::TABLE;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET parent_key = %s WHERE item_key = %s AND parent_key IS NULL",
            $parentKey,
            $itemKey
        ));
    }

    private function insertIfMissing(string $itemKey, string $label, string $viewKey, ?string $parentKey, bool $isDeletable = true, bool $allowChildren = true): void
    {
        global $wpdb;
        $table  = $wpdb->prefix . MenuTable::TABLE;
        $exists = (string) $wpdb->get_var($wpdb->prepare("SELECT item_key FROM {$table} WHERE item_key = %s", $itemKey));
        if ($exists !== '') {
            $wpdb->update($table, ['is_deletable' => $isDeletable ? 1 : 0, 'allow_children' => $allowChildren ? 1 : 0], ['item_key' => $itemKey]);
            return;
        }
        $now = current_time('mysql');
        $wpdb->insert($table, [
            'parent_key'     => $parentKey,
            'item_key'       => $itemKey,
            'label'          => $label,
            'view_key'       => $viewKey,
            'sort_order'     => 0,
            'is_enabled'     => 1,
            'is_deletable'   => $isDeletable ? 1 : 0,
            'allow_children' => $allowChildren ? 1 : 0,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $newId = (int) $wpdb->insert_id;
        if ($newId > 0) {
            $wpdb->update($table, ['sort_order' => $newId], ['id' => $newId]);
        }
    }
}
