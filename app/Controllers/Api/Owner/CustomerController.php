<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\TenantHelper;
use App\Models\ActivityModel;
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
        if ($portalAccount === 'created') {
            $customer = $this->customers->find($id);
        }

        return $this->created(
            [...$customer, 'router_sync' => $routerSync, 'portal_account' => $portalAccount],
            'Pelanggan berhasil ditambahkan.'
        );
    }

    /**
     * Auto-provisions a `pelanggan`-role login for a newly created customer,
     * with a random password sent via the existing WhatsApp notification
     * queue — never blocks or fails customer creation itself.
     *
     * @return string One of: skipped_disabled|skipped_no_email|skipped_email_taken|created
     */
    private function provisionPortalAccount(array $customer, int $tenantId): string
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (empty($tenant['customer_portal_enabled'])) {
            return 'skipped_disabled';
        }

        if (empty($customer['email'])) {
            return 'skipped_no_email';
        }

        $userModel = new UserModel();
        if ($userModel->findByEmail($customer['email'])) {
            return 'skipped_email_taken';
        }

        $pelangganRole = (new RoleModel())->findBySlug('pelanggan');
        if (! $pelangganRole) {
            return 'skipped_no_email';
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

        return 'created';
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

        if (! $this->customers->update((int) $id, $data)) {
            return $this->notFound('Pelanggan tidak ditemukan.');
        }

        $customer = $this->customers->find((int) $id);

        // Package change → push the new rate-limit to the router. Best-
        // effort, same as create/suspend/enable.
        $routerSync = 'skipped';
        if (array_key_exists('package_id', $data) && ! empty($customer['package_id'])) {
            $package = (new PackageModel())->find($customer['package_id']);
            if ($package) {
                $routerSync = $this->routerService->updateCustomerSpeed($customer, $package);
            }
        }

        return $this->ok([...$customer, 'router_sync' => $routerSync], 'Pelanggan berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        if (! $this->customers->delete((int) $id)) {
            return $this->notFound('Pelanggan tidak ditemukan.');
        }

        (new ActivityModel())->log('customer_deleted', TenantHelper::id(), null, "Pelanggan #{$id} dihapus");

        return $this->ok(null, 'Pelanggan berhasil dihapus.');
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
