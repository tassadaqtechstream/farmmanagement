<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvestmentRequest;
use App\Models\Investment;
use App\Models\UserFarm;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    public function index()
    {
        return Investment::with(['user', 'farm'])->get();
    }

    /**
     * @OA\Post(
     *      path="/api/investments",
     *      operationId="storeInvestment",
     *      tags={"Investments"},
     *      summary="Store new investment",
     *      description="Stores a new investment in the system",
     *      security={{"Bearer":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/InvestmentRequest")
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/Investment")
     *       ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *       )
     * )
     */
    public function store(InvestmentRequest $request)
    {
        $validated = $request->validated();



        $investment = Investment::create($validated);

        return response()->json($investment, 201);
    }

    public function show(Investment $investment)
    {
        return $investment->load(['user', 'farm']);
    }

    public function update(InvestmentRequest $request, Investment $investment)
    {
        $validated = $request->validated();

        $investment->update($validated);

        return response()->json($investment, 200);
    }

    public function destroy(Investment $investment)
    {
        $investment->delete();

        return response()->json(null, 204);
    }
}
