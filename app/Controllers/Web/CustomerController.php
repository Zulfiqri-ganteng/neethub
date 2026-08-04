<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class CustomerController extends BaseController
{
    public function index()
    {
        return view('owner/customers', ['title' => 'Pelanggan - NetHub']);
    }
}
