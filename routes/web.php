<?php

use Illuminate\Support\Facades\Artisan;



$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/install', 'InstallController@index');
