<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors SearchStore.vue's state shape / ListingsController's query
     * params — see SaveSearchAction, which normalizes this further before
     * fingerprinting.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'criteria' => 'required|array',
            'criteria.city' => 'nullable|string|max:255',
            // Bounded, not just numeric — same as UpdateSavedSearchRequest's
            // own lat/lng and the four map corners below. A coordinate that
            // is numeric but off the planet is what 500'd the search page
            // (EI-LARAVEL-11); it is dropped at query time now, but there is
            // no reason to store it, and '1e400' casts to INF, which does not
            // survive a json_encode of the criteria column.
            'criteria.lat' => 'nullable|numeric|between:-90,90',
            'criteria.lng' => 'nullable|numeric|between:-180,180',
            'criteria.searchType' => 'nullable|in:inPerson,atHome',
            'criteria.remoteLocation' => 'nullable|string|max:255',
            'criteria.live' => 'nullable|boolean',
            // See UpdateSavedSearchRequest's identical fields — not currently
            // sent by the nav's own auto-save (only the Hub's editor sets a
            // custom-map-search bounds), but accepted here too for schema
            // consistency.
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
