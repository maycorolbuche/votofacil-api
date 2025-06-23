<?php
$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/install', 'InstallController@index');

// Rooms
$router->post('/room', 'RoomController@store');
$router->get('/room', 'RoomController@show');

// Devices
$router->post('/device', 'DeviceController@store');
$router->get('/device', 'DeviceController@show');
$router->delete('/device', 'DeviceController@destroy');
