<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProductController;
use \App\Http\Controllers\API\FarmController;
use \App\Http\Controllers\API\WalletController;
use \App\Http\Controllers\API\RewardsController;
use App\Http\Controllers\API\AdminAuthController;
use  \App\Http\Controllers\API\InvestmentController;
use \App\Http\Controllers\API\FarmConfigurationController;
use App\Http\Controllers\SmsController;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Controllers\API\ProjectController;
use \App\Http\Controllers\API\RolesController;
use \App\Http\Controllers\API\UserController;
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


Route::post('/send-sms', [SmsController::class, 'sendSms']);

Route::post('register', [RegisterController::class, 'register']);
Route::post('login', [RegisterController::class, 'login']);
Route::post('resend-otp', [RegisterController::class, 'resendOtp']);
Route::post('verify-otp', [RegisterController::class, 'verifyOtp'])->name('verify.otp');
Route::get('install-configuration', function () {

    \Illuminate\Support\Facades\Artisan::call('passport:install');
});
Route::post('update-password', [RegisterController::class, 'updateUserPassword']);
Route::middleware('auth:api')->group(function () {
    Route::resource('products', ProductController::class);

    Route::get('get-farm-list', [FarmController::class, 'getFarmList']);
    Route::post('add-farm-details', [FarmController::class, 'addFarmDetails']);
    Route::get('/wallet', [WalletController::class, 'getWallet'])->name('wallet.get');
    Route::post('/wallet/add-funds', [WalletController::class, 'addFunds'])->name('wallet.addFunds');
    Route::post('/wallet/withdraw', [WalletController::class, 'withdrawFunds'])->name('wallet.withdraw');
    Route::get('/wallet/transactions', [WalletController::class, 'getTransactions'])->name('wallet.transactions');
    Route::get('/rewards', [RewardsController::class, 'getRewards']);
    Route::post('/rewards', [RewardsController::class, 'addOrUpdateRewards']);
    Route::get('/rewards/history', [RewardsController::class, 'getRewardsHistory']);
    Route::get('/configured-projects', [\App\Http\Controllers\API\FarmConfigurationController::class, 'getConfiguredProjects']);
    Route::post('/investments', [InvestmentController::class, 'store']);
    Route::get('farm-statistics', [FarmController::class, 'getFarmStatistics']);
    //Route for list projects for mobile
    Route::get('/get-project-list', [ProjectController::class, 'getAllPaginatedDataList']);
    Route::get('/farm/enums', [FarmController::class, 'getEnums']);

});


Route::post('admin/register', [AdminAuthController::class, 'register']);
Route::post('admin/login', [AdminAuthController::class, 'login']);


Route::middleware('auth:admin')->prefix('admin/')->group(function () {
    Route::get('admin/profile', function (Request $request) {
        return $request->user();
    });
    Route::post('logout', [AdminAuthController::class, 'logout']);
    Route::get('get-farms', [FarmController::class, 'getAllFarms']);
    Route::get('get-all-users', [UserController::class, 'getAllUsers']);
    Route::post('configured-projects', [FarmConfigurationController::class, 'postConfiguration']);
    Route::POST('configured-projects-list', [FarmConfigurationController::class, 'getConfiguredProjects']);
    Route::get('get-project-list', [ProjectController::class, 'getAllPaginatedData']);
    Route::POST('add-project', [ProjectController::class, 'addProject']);
    Route::post('users-with-roles', [UserController::class, 'getUsersWithRoles']);
    Route::POST('roles', [RolesController::class, 'getAllRoles']);
    Route::post('users', [UserController::class, 'addUsers']);
    Route::post('all-farm-list', [FarmController::class, 'getAllPaginatedFarm']);

    Route::get('admin/dashboard-statistics',function (){

      return   response()->json([
            'data' => [
                'activeUsers' => \App\Models\User::count(),
                'activeProjects' => 10,
                'activeInvestors' => 10,
                'completedProjectsPercentage' => 20,
                'ongoingProjectsPercentage' => 20,
                'downloads' => 300,
            ]
        ]);
    });

    Route::get('/users-with-wallets', [WalletController::class, 'getUsersWithWallets']);
    Route::post('/wallet/add-funds', [WalletController::class, 'addFunds']);
    Route::post('/wallet/withdraw', [WalletController::class, 'withdrawFunds']);
});

Route::get('/send-otp', function () {
    // Generate OTP
    $otp = Str::random(6);


    Mail::to('engr.tassadaq@gmail.com')->send(new OtpMail($otp));

    return 'OTP sent successfully!';
});

