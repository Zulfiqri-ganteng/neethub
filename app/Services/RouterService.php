<?php

namespace App\Services;

use App\Drivers\RouterDriverFactory;
use App\Helpers\CryptoHelper;
use App\Models\PackageModel;
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

    /**
     * Upserts rather than a bare enable, so activating a customer whose
     * secret never reached the router (router offline at create time) is
     * also the owner's natural retry: the secret gets created now instead of
     * the enable failing with "not found".
     */
    public function enableCustomer(array $customer): string
    {
        $package = ! empty($customer['package_id']) ? (new PackageModel())->find($customer['package_id']) : null;

        if ($package === null || empty($customer['pppoe_password'])) {
            return $this->applyToRouter($customer, 'enableUser');
        }

        return $this->pushCustomer([...$customer, 'status' => 'active'], $package);
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
        return $this->pushCustomer($customer, $package);
    }

    /**
     * Called after a customer edit with the row as it was before and after.
     * Covers everything the old package-only sync missed: router/PPPoE
     * assigned for the first time via edit, PPPoE username or password
     * changed, moved to a different router, or unlinked from the router.
     * Pushes the full desired state (upsert), so it also self-heals a secret
     * that never reached the router because it was offline at create time.
     *
     * @param bool $passwordChanged whether the edit payload carried a new
     *                              pppoe_password (the stored value is
     *                              ciphertext, so before/after can't be
     *                              compared directly)
     */
    public function syncCustomerChange(array $before, array $after, ?array $package, bool $passwordChanged): string
    {
        $wasLinked = $this->isLinked($before);
        $isLinked  = $this->isLinked($after);

        $identityChanged = (int) ($before['router_id'] ?? 0) !== (int) ($after['router_id'] ?? 0)
            || ($before['pppoe_username'] ?? '') !== ($after['pppoe_username'] ?? '');

        $packageChanged = (int) ($before['package_id'] ?? 0) !== (int) ($after['package_id'] ?? 0);

        if (! $wasLinked && ! $isLinked) {
            return 'skipped';
        }

        if ($wasLinked && $isLinked && ! $identityChanged && ! $packageChanged && ! $passwordChanged) {
            return 'skipped';
        }

        $results = [];

        // Old secret has to go when the customer moved router, got a new
        // username, or was unlinked — otherwise it lingers on the router as
        // a working login nobody in NetHub knows about anymore.
        if ($wasLinked && ($identityChanged || ! $isLinked)) {
            $results[] = $this->removeCustomer($before);
        }

        if ($isLinked) {
            $results[] = $this->pushCustomer($after, $package);
        }

        return $this->combine($results);
    }

    /**
     * Removes the customer's PPPoE secret (and live session) from their
     * router — used on customer delete, so a deleted customer can't keep
     * using the internet for free.
     */
    public function removeCustomer(array $customer): string
    {
        if (! $this->isLinked($customer)) {
            return 'skipped';
        }

        $router = $this->routers->find((int) $customer['router_id']);
        if (! $router) {
            return 'skipped';
        }

        try {
            RouterDriverFactory::make($router)->removeUser($customer['pppoe_username']);

            return 'success';
        } catch (Throwable $e) {
            log_message('error', "Router removeUser failed for customer {$customer['id']}: {$e->getMessage()}");

            return 'failed';
        }
    }

    /**
     * Upserts the customer's secret with their full current state: stored
     * password, package speed, and disabled when NetHub has them
     * suspended/disabled — so re-pushing never accidentally re-enables a
     * suspended customer.
     */
    private function pushCustomer(array $customer, ?array $package): string
    {
        if (! $this->isLinked($customer) || empty($customer['pppoe_password']) || $package === null) {
            return 'skipped';
        }

        $router = $this->routers->find((int) $customer['router_id']);
        if (! $router) {
            return 'skipped';
        }

        $password = CryptoHelper::decrypt($customer['pppoe_password']);
        if ($password === null) {
            log_message('error', "Router upsertUser skipped for customer {$customer['id']}: PPPoE password could not be decrypted.");

            return 'failed';
        }

        try {
            RouterDriverFactory::make($router)->upsertUser(
                $customer['pppoe_username'],
                $password,
                (int) $package['speed_download'],
                (int) $package['speed_upload'],
                in_array($customer['status'] ?? '', ['suspended', 'disabled'], true)
            );

            return 'success';
        } catch (Throwable $e) {
            log_message('error', "Router upsertUser failed for customer {$customer['id']}: {$e->getMessage()}");

            return 'failed';
        }
    }

    private function isLinked(array $customer): bool
    {
        return ! empty($customer['router_id']) && ! empty($customer['pppoe_username']);
    }

    /**
     * @param string[] $results
     */
    private function combine(array $results): string
    {
        if (in_array('failed', $results, true)) {
            return 'failed';
        }

        return in_array('success', $results, true) ? 'success' : 'skipped';
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
