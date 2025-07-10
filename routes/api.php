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
use App\Http\Controllers\Api\CollectController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\PrintController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ForTTNController;
use App\Http\Controllers\Api\BaseLinkerController;

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
    Route::get('getProduct/{id}', [LocationController::class, 'getProduct']);
    Route::post('doWz', [ReturnController::class, 'doWz']);
    Route::post('getDocsWZk', [ReturnController::class, 'getDocsWZk']);
    Route::post('getWZkProducts', [ReturnController::class, 'getWZkProducts']);
    Route::post('saveUwagiDoc', [ReturnController::class, 'saveUwagiDoc']);
    Route::post('saveUwagiSprz', [ReturnController::class, 'saveUwagiSprz']);
    Route::post('saveUwagiProduct', [ReturnController::class, 'saveUwagiProduct']);
    Route::post('sendEmail', [ReturnController::class, 'sendEmail']);
    Route::post('whenSendedEmail', [ReturnController::class, 'whenSendedEmail']);
    Route::get('getProductsInLocation/{IDWarehouse}/{location}', [ReturnController::class, 'getProductsInLocation']);
    Route::get('sendPDF', [SendPDF::class, 'index']);
    Route::post('TowarLocationTipTab', [LocationController::class, 'TowarLocationTipTab']);
    Route::get('getWarehouseLocations/{id}', [LocationController::class, 'getWarehouseLocations']);
    Route::post('doRelokacja', [LocationController::class, 'doRelokacjaTowaru']);
    Route::post('refreshLocations', [LocationController::class, 'refreshLocations']);
    Route::get('getProductLocations/{id}', [LocationController::class, 'getProductLocations']);
    Route::post('getOrder', [OrderController::class, 'getOrder']);
    Route::post('getOrderProducts', [OrderController::class, 'getOrderProducts']);
    Route::get('getOrderPack/{IDOrder}', [OrderController::class, 'getOrderPack']);

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

    Route::post('getAllOrders', [CollectController::class, 'getAllOrders']);
    Route::post('getOrderProductsToCollect', [CollectController::class, 'getOrderProductsToCollect']);
    Route::post('collectOrders', [CollectController::class, 'collectOrders']);
    Route::post('deleteSelectedMakeOrders', [CollectController::class, 'deleteSelectedMakeOrders']);
    Route::post('prepareDoc', [CollectController::class, 'prepareDoc']);
    Route::get('getPackOrders', [CollectController::class, 'getPackOrders']);
    Route::post('getOrderPackProducts/{IDOrder}', [CollectController::class, 'getOrderPackProducts']);
    Route::post('setOrderPackProducts', [CollectController::class, 'setOrderPackProducts']);
    Route::post('writeTTN', [CollectController::class, 'writeTTN']);
    Route::post('deleteTTN', [CollectController::class, 'deleteTTN']);
    Route::get('getRodzajTransportu', [CollectController::class, 'getRodzajTransportu']);
    Route::post('setRodzajTransportu', [CollectController::class, 'setRodzajTransportu']);
    Route::get('getListPackProducts', [CollectController::class, 'getListPackProducts']);

    // Add routes for UsersController
    Route::get('settings', [UsersController::class, 'index']);
    Route::post('settings', [UsersController::class, 'store']);
    Route::get('settings/{id}', [UsersController::class, 'show']);
    Route::put('settings/{id}', [UsersController::class, 'update']);
    Route::delete('settings/{id}', [UsersController::class, 'destroy']);
    Route::get('uzytkownicy', [UsersController::class, 'uzytkownicy']);
    Route::post('intervalSetting', [UsersController::class, 'intervalSetting']);

    Route::get('getWarehouseLocations/{id}', [LocationController::class, 'getWarehouseLocations']);
    Route::get('getLocationsM3', [LocationController::class, 'getLocationsM3']);
    Route::get('getLocationsTyp', [LocationController::class, 'getLocationsTyp']);
    Route::post('updateLocationsTyp', [LocationController::class, 'updateLocationsTyp']);

    Route::post('log_orders', [LogController::class, 'log_orders']);
    Route::post('print', [PrintController::class, 'print']);

    Route::apiResource('for-ttn', ForTTNController::class);
    Route::get('for-ttn/get-codes-from-bl/{id_warehouse}', [ForTTNController::class, 'getCodesFromBL']);
    Route::get('for-ttn/get-accounts-from-bl/{id_warehouse}/{courier_code}', [ForTTNController::class, 'getAccountsFromBL']);
    Route::get('getForm/{id}', [ForTTNController::class, 'getForm']);
    Route::post('getTTN', [ForTTNController::class, 'getTTN']);

    Route::get('importSingleOrder/{warehouseId}/{orderId}', [\App\Http\Controllers\Api\importBLController::class, 'importSingleOrder']);
});
