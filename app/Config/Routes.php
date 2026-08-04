<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', static fn () => redirect()->to('/login'));
$routes->get('login', 'Web\LoginController::index');
$routes->get('register', 'Web\RegisterController::index');
$routes->get('dashboard', 'Web\DashboardController::index');
$routes->get('platform-billing', 'Web\PlatformBillingController::index');
$routes->get('customers', 'Web\CustomerController::index');
$routes->get('packages', 'Web\PackageController::index');
$routes->get('invoices', 'Web\InvoiceController::index');
$routes->get('payments', 'Web\PaymentController::index');
$routes->get('routers', 'Web\RouterController::index');
$routes->get('broadcast', 'Web\BroadcastController::index');
$routes->get('reports', 'Web\ReportController::index');
$routes->get('settings', 'Web\SettingsController::index');
$routes->get('monitoring', 'Web\MonitoringController::index');

$routes->group('customer', ['namespace' => 'App\Controllers\Web\Customer'], static function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('invoices', 'InvoiceController::index');
    $routes->get('profile', 'ProfileController::index');
});

$routes->group('superadmin', ['namespace' => 'App\Controllers\Web\SuperAdmin'], static function ($routes) {
    $routes->get('tenants', 'TenantController::index');
    $routes->get('tenants/(:num)', 'TenantController::show/$1');
    $routes->get('settings', 'SettingController::index');
});

$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes) {
    $routes->group('auth', function ($routes) {
        $routes->post('register', 'AuthController::register', ['filter' => 'ratelimit:register,10,900']);
        $routes->post('login', 'AuthController::login', ['filter' => 'ratelimit:login,5,900']);
        $routes->post('refresh', 'AuthController::refresh', ['filter' => 'ratelimit:refresh,20,900']);
        $routes->post('logout', 'AuthController::logout', ['filter' => 'jwtauth']);
    });

    // Owner + Admin + Teknisi + Kasir share the /api/owner/* surface; each
    // endpoint narrows further with a 'permission:<slug>' filter so e.g. a
    // Kasir can view customers but can't delete them.
    $routes->group('owner', ['namespace' => 'App\Controllers\Api\Owner', 'filter' => ['jwtauth:owner,admin,teknisi,kasir', 'tenant']], function ($routes) {
        $routes->get('dashboard/stats', 'DashboardController::stats', ['filter' => 'permission:dashboard.view']);

        $routes->get('customers', 'CustomerController::index', ['filter' => 'permission:customers.view']);
        $routes->post('customers', 'CustomerController::create', ['filter' => 'permission:customers.create']);
        $routes->get('customers/(:num)', 'CustomerController::show/$1', ['filter' => 'permission:customers.view']);
        $routes->put('customers/(:num)', 'CustomerController::update/$1', ['filter' => 'permission:customers.update']);
        $routes->delete('customers/(:num)', 'CustomerController::delete/$1', ['filter' => 'permission:customers.delete']);
        $routes->post('customers/(:num)/suspend', 'CustomerController::suspend/$1', ['filter' => 'permission:customers.suspend']);
        $routes->post('customers/(:num)/enable', 'CustomerController::enable/$1', ['filter' => 'permission:customers.suspend']);

        $routes->get('packages', 'PackageController::index', ['filter' => 'permission:packages.view']);
        $routes->post('packages', 'PackageController::create', ['filter' => 'permission:packages.create']);
        $routes->get('packages/(:num)', 'PackageController::show/$1', ['filter' => 'permission:packages.view']);
        $routes->put('packages/(:num)', 'PackageController::update/$1', ['filter' => 'permission:packages.update']);
        $routes->delete('packages/(:num)', 'PackageController::delete/$1', ['filter' => 'permission:packages.delete']);

        $routes->get('invoices', 'InvoiceController::index', ['filter' => 'permission:invoices.view']);
        $routes->post('invoices/generate', 'InvoiceController::generate', ['filter' => 'permission:invoices.create']);
        $routes->get('invoices/(:num)', 'InvoiceController::show/$1', ['filter' => 'permission:invoices.view']);
        $routes->get('invoices/(:num)/qris', 'InvoiceController::qris/$1', ['filter' => 'permission:invoices.view']);
        $routes->get('invoices/(:num)/pdf', 'InvoiceController::pdf/$1', ['filter' => 'permission:invoices.view']);

        $routes->get('routers', 'RouterController::index', ['filter' => 'permission:routers.view']);
        $routes->post('routers', 'RouterController::create', ['filter' => 'permission:routers.create']);
        $routes->get('routers/(:num)', 'RouterController::show/$1', ['filter' => 'permission:routers.view']);
        $routes->put('routers/(:num)', 'RouterController::update/$1', ['filter' => 'permission:routers.update']);
        $routes->delete('routers/(:num)', 'RouterController::delete/$1', ['filter' => 'permission:routers.delete']);
        $routes->post('routers/(:num)/test', 'RouterController::test/$1', ['filter' => 'permission:routers.test']);
        $routes->get('routers/(:num)/online-users', 'RouterController::onlineUsers/$1', ['filter' => 'permission:monitoring.view']);

        $routes->get('monitoring/traffic', 'MonitoringController::traffic', ['filter' => 'permission:monitoring.view']);
        $routes->get('monitoring/users-online', 'MonitoringController::usersOnline', ['filter' => 'permission:monitoring.view']);

        $routes->get('payments', 'PaymentController::index', ['filter' => 'permission:payments.view']);
        $routes->get('payments/(:num)', 'PaymentController::show/$1', ['filter' => 'permission:payments.view']);
        $routes->post('payments/(:num)/verify', 'PaymentController::verify/$1', ['filter' => 'permission:payments.verify']);
        $routes->get('payments/(:num)/proof', 'PaymentController::proof/$1', ['filter' => 'permission:payments.view']);

        $routes->get('settings', 'SettingsController::show', ['filter' => 'permission:settings.manage']);
        $routes->put('settings', 'SettingsController::update', ['filter' => 'permission:settings.manage']);
        $routes->post('settings/logo', 'SettingsController::uploadLogo', ['filter' => 'permission:settings.manage']);
        $routes->delete('settings/logo', 'SettingsController::deleteLogo', ['filter' => 'permission:settings.manage']);

        $routes->get('backup/export', 'BackupController::export', ['filter' => 'permission:settings.manage']);
        $routes->post('backup/restore', 'BackupController::restore', ['filter' => 'permission:settings.manage']);

        $routes->get('reports/revenue', 'ReportController::revenue', ['filter' => 'permission:reports.view']);
        $routes->get('reports/customers', 'ReportController::customers', ['filter' => 'permission:reports.view']);
        $routes->get('reports/payments', 'ReportController::payments', ['filter' => 'permission:reports.view']);
        $routes->get('reports/export', 'ReportController::export', ['filter' => 'permission:reports.view']);

        $routes->post('broadcast/whatsapp', 'BroadcastController::whatsapp', ['filter' => 'permission:broadcast.send']);

        // Stage 13 — what THIS tenant owes the platform (not their own
        // customers). Reuses settings.manage (owner-only, same as bank/QRIS
        // config) rather than a new permission slug.
        $routes->get('platform-billing', 'PlatformBillingController::index', ['filter' => 'permission:settings.manage']);
        $routes->post('platform-billing/invoices/(:num)/pay', 'PlatformBillingController::pay/$1', ['filter' => 'permission:settings.manage']);
    });

    // Stage 13 — the platform operator's own surface. Cross-tenant on
    // purpose (see TenantController's docblock) — no `tenant` filter, only
    // `jwtauth:super_admin` (TenantFilter already no-ops for super_admin
    // even if added, but omitting it here makes the intent obvious).
    $routes->group('superadmin', ['namespace' => 'App\Controllers\Api\SuperAdmin', 'filter' => 'jwtauth:super_admin'], function ($routes) {
        $routes->get('tenants', 'TenantController::index');
        $routes->get('tenants/(:num)', 'TenantController::show/$1');
        $routes->post('tenants/(:num)/suspend', 'TenantController::suspend/$1');
        $routes->post('tenants/(:num)/reactivate', 'TenantController::reactivate/$1');

        $routes->get('platform-payments/pending', 'PlatformPaymentController::pending');
        $routes->get('platform-payments/(:num)/proof', 'PlatformPaymentController::proof/$1');
        $routes->post('platform-payments/(:num)/verify', 'PlatformPaymentController::verify/$1');

        $routes->get('settings', 'PlatformSettingController::show');
        $routes->put('settings', 'PlatformSettingController::update');
    });

    $routes->group('customer', ['namespace' => 'App\Controllers\Api\Customer', 'filter' => ['jwtauth:pelanggan', 'tenant']], function ($routes) {
        $routes->get('dashboard', 'DashboardController::index');

        $routes->get('invoices', 'InvoiceController::index', ['filter' => 'permission:invoices.view']);
        $routes->get('invoices/(:num)', 'InvoiceController::show/$1', ['filter' => 'permission:invoices.view']);
        $routes->get('invoices/(:num)/qris', 'InvoiceController::qris/$1', ['filter' => 'permission:invoices.view']);
        $routes->get('invoices/(:num)/pdf', 'InvoiceController::pdf/$1', ['filter' => 'permission:invoices.view']);
        $routes->post('invoices/(:num)/pay', 'InvoiceController::pay/$1', ['filter' => 'permission:invoices.view']);

        $routes->get('payments', 'PaymentController::index', ['filter' => 'permission:payments.view']);

        $routes->get('profile', 'ProfileController::show', ['filter' => 'permission:profile.manage']);
        $routes->put('profile', 'ProfileController::update', ['filter' => 'permission:profile.manage']);
    });

    // Public — gateways can't carry our Bearer tokens. Trust is verified
    // per-request inside WebhookController against the specific tenant's
    // own gateway credentials, not a shared secret. Deliberately outside
    // jwtauth/tenant/ratelimit — see the controller docblock.
    $routes->post('webhooks/midtrans', 'WebhookController::midtrans');
    $routes->post('webhooks/xendit', 'WebhookController::xendit');
});
