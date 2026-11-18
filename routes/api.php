<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\BookController;

Route::apiResource('loans',   LoanController::class)->only(['index', 'store', 'show']);
Route::apiResource('members', MemberController::class)->only(['index', 'store', 'show', 'destroy']);
Route::apiResource('books',   BookController::class)->only(['index', 'store', 'show', 'destroy']);

Route::patch('/loans/{loan}/return', [LoanController::class, 'returnBook']);