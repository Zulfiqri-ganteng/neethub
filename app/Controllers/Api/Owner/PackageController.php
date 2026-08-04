<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Repositories\PackageRepository;

class PackageController extends BaseApiController
{
    private PackageRepository $packages;

    public function __construct()
    {
        $this->packages = new PackageRepository();
    }

    public function index()
    {
        [$page, $perPage] = $this->paginationParams();

        $filters = [];
        if ($this->request->getGet('is_active') !== null) {
            $filters['is_active'] = (bool) $this->request->getGet('is_active');
        }

        return $this->paginated($this->packages->paginate($filters, $page, $perPage));
    }

    public function show($id = null)
    {
        $package = $this->packages->find((int) $id);

        if (! $package) {
            return $this->notFound('Paket tidak ditemukan.');
        }

        return $this->ok($package);
    }

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $id   = $this->packages->create($this->onlyAllowedFields($data));

        return $this->created($this->packages->find($id), 'Paket berhasil ditambahkan.');
    }

    public function update($id = null)
    {
        if (! $this->validate($this->rules(true))) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (! $this->packages->update((int) $id, $this->onlyAllowedFields($data))) {
            return $this->notFound('Paket tidak ditemukan.');
        }

        return $this->ok($this->packages->find((int) $id), 'Paket berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        if (! $this->packages->delete((int) $id)) {
            return $this->notFound('Paket tidak ditemukan.');
        }

        return $this->ok(null, 'Paket berhasil dihapus.');
    }

    private function rules(bool $partial = false): array
    {
        $prefix = $partial ? 'permit_empty|' : 'required|';

        return [
            'name'           => $prefix . 'min_length[2]|max_length[100]',
            'speed_download' => $prefix . 'integer|greater_than[0]',
            'speed_upload'   => $prefix . 'integer|greater_than[0]',
            'price'          => $prefix . 'decimal|greater_than[0]',
            'billing_cycle'  => $prefix . 'in_list[weekly,monthly,yearly,custom]',
        ];
    }

    private function onlyAllowedFields(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'name', 'description', 'speed_download', 'speed_upload', 'price', 'billing_cycle',
            'custom_cycle_days', 'fup_quota_gb', 'is_active',
        ]));
    }
}
