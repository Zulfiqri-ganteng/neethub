<?php

namespace App\Controllers\Web\SuperAdmin;

use App\Controllers\BaseController;

class TenantController extends BaseController
{
    public function index()
    {
        return view('superadmin/tenants', ['title' => 'Tenant - NetHub Super Admin']);
    }

    public function show($id = null)
    {
        return view('superadmin/tenant_detail', ['title' => 'Detail Tenant - NetHub Super Admin', 'tenantId' => (int) $id]);
    }
}
