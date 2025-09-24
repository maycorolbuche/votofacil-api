<?php

namespace App\Http\Controllers;

use App\Helpers\Cache;

class ClearController extends Controller
{
    public function index()
    {
        Cache::clear();
    }
}
