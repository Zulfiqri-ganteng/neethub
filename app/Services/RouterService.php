<?php

namespace App\Services;

use App\Drivers\RouterDriverFactory;
use App\Helpers\CryptoHelper;
use App\Repositories\RouterRepository;
use RuntimeException;
use Throwable;

/**
 * Thin orchestration layer between controllers and RouterDriverFactory:
 * resolves the tenant-scoped router row, calls the right driver, and
 * translates driver exceptions into results controllers can turn into API
 * responses without every caller needing its own try/catch.
 */
class RouterService
{
    public function __construct(private RouterRepository $routers = new RouterRepository())
    {
    }

    /**
     * @return array{router_id: int, status: string, error?: string}
     */
    public function testConnection(int $routerId): array
    {
        $router = $this->routers->find($routerId);
        if (! $router) {
            throw new RuntimeException('Router tidak ditemukan.');
        }

        $result = ['router_id' => $routerId];

        try {
            $ok             = RouterDriverFactory::make($router)->testConnection();
            $result['status'] = $ok ? 'online' : 'offline';
        } catch (Throwable $e) {
            $result['status'] = 'offline';
            $result['error']  = $e->getMessage();
        }

        $this->routers->update($routerId, [
            'status'          => $result['status'],
            'last_checked_at' => date('Y-m-d H:i:s'),
        ]);

        return $result;
    }

    public function onlineUsers(int $routerId): array
    {
        $router = $this->routers->find($routerId);
        if (! $router) {
            throw new RuntimeException('Router tidak ditemukan.');
        }

        return RouterDriverFactory::make($router)->getOnlineUsers();
    }

    public function trafficForCustomer(array $customer): array
    {
        if (empty($customer['router_id']) || empty($customer['pppoe_username'])) {
            throw new RuntimeException('Pelanggan belum terhubung ke router (belum ada PPPoE username).');
        }

        $router = $this->routers->find((int) $customer['router_id']);
        if (! $router) {
            throw new RuntimeException('Router pelanggan tidak ditemukan.');
        }

        return RouterDriverFactory::make($router)->getTraffic($customer['pppoe_username']);
    }

    /**
     * Best-effort: a customer without a router/PPPoE assignment yet, or a
     * momentarily-unreachable router, must never block the owner from
     * changing the customer's status in NetHub itself — it just means the
     * router-side cutoff didn't happen yet. Returns 'success' | 'failed' |
     * 'skipped' so the caller can surface a warning without hard-failing.
     */
    public function suspendCustomer(array $customer): string
    {
        return $this->applyToRouter($customer, 'suspendUser');
    }

    public function enableCustomer(array $customer): string
    {
        return $this->applyToRouter($customer, 'enableUser');
    }

    /**
     * Pushes a new PPPoE secret to the customer's assigned router, using
     * their package's speed as the rate-limit. Same best-effort contract as
     * suspend/enable — a customer can exist in NetHub without a router
     * assignment yet, or the router can be briefly unreachable, and neither
     * should block creating the customer record itself.
     */
    public function addCustomer(array $customer, array $package): string
    {
        if (empty($customer['router_id']) || empty($customer['pppoe_username']) || empty($customer['pppoe_password'])) {
            return 'skipped';
        }

        $router = $this->routers->find((int) $customer['router_id']);
        if (! $router) {
            return 'skipped';
        }

        $password = CryptoHelper::decrypt($customer['pppoe_password']);
        if ($password === null) {
            log_message('error', "Router addUser skipped for customer {$customer['id']}: PPPoE password could not be decrypted.");

            return 'failed';
        }

        try {
            RouterDriverFactory::make($router)->addUser(
                $customer['pppoe_username'],
                $password,
                (int) $package['speed_download'],
                (int) $package['speed_upload']
            );

            return 'success';
        } catch (Throwable $e) {
            log_message('error', "Router addUser failed for customer {$customer['id']}: {$e->getMessage()}");

            return 'failed';
        }
    }

    /**
     * Pushes an updated rate-limit to the router when a customer's package
     * changes. `$package` is null when the customer has no package assigned
     * (nothing to update on the router side in that case).
     */
    public function updateCustomerSpeed(array $customer, ?array $package): string
    {
        if (empty($customer['router_id']) || empty($customer['pppoe_username']) || $package === null) {
            return 'skipped';
        }

        $router = $this->routers->find((int) $customer['router_id']);
        if (! $router) {
            return 'skipped';
        }

        try {
            RouterDriverFactory::make($router)->updateUser(
                $customer['pppoe_username'],
                null,
                (int) $package['speed_download'],
                (int) $package['speed_upload']
            );

            return 'success';
        } catch (Throwable $e) {
            log_message('error', "Router updateUser failed for customer {$customer['id']}: {$e->getMessage()}");

            return 'failed';
        }
    }

    private function applyToRouter(array $customer, string $method): string
    {
        if (empty($customer['router_id']) || empty($customer['pppoe_username'])) {
            return 'skipped';
        }

        $router = $this->routers->find((int) $customer['router_id']);
        if (! $router) {
            return 'skipped';
        }

        try {
            RouterDriverFactory::make($router)->{$method}($customer['pppoe_username']);

            return 'success';
        } catch (Throwable $e) {
            log_message('error', "Router {$method} failed for customer {$customer['id']}: {$e->getMessage()}");

            return 'failed';
        }
    }
}
