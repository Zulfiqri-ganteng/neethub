<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\TenantHelper;
use App\Models\ActivityModel;
use App\Models\CustomerModel;
use App\Models\NotificationModel;
use App\Models\PackageModel;
use App\Models\RoleModel;
use App\Models\TenantModel;
use App\Models\UserModel;
use App\Repositories\CustomerRepository;
use App\Services\RouterService;

class CustomerController extends BaseApiController
{
    private CustomerRepository $customers;
    private RouterService $routerService;

    public function __construct()
    {
        $this->customers     = new CustomerRepository();
        $this->routerService = new RouterService();
    }

    public function index()
    {
        [$page, $perPage] = $this->paginationParams();

        $filters = array_filter([
            'status'     => $this->request->getGet('status'),
            'package_id' => $this->request->getGet('package_id'),
            'search'     => $this->request->getGet('search'),
        ]);

        return $this->paginated($this->customers->paginate($filters, $page, $perPage));
    }

    public function show($id = null)
    {
        $customer = $this->customers->find((int) $id);

        if (! $customer) {
            return $this->notFound('Pelanggan tidak ditemukan.');
        }

        return $this->ok($customer);
    }

    public function create()
    {
        $rules = [
            'name'           => 'required|min_length[2]|max_length[150]',
            'phone'          => 'permit_empty|max_length[20]',
            'email'          => 'permit_empty|valid_email',
            'address'        => 'permit_empty',
            'package_id'     => 'permit_empty|integer',
            'router_id'      => 'permit_empty|integer',
            'pppoe_username' => 'permit_empty|max_length[100]',
            'pppoe_password' => 'permit_empty|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $data = array_intersect_key($data, array_flip([
            'name', 'phone', 'email', 'address', 'whatsapp', 'package_id', 'router_id',
            'pppoe_username', 'pppoe_password', 'installation_date', 'notes',
        ]));
        $data['status'] = 'pending';

        $id = $this->customers->create($data);
        $customer = $this->customers->find($id);

        (new ActivityModel())->log(
            'customer_created',
            TenantHelper::id(),
            null,
            "Pelanggan baru: {$data['name']}"
        );

        // Best-effort, same contract as suspend/enable: push the PPPoE
        // secret to the router now if we have everything needed for it,
        // but never let a router/network hiccup block creating the record.
        $routerSync = 'skipped';
        if (! empty($customer['package_id'])) {
            $package = (new PackageModel())->find($customer['package_id']);
            if ($package) {
                $routerSync = $this->routerService->addCustomer($customer, $package);
            }
        }

        // Same best-effort contract as router sync above: a pelanggan login
        // is a nice-to-have on top of an already-valid customer record, not
        // a precondition for one. See Stage 13 in this repo's CLAUDE.md.
        $portalAccount = $this->provisionPortalAccount($customer, TenantHelper::requireTenantId());
        if ($portalAccount['status'] === 'created') {
            $customer = $this->customers->find($id);
        }

        return $this->created(
            [
                ...$customer,
                'router_sync'     => $routerSync,
                'portal_account'  => $portalAccount['status'],
                'portal_password' => $portalAccount['password'],
            ],
            'Pelanggan berhasil ditambahkan.'
        );
    }

    /**
     * Downloadable CSV starting point for import() below — deliberately a
     * fixed, narrow column set (no router/PPPoE) since data migrated from a
     * previous system never has NetHub's own router IDs; those get assigned
     * per-customer after import, same as any other manually-added customer.
     */
    public function importTemplate()
    {
        $headers = ['name', 'phone', 'whatsapp', 'email', 'address', 'package_name', 'installation_date', 'notes'];
        $example = [
            'Budi Santoso', '081234567890', '081234567890', 'budi@email.com',
            'Jl. Merdeka No. 1', 'Paket 10 Mbps', '2026-01-15', 'Contoh baris - hapus sebelum upload',
        ];

        $buffer = fopen('php://temp', 'w+');
        fputcsv($buffer, $headers);
        fputcsv($buffer, $example);
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return $this->response->download('template_import_pelanggan.csv', $csv);
    }

    /**
     * Bulk-creates customers from a CSV file matching importTemplate()'s
     * column set. Never fails the whole batch on one bad row — every row is
     * validated and reported independently (created/skipped/failed with a
     * reason), same "best-effort, always tell the truth" contract as
     * router_sync/portal_account on the single-create path above. No
     * router/PPPoE assignment or router sync happens here by design (see
     * importTemplate()'s docblock) — only the portal-account
     * auto-provisioning from create() is reused, for consistency with
     * adding customers one at a time.
     */
    public function import()
    {
        $file = $this->request->getFile('customers_file');
        if (! $file || ! $file->isValid()) {
            return $this->fail('File CSV wajib diunggah.', 422);
        }

        $content = file_get_contents($file->getTempName());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content ?: '');

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream);
        if ($header === false) {
            fclose($stream);

            return $this->fail('File CSV kosong.', 422);
        }

        $colMap = [];
        foreach ($header as $i => $col) {
            $colMap[strtolower(trim((string) $col))] = $i;
        }

        if (! isset($colMap['name'])) {
            fclose($stream);

            return $this->fail('Kolom "name" wajib ada di file CSV. Gunakan template yang disediakan.', 422);
        }

        $tenantId = TenantHelper::requireTenantId();
        $packagesByName = [];
        foreach ((new PackageModel())->where('tenant_id', $tenantId)->findAll() as $package) {
            $packagesByName[strtolower(trim($package['name']))] = $package['id'];
        }

        $get = static function (array $row, array $colMap, string $key) {
            $i = $colMap[$key] ?? null;

            return $i !== null && isset($row[$i]) ? trim((string) $row[$i]) : '';
        };

        $results     = [];
        $createdCnt  = 0;
        $skippedCnt  = 0;
        $failedCnt   = 0;
        $rowNumber   = 1; // header is row 1

        while (($row = fgetcsv($stream)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // fully blank row, e.g. trailing newline
            }

            $name = $get($row, $colMap, 'name');
            if ($name === '') {
                $results[] = ['row' => $rowNumber, 'name' => '-', 'status' => 'failed', 'reason' => 'Nama wajib diisi.'];
                $failedCnt++;
                continue;
            }

            $email = $get($row, $colMap, 'email') ?: null;
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[] = ['row' => $rowNumber, 'name' => $name, 'status' => 'failed', 'reason' => 'Format email tidak valid.'];
                $failedCnt++;
                continue;
            }

            $phone = $get($row, $colMap, 'phone') ?: null;

            if ($email || $phone) {
                $dupQuery = (new CustomerModel())->where('tenant_id', $tenantId)->groupStart();
                if ($email) {
                    $dupQuery = $dupQuery->orWhere('email', $email);
                }
                if ($phone) {
                    $dupQuery = $dupQuery->orWhere('phone', $phone);
                }
                $existing = $dupQuery->groupEnd()->first();
                if ($existing) {
                    $results[] = ['row' => $rowNumber, 'name' => $name, 'status' => 'skipped', 'reason' => 'Pelanggan dengan email/telepon ini sudah ada.'];
                    $skippedCnt++;
                    continue;
                }
            }

            $packageName = $get($row, $colMap, 'package_name');
            $packageId   = null;
            if ($packageName !== '') {
                $key = strtolower($packageName);
                if (! isset($packagesByName[$key])) {
                    $results[] = ['row' => $rowNumber, 'name' => $name, 'status' => 'failed', 'reason' => "Paket \"{$packageName}\" tidak ditemukan."];
                    $failedCnt++;
                    continue;
                }
                $packageId = $packagesByName[$key];
            }

            $installationDate = $get($row, $colMap, 'installation_date');
            if ($installationDate !== '' && ! \DateTime::createFromFormat('Y-m-d', $installationDate)) {
                $results[] = ['row' => $rowNumber, 'name' => $name, 'status' => 'failed', 'reason' => 'Format tanggal instalasi salah, gunakan YYYY-MM-DD.'];
                $failedCnt++;
                continue;
            }

            $data = array_filter([
                'name'              => $name,
                'phone'             => $phone,
                'whatsapp'          => $get($row, $colMap, 'whatsapp') ?: null,
                'email'             => $email,
                'address'           => $get($row, $colMap, 'address') ?: null,
                'package_id'        => $packageId,
                'installation_date' => $installationDate ?: null,
                'notes'             => $get($row, $colMap, 'notes') ?: null,
            ], static fn ($v) => $v !== null);
            $data['status'] = 'pending';

            $id       = $this->customers->create($data);
            $customer = $this->customers->find($id);
            $this->provisionPortalAccount($customer, $tenantId);

            $results[] = ['row' => $rowNumber, 'name' => $name, 'status' => 'created', 'reason' => null];
            $createdCnt++;
        }
        fclose($stream);

        (new ActivityModel())->log(
            'customers_imported',
            TenantHelper::id(),
            null,
            "Import CSV: {$createdCnt} dibuat, {$skippedCnt} dilewati, {$failedCnt} gagal"
        );

        return $this->ok([
            'total_rows' => $rowNumber - 1,
            'created'    => $createdCnt,
            'skipped'    => $skippedCnt,
            'failed'     => $failedCnt,
            'results'    => $results,
        ], 'Import selesai.');
    }

    /**
     * Auto-provisions a `pelanggan`-role login for a newly created customer,
     * with a random password sent via the existing WhatsApp notification
     * queue — never blocks or fails customer creation itself. The plaintext
     * password is also returned here (once) purely so the caller can show
     * it to the owner as a manual-info fallback for as long as the WhatsApp
     * gateway isn't configured yet — it is never stored or retrievable
     * again after this call returns (the `users` row only ever holds the
     * hash).
     *
     * @return array{status: string, password: ?string} status is one of:
     *         skipped_disabled|skipped_no_email|skipped_email_taken|created
     */
    private function provisionPortalAccount(array $customer, int $tenantId): array
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (empty($tenant['customer_portal_enabled'])) {
            return ['status' => 'skipped_disabled', 'password' => null];
        }

        if (empty($customer['email'])) {
            return ['status' => 'skipped_no_email', 'password' => null];
        }

        $userModel = new UserModel();
        if ($userModel->findByEmail($customer['email'])) {
            return ['status' => 'skipped_email_taken', 'password' => null];
        }

        $pelangganRole = (new RoleModel())->findBySlug('pelanggan');
        if (! $pelangganRole) {
            return ['status' => 'skipped_no_email', 'password' => null];
        }

        $password = $this->generatePassword();

        $userId = $userModel->insert([
            'tenant_id' => $tenantId,
            'role_id'   => $pelangganRole['id'],
            'name'      => $customer['name'],
            'email'     => $customer['email'],
            'phone'     => $customer['phone'] ?? null,
            'password'  => $password,
            'is_active' => 1,
        ]);

        $this->customers->update((int) $customer['id'], ['user_id' => $userId]);

        (new NotificationModel())->queue(
            'whatsapp',
            'account_created',
            'customer',
            (int) $customer['id'],
            ['email' => $customer['email'], 'password' => $password],
            $tenantId
        );

        return ['status' => 'created', 'password' => $password];
    }

    /**
     * Random, WhatsApp-readable password: excludes visually ambiguous
     * characters (0/O, 1/l/I) since it's read off a phone screen, not typed
     * from a password manager.
     */
    private function generatePassword(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    public function update($id = null)
    {
        $rules = [
            'name'       => 'permit_empty|min_length[2]|max_length[150]',
            'phone'      => 'permit_empty|max_length[20]',
            'email'      => 'permit_empty|valid_email',
            'package_id' => 'permit_empty|integer',
            'router_id'  => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $data = array_intersect_key($data, array_flip([
            'name', 'phone', 'email', 'address', 'whatsapp', 'package_id', 'router_id',
            'pppoe_username', 'pppoe_password', 'installation_date', 'notes',
        ]));

        $before = $this->customers->find((int) $id);

        if (! $before || ! $this->customers->update((int) $id, $data)) {
            return $this->notFound('Pelanggan tidak ditemukan.');
        }

        $customer = $this->customers->find((int) $id);

        // Router/PPPoE/package changes → bring the router in line with the
        // edited record. Best-effort, same as create/suspend/enable.
        $package    = ! empty($customer['package_id']) ? (new PackageModel())->find($customer['package_id']) : null;
        $routerSync = $this->routerService->syncCustomerChange(
            $before,
            $customer,
            $package,
            ! empty($data['pppoe_password'])
        );

        return $this->ok([...$customer, 'router_sync' => $routerSync], 'Pelanggan berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        $customer = $this->customers->find((int) $id);

        if (! $customer || ! $this->customers->delete((int) $id)) {
            return $this->notFound('Pelanggan tidak ditemukan.');
        }

        // A deleted customer's PPPoE secret must not stay usable on the
        // router. Best-effort like every other router action.
        $routerSync = $this->routerService->removeCustomer($customer);

        (new ActivityModel())->log('customer_deleted', TenantHelper::id(), null, "Pelanggan #{$id} dihapus (router: {$routerSync})");

        return $this->ok(['router_sync' => $routerSync], 'Pelanggan berhasil dihapus.');
    }

    public function suspend($id = null)
    {
        return $this->changeStatus((int) $id, 'suspended', 'customer_suspended', 'disuspend', 'suspendCustomer', 'suspend');
    }

    public function enable($id = null)
    {
        return $this->changeStatus((int) $id, 'active', 'customer_enabled', 'diaktifkan', 'enableCustomer', 'reactivation');
    }

    private function changeStatus(int $id, string $status, string $action, string $verbLabel, string $routerMethod, string $notificationType)
    {
        $customer = $this->customers->find($id);
        if (! $customer) {
            return $this->notFound('Pelanggan tidak ditemukan.');
        }

        $this->customers->setStatus($id, $status);

        // NetHub's own record is the source of truth and always gets
        // updated; the router action is best-effort on top of that — a
        // customer without a router/PPPoE assignment, or one whose router
        // is briefly unreachable, must never block the owner from changing
        // status here (see RouterService::suspendCustomer/enableCustomer).
        $routerResult = $this->routerService->{$routerMethod}($customer);

        (new ActivityModel())->log(
            $action,
            TenantHelper::id(),
            null,
            "Pelanggan #{$id} {$verbLabel} (router: {$routerResult})"
        );

        (new NotificationModel())->queue(
            'whatsapp',
            $notificationType,
            'customer',
            $id,
            [],
            TenantHelper::id()
        );

        return $this->ok(
            [...$this->customers->find($id), 'router_sync' => $routerResult],
            "Pelanggan berhasil {$verbLabel}."
        );
    }
}
