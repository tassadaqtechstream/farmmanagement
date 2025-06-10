<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PreHarvestListing;
use App\Models\PreHarvestBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PreHarvestController extends Controller
{
    // Get all pre-harvest listings
    public function index(Request $request)
    {
        $query = PreHarvestListing::with('user:id,name,email')
            ->available();

        // Filter by crop type
        if ($request->has('crop_type')) {
            $query->byCropType($request->crop_type);
        }

        // Filter by location
        if ($request->has('location')) {
            $query->byLocation($request->location);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('variety', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'harvest_date');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        $listings = $query->paginate($request->get('per_page', 12));

        return response()->json($listings);
    }

    // Get single listing
    public function show($id)
    {
        $listing = PreHarvestListing::with(['user:id,name,email', 'bookings'])
            ->findOrFail($id);

        return response()->json([
            'listing' => $listing,
            'related_listings' => PreHarvestListing::where('crop_type', $listing->crop_type)
                ->where('id', '!=', $listing->id)
                ->available()
                ->limit(3)
                ->get()
        ]);
    }

    // Create new listing
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'crop_type' => 'required|string',
            'variety' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'estimated_yield' => 'required|numeric|min:1',
            'price_per_kg' => 'required|numeric|min:0',
            'harvest_date' => 'required|date|after:today',
            'quality_grade' => 'required|in:premium,grade-a,grade-b,standard',
            'minimum_order' => 'required|integer|min:1',
            'organic_certified' => 'boolean',
            'description' => 'required|string',
            'terms_conditions' => 'nullable|string',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        DB::beginTransaction();
        try {
            $listing = PreHarvestListing::create([
                'user_id' => Auth::id(),
                'title' => $request->title,
                'crop_type' => $request->crop_type,
                'variety' => $request->variety,
                'location' => $request->location,
                'estimated_yield' => $request->estimated_yield,
                'price_per_kg' => $request->price_per_kg,
                'harvest_date' => $request->harvest_date,
                'quality_grade' => $request->quality_grade,
                'minimum_order' => $request->minimum_order,
                'organic_certified' => $request->boolean('organic_certified'),
                'description' => $request->description,
                'terms_conditions' => $request->terms_conditions,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                $imagePaths = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('pre-harvest-listings', 'public');
                    $imagePaths[] = $path;
                }
                $listing->update(['images' => $imagePaths]);
            }

            DB::commit();
            return response()->json($listing->load('user'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create listing'], 500);
        }
    }

    // Update listing
    public function update(Request $request, $id)
    {
        $listing = PreHarvestListing::where('user_id', Auth::id())
            ->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'variety' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'estimated_yield' => 'sometimes|numeric|min:1',
            'price_per_kg' => 'sometimes|numeric|min:0',
            'harvest_date' => 'sometimes|date|after:today',
            'quality_grade' => 'sometimes|in:premium,grade-a,grade-b,standard',
            'minimum_order' => 'sometimes|integer|min:1',
            'organic_certified' => 'sometimes|boolean',
            'description' => 'sometimes|string',
            'terms_conditions' => 'sometimes|string',
            'status' => 'sometimes|in:available,reserved,harvested,cancelled',
        ]);

        $listing->update($request->only([
            'title', 'variety', 'location', 'estimated_yield',
            'price_per_kg', 'harvest_date', 'quality_grade',
            'minimum_order', 'organic_certified', 'description',
            'terms_conditions', 'status'
        ]));

        return response()->json($listing->load('user'));
    }

    // Delete listing
    public function destroy($id)
    {
        $listing = PreHarvestListing::where('user_id', Auth::id())
            ->findOrFail($id);

        // Check if there are active bookings
        if ($listing->bookings()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return response()->json(['error' => 'Cannot delete listing with active bookings'], 422);
        }

        // Delete images
        if ($listing->images) {
            foreach ($listing->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $listing->delete();
        return response()->json(['message' => 'Listing deleted successfully']);
    }

    // Create booking
    public function createBooking(Request $request, $id)
    {
        $listing = PreHarvestListing::available()->findOrFail($id);

        $request->validate([
            'buyer_name' => 'required|string|max:255',
            'buyer_email' => 'required|email',
            'buyer_phone' => 'required|string',
            'quantity' => 'required|numeric|min:' . $listing->minimum_order,
            'special_requests' => 'nullable|string',
        ]);

        // Check if enough quantity available
        if ($request->quantity > $listing->available_quantity) {
            return response()->json(['error' => 'Requested quantity exceeds available quantity'], 422);
        }

        DB::beginTransaction();
        try {
            $totalPrice = $request->quantity * $listing->price_per_kg;
            $depositAmount = $totalPrice * 0.3; // 30% deposit

            $booking = PreHarvestBooking::create([
                'listing_id' => $listing->id,
                'buyer_id' => Auth::id(),
                'buyer_name' => $request->buyer_name,
                'buyer_email' => $request->buyer_email,
                'buyer_phone' => $request->buyer_phone,
                'quantity' => $request->quantity,
                'total_price' => $totalPrice,
                'deposit_amount' => $depositAmount,
                'special_requests' => $request->special_requests,
            ]);

            // Update listing reserved quantity
            $listing->increment('reserved_quantity', $request->quantity);

            DB::commit();
            return response()->json($booking->load('listing'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create booking'], 500);
        }
    }

    // Get user's listings
    public function myListings()
    {
        $listings = PreHarvestListing::where('user_id', Auth::id())
            ->withCount('bookings')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($listings);
    }

    // Get user's bookings
    public function myBookings()
    {
        $bookings = PreHarvestBooking::where('buyer_id', Auth::id())
            ->with(['listing:id,title,crop_type,harvest_date,price_per_kg'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    // Get analytics data
    public function analytics()
    {
        $userId = Auth::id();

        $stats = [
            'total_listings' => PreHarvestListing::where('user_id', $userId)->count(),
            'active_listings' => PreHarvestListing::where('user_id', $userId)->available()->count(),
            'total_bookings' => PreHarvestBooking::whereHas('listing', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count(),
            'total_revenue' => PreHarvestBooking::whereHas('listing', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', 'confirmed')->sum('total_price'),
            'pending_bookings' => PreHarvestBooking::whereHas('listing', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->pending()->count(),
        ];

        return response()->json($stats);
    }

    // Confirm booking (for sellers)
    public function confirmBooking($bookingId)
    {
        $booking = PreHarvestBooking::whereHas('listing', function($q) {
            $q->where('user_id', Auth::id());
        })->findOrFail($bookingId);

        $booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return response()->json($booking->load('listing'));
    }

    // Cancel booking
    public function cancelBooking($bookingId)
    {
        $booking = PreHarvestBooking::where(function($q) {
            $q->where('buyer_id', Auth::id())
                ->orWhereHas('listing', function($q2) {
                    $q2->where('user_id', Auth::id());
                });
        })->findOrFail($bookingId);

        DB::beginTransaction();
        try {
            // Return reserved quantity to listing
            $booking->listing->decrement('reserved_quantity', $booking->quantity);

            $booking->update(['status' => 'cancelled']);

            DB::commit();
            return response()->json(['message' => 'Booking cancelled successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to cancel booking'], 500);
        }
    }
}
