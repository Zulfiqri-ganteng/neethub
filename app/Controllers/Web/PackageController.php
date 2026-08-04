<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class PackageController extends BaseController
{
    public function index()
    {
        return view('owner/packages', ['title' => 'Paket - NetHub']);
    }
}
