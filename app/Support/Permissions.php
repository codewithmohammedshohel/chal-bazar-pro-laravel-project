<?php

namespace App\Support;

/**
 * Central RBAC catalog.
 *
 * Permission keys are preserved from the legacy React application so that
 * feature flags can be reconciled one-to-one during the migration. The
 * `catalog.*` keys are Laravel-native additions for the admin catalog screens.
 */
final class Permissions
{
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_CUSTOMER = 'customer';

    /** @var list<string> */
    public const ROLES = [
        self::ROLE_SUPERADMIN,
        self::ROLE_ADMIN,
        self::ROLE_STAFF,
        self::ROLE_CUSTOMER,
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_BLOCKED = 'blocked';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_BLOCKED,
    ];

    /**
     * Every feature the shop understands.
     *
     * @var array<string, array{label: string, group: string}>
     */
    public const CATALOG = [
        'dashboard' => ['label' => 'Dashboard Overview', 'group' => 'General'],
        'reports' => ['label' => 'Sales Reports', 'group' => 'General'],
        'orders' => ['label' => 'Orders & Processing', 'group' => 'Store'],
        'invoices' => ['label' => 'Invoice & WhatsApp Send', 'group' => 'Store'],
        'wholesale' => ['label' => 'Wholesale RFQs', 'group' => 'Store'],
        'customers' => ['label' => 'Customer Ledgers', 'group' => 'Store'],
        'ledgers' => ['label' => 'Ledger & Invoices', 'group' => 'Store'],
        'pos' => ['label' => 'POS Counter & Billing', 'group' => 'Store'],
        'inventory' => ['label' => 'Rice Inventory', 'group' => 'Store'],
        'catalog.categories' => ['label' => 'Catalog Categories', 'group' => 'Store'],
        'catalog.products' => ['label' => 'Catalog Products', 'group' => 'Store'],
        'offers' => ['label' => 'Special Offers', 'group' => 'Store'],
        'market_ticker' => ['label' => 'Market Rate Ticker', 'group' => 'Store'],
        'reviews' => ['label' => 'Customer Reviews', 'group' => 'Store'],
        'attendance' => ['label' => 'Attendance', 'group' => 'Team'],
        'payroll' => ['label' => 'Payroll', 'group' => 'Team'],
        'users' => ['label' => 'User Accounts', 'group' => 'Superadmin'],
        'roles' => ['label' => 'Roles & Permissions', 'group' => 'Superadmin'],
        'audit_logs' => ['label' => 'Audit Logs', 'group' => 'Superadmin'],
        'settings' => ['label' => 'Store Settings', 'group' => 'Superadmin'],
        'seo' => ['label' => 'SEO Management', 'group' => 'Superadmin'],
        'customer_orders' => ['label' => 'Customer: My Orders', 'group' => 'Customer Portal'],
        'customer_rfq' => ['label' => 'Customer: Wholesale RFQ', 'group' => 'Customer Portal'],
        'customer_ledger' => ['label' => 'Customer: Ledger & Invoices', 'group' => 'Customer Portal'],
        'customer_profile' => ['label' => 'Customer: Profile', 'group' => 'Customer Portal'],
    ];

    /**
     * Role defaults imported from the legacy React application.
     *
     * Superadmin is handled by an explicit bypass, so its list is empty here.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_DEFAULTS = [
        self::ROLE_SUPERADMIN => [],
        self::ROLE_ADMIN => [
            'dashboard',
            'pos',
            'inventory',
            'catalog.categories',
            'catalog.products',
            'orders',
            'wholesale',
            'ledgers',
            'customers',
            'attendance',
            'payroll',
            'reports',
            'settings',
            'seo',
            'offers',
            'market_ticker',
            'reviews',
            'invoices',
        ],
        self::ROLE_STAFF => [
            'pos',
            'orders',
            'attendance',
            'payroll',
        ],
        self::ROLE_CUSTOMER => [
            'dashboard',
            'customer_orders',
            'customer_rfq',
            'customer_ledger',
            'customer_profile',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::CATALOG);
    }

    /**
     * @return array<string, array{label: string, group: string}>
     */
    public static function catalog(): array
    {
        return self::CATALOG;
    }

    public static function isValid(string $permission): bool
    {
        return array_key_exists($permission, self::CATALOG);
    }

    public static function isValidRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    /**
     * @return list<string>
     */
    public static function roleDefaults(string $role): array
    {
        return self::ROLE_DEFAULTS[$role] ?? [];
    }

    /**
     * Grouped catalog for future admin screens.
     *
     * @return array<string, array<string, array{label: string, group: string}>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::CATALOG as $key => $meta) {
            $groups[$meta['group']][$key] = $meta;
        }

        return $groups;
    }
}
