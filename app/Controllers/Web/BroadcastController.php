<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

class BroadcastController extends BaseController
{
    public function index()
    {
        return view('owner/broadcast', ['title' => 'Broadcast - NetHub']);
    }
}
