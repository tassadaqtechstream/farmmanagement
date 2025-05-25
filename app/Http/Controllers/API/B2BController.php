<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class B2BController extends Controller
{
    /**
     * Constructor with middleware
     */
    public function __construct()
    {
        /*// Apply permissions middleware to specific methods
        // For Passport, we need to specify the guard when checking permissions
        $this->middleware(['auth:api', 'permission:manage b2b orders|place b2b orders'])->only(['placeOrder', 'getOrderHistory', 'getOrderDetails']);
        $this->middleware(['auth:api', 'permission:request b2b quotes'])->only(['requestQuote']);
        $this->middleware(['auth:api', 'permission:view b2b catalog'])->only(['getBusinessCatalog']);*/
    }

    /**
     * Register a new business account
     */

    public function registerBusiness(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required|string|same:password',
            'phone_number' => 'required|string|max:20',
            'company' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'company_activity_id' => 'nullable|integer',
            'city' => 'nullable|string|max:100',
            'address' => 'required|string|max:500',
            'fiscal_address' => 'nullable|string|max:500',
            'zip_code' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'product_category' => 'nullable|string|max:100',
            'user_type' => 'required|string|in:buyer,seller,both',
            'vatin' => 'nullable|string|max:255',
            'preferred_language' => 'nullable|string|max:50',
            'preferred_product_ids' => 'nullable|array',
            'preferred_product_ids.*' => 'integer',
            'other_preferred_products' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Begin transaction to ensure both user and profile are created
        DB::beginTransaction();

        try {
            // Create the user
            $user = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->phone_number,
            ]);

            // Create user profile
            $profile = UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'company' => $request->company,
                'business_type' => $request->business_type,
                'vatin' => $request->vatin,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'fiscal_address' => $request->fiscal_address ?? $request->address, // Use address as fiscal_address if not provided
                'zip_code' => $request->zip_code,
                'city' => $request->city,
                'country' => $request->country,
                'description' => $request->description,
                'product_category' => $request->product_category,
                'company_activity_id' => $request->company_activity_id ?? 1, // Default to 1 or handle appropriately
                'preferred_language' => $request->preferred_language ?? 'en', // Default to English
                'preferred_product_ids' => json_encode($request->preferred_product_ids ?? []),
                'other_preferred_products' => $request->other_preferred_products,
            ]);



            // If the user selected "both" as role, assign both buyer and seller roles
            if ($request->user_type === 'both') {
                $buyerRole = Role::firstOrCreate(['name' => 'buyer']);
                $sellerRole = Role::firstOrCreate(['name' => 'seller']);
                $user->assignRole($buyerRole);
                $user->assignRole($sellerRole);
            }else{
                // Find or create the role
                $role = Role::firstOrCreate(['name' => $request->user_type]);

                // Assign role to user
                $user->assignRole($role);
            }

            DB::commit();

            // Generate token for the user
            $token = $user->createToken('Personal Access Token')->accessToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user->load('profile', 'roles'),
                'token' => $token,
                'role' => $request->user_type
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get business-specific catalog with custom pricing
     */
    public function getBusinessCatalog(Request $request)
    {
        $user = $request->user();
        $business = Business::find($user->business_id);

        if (!$business || $business->status !== 'approved') {
            return response()->json(['message' => 'Unauthorized or pending approval'], 403);
        }

        // Get products with business-specific pricing
        $products = Product::with(['businessPricing' => function ($query) use ($business) {
            $query->where('business_id', $business->id);
        }])
            ->where('is_b2b_available', true)
            ->paginate(20);

        // Format products with correct pricing
        $formattedProducts = $products->map(function ($product) {
            // If business has custom pricing, use it, otherwise use the default B2B price
            $price = $product->businessPricing->first() ?
                $product->businessPricing->first()->price :
                $product->b2b_price;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $price,
                'min_order_quantity' => $product->b2b_min_quantity,
                'stock' => $product->stock,
                'images' => $product->images,
                // Add other necessary fields
            ];
        });

        return response()->json([
            'products' => $formattedProducts,
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    /**
     * Place a B2B order
     */
    public function placeOrder(Request $request)
    {
        // Determine if this is a guest or authenticated user
        $isGuest = !auth()->check();
        $user = $isGuest ? null : $request->user();
        $isB2BOrder = !$isGuest && $user->business_id;

        // Different validation rules based on user type
        $validationRules = [

            'shipping_address' => 'required|string',
            'billing_address' => 'required|string',
            'payment_method' => 'required|string',
            'shipping_method' => 'required|string',
        ];

        // Additional validation for guest users
        if ($isGuest) {
            $validationRules = array_merge($validationRules, [
                'guest_email' => 'required|email',
                'guest_name' => 'required|string',
                'guest_phone' => 'required|string',
                'payment_method' => 'required|string|in:credit,bank_transfer', // No invoice option for guests
            ]);
        }

        // Additional validation for B2B users
        if ($isB2BOrder) {
            $validationRules = array_merge($validationRules, [
                'purchase_order_number' => 'required|string',
                'payment_method' => 'required|string|in:credit,invoice,bank_transfer',
            ]);
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Business checks for B2B orders
        $business = null;
        if ($isB2BOrder) {
            $business = Business::find($user->business_id);

            if (!$business || $business->status !== 'approved') {
                return response()->json(['message' => 'Unauthorized or pending approval'], 403);
            }

            // Check if user has permission to place orders
            if (!$user->hasPermissionTo('place b2b orders', 'api')) {
                return response()->json(['message' => 'You do not have permission to place orders'], 403);
            }
        }

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Calculate total and validate inventory
            $total = 0;
            $orderItems = [];

            foreach ($request->products as $item) {

                $product = Product::findOrFail($item['id']);

                // B2B specific checks
              /*  if ($isB2BOrder) {
                    // Check if product is available for B2B
                    if (!$product->is_b2b_available) {
                        throw new \Exception("Product {$product->name} is not available for B2B orders");
                    }

                    // Check minimum order quantity
                    if ($item['quantity'] < $product->b2b_min_quantity) {
                        throw new \Exception("Minimum order quantity for {$product->name} is {$product->b2b_min_quantity}");
                    }
                } else {
                    // Check if product is available for regular customers
                    if (!$product->is_active) {
                        throw new \Exception("Product {$product->name} is not available");
                    }
                }*/

                // Check inventory for all orders
             /*   if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}");
                }*/

                // Determine price based on user type
                $price = $product->price; // Default retail price

                if ($isB2BOrder) {
                    // Get business-specific price if available
                    $businessPrice = $product->businessPricing()
                        ->where('business_id', $business->id)
                        ->first();

                    $price = $businessPrice ? $businessPrice->price : $product->b2b_price;
                }

                $itemTotal = $price * $item['quantity'];
                $total += $itemTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $price,
                    'total' => $itemTotal,
                ];
                Log::info('data',['data',$orderItems]);
                // Update inventory
                $product->stock -= $item['quantity'];
                $product->save();
            }

            // Check if the business is within credit limit for invoice payments
            if ($isB2BOrder && $request->payment_method === 'invoice') {
                $currentOutstanding = Order::where('business_id', $business->id)
                    ->where('status', 'awaiting_payment')
                    ->sum('total');

                $totalCredit = $currentOutstanding + $total;

                if ($business->credit_limit && $totalCredit > $business->credit_limit) {
                    throw new \Exception("This order would exceed your available credit limit");
                }
            }

            // Create order data array
            $orderData = [
                'total' => $total,
                'status' => $request->payment_method === 'invoice' ? 'awaiting_payment' : 'processing',
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
                'payment_method' => $request->payment_method,
                'shipping_method' => $request->shipping_method,
                'notes' => $request->notes,
                'is_guest_order' => $isGuest,
            ];

            // Add B2B specific data
            if ($isB2BOrder) {
                $orderData = array_merge($orderData, [
                    'business_id' => $business->id,
                    'user_id' => $user->id,
                    'purchase_order_number' => $request->purchase_order_number,
                    'payment_terms' => $business->payment_terms ?? 'net_30',
                ]);
            }
            // Add guest specific data
            else if ($isGuest) {
                $orderData = array_merge($orderData, [
                    'guest_email' => $request->guest_email,
                    'guest_name' => $request->guest_name,
                    'guest_phone' => $request->guest_phone,
                    'payment_terms' => 'immediate',
                ]);
            }
            // Add regular user data
            else {
                $orderData = array_merge($orderData, [
                    'user_id' => $user->id,
                    'payment_terms' => 'immediate',
                ]);
            }

            // Create order

            $order = Order::create($orderData);

            // Create order items
            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            // Record order history
            $order->statusHistory()->create([
                'status' => $order->status,
                'comment' => 'Order created',
                'user_id' => $isGuest ? null : $user->id,
                'created_by_type' => $isGuest ? 'guest' : 'user',
            ]);

            // Process payment if not invoice
            if ($request->payment_method !== 'invoice') {
                // Implement payment processing logic based on the payment method
                // This is a placeholder for payment processing
                $paymentProcessed = true;

                if (!$paymentProcessed) {
                    throw new \Exception("Payment processing failed");
                }
            }

            // Commit transaction
            DB::commit();

            // Log activity using Spatie Activity Log if installed (only for authenticated users)
            if (!$isGuest && class_exists('\Spatie\Activitylog\Models\Activity')) {
                activity()
                    ->performedOn($order)
                    ->causedBy($user)
                    ->withProperties(['order_id' => $order->id, 'total' => $total])
                    ->log($isB2BOrder ? 'placed a B2B order' : 'placed an order');
            }

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order->load('items'),
            ]);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'message' => 'Order placement failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get business order history
     */
    public function getOrderHistory(Request $request)
    {
        $user = $request->user();
        $business = Business::find($user->business_id);

        if (!$business) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if user has permission to view orders
        $viewAll = $user->hasPermissionTo('manage b2b orders', 'api');
        $viewOwn = $user->hasPermissionTo('place b2b orders', 'api');

        $query = Order::where('business_id', $business->id);

        // If user can only view their own orders
        if (!$viewAll && $viewOwn) {
            $query->where('user_id', $user->id);
        }

        // If user has neither permission
        if (!$viewAll && !$viewOwn) {
            return response()->json(['message' => 'You do not have permission to view orders'], 403);
        }

        $orders = $query->with('items.product')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'orders' => $orders,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ]
        ]);
    }

    /**
     * Get a specific order details
     */
    public function getOrderDetails(Request $request, $orderId)
    {
        $user = $request->user();
        $business = Business::find($user->business_id);

        if (!$business) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if user has permission to view orders
        $viewAll = $user->hasPermissionTo('manage b2b orders', 'api');
        $viewOwn = $user->hasPermissionTo('place b2b orders', 'api');

        $query = Order::where('id', $orderId)
            ->where('business_id', $business->id);

        // If user can only view their own orders
        if (!$viewAll && $viewOwn) {
            $query->where('user_id', $user->id);
        }

        // If user has neither permission
        if (!$viewAll && !$viewOwn) {
            return response()->json(['message' => 'You do not have permission to view this order'], 403);
        }

        $order = $query->with(['items.product', 'statusHistory', 'documents'])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'order' => $order
        ]);
    }

    /**
     * Request a quote for bulk orders
     */
    public function requestQuote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $business = Business::find($user->business_id);

        if (!$business || $business->status !== 'approved') {
            return response()->json(['message' => 'Unauthorized or pending approval'], 403);
        }

        // Check if user has permission to request quotes
        if (!$user->hasPermissionTo('request b2b quotes', 'api')) {
            return response()->json(['message' => 'You do not have permission to request quotes'], 403);
        }

        // Start transaction
        DB::beginTransaction();

        try {
            // Create quote request
            $quote = Quote::create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            // Add products to quote
            foreach ($request->products as $item) {
                $product = Product::findOrFail($item['id']);

                $quote->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => null, // To be filled by admin
                ]);
            }

            DB::commit();

            // Notify admins about new quote request
            // Find users with permission to manage quotes
            $adminUsers = User::permission('manage b2b quotes', 'api')->get();

            // Implement notification logic here
            // Example:
            // Notification::send($adminUsers, new NewQuoteRequest($quote));

            // Log activity
            if (class_exists('\Spatie\Activitylog\Models\Activity')) {
                activity()
                    ->performedOn($quote)
                    ->causedBy($user)
                    ->withProperties(['quote_id' => $quote->id])
                    ->log('requested a B2B quote');
            }

            return response()->json([
                'message' => 'Quote request submitted successfully',
                'quote' => $quote->load('items.product'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Quote request failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Add business user with specific role
     */
    public function addBusinessUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:business_admin,business_manager,business_purchaser,business_viewer',
            'job_title' => 'nullable|string',
            'department' => 'nullable|string',
            'phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $business = Business::find($user->business_id);

        if (!$business || $business->status !== 'approved') {
            return response()->json(['message' => 'Unauthorized or pending approval'], 403);
        }

        // Check if current user has permission to manage users
        if (!$user->hasPermissionTo('manage b2b users', 'api')) {
            return response()->json(['message' => 'You do not have permission to add users'], 403);
        }

        DB::beginTransaction();

        try {
            // Create the new user
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'business_id' => $business->id,
                'job_title' => $request->job_title,
                'department' => $request->department,
                'phone' => $request->phone,
            ]);

            // Assign role
            $role = Role::findByName($request->role, 'api');
            $newUser->assignRole($role);

            DB::commit();

            return response()->json([
                'message' => 'Business user added successfully',
                'user' => $newUser
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to add business user',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get available business roles and their permissions
     */
    public function getBusinessRoles()
    {
        // Get all roles that contain 'business' in the name
        $roles = Role::where('name', 'like', '%business%')
            ->where('guard_name', 'api')
            ->with('permissions')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')
                ];
            });

        return response()->json([
            'roles' => $roles
        ]);
    }
}
