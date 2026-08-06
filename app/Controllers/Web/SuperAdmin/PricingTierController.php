<?php

namespace App\Controllers\Web\SuperAdmin;

use App\Controllers\BaseController;

class PricingTierController extends BaseController
{
    public function index()
    {
        return view('superadmin/pricing_tiers', ['title' => 'Tier Harga Platform - NetHub Super Admin']);
    }
}
