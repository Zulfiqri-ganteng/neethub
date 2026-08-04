<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class LoginController extends BaseController
{
    public function index()
    {
        return view('auth/login', ['title' => 'Login - NetHub']);
    }
}
