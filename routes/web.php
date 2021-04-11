<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/test', 'App\Http\Controllers\Controller@test');
//Route::get('/test', [App\Http\Controllers\Controller::class, 'test']);

Route::get('/test', 'App\Http\Controllers\SalonsForVisitors@test');
Route::get('/get-services-list', 'App\Http\Controllers\SalonsForVisitors@getServicesList');
Route::get('/get-salons-performing-service', 'App\Http\Controllers\SalonsForVisitors@getSalonsPerformingService');
Route::get('/send-request-to-salon', 'App\Http\Controllers\SalonsForVisitors@sendRequestToSalon');
Route::get('/get-unoccupied-schedule', 'App\Http\Controllers\SalonsForVisitors@getUnoccupiedSchedule');
