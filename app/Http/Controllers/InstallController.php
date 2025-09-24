<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use App\Helpers\Cache;

class InstallController extends Controller
{
    public function index()
    {
        try {
            chdir(base_path());
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            Cache::clear();

            return response($output, 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            return response($e->getMessage(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }
}
