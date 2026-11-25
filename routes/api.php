<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookSearchController;
use App\Http\Controllers\Api\BookSummaryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberController;
use Illuminate\Support\Facades\Route;

// ================= PUBLIC (no token) =================
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('login',    [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::prefix('v1')->group(function () {
    // Read an existing summary (no OpenAI call)
    Route::get('books/{book}/summary', [BookSummaryController::class, 'show']);

    // Natural-language search (calls OpenAI, so strict rate limit)
    Route::post('books/smart-search', BookSearchController::class)
        ->middleware('throttle:10,1');
});

// ================= PROTECTED (Bearer token) =================
Route::middleware('auth:sanctum')->group(function () {

    // ---- Auth ----
    Route::get('me',    [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // ---- Books ----
    // "popular" BEFORE apiResource, otherwise it is read as a book id
    Route::get('books/popular', [BookController::class, 'popular']);
    Route::apiResource('books', BookController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // ---- Authors ----
    Route::apiResource('authors', AuthorController::class);
    Route::get('authors/{author}/books',  [AuthorController::class, 'books']);
    Route::post('authors/{author}/books', [AuthorController::class, 'storeBook']);

    // ---- Categories ----
    Route::apiResource('categories', CategoryController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // ---- Loans ----
    Route::apiResource('loans', LoanController::class)
        ->only(['index', 'store', 'show', 'update']);
    Route::patch('loans/{loan}/return',  [LoanController::class, 'returnBook']);
    Route::patch('loans/{loan}/payment', [LoanController::class, 'updatePayment']);

    // ---- AI: generate and save a summary ----
    Route::post('v1/books/{book}/summary', [BookSummaryController::class, 'store'])
        ->middleware('throttle:5,1');

    // ================= ADMIN ONLY =================
    Route::middleware('admin')->group(function () {

        // Gestion des membres (lecture + écriture réservées aux admins)
        Route::apiResource('members', MemberController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
    });
});
