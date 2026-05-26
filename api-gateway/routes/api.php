<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::any('/{service}/{endpoint?}', [GatewayController::class, 'routeRequest'])
    ->where('endpoint', '.*');