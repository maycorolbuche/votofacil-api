<?php

use Illuminate\Support\Facades\Artisan;



$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/install', function () {
    try {
        // Alterar o diretório base para a raiz do projeto
        chdir(base_path());

        // Executar as migrations
        Artisan::call('migrate', ['--force' => true]);

        return response()->json(['message' => 'Migrations executed successfully!']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
