<?php

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::match(['get','post'], '/register', [App\Http\Controllers\Api\AuthController::class, 'Register']);
Route::match(['get','post'], '/login', [App\Http\Controllers\Api\AuthController::class, 'Login']);
Route::match(['get','post'], '/logout', [App\Http\Controllers\Api\AuthController::class, 'Logout']);
Route::match(['get','post'], '/test', [App\Http\Controllers\Api\AuthController::class, 'test'])
    ->middleware('auth:api');

Route::match(['get','post'], '/actual-workshifts-get', 'App\Http\Controllers\Api\SalonController@ActualWorkshiftsGet');
Route::match(['get','post'], '/schedule-get', 'App\Http\Controllers\Api\SalonController@ScheduleGet');
Route::match(['get','post'], '/schedule-add-service', 'App\Http\Controllers\Api\SalonController@ScheduleAddService');
Route::match(['get','post'], '/get-salon-services-list', 'App\Http\Controllers\Api\SalonController@GetSalonServicesList');
Route::match(['get','post'], '/get-all-services-dir', 'App\Http\Controllers\Api\SalonController@GetAllServicesDir');


Route::group(['namespace' => 'Api'], function () {

});
