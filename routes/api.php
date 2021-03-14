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
Route::match(['get','post'], '/test', [App\Http\Controllers\Api\AuthController::class, 'test']);
    //->middleware('auth:api');
Route::match(['get','post'], '/send_sms_code', [App\Http\Controllers\Api\AuthController::class, 'SendSmsCode']);
Route::match(['get','post'], '/verify_sms_code', [App\Http\Controllers\Api\AuthController::class, 'VerifySmsCode']);
Route::match(['get','post'], '/set_password', [App\Http\Controllers\Api\AuthController::class, 'SetPassword']);

Route::match(['get','post'], '/actual-workshifts-get', 'App\Http\Controllers\Api\SalonController@ActualWorkshiftsGet');
Route::match(['get','post'], '/create-workshift', 'App\Http\Controllers\Api\SalonController@CreateWorkshift');
Route::match(['get','post'], '/schedule-get', 'App\Http\Controllers\Api\SalonController@ScheduleGet');
Route::match(['get','post'], '/schedule-add-service', 'App\Http\Controllers\Api\SalonController@ScheduleAddService');
Route::match(['get','post'], '/get-salon-services-list', 'App\Http\Controllers\Api\SalonController@GetSalonServicesList');
Route::match(['get','post'], '/get-all-services-dir', 'App\Http\Controllers\Api\SalonController@GetAllServicesDir');
Route::match(['get','post'], '/save-salon-service', 'App\Http\Controllers\Api\SalonController@SaveSalonService');

Route::match(['get','post'], '/get_my_salon_services_active_requests', [App\Http\Controllers\Api\SalonController::class, 'GetMySalonServicesActiveRequests']);
Route::match(['get','post'], '/set_my_response', [App\Http\Controllers\Api\SalonController::class, 'SetMyResponse']);


Route::match(['get','post'], '/yandex_maps_firms_save', [App\Http\Controllers\ProcessGeodata::class, 'YandexMapsFirmsSave']);
Route::match(['get','post'], '/find_salons_by_street_name', [App\Http\Controllers\ProcessGeodata::class, 'FindSalonsByStreetName']);

Route::group(['middleware' => 'auth'], function() {
    Route::match(['get','post'], '/logout', [App\Http\Controllers\Api\AuthController::class, 'Logout']);
    Route::match(['get','post'], '/get_my_salons', [App\Http\Controllers\ProcessGeodata::class, 'getMySalons']);
    Route::match(['get','post'], '/add_me_to_salon', [App\Http\Controllers\ProcessGeodata::class, 'addMeToSalon']);
    //Route::match(['get','post'], '/get_masters_list', [App\Http\Controllers\Api\SalonController::class, 'getMastersList']);

    Route::match(['get','post'], '/salon/set_member', [App\Http\Controllers\Api\SalonController::class, 'setMemberOfSalon']);
});

Route::match(['get','post'], '/upload_image', [App\Http\Controllers\Api\SalonController::class, 'uploadImage']);


Route::group(['namespace' => 'Api'], function () {

});
