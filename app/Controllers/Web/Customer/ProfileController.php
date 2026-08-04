<?php

namespace App\Controllers\Web\Customer;

use App\Controllers\BaseController;

class ProfileController extends BaseController
{
    public function index()
    {
        return view('customer/profile', ['title' => 'Profil - NetHub']);
    }
}
