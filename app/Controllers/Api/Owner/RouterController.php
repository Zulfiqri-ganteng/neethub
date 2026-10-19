<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Drivers\RouterDriverFactory;
use App\Repositories\RouterRepository;
use App\Services\RouterService;
use RuntimeException;

class RouterController extends BaseApiController
{
    /**
     * `ssh` is still in the `api_scheme` enum, but MikroTikDriver only
     * speaks the RouterOS API protocol (plain or TLS) — an "ssh" router
     * would silently be dialed as plain API on the SSH port and always
     * fail. Neither UI offers it; this closes the API-level gap too.
     */
    private const API_SCHEME_RULE = [
        'rules'  => 'permit_empty|in_list[api,api-ssl]',
        'errors' => ['in_list' => 'Mode koneksi harus API (port 8728) atau API-SSL (port 8729). SSH belum didukung.'],
    ];

    private RouterRepository $routers;
    private RouterService $routerService;

    public function __construct()
    {
        $this->routers       = new RouterRepository();
        $this->routerService = new RouterService();
    }

    public function index()
    {
        [$page, $perPage] = $this->paginationParams();
        $filters = array_filter(['status' => $this->request->getGet('status')]);

        $result = $this->routers->paginate($filters, $page, $perPage);
        // Never surface the encrypted credential blob in a list response.
        $result['items'] = array_map(fn ($r) => $this->withoutPassword($r), $result['items']);

        return $this->paginated($result);
    }

    public function show($id = null)
    {
        $router = $this->routers->find((int) $id);

        if (! $router) {
            return $this->notFound('Router tidak ditemukan.');
        }

        return $this->ok($this->withoutPassword($router));
    }

    public function create()
    {
        $rules = [
            'name'       => 'required|min_length[2]|max_length[100]',
            'type'       => 'required|in_list[mikrotik,ubiquiti,omada,openwrt,huawei,cisco,ruijie]',
            'ip_address' => 'required|max_length[45]',
            'port'       => 'permit_empty|integer',
            'username'   => 'required|max_length[100]',
            'password'   => 'required|max_length[100]',
            'api_scheme' => self::API_SCHEME_RULE,
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        // The `type` enum still lists every brand we eventually want to
        // support (schema/driver contract are ready for them — see
        // RouterDriverFactory), but only `implementedTypes()` actually work
        // today. Rejecting the rest here, with a clear message, beats
        // letting an owner configure a router that silently fails on every
        // real operation (test connection, auto-provisioning, suspend on
        // nonpayment, monitoring) — those all resolve the driver deep
        // inside RouterService, far from where the owner would see why.
        if (! in_array($data['type'] ?? null, RouterDriverFactory::implementedTypes(), true)) {
            return $this->validationError([
                'type' => "Tipe router '{$data['type']}' belum didukung sepenuhnya saat ini — gunakan MikroTik untuk saat ini. Brand lain akan menyusul.",
            ]);
        }

        $data = array_intersect_key($data, array_flip([
            'name', 'type', 'ip_address', 'port', 'username', 'password', 'api_scheme', 'location', 'notes',
        ]));

        $id = $this->routers->create($data);

        return $this->created($this->withoutPassword($this->routers->find($id)), 'Router berhasil ditambahkan.');
    }

    public function update($id = null)
    {
        $rules = [
            'name'       => 'permit_empty|min_length[2]|max_length[100]',
            'ip_address' => 'permit_empty|max_length[45]',
            'port'       => 'permit_empty|integer',
            'api_scheme' => self::API_SCHEME_RULE,
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $data = array_intersect_key($data, array_flip([
            'name', 'ip_address', 'port', 'username', 'password', 'api_scheme', 'location', 'notes',
        ]));

        if (! $this->routers->update((int) $id, $data)) {
            return $this->notFound('Router tidak ditemukan.');
        }

        return $this->ok($this->withoutPassword($this->routers->find((int) $id)), 'Router berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        if (! $this->routers->delete((int) $id)) {
            return $this->notFound('Router tidak ditemukan.');
        }

        return $this->ok(null, 'Router berhasil dihapus.');
    }

    public function test($id = null)
    {
        try {
            return $this->ok($this->routerService->testConnection((int) $id));
        } catch (RuntimeException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    public function onlineUsers($id = null)
    {
        try {
            return $this->ok($this->routerService->onlineUsers((int) $id));
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    private function withoutPassword(array $router): array
    {
        unset($router['password']);

        return $router;
    }
}
