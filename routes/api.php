<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\MagazynController;
use App\Http\Controllers\Api\SendPDF;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\AuthUserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware(['auth.jwt'])->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::post('login', [AuthUserController::class, 'login']);
Route::middleware(['auth.jwt'])->get('getWarehouse', [ReturnController::class, 'getWarehouse']);
Route::middleware(['auth.jwt'])->get('loadMagEmail', [MagazynController::class, 'loadMagEmail']);
Route::middleware(['auth.jwt'])->post('saveMagEmail', [MagazynController::class, 'saveMagEmail']);
Route::middleware(['auth.jwt'])->post('deleteMagEmail', [MagazynController::class, 'deleteMagEmail']);
Route::middleware(['auth.jwt'])->post('getOrder', [ReturnController::class, 'getOrder']);
Route::middleware(['auth.jwt'])->get('getProduct/{id}', [LocationController::class, 'getProduct']);
Route::middleware(['auth.jwt'])->post('doWz', [ReturnController::class, 'doWz']);
Route::middleware(['auth.jwt'])->get('sendPDF', [SendPDF::class, 'index']);
Route::middleware(['auth.jwt'])->post('TowarLocationTipTab', [LocationController::class, 'TowarLocationTipTab']);
Route::middleware(['auth.jwt'])->get('getWarehouseLocations/{id}', [LocationController::class, 'getWarehouseLocations']);
Route::middleware(['auth.jwt'])->post('doRelokacja', [LocationController::class, 'doRelokacja']);