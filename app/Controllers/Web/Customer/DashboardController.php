<?php

namespace App\Controllers\Web\Customer;

use App\Controllers\BaseController;

class DashboardController extends BaseController
{
    public function index()
    {
        return view('customer/dashboard', ['title' => 'Dashboard - NetHub']);
    }
}
