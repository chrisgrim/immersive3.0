<?php

namespace App\Mcp\Tools;

use App\Models\AttendanceType;
use App\Models\Category;
use App\Models\Events\AgeLimit;
use App\Models\Events\ContactLevel;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\InteractiveLevel;
use App\Models\Events\MobilityAdvisory;
use App\Models\Genre;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the valid options for an event attribute so you can pass real IDs to update-event: categories (filterable by attendance type), genres, contact_levels, interactive_levels, age_limits, attendance_types, plus existing content_advisories and mobility_advisories names for consistency (those two are free-form).')]
class ListEventAttributes extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'type' => 'required|string|in:categories,genres,contact_levels,interactive_levels,age_limits,attendance_types,content_advisories,mobility_advisories',
            'attendance_type_id' => 'nullable|integer|exists:attendance_types,id',
        ]);

        $data = match ($validated['type']) {
            'categories' => $this->categories($validated['attendance_type_id'] ?? null),
            'genres' => Genre::where('admin', true)
                ->orWhere('user_id', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'contact_levels' => ContactLevel::orderBy('id')->get(['id', 'name']),
            'interactive_levels' => InteractiveLevel::orderBy('id')->get(['id', 'name', 'description']),
            'age_limits' => AgeLimit::orderBy('id')->get(['id', 'name']),
            'attendance_types' => AttendanceType::orderBy('id')->get(['id', 'name']),
            // Same visibility as the wizard's suggestion dropdowns: curated
            // (admin) advisories plus ones this user created previously.
            'content_advisories' => ContentAdvisory::where('admin', true)
                ->orWhere('user_id', $request->user()->id)
                ->orderBy('name')->pluck('name'),
            'mobility_advisories' => MobilityAdvisory::where('admin', true)
                ->orWhere('user_id', $request->user()->id)
                ->orderBy('name')->pluck('name'),
        };

        return Response::json([
            'type' => $validated['type'],
            'options' => $data,
        ]);
    }

    protected function categories(?int $attendanceTypeId)
    {
        $categories = Category::orderBy('name');

        if ($attendanceTypeId !== null) {
            $categories->where(function ($query) use ($attendanceTypeId) {
                $query->whereJsonContains('applicable_attendance_types', $attendanceTypeId)
                    ->orWhereNull('applicable_attendance_types')
                    ->orWhere('applicable_attendance_types', '[]');
            });
        }

        return $categories->get(['id', 'name', 'applicable_attendance_types'])
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                // null/empty means the category supports every attendance type
                'applicable_attendance_types' => $category->applicable_attendance_types ?: 'all',
            ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['categories', 'genres', 'contact_levels', 'interactive_levels', 'age_limits', 'attendance_types', 'content_advisories', 'mobility_advisories'])
                ->description('Which attribute list to fetch.')
                ->required(),
            'attendance_type_id' => $schema->integer()
                ->description('Optional, only for type=categories: restrict to categories compatible with this attendance type (1 = In Person, 2 = Remote).'),
        ];
    }
}
