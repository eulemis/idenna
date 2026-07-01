<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');
Route::redirect('/login', '/app/login');

Route::get('/app/{path?}', [SpaController::class, 'serve'])->where('path', '.*');
