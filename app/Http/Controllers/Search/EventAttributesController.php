<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Events\RemoteLocation;
use App\Models\Events\ContactLevel;
use App\Models\Events\InteractiveLevel;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\AgeLimit;

class EventAttributesController extends Controller
{
    /**
     * Get categories, optionally filtered by attendance type
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories(Request $request)
    {
        $categories = Category::with('images')
            ->orderBy('name');
            
        // Support both old 'remote' parameter and new 'attendance_type_id' parameter
        if ($request->has('attendance_type_id')) {
            // New way: filter by attendance_type_id using the applicable_attendance_types array
            $attendanceTypeId = (int)$request->query('attendance_type_id');
            $categories->where(function($query) use ($attendanceTypeId) {
                $query->whereJsonContains('applicable_attendance_types', $attendanceTypeId)
                    ->orWhereNull('applicable_attendance_types')  // Categories with null applicable_attendance_types support all types
                    ->orWhere('applicable_attendance_types', '[]'); // Categories with empty applicable_attendance_types support all types
            });
        } elseif ($request->has('remote')) {
            // Legacy way: filter by remote flag (0 = in-person, 1 = remote)
            $remote = $request->query('remote');
            $categories->where('remote', $remote);
        }
        
        return response()->json($categories->get());
    }

    /**
     * Get genres available to the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function genres()
    {
        $genres = Genre::where('admin', true)
            ->orWhere('user_id', auth()->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json($genres);
    }

    /**
     * Get remote locations with optional search and filtering
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function remoteLocations(Request $request)
    {
        $majorPlatforms = ['zoom', 'teams', 'meet', 'webex'];
        $selectedIds = $request->get('selected', []);
        $searchTerm = $request->get('search');
        $limit = $request->get('limit', 10);

        $query = RemoteLocation::query();

        if (!empty($searchTerm)) {
            // Search mode
            return $query
                ->whereNotIn('id', $selectedIds)
                ->where('name', 'like', "%{$searchTerm}%")
                ->orderBy('name')
                ->get();
        }

        // Default mode: Major platforms first, then others
        $results = $query
            ->whereNotIn('id', $selectedIds)
            ->orderByRaw("CASE WHEN slug IN ('zoom', 'teams', 'meet', 'webex') THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json($results);
    }

    /**
     * Get all contact levels
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function contactLevels()
    {
        $levels = ContactLevel::orderBy('id')->get();
        return response()->json($levels);
    }

    /**
     * Get all interactive levels
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function interactiveLevels()
    {
        $levels = InteractiveLevel::orderBy('id')->get();
        return response()->json($levels);
    }

    /**
     * Get content advisories available to the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function contentAdvisories()
    {
        $advisories = ContentAdvisory::where('admin', true)
            ->orWhere('user_id', auth()->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json($advisories);
    }

    /**
     * Get mobility advisories available to the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function mobilityAdvisories()
    {
        $advisories = MobilityAdvisory::where('admin', true)
            ->orWhere('user_id', auth()->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json($advisories);
    }

    public function ageLimits()
    {
        return AgeLimit::orderBy('age')->get();
    }

    /**
     * Constructor to ensure authentication
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
}
