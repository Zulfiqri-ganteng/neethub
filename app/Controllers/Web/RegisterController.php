<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Models\PlatformSettingModel;

class RegisterController extends BaseController
{
    public function index()
    {
        return view('auth/register', [
            'title'     => 'Daftar - NetHub',
            'trialDays' => (new PlatformSettingModel())->current()['trial_days'] ?? 30,
        ]);
    }
}
