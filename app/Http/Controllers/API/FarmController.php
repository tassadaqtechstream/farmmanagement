<?php

namespace App\Http\Controllers\API;

use App\Enums\Crop;
use App\Enums\CropStage;
use App\Enums\IrrigationSource;
use App\Enums\SeedVariety;
use App\Enums\SoilType;
use App\Enums\SowingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmRequest;
use App\Models\FarmConfiguration;
use App\Models\Project;
use App\Models\UserFarm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

class FarmController extends BaseController
{
    /**
     * @OA\Post(
     *     path="/api/add-farm-details",
     *     tags={"Farm"},
     *     summary="Create a new farm",
     *     description="Creates a new farm with location, size, configuration details, and crop stage.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={
     *                     "location",
     *                     "size",
     *                     "latitude",
     *                     "longitude",
     *                     "irrigation_source",
     *                     "soil_type",
     *                     "sowing_method",
     *                     "seed_variety",
     *                     "crop",
     *                     "sowing_date",
     *                     "farm_configuration",
     *                     "crop_stage"
     *                 },
     *                 @OA\Property(property="location", type="string", example="Kampala"),
     *                 @OA\Property(property="size", type="string", example="10"),
     *                 @OA\Property(property="latitude", type="string", example="0.3131"),
     *                 @OA\Property(property="longitude", type="string", example="32.5811"),
     *                 @OA\Property(
     *                     property="irrigation_source",
     *                     type="string",
     *                     description="Dropdown selection: well, canal, rainfed",
     *                     example="well",
     *                     enum={"well", "canal", "rainfed"}
     *                 ),
     *                 @OA\Property(
     *                     property="soil_type",
     *                     type="string",
     *                     description="Dropdown selection: clay, loam, sandy",
     *                     example="clay",
     *                     enum={"clay", "loam", "sandy"}
     *                 ),
     *                 @OA\Property(
     *                     property="sowing_method",
     *                     type="string",
     *                     description="Dropdown selection: manual, mechanized, hydroponic",
     *                     example="manual",
     *                     enum={"manual", "mechanized", "hydroponic"}
     *                 ),
     *                 @OA\Property(
     *                     property="seed_variety",
     *                     type="string",
     *                     description="Dropdown selection: hybrid, organic, GMO",
     *                     example="hybrid",
     *                     enum={"hybrid", "organic", "GMO"}
     *                 ),
     *                 @OA\Property(
     *                     property="crop",
     *                     type="string",
     *                     description="Dropdown selection: wheat, rice, maize",
     *                     example="wheat",
     *                     enum={"wheat", "rice", "maize"}
     *                 ),
     *                 @OA\Property(property="sowing_date", type="string", example="2021-09-01"),
     *                 @OA\Property(property="name", type="string", example="Farm 1"),
     *                 @OA\Property(
     *                     property="farm_configuration",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="latitude", type="number", example=1),
     *                         @OA\Property(property="longitude", type="number", example=10),
     *                         @OA\Property(property="id", type="integer", example=1)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="crop_stage",
     *                     type="string",
     *                     description="Dropdown selection: sapling_stage, vegetative_growth, flowering_stage, fruit_setting, fruit_development, harvesting, post_harvest_ripening",
     *                     example="vegetative_growth",
     *                     enum={"sapling_stage", "vegetative_growth", "flowering_stage", "fruit_setting", "fruit_development", "harvesting", "post_harvest_ripening"}
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Farm created successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Farm created successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Resource Not Found",
     *         @OA\JsonContent()
     *     ),
     * )
     */

    public function addFarmDetails(FarmRequest $request)
    {
        $data = $request->validated();

        // Ensure farm_configuration is properly stored
        $farmConfiguration = $request->input('farm_configuration');

        $data['user_id'] = auth()->user()->id;
        $data['farm_configuration'] = $farmConfiguration;

        UserFarm::create($data);

        return $this->sendResponse($data, 'Farm details added successfully');
    }


    /**
     * @OA\Get(
     *     path="/api/get-farm-list",
     *     tags={"Farm"},
     *     summary="Get list of farms for the authenticated user",
     *     operationId="getFarmList",
     *     security={{"Bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Farm details retrieved successfully",
     *
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     * )
     */
    public function getFarmList(Request $request)
    {
        // with related data
        $data = UserFarm::where('user_id', auth()->user()->id)->get();
        $data->map(function ($farm) {
            $farm->farm_configuration = json_decode($farm->farm_configuration, true);
            return $farm;
        });
        return $this->sendResponse($data, 'Farm details retrieved successfully');
    }

    public function getAllFarms()
    {
        $data = UserFarm::all();
        return $this->sendResponse($data, 'Farm details retrieved successfully');
    }

    /**
     * Display available enum options.
     *
     * @OA\Get(
     *     path="/api/farm/enums",
     *     summary="Get available enums for farm",
     *     tags={"Farm"},
     *     security={{"Bearer":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of Enums",
     *         @OA\JsonContent(
     *             @OA\Property(property="irrigation_source", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="soil_type", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="sowing_method", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="seed_variety", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="crop", type="array", @OA\Items(type="string")),
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function getEnums(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'irrigation_source' => IrrigationSource::cases(),
            'soil_type' => SoilType::cases(),
            'sowing_method' => SowingMethod::cases(),
            'seed_variety' => SeedVariety::cases(),
            'crop' => Crop::cases(),
            'crop_stage' => CropStage::cases(),
        ]);
    }


    public function getAllPaginatedFarm(Request $request)
    {
        // Get all projects with pagination
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // Fetch paginated farm configurations with userFarm relationships
        $farmConfigurations = UserFarm::paginate($pageSize, ['*'], 'page', $page);

        // Create the response structure
        return response()->json([
            'data' => $farmConfigurations->items(),
            'total' => $farmConfigurations->total(),
            'page' => $farmConfigurations->currentPage(),
            'pageSize' => $farmConfigurations->perPage()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/farm-statistics",
     *     summary="Get farm statistics",
     *     tags={"Farm"},
     *     security={{"Bearer":{}}},
     *     description="This endpoint returns the total number of farms, total land size, and crop distribution (in acres).",
     *     @OA\Response(
     *         response=200,
     *         description="Farm statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_farms", type="integer", example=29, description="The total number of farms"),
     *             @OA\Property(property="total_land", type="integer", example=120, description="The total size of land in acres"),
     *             @OA\Property(
     *                 property="crops",
     *                 type="object",
     *                 description="Breakdown of crop distribution in acres",
     *                 @OA\Property(property="Sugarcane", type="integer", example=29, description="Acres used for Sugarcane"),
     *                 @OA\Property(property="Cotton", type="integer", example=12, description="Acres used for Cotton"),
     *                 @OA\Property(property="Wheat", type="integer", example=11, description="Acres used for Wheat"),
     *                 @OA\Property(property="Free", type="integer", example=3, description="Acres of free land")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Something went wrong")
     *         )
     *     )
     * )
     */

    public function getFarmStatistics()
    {
        // Fetch all user farms
        $userFarms = UserFarm::where('user_id',Auth::user()->id)->get();

        // Calculate total farms and total acres of land
        $totalFarms = $userFarms->count();
        $totalAcres = $userFarms->sum('size'); // Assuming `land_size` exists in UserFarm

        // Group by crop and calculate acres
        $cropStats = $userFarms->groupBy('crop')->map(function ($farms, $crop) {
            return [
                'crop' => $crop,
                'acres' => $farms->sum('size')
            ];
        });

        // Fill missing crop types with 0 acres
        $cropsEnum =Crop::cases();

        $cropsFormatted = array_map(function ($crop) use ($cropStats) {
            $size = 0; // Default value
            foreach ($cropStats as $cropData) {
                if ($cropData['crop'] === $crop) {
                    $size = $cropData['size'];
                    break;
                }
            }
            return [
                'label' => $crop,
                'value' => $size,
            ];
        }, $cropsEnum);

        return response()->json([
            'total_farms' => $totalFarms,
            'total_land' => $totalAcres,
            'crops' => $cropsFormatted,
        ]);

    }


}
