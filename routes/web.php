<?php
$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/install', 'InstallController@index');

// Rooms
$router->post('/rooms', 'RoomController@store');
$router->get('/rooms', 'RoomController@store');
