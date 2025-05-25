<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    public function addProject(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'location' => 'required|string',
                'size' => 'required|integer',
                'funding' => 'required|numeric',
                'annual_return' => 'required|numeric',
                'gross_yield' => 'required|numeric',
                'net_yield' => 'required|numeric',
                'amount' => 'required|numeric',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            // Upload image if it exists
            if ($request->hasFile('image')) {
                $imageName = time().'.'.$request->image->extension();

                $request->image->move(public_path('images'), $imageName);
            }



            // Create a new project
            $project = Project::create([
                'name' => $request->name,
                'location' => $request->location,
                'size' => $request->size,
                'funding' => $request->funding,
                'annual_return' => $request->annual_return,
                'gross_yield' => $request->gross_yield,
                'net_yield' => $request->net_yield,
                'image' => $imageName,
                'amount' => $request->amount,
            ]);
            return response()->json($project, 200);
        }catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred'.$e->getMessage()], 500);
        }

    }

    public function getAllPaginatedData(Request $request)
    {
        // Get all projects with pagination
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // Fetch paginated farm configurations with userFarm relationships
        $farmConfigurations = Project::paginate($pageSize, ['*'], 'page', $page);

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
     *     path="/api/get-project-list",
     *     summary="Get all paginated project data",
     *     description="Retrieve a list of available, funded, and completed projects with pagination.",
     *     operationId="getAllPaginatedDataList",
     *     tags={"Projects"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="available_projects",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Project")),
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="page", type="integer", example=1),
     *                 @OA\Property(property="pageSize", type="integer", example=10)
     *             ),
     *             @OA\Property(
     *                 property="funded_project",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Project")),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="page", type="integer", example=1),
     *                 @OA\Property(property="pageSize", type="integer", example=10)
     *             ),
     *             @OA\Property(
     *                 property="completed_projects",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Project")),
     *                 @OA\Property(property="total", type="integer", example=30),
     *                 @OA\Property(property="page", type="integer", example=1),
     *                 @OA\Property(property="pageSize", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function getAllPaginatedDataList(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $projects = Project::whereNotIn('id', function ($query) {
            $query->select('farm_id')->from('investments');
        })->whereStatus('active')->paginate($pageSize, ['*'], 'page', $page);

        $investedProjects = Project::whereIn('id', function ($query) {
            $query->select('farm_id')->from('investments');
        })->whereStatus('active')->paginate($pageSize, ['*'], 'page', $page);

        $completedProjects = Project::where('status', 'completed')->paginate($pageSize, ['*'], 'page', $page);

        return response()->json([
            'available_projects' => [
                'data' => $projects->items(),
                'total' => $projects->total(),
                'page' => $projects->currentPage(),
                'pageSize' => $projects->perPage()
            ],
            'funded_project' => [
                'data' => $investedProjects->items(),
                'total' => $investedProjects->total(),
                'page' => $investedProjects->currentPage(),
                'pageSize' => $investedProjects->perPage()
            ],
            'completed_projects' => [
                'data' => $completedProjects->items(),
                'total' => $completedProjects->total(),
                'page' => $completedProjects->currentPage(),
                'pageSize' => $completedProjects->perPage()
            ]
        ]);
    }
}
