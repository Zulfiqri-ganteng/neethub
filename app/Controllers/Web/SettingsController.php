<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class SettingsController extends BaseController
{
    public function index()
    {
        return view('owner/settings', ['title' => 'Pengaturan - NetHub']);
    }
}
