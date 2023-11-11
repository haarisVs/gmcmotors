<?php

use Illuminate\Support\Facades\Artisan;
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
Route::get('/clear', function () {
    // Clear the application cache
    Artisan::call('cache:clear');

    // Optionally, you can also clear other caches if needed
     Artisan::call('config:clear');
     Artisan::call('route:clear');
     Artisan::call('view:clear');

    return 'Cache cleared successfully!';
});

Route::get('/', function () {
    return redirect( 'https://gmcmotors.co.uk' );
});
