<?php
$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/install', 'InstallController@index');

$router->group(['prefix' => 'admin'], function () use ($router) {
    $router->post('/room', 'Admin\RoomController@store');

    $router->group(['middleware' => ['auth-admin']], function () use ($router) {
        $router->get('/room', 'Admin\RoomController@show');
        $router->patch('/room', 'Admin\RoomController@update');

        $router->post('/candidate', 'Admin\CandidateController@store');
        $router->patch('/candidate', 'Admin\CandidateController@update');
        $router->delete('/candidate', 'Admin\CandidateController@destroy');

        $router->get('/sync', 'Admin\SyncController@index');
    });
});

$router->group(['prefix' => 'user'], function () use ($router) {
    $router->post('/device', 'User\DeviceController@store');

    $router->group(['middleware' => ['auth-user']], function () use ($router) {
        $router->get('/device', 'User\DeviceController@show');
        $router->delete('/device', 'User\DeviceController@destroy');

        $router->post('/user', 'User\UserController@store');

        $router->get('/sync', 'User\SyncController@index');
    });
});
