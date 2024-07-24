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

Route::middleware(['jwt.verify'])->group(function () {
    Route::get('getWarehouse', [ReturnController::class, 'getWarehouse']);
    Route::get('loadMagEmail', [MagazynController::class, 'loadMagEmail']);
    Route::post('saveMagEmail', [MagazynController::class, 'saveMagEmail']);
    Route::post('deleteMagEmail', [MagazynController::class, 'deleteMagEmail']);
    Route::post('getDataForXLS', [MagazynController::class, 'getDataForXLS']);
    Route::get('getDataForXLSDay/{day}/{idwarehouse}', [MagazynController::class, 'getDataForXLSDay']);
    Route::get('getDataNotActivProduct/{day}/{idwarehouse}', [MagazynController::class, 'getDataNotActivProduct']);
    Route::post('getOrder', [ReturnController::class, 'getOrder']);
    Route::get('getProduct/{id}', [LocationController::class, 'getProduct']);
    Route::post('doWz', [ReturnController::class, 'doWz']);
    Route::get('sendPDF', [SendPDF::class, 'index']);
    Route::post('TowarLocationTipTab', [LocationController::class, 'TowarLocationTipTab']);
    Route::get('getWarehouseLocations/{id}', [LocationController::class, 'getWarehouseLocations']);
    Route::post('doRelokacja', [LocationController::class, 'doRelokacja']);
});