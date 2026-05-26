<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseController;

Route::post('/enroll', [CourseController::class, 'enroll']);