<?php

namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use App\Http\Requests\FarmConfigurationRequest;
use App\Http\Resources\FarmConfigurationCollection;
use App\Http\Resources\FarmConfigurationResource;
use App\Models\FarmConfiguration;
use Illuminate\Http\Request;

class FarmConfigurationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/configured-projects",
     *     summary="Get paginated Projects",
     *     description="Get paginated Projects",
     *     operationId="getConfiguredProjects",
     *     tags={"FarmConfigurations"},
     *     security={{"Bearer":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             default=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             default=10
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/FarmConfigurationResource")
     *             ),
     *             @OA\Property(
     *                 property="total",
     *                 type="integer",
     *                 example=100
     *             ),
     *             @OA\Property(
     *                 property="page",
     *                 type="integer",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="pageSize",
     *                 type="integer",
     *                 example=10
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function getConfiguredProjects(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // Fetch paginated farm configurations with userFarm relationships
        $farmConfigurations = FarmConfiguration::with('userFarms')
            ->paginate($pageSize, ['*'], 'page', $page);

        // Create the response structure
        return response()->json([
            'data' => FarmConfigurationResource::collection($farmConfigurations->items()),
            'total' => $farmConfigurations->total(),
            'page' => $farmConfigurations->currentPage(),
            'pageSize' => $farmConfigurations->perPage()
        ]);
    }


    public function store(Request $request)
    {

        $configuration = FarmConfiguration::create([

        ]);
        return response()->json($configuration, 201);
    }

    public function show(FarmConfiguration $farmConfiguration)
    {
        return response()->json($farmConfiguration);
    }

    public function update(FarmConfigurationRequest $request, FarmConfiguration $farmConfiguration)
    {
        $farmConfiguration->update($request->validated());
        return response()->json($farmConfiguration, 200);
    }

    public function destroy(FarmConfiguration $farmConfiguration)
    {
        $farmConfiguration->delete();
        return response()->json(null, 204);
    }

    public function postConfiguration(Request $request)
    {
        try {
            $data = FarmConfiguration::create([
                'farm_id' => $request->farm_id,
                'investment_percentage' => $request->investment_percentage,
                'investment_period' => $request->investment_period,
                'min_investment_amount' => $request->min_investment_amount,
                'is_active' => $request->is_active,

            ]);
            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Internal Server Error'.$e->getMessage()], 500);
        }
    }
}
