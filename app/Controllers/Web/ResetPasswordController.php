<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class ResetPasswordController extends BaseController
{
    public function index()
    {
        return view('auth/reset_password', ['title' => 'Reset Password - NetHub']);
    }
}
