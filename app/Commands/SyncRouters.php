<?php

namespace App\Commands;

use App\Drivers\RouterDriverFactory;
use App\Models\CustomerModel;
use App\Models\OnlineUserModel;
use App\Models\RouterModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Meant to run every ~30s via cron/supervisor (`php spark router:sync`) —
 * matches the spec's "router status: cache 30 detik (realtime)" rule. Polls
 * every non-deleted router for its currently-connected PPP sessions and
 * mirrors them into `online_users`, so the Monitoring API can serve a fast
 * DB read instead of a live per-request round trip to every router.
 *
 * If a router can't be reached, its online_users rows are cleared rather
 * than left stale — "we can't confirm anyone is online" should never render
 * as "these people are online".
 */
class SyncRouters extends BaseCommand
{
    protected $group       = 'NetHub';
    protected $name        = 'router:sync';
    protected $description = 'Polls every router for online PPP sessions and updates online_users + router status.';

    public function run(array $params)
    {
        $routerModel = new RouterModel();
        $onlineModel = new OnlineUserModel();
        $customerModel = new CustomerModel();

        $routers = $routerModel->findAll();

        foreach ($routers as $router) {
            try {
                $sessions = RouterDriverFactory::make($router)->getOnlineUsers();
                $this->syncRouterSessions((int) $router['id'], (int) $router['tenant_id'], $sessions, $onlineModel, $customerModel);

                $routerModel->update($router['id'], ['status' => 'online', 'last_checked_at' => date('Y-m-d H:i:s')]);
                CLI::write("Router {$router['name']}: " . count($sessions) . ' online', 'green');
            } catch (Throwable $e) {
                $onlineModel->where('router_id', $router['id'])->delete();
                $routerModel->update($router['id'], ['status' => 'offline', 'last_checked_at' => date('Y-m-d H:i:s')]);
                CLI::write("Router {$router['name']}: unreachable ({$e->getMessage()})", 'red');
            }
        }

        CLI::write('Done syncing ' . count($routers) . ' router(s).', 'yellow');
    }

    /**
     * @param array<int, array<string, string>> $sessions
     */
    private function syncRouterSessions(int $routerId, int $tenantId, array $sessions, OnlineUserModel $onlineModel, CustomerModel $customerModel): void
    {
        $seenUsernames = [];
        $now           = date('Y-m-d H:i:s');

        foreach ($sessions as $session) {
            $username = $session['name'] ?? null;
            if ($username === null) {
                continue;
            }

            $seenUsernames[] = $username;
            $customer        = $customerModel->where('tenant_id', $tenantId)->where('pppoe_username', $username)->first();

            $existing = $onlineModel->where('router_id', $routerId)->where('username', $username)->first();
            $row      = [
                'tenant_id'      => $tenantId,
                'router_id'      => $routerId,
                'customer_id'    => $customer['id'] ?? null,
                'username'       => $username,
                'ip_address'     => $session['address'] ?? null,
                'uptime_seconds' => $this->parseUptime($session['uptime'] ?? ''),
                'last_seen_at'   => $now,
            ];

            if ($existing) {
                $onlineModel->update($existing['id'], $row);
            } else {
                $row['session_start'] = $now;
                $onlineModel->insert($row);
            }
        }

        $builder = $onlineModel->where('router_id', $routerId);
        if ($seenUsernames !== []) {
            $builder->whereNotIn('username', $seenUsernames);
        }
        $builder->delete();
    }

    /**
     * RouterOS reports uptime like "1d2h3m4s" — convert to seconds.
     */
    private function parseUptime(string $uptime): int
    {
        preg_match_all('/(\d+)([wdhms])/', $uptime, $matches, PREG_SET_ORDER);

        $multipliers = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
        $seconds     = 0;

        foreach ($matches as [, $value, $unit]) {
            $seconds += ((int) $value) * $multipliers[$unit];
        }

        return $seconds;
    }
}
