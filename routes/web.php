<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
use App\Http\Controllers\MailController;
use Illuminate\Support\Facades\Artisan;

Route::get('/send-test-email', [MailController::class, 'sendTestEmail']);

Route::get('/', function () {
    return view('welcome');
});


Route::get('/run-migrations', function () {
    Artisan::call('migrate', ['--force' => true]);
    return 'Migrations executed!';
});
