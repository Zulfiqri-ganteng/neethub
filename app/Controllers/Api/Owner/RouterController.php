<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Repositories\RouterRepository;
use App\Services\RouterService;
use RuntimeException;

class RouterController extends BaseApiController
{
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
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
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
