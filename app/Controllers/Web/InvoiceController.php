<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class InvoiceController extends BaseController
{
    public function index()
    {
        return view('owner/invoices', ['title' => 'Tagihan - NetHub']);
    }
}
