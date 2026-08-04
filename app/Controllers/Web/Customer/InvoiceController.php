<?php

namespace App\Controllers\Web\Customer;

use App\Controllers\BaseController;

class InvoiceController extends BaseController
{
    public function index()
    {
        return view('customer/invoices', ['title' => 'Tagihan - NetHub']);
    }
}
