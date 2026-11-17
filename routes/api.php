<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\BookController;
use Illuminate\Support\Facades\Route;

Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{article}', [ArticleController::class, 'show']);
Route::post('/articles', [ArticleController::class, 'store']);
Route::put('/articles/{article}', [ArticleController::class, 'update']);
Route::delete('/articles/{article}', [ArticleController::class, 'destroy']);

Route::apiResource('books', BookController::class);


Route::apiResource('members', MemberController::class);
