<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RewardsRequest;
use Illuminate\Http\Request;
use App\Models\Reward;
use App\Models\Wallet;
use App\Models\RewardsHistory;
use Illuminate\Support\Facades\Auth;

class RewardsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/rewards",
     *     summary="Get the rewards for the authenticated user",
     *     tags={"Rewards"},
     *     security={{"Bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="total_rewards", type="string"),
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(
     *                         property="amount",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="title", type="string"),
     *                             @OA\Property(property="amount", type="string")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="total_rewards", type="string", example="0.00"),
     *                     @OA\Property(
     *                         property="amount",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="title", type="string"),
     *                             @OA\Property(property="amount", type="string", example="0.00")
     *                         )
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="No rewards found")
     * )
     */
    public function getRewards()
    {
        $user = Auth::user();
        $reward = Reward::where('user_id', $user->id)->first();

        if (!$reward) {
            $response = [
                'total_rewards' => '0.00',
                'amount' => [
                    [
                        'title' => 'Cashback',
                        'amount' => '0.00',
                    ],
                    [
                        'title' => 'Referrals',
                        'amount' => '0.00',
                    ],
                    [
                        'title' => 'Promotions',
                        'amount' => '0.00',
                    ],
                ],
            ];

            return response()->json($response, 200);
        }

        $response = [
            'total_rewards' => $reward->total_rewards,
            'id' => $reward->id,
            'amount' => [
                [
                    'title' => 'Cashback',
                    'amount' => $reward->cashback_amount??'0.00',
                ],
                [
                    'title' => 'Referrals',
                    'amount' => $reward->referrals_amount ?? '0.00',
                ],
                [
                    'title' => 'Promotions',
                    'amount' => $reward->promotions_amount ??'0.00',
                ],
            ],
        ];

        return response()->json($response);
    }



    /**
     * @OA\Post(
     *     path="/api/rewards",
     *     summary="Add or update the rewards for the authenticated user",
     *     tags={"Rewards"},
     *     security={{"Bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="total_rewards", type="number", format="float"),
     *             @OA\Property(property="cashback", type="number", format="float"),
     *             @OA\Property(property="referrals", type="number", format="float"),
     *             @OA\Property(property="promotions", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rewards updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="reward", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="total_rewards", type="number", format="float"),
     *                 @OA\Property(property="cashback", type="number", format="float"),
     *                 @OA\Property(property="referrals", type="number", format="float"),
     *                 @OA\Property(property="promotions", type="number", format="float"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="wallet", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="cash_balance", type="number", format="float"),
     *                 @OA\Property(property="rewards_balance", type="number", format="float"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function addOrUpdateRewards(RewardsRequest $request)
    {
        $user = Auth::user();
        $reward = Reward::firstOrCreate(['user_id' => $user->id]);

        $totalRewardsChange = $request->input('total_rewards');
        $cashbackChange = $request->input('cashback');
        $referralsChange = $request->input('referrals');
        $promotionsChange = $request->input('promotions');
        $type = $request->input('type');

        // Adjust the change values if the type is debit
        if ($type === 'debit') {
            $totalRewardsChange = -$totalRewardsChange;
            $cashbackChange = -$cashbackChange;
            $referralsChange = -$referralsChange;
            $promotionsChange = -$promotionsChange;
        }else{
            $type= 'credit';

        }

        // Create a history record before updating
        RewardsHistory::create([
            'user_id' => $user->id,
            'total_rewards' => $totalRewardsChange,
            'cashback' => $cashbackChange,
            'referrals' => $referralsChange,
            'promotions' => $promotionsChange,
            'type' => $type
        ]);

        // Update the current rewards
        $reward->total_rewards += $request->input('total_rewards');
        $reward->cashback += $request->input('cashback');
        $reward->referrals += $request->input('referrals');
        $reward->promotions += $request->input('promotions');
        $reward->save();

        // Sync with wallet
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
        $wallet->rewards_balance = $reward->total_rewards;
        $wallet->save();

        return response()->json(['message' => 'Rewards updated successfully', 'reward' => $reward, 'wallet' => $wallet]);
    }

    /**
     * @OA\Get(
     *     path="/api/rewards/history",
     *     summary="Get the rewards history for the authenticated user",
     *     tags={"Rewards"},
     *     security={{"Bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="total_rewards", type="number", format="float"),
     *                 @OA\Property(property="cashback", type="number", format="float"),
     *                 @OA\Property(property="referrals", type="number", format="float"),
     *                 @OA\Property(property="promotions", type="number", format="float"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No rewards history found")
     * )
     */
    public function getRewardsHistory()
    {
        $user = Auth::user();
        $history = RewardsHistory::where('user_id', $user->id)->get();
        if ($history->isEmpty()) {
            return response()->json(['message' => 'No rewards history found'], 404);
        }

        return response()->json($history);
    }
}
