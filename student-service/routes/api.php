<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;

Route::get('/profile/{student_number}', [StudentController::class, 'getProfile']);