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

Route::group(['namespace' => 'Api'], function () {

});
