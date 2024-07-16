<?php

use App\Http\Controllers\ICTController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::prefix('ict')->group(function () {
        Route::get('search-paginate', [ICTController::class, 'searchPaginate']);
        Route::get('to-spreadsheet', [ICTController::class, 'toSpreadsheet']);
    });
});

Route::prefix('users')->group(function () {
    Route::post('login', [UserController::class, 'login']);
    Route::delete('logout', [UserController::class, 'logout'])->middleware('auth:sanctum');
});

Route::post('/welcome', function () {
    return 'login dulu';
})->name('login');

Route::get('/welcome', function () {
    return 'login dulud';
})->name('login');
