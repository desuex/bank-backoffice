<?php

use App\Http\Controllers\TransferController;
use App\Http\Controllers\TopUpController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::resource('users', UserController::class)->only(['update']);
Route::resource('top-up', TopUpController::class)->only(['store']);;
Route::resource('transfers', TransferController::class)->only(['store']);;
