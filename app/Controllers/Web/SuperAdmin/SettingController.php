<?php

namespace App\Controllers\Web\SuperAdmin;

use App\Controllers\BaseController;

class SettingController extends BaseController
{
    public function index()
    {
        return view('superadmin/settings', ['title' => 'Pengaturan Platform - NetHub Super Admin']);
    }
}
