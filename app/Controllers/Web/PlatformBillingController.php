<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class PlatformBillingController extends BaseController
{
    public function index()
    {
        return view('owner/platform_billing', ['title' => 'Tagihan Platform - NetHub']);
    }
}
