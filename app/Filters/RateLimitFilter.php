<?php

namespace App\Filters;

use App\Helpers\ResponseHelper;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Fixed-window rate limiter keyed by client IP + a label (so /login and the
 * general API cap use separate buckets). Route filter arg format:
 * 'ratelimit:label,maxRequests,windowSeconds', e.g. 'ratelimit:login,5,900'.
 */
class RateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        [$label, $max, $window] = array_pad($arguments ?? [], 3, null);
        $max    = (int) ($max ?? 100);
        $window = (int) ($window ?? 60);
        $label  = $label ?? 'default';

        $cache = Services::cache();
        $key   = 'throttle_' . $label . '_' . md5($request->getIPAddress());

        // Rolling window: each hit re-saves with a fresh TTL, so the block
        // only lifts once $window seconds pass with no further attempts.
        $current = (int) $cache->get($key) + 1;
        $cache->save($key, $current, $window);

        if ($current > $max) {
            return service('response')
                ->setStatusCode(429)
                ->setJSON(ResponseHelper::error('Terlalu banyak percobaan. Coba lagi nanti.'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
