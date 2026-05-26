<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegistryController;



Route::post('/register', [RegistryController::class, 'register']);
Route::get('/discover/{service_name}', [RegistryController::class, 'discover']);