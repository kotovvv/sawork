<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\MagazynController;
use App\Http\Controllers\Api\SendPDF;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\AuthUserController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ComingController;
use App\Http\Controllers\Api\FileController;

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
    Route::get('getReportTarif/{month}/{idwarehouse}', [MagazynController::class, 'getReportTarif']);
    Route::get('getClientPriceCondition/{idwarehouse}', [MagazynController::class, 'getClientPriceCondition']);
    Route::post('setClientPriceCondition', [MagazynController::class, 'setClientPriceCondition']);
    Route::get('getPriceCondition', [MagazynController::class, 'getPriceCondition']);
    Route::post('getOborot', [MagazynController::class, 'getOborot']);
    Route::post('getQuantity', [MagazynController::class, 'getQuantity']);

    Route::get('getProductHistory/{IDTowaru}', [MagazynController::class, 'getProductHistory']);
    Route::post('getOrders', [MagazynController::class, 'getOrders']);
    Route::post('createWZfromZO', [MagazynController::class, 'createWZfromZO']);
    Route::get('getStatuses', [MagazynController::class, 'getStatuses']);
    Route::get('getClients', [MagazynController::class, 'getClients']);
    Route::get('setCenaWZkFromWZ', [MagazynController::class, 'setCenaWZkFromWZ']);
    Route::get('setCenaZLfromPZ', [MagazynController::class, 'setCenaZLfromPZ']);
    Route::post('getOrder', [ReturnController::class, 'getOrder']);
    Route::get('getProduct/{id}', [LocationController::class, 'getProduct']);
    Route::post('doWz', [ReturnController::class, 'doWz']);
    Route::post('getDocsWZk', [ReturnController::class, 'getDocsWZk']);
    Route::post('getWZkProducts', [ReturnController::class, 'getWZkProducts']);
    Route::post('saveUwagiDoc', [ReturnController::class, 'saveUwagiDoc']);
    Route::post('saveUwagiSprz', [ReturnController::class, 'saveUwagiSprz']);
    Route::post('saveUwagiProduct', [ReturnController::class, 'saveUwagiProduct']);
    Route::post('sendEmail', [ReturnController::class, 'sendEmail']);
    Route::post('whenSendedEmail', [ReturnController::class, 'whenSendedEmail']);
    Route::get('sendPDF', [SendPDF::class, 'index']);
    Route::post('TowarLocationTipTab', [LocationController::class, 'TowarLocationTipTab']);
    Route::get('getWarehouseLocations/{id}', [LocationController::class, 'getWarehouseLocations']);
    Route::post('doRelokacja', [LocationController::class, 'doRelokacja']);
    Route::post('refreshLocations', [LocationController::class, 'refreshLocations']);

    Route::get('/logs/useReport', [LogController::class, 'getUseReportLog']);
    Route::get('/logs/users', [LogController::class, 'getUsersLog']);

    Route::post('getDM', [ComingController::class, 'getDM']);
    Route::post('createPZ', [ComingController::class, 'createPZ']);
    Route::post('setBrack', [ComingController::class, 'setBrack']);
    Route::post('get_PZproducts', [ComingController::class, 'get_PZproducts']);
    Route::post('getSetPZ', [ComingController::class, 'getSetPZ']);
    Route::post('uploadFiles', [FileController::class, 'uploadFiles']);
    Route::get('getFiles/{IDRuchuMagazynowego}/{folder_name}', [FileController::class, 'getFiles']);
    Route::get('dowloadFile/{filename}', [FileController::class, 'dowloadFile']);
    Route::post('deleteFile', [FileController::class, 'deleteFile']);
});