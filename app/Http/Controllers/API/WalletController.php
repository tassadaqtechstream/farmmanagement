<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;


class WalletController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/wallet",
     *     summary="Get wallet balances",
     *     tags={"Wallet"},
     *     security={{"Bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="available_balance", type="number", format="float"),
     *             @OA\Property(property="cash_balance", type="number", format="float"),
     *             @OA\Property(property="reward_balance", type="number", format="float")
     *         )
     *     )
     * )
     */
    public function getWallet()
    {
        $wallet = Auth::user()->wallet;
        // check if exists
        if ($wallet) {
            return response()->json([
                'available_balance' => $wallet->cash_balance + $wallet->rewards_balance,
                'cash_balance' => $wallet->cash_balance,
                'reward_balance' => $wallet->rewards_balance,
            ]);
        }
        return response()->json([
            'available_balance' => 0.00,
            'cash_balance' => 0.00,
            'reward_balance' => 0.00,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/wallet/add-funds",
     *     summary="Add funds to wallet",
     *     tags={"Wallet"},
     *     security={{"Bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="type", type="string", enum={"cash", "rewards"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Funds added successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input"
     *     )
     * )
     */
    public function addFunds(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:cash,rewards',
            'user_id' => 'nullable|exists:users,id', // `user_id` is optional and must exist if provided
        ]);

        // Determine the user ID
        $userId = $request->user_id ?? Auth::id();

        // Retrieve or create the user's wallet
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['cash_balance' => 0.00, 'rewards_balance' => 0.00]
        );

        $amount = $request->amount;
        $type = $request->type;

        // Add funds to the appropriate balance type
        if ($type === 'cash') {
            $wallet->cash_balance += $amount;
        } else {
            $wallet->rewards_balance += $amount;
        }

        $wallet->save();

        // Log the transaction
        Transaction::create([
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'type' => 'credit',
            'description' => ucfirst($type) . ' Funds Added',
        ]);

        return response()->json(['message' => 'Funds added successfully.']);
    }

    /**
     * @OA\Post(
     *     path="/api/wallet/withdraw",
     *     summary="Withdraw funds from wallet",
     *     tags={"Wallet"},
     *     security={{"Bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="type", type="string", enum={"cash", "rewards"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Funds withdrawn successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Insufficient balance"
     *     )
     * )
     */
    public function withdrawFunds(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:cash,rewards',
            'user_id' => 'nullable|exists:users,id', // Validate user_id if provided
        ]);

        // Determine user based on `user_id` or `Auth`
        $userId = $request->user_id ?? Auth::id();
        $wallet = Wallet::where('user_id', $userId)->first();

        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found.'], 404);
        }

        $amount = $request->amount;
        $type = $request->type;

        if ($type === 'cash') {
            // Check if there is sufficient cash balance
            if ($wallet->cash_balance < $amount) {
                return response()->json(['message' => 'Insufficient cash balance.'], 400);
            }
            $wallet->cash_balance -= $amount;
        } else {
            // Check if there is sufficient rewards balance
            if ($wallet->rewards_balance < $amount) {
                return response()->json(['message' => 'Insufficient rewards balance.'], 400);
            }
            $wallet->rewards_balance -= $amount;
        }

        // Save updated wallet
        $wallet->save();

        // Log the transaction
        Transaction::create([
            'wallet_id' => $wallet->id,
            'amount' => -$amount,
            'type' => 'debit',
            'description' => ucfirst($type) . ' Withdrawal',
        ]);

        return response()->json(['message' => 'Funds withdrawn successfully.']);
    }

    /**
     * @OA\Get(
     *     path="/api/wallet/transactions",
     *     summary="Get wallet transactions",
     *     tags={"Wallet"},
     *     security={{"Bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Transaction"))
     *     )
     * )
     */
    public function getTransactions()
    {
        $transactions = Auth::user()->wallet->transactions()->orderBy('created_at', 'desc')->get();

        return response()->json($transactions);
    }

    /**
     * @OA\Schema(
     *     schema="Wallet",
     *     type="object",
     *     title="Wallet",
     *     properties={
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="user_id", type="integer", example=123),
     *         @OA\Property(property="balance", type="number", format="float", example=100.50),
     *         @OA\Property(property="currency", type="string", example="USD")
     *     }
     * )
     */

    public function getUsersWithWallets(Request $request)
    {
        // Get pagination parameters
        $pageSize = $request->query('pageSize', 10); // Default to 10 items per page

        // Fetch users with wallets (eager load wallets and transactions)
        $users = User::with(['wallet', 'wallet.transactions'])
            ->paginate($pageSize); // Automatically handles `page` and `per_page`

        // Transform data to include calculated balances
        $usersTransformed = $users->getCollection()->map(function ($user) {
            $wallet = $user->wallet;

            // If the user has a wallet, calculate balances
            if ($wallet) {
                $credit = $wallet->transactions->where('type', 'credit')->sum('amount');
                $debit = $wallet->transactions->where('type', 'debit')->sum('amount');
                $availableBalance = $wallet->cash_balance - $debit + $credit;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'cash_balance' => (float) $wallet->cash_balance,
                    'rewards_balance' => (float) $wallet->rewards_balance,
                    'credit' => (float) $wallet->credit,
                    'debit' => (float) $wallet->debit,
                    'available_balance' => (float) $wallet->available_balance,

                    'transactions' => $wallet->transactions,
                ];
            }

            // If the user does not have a wallet, return default values
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cash_balance' => 0,
                'rewards_balance' => 0,
                'credit' => 0,
                'debit' => 0,
                'available_balance' => 0,
                'transactions' => [],
            ];
        });

        // Return paginated response
        return response()->json([
            'success' => true,
            'data' => [
                'users' => $usersTransformed,
                'total' => $users->total(),
                'page' => $users->currentPage(),
                'pageSize' => $users->perPage(),
            ],
        ]);
    }
}
