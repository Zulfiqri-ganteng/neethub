<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class MonitoringController extends BaseController
{
    public function index()
    {
        return view('owner/monitoring', ['title' => 'Monitoring - NetHub']);
    }
}
