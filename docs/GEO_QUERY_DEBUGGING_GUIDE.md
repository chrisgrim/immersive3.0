# Geo Query Debugging Guide

## Problem Summary

Non-numeric values (`"NaN"` strings) were being sent to the Laravel backend, causing 624 Elasticsearch geo query failures.

## Root Causes Identified

### 1. Frontend Issues (JavaScript)

#### Issue: `parseFloat()` returns `NaN`, `.toFixed(6)` converts to string `"NaN"`

**Files affected:**
- `resources/js/PageComponents/Nav/nav-search.vue` (line 522-525)
- `resources/js/PageComponents/EventListings/container.vue` (lines 104-109)
- `resources/js/Stores/MapStore.vue` (lines 23-28)

**Flow:**
1. Map bounds get invalid data
2. `parseFloat(invalidValue)` returns `NaN`
3. `NaN.toFixed(6)` returns string `"NaN"`
4. URL params sent as: `?NElat=NaN&NElng=NaN...`
5. Laravel receives these as strings
6. Elasticsearch rejects: "latitude must be a valid double value"

### 2. Backend Issues (Laravel)

**File:** `app/Http/Controllers/Search/ListingsController.php`

**Issues:**
- `buildMapBoundaryFilter()` didn't validate numeric values before sending to Elasticsearch
- `buildLocationFilter()` didn't validate lat/lng values

## Fixes Applied

### Backend Fixes ✅

**File:** `app/Http/Controllers/Search/ListingsController.php`

1. **`buildMapBoundaryFilter()`** - Added validation:
   ```php
   // Only build boundary filter when live is explicitly 'true'
   if (!isset($request->live) || $request->live !== 'true') {
       return null;
   }
   
   // Validate that all required geo coordinates are present and numeric
   $requiredCoords = ['NElat', 'NElng', 'SWlat', 'SWlng'];
   foreach ($requiredCoords as $coord) {
       if (!isset($request->$coord) || !is_numeric($request->$coord)) {
           \Log::warning('Invalid geo boundary coordinates received', [...]);
           return null;
       }
   }
   ```
   This ensures warnings are only logged when map boundary filtering is actually being used (`live=true`).

2. **`buildLocationFilter()`** - Added validation and logging for lat/lng:
   ```php
   $geoFilter = null;
   
   if ($request->lat && $request->lng) {
       if (is_numeric($request->lat) && is_numeric($request->lng)) {
           $geoFilter = Query::geoDistance()
               ->field('location_latlon')
               ->distance('40km')
               ->lat((float)$request->lat)
               ->lon((float)$request->lng);
       } else {
           \Log::warning('Invalid lat/lng coordinates received', [
               'lat' => $request->lat,
               'lng' => $request->lng,
               'city' => $request->city
           ]);
       }
   }
   ```
   This logs invalid lat/lng coordinates (like "nan") so we can track and monitor these occurrences.

3. **`index()` and `apiIndex()` methods** - Added null check before applying filters:
   ```php
   // Only apply geo filter if it's not null (validation may have rejected it)
   if ($request->searchType === 'inPerson' && isset($request->live)) {
       $geoFilter = $request->live === 'true' ? $boundaryFilter : $locationFilters['geoFilter'];
       if ($geoFilter !== null) {
           $query->filter($geoFilter);
       }
   }
   ```
   This prevents passing `null` to Elasticsearch which would cause a TypeError.

### Frontend Fixes ✅

1. **`resources/js/Stores/MapStore.vue`** - Validates at source:
   ```javascript
   boundsUpdate(bounds, center) {
       // Parse and validate all coordinates
       const neLat = parseFloat(bounds._northEast?.lat);
       ...
       // If any coordinate is NaN, log warning and skip update
       if (isNaN(neLat) || isNaN(neLng) || ...) {
           console.warn('MapStore: Invalid bounds data received');
           return; // Don't update state with invalid data
       }
       ...
   }
   ```

2. **`resources/js/PageComponents/Nav/nav-search.vue`** - Validates before sending:
   ```javascript
   subscribeToMapStore = () => {
       const unsubscribeMap = MapStore.subscribe((mapState) => {
           // Validate that all boundary coordinates are numeric
           const neLat = parseFloat(mapState.bounds.northEast.lat);
           ...
           // Check if any values are NaN - if so, skip this update
           if (isNaN(neLat) || isNaN(neLng) || ...) {
               console.warn('Invalid map bounds received, skipping update');
               return;
           }
           // Now safe to set URL params
           ...
       });
   };
   ```

3. **`resources/js/PageComponents/EventListings/container.vue`** - Validates URL params:
   ```javascript
   function initializeBoundaries() {
       const searchParams = new URL(window.location.href).searchParams;
       if (!searchParams.get("NElat")) return null;
       const neLat = parseFloat(searchParams.get("NElat"));
       ...
       if (isNaN(neLat) || isNaN(neLng) || isNaN(swLat) || isNaN(swLng)) {
           console.warn('Invalid boundary coordinates in URL, ignoring');
           return null;
       }
       ...
   }
   ```

## How to Check Google Analytics

### 1. Check for URLs with NaN Parameters

**Goal:** Find URLs that contain `NaN` as parameter values

**Steps:**
1. Go to Google Analytics
2. Navigate to: **Behavior → Site Content → All Pages**
3. Add secondary dimension: **Page**
4. Use search/filter: Look for URLs containing `"NaN"`
5. Check for patterns like:
   - `/index/search?NElat=NaN`
   - `/index/search?lat=NaN&lng=NaN`

### 2. Check Browser/Device Patterns

**Goal:** Identify if specific browsers/devices send invalid data

**Steps:**
1. If you found URLs with NaN, note the time periods
2. Go to: **Audience → Technology → Browser & OS**
3. Set date range to match when errors occurred (Nov 22-25)
4. Look for unusual patterns or specific browser versions

### 3. Check Geographic Sources

**Goal:** See if certain regions/referrers are sending bad links

**Steps:**
1. Go to: **Acquisition → All Traffic → Source/Medium**
2. Check if specific referrers are sending malformed URLs
3. Look for direct traffic vs. organic search patterns

### 4. Custom Event Tracking (For Future)

Add event tracking when invalid coordinates are detected:

```javascript
if (isNaN(neLat) || isNaN(neLng) || ...) {
    // Log to Google Analytics
    if (window.gtag) {
        gtag('event', 'invalid_geo_coordinates', {
            'event_category': 'error',
            'event_label': 'map_bounds',
            'value': window.location.search
        });
    }
    console.warn('Invalid map bounds received, skipping update');
    return;
}
```

## Monitoring

### Check Laravel Logs

```bash
# Watch for new invalid coordinate warnings
tail -f storage/logs/laravel.log | grep "Invalid geo"

# Count occurrences
grep -c "Invalid geo" storage/logs/laravel.log
```

### Check Browser Console

When testing locally, watch for warning messages:
- "Invalid map bounds received, skipping update"
- "MapStore: Invalid bounds data received"
- "Invalid boundary coordinates in URL, ignoring"

## Common Causes of Invalid Coordinates

1. **Bookmarked URLs** - Users bookmarked a page with corrupted parameters
2. **Shared Links** - Someone shared a malformed URL
3. **Third-party Link Aggregators** - Sites scraping/sharing your URLs incorrectly
4. **Browser Extensions** - Extensions modifying URLs
5. **Bots/Crawlers** - Automated tools with poor URL handling
6. **Edge Cases** - Map not fully loaded before bounds update triggered

## Resolution Status

- ✅ Backend validation added (prevents Elasticsearch errors)
- ✅ Frontend validation added at three levels:
  - MapStore (source)
  - nav-search (subscription)
  - container.vue (URL parsing)
- ✅ Logs backed up (laravel.log → laravel.log.backup-20251125)
- ✅ Fresh log started

The system should now gracefully handle invalid coordinates by:
1. Rejecting them early in the frontend
2. Falling back safely if they reach the backend
3. Logging warnings for monitoring

