<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookSearchController;
use App\Http\Controllers\Api\BookSummaryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 1. ROUTES PUBLIQUES (aucun token demandé)
|--------------------------------------------------------------------------
*/
//
Route::post('webhooks/stripe', StripeWebhookController::class);
// Inscription et connexion : 5 essais par minute au maximum (anti force brute)
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('login',    [AuthController::class, 'login'])->middleware('throttle:5,1');

// Endpoints IA accessibles sans connexion (préfixe /api/v1)
Route::prefix('v1')->group(function () {

    // GET /api/v1/books/{id}/summary
    // Lit le résumé déjà enregistré. Aucun appel à l'IA, donc aucun coût.
    Route::get('books/{book}/summary', [BookSummaryController::class, 'show']);

    // POST /api/v1/books/smart-search
    // Recherche par description en langage naturel. Elle appelle l'IA,
    // donc limitée à 10 requêtes par minute.
    Route::post('books/smart-search', BookSearchController::class)
        ->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| 2. ROUTES PROTÉGÉES (token Bearer obligatoire : auth:sanctum)
|--------------------------------------------------------------------------
| Sans token valide, Laravel répond 401.
*/
Route::middleware('auth:sanctum')->group(function () {

    // ---------- Compte du membre connecté ----------
    Route::get('me',      [AuthController::class, 'me']);       // mes informations
    Route::post('logout', [AuthController::class, 'logout']);   // supprime le token actuel

    // ---------- Livres ----------
    // "popular" doit être AVANT apiResource : sinon Laravel lit "popular"
    // comme l'id d'un livre et répond 404.
    Route::get('books/popular', [BookController::class, 'popular']);
    Route::apiResource('books', BookController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // ---------- Auteurs ----------
    Route::apiResource('authors', AuthorController::class);
    Route::get('authors/{author}/books',  [AuthorController::class, 'books']);      // livres d'un auteur
    Route::post('authors/{author}/books', [AuthorController::class, 'storeBook']);  // créer un livre pour cet auteur

    // ---------- Catégories ----------
    Route::apiResource('categories', CategoryController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // ---------- Emprunts ----------
    // update = prolonger la date de retour (due_at)
    Route::apiResource('loans', LoanController::class)
        ->only(['index', 'store', 'show', 'update']);
    Route::patch('loans/{loan}/return',  [LoanController::class, 'returnBook']);     // retourner le livre
    Route::patch('loans/{loan}/payment', [LoanController::class, 'updatePayment']);  // pending <-> paid

    // ---------- IA : résumé d'un livre ----------
    // POST /api/v1/books/{id}/summary
    // Génère le résumé et l'enregistre. Si un résumé existe déjà, il est
    // renvoyé sans nouvel appel à l'IA (sauf avec ?force=1).
    Route::post('v1/books/{book}/summary', [BookSummaryController::class, 'store'])
        ->middleware('throttle:5,1');

    // GET /api/v1/books/{id}/summary/stream
    // Même génération, mais envoyée en direct (SSE) : événements delta, puis done.
    Route::get('v1/books/{book}/summary/stream', [BookSummaryController::class, 'stream'])
        ->middleware('throttle:5,1');

    /*
    |----------------------------------------------------------------------
    | 3. ROUTES ADMIN (token + rôle admin)
    |----------------------------------------------------------------------
    | Un membre normal reçoit 403. Seul un admin peut lister, créer,
    | modifier (type, abonnement) ou supprimer les membres.
    */
    Route::middleware('admin')->group(function () {
        Route::apiResource('members', MemberController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
    });
    Route::post('v1/subscriptions/payment-intent', [SubscriptionController::class, 'paymentIntent'])
        ->middleware('throttle:10,1');
    Route::get('v1/subscriptions/status', [SubscriptionController::class, 'status']);
});
