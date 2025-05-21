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
use \App\Http\Controllers\API\B2BController;
use \App\Http\Controllers\API\CategoryController;
use \App\Http\Controllers\API\AuthController;
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
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'index']);
        Route::post('/add', [ProductController::class, 'store']);
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::put('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);

        // Stock management
        Route::patch('/{id}/stock', [ProductController::class, 'updateStock']);

        // B2B settings
        Route::patch('/{id}/b2b', [ProductController::class, 'updateB2BSettings']);

        // Restore soft deleted product
        Route::patch('/{id}/restore', [ProductController::class, 'restore']);

        // Force delete product
        Route::delete('/{id}/force', [ProductController::class, 'forceDelete']);

        // Product bulk operations
        Route::post('/bulk-update', [ProductController::class, 'bulkUpdate']);
        Route::post('/bulk-delete', [ProductController::class, 'bulkDelete']);

        // Product import/export
        Route::post('/import', [ProductController::class, 'import']);
        Route::get('/export', [ProductController::class, 'export']);
    });

    // Categories
    Route::prefix('categories')->group(function () {
        Route::POST('/', [CategoryController::class, 'index']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::post('/add', [CategoryController::class, 'store']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);

        // Category sort order (batch update)
        Route::post('/sort', [CategoryController::class, 'updateSortOrder']);

        // Get products in a category
        Route::get('/{id}/products', [CategoryController::class, 'getCategoryProducts']);

        // Toggle B2B visibility
        Route::patch('/{id}/b2b-visibility', [CategoryController::class, 'toggleB2BVisibility']);

        // Move or copy products between categories
        Route::post('/move-products', [CategoryController::class, 'moveProducts']);
    });

    // Get product attributes
    Route::get('/product-attributes', [ProductController::class, 'getAttributes']);
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

Route::prefix('b2b')->group(function () {
    // Business registration
    Route::post('/register', [B2BController::class, 'registerBusiness']);

    // Authentication

    Route::post('/login', [AuthController::class, 'businessLogin']);
    Route::get('/products/featured', [ProductController::class, 'getAllFeatureProducts']);
    Route::get('/products/{id}', [ProductController::class, 'getProductDetail']);

    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/orders', [B2BController::class, 'placeOrder']);
    Route::get('/get-filter-products', [ProductController::class, 'getProductsByCategory']);
Route::get('/all-categories', [CategoryController::class, 'tree']);
Route::get('/get-all-products', [ProductController::class, 'getAllProducts']);
});


/*Route::get('/all-categories', [ProductController::class, 'getAllCategories']);*/
Route::get('/subcategories', [ProductController::class, 'getSubcategories']);

Route::get('/category/{slug}/{subCategorySlug?}', [ProductController::class, 'getBySlug']);

Route::get('/get-filter-products', [ProductController::class, 'getProductsByCategory']);
 Route::get('/subcategories', [ProductController::class, 'getSubcategories']);
Route::get('/category/{slug}/{subCategorySlug?}', [ProductController::class, 'getBySlug']);
Route::middleware(['auth:sanctum', 'business.approved'])->prefix('b2b')->group(function () {
    // Business profile
    Route::get('/profile', [B2BController::class, 'getBusinessProfile']);
    Route::put('/profile', [B2BController::class, 'updateBusinessProfile']);

    // Business users management
    Route::get('/users', [B2BController::class, 'getBusinessUsers']);
    Route::post('/users', [B2BController::class, 'addBusinessUser']);
    Route::put('/users/{id}', [B2BController::class, 'updateBusinessUser']);
    Route::delete('/users/{id}', [B2BController::class, 'removeBusinessUser']);

    // Products
    Route::get('/catalog', [B2BController::class, 'getBusinessCatalog']);

    // Orders
    Route::get('/orders', [B2BController::class, 'getOrderHistory']);
    Route::get('/orders/{id}', [B2BController::class, 'getOrderDetails']);
    Route::get('/orders/{id}/invoice', [B2BController::class, 'getOrderInvoice']);

    // Quotes
    Route::post('/quotes', [B2BController::class, 'requestQuote']);
    Route::get('/quotes', [B2BController::class, 'getQuotes']);
    Route::get('/quotes/{id}', [B2BController::class, 'getQuoteDetails']);
    Route::post('/quotes/{id}/accept', [B2BController::class, 'acceptQuote']);

    // Reports
    Route::get('/reports/orders', [B2BController::class, 'getOrdersReport']);
    Route::get('/reports/spending', [B2BController::class, 'getSpendingReport']);
});
