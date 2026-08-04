<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class ForgotPasswordController extends BaseController
{
    public function index()
    {
        return view('auth/forgot_password', ['title' => 'Lupa Password - NetHub']);
    }
}
