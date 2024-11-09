<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminAdvisoryController extends Controller
{
    public function index($type)
    {
        $model = $this->getModelClass($type);
        $query = $model::withoutGlobalScopes();

        $this->applySearchFilter($query);
        $this->applyTypeFilter($query, $type);
        $this->applySorting($query);

        return $query->paginate(10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:content,mobility,interactive,contact',
            'name' => 'required|string',
            'description' => 'sometimes|string',
            'rank' => 'sometimes|integer',
            'admin' => 'sometimes|boolean'
        ]);

        $model = $this->getModelClass($validated['type']);
        $data = $this->prepareStoreData($validated);

        return $model::create($data);
    }

    public function update(Request $request, $type, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'description' => 'sometimes|required|string',
            'rank' => 'sometimes|required|integer',
            'admin' => 'sometimes|required|boolean'
        ]);

        $model = $this->getModelClass($type);
        $advisory = $model::withoutGlobalScopes()->findOrFail($id);
        
        if (in_array($type, ['content', 'mobility']) && isset($validated['name'])) {
            $validated['slug'] = \Str::slug($validated['name']);
        }

        return $advisory->update($validated);
    }

    public function destroy($type, $id)
    {
        $model = $this->getModelClass($type);
        $advisory = $model::withoutGlobalScopes()->findOrFail($id);
        
        return $advisory->delete();
    }

    private function getModelClass($type)
    {
        return match ($type) {
            'content' => \App\Models\Events\ContentAdvisory::class,
            'mobility' => \App\Models\Events\MobilityAdvisory::class,
            'interactive' => \App\Models\Events\InteractiveLevel::class,
            'contact' => \App\Models\Events\ContactLevel::class,
            default => throw new \InvalidArgumentException('Invalid advisory type')
        };
    }

    private function applySearchFilter($query)
    {
        if (request()->filled('search')) {
            $search = request()->input('search');
            $query->where('name', 'like', "%{$search}%");
        }
    }

    private function applyTypeFilter($query, $type)
    {
        if (in_array($type, ['content', 'mobility']) && request()->filled('type')) {
            $adminType = request()->input('type');
            $query->where('admin', (bool)$adminType);
        }
    }

    private function applySorting($query)
    {
        $sortField = request()->input('sort_field', 'name');
        $sortDirection = request()->input('sort_direction', 'asc');
        
        if ($sortField === 'rank') {
            $query->orderBy('rank', $sortDirection)
                  ->orderBy('name', 'asc');
        } else {
            $query->orderBy($sortField, $sortDirection)
                  ->orderBy('rank', 'asc');
        }
    }

    private function prepareStoreData($validated)
    {
        $data = [
            'name' => $validated['name'],
            'rank' => $validated['rank'] ?? 0,
            'user_id' => auth()->id(),
        ];

        if ($validated['type'] === 'interactive' && isset($validated['description'])) {
            $data['description'] = $validated['description'];
        }

        if (in_array($validated['type'], ['content', 'mobility'])) {
            $data['admin'] = $validated['admin'] ?? true;
            $data['slug'] = \Str::slug($validated['name']);
        }

        return $data;
    }
} 