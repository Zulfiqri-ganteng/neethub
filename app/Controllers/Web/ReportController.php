<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class ReportController extends BaseController
{
    public function index()
    {
        return view('owner/reports', ['title' => 'Laporan - NetHub']);
    }
}
