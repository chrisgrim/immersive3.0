<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Backs the Hub's deliberate "edit a saved search" flow — stricter than
 * StoreSavedSearchRequest (which backs the passive auto-save POST every nav
 * search fires): searchType is required, and each mode's own fields are
 * required rather than everything being optional. UpdateSavedSearchAction
 * additionally nulls out the other mode's fields server-side regardless of
 * what's submitted, so validation here only needs to require what each mode
 * actually needs, not prohibit the other mode's fields too.
 */
class UpdateSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'criteria' => 'required|array',
            'criteria.searchType' => 'required|in:inPerson,atHome',

            'criteria.city' => 'required_if:criteria.searchType,inPerson|nullable|string|max:255',
            'criteria.lat' => 'required_if:criteria.searchType,inPerson|nullable|numeric|between:-90,90',
            'criteria.lng' => 'required_if:criteria.searchType,inPerson|nullable|numeric|between:-180,180',

            'criteria.remoteLocation' => 'required_if:criteria.searchType,atHome|nullable|string|max:255',

            'criteria.live' => 'nullable|boolean',
            // A "custom map search" (dragging/zooming the map rather than
            // picking a city) — see BuildSearchUrlAction/ListingsController::
            // buildMapBoundaryFilter(). Optional even in Location mode; a
            // typed city never sets these.
            'criteria.NElat' => 'nullable|numeric|between:-90,90',
            'criteria.NElng' => 'nullable|numeric|between:-180,180',
            'criteria.SWlat' => 'nullable|numeric|between:-90,90',
            'criteria.SWlng' => 'nullable|numeric|between:-180,180',
            'criteria.start' => 'nullable|date',
            'criteria.end' => 'nullable|date',
            'criteria.categories' => 'nullable|array',
            'criteria.categories.*' => 'integer|exists:categories,id',
            'criteria.tags' => 'nullable|array',
            'criteria.tags.*' => 'integer|exists:genres,id',
            'criteria.price' => 'nullable|array|size:2',
            'criteria.price.*' => 'nullable|numeric',
        ];
    }
}
