<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class RouterController extends BaseController
{
    public function index()
    {
        return view('owner/routers', ['title' => 'Router - NetHub']);
    }
}
