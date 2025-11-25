# Testing Guide: Geo Query Validation Fixes

## Prerequisites

1. **Build Frontend Assets**
   ```bash
   npm run dev
   # OR for production build
   npm run build
   ```

2. **Clear Browser Cache**
   - Hard reload: `Cmd + Shift + R` (Mac) or `Ctrl + Shift + R` (Windows)
   - Or open DevTools → Network tab → Check "Disable cache"

## Test Plan

### Test 1: Normal Map Search (Should Work)

**Goal:** Verify that valid coordinates still work correctly

**Steps:**
1. Navigate to your search page with a map (e.g., `/index/search?searchType=inPerson&city=New York&lat=40.7128&lng=-74.0060`)
2. Open browser DevTools (F12) → Console tab
3. Interact with the map:
   - Pan around
   - Zoom in/out
   - Click on markers
4. Watch the URL update with boundary coordinates

**Expected Result:**
- ✅ Map loads and displays correctly
- ✅ Events show in the list
- ✅ URL parameters update when map moves (e.g., `NElat=40.758902&NElng=-73.985130`)
- ✅ No console errors or warnings
- ✅ Search results update as you move the map

**Red Flags:**
- ❌ Map doesn't load
- ❌ Events don't show
- ❌ Console shows errors
- ❌ URL has `NaN` values

---

### Test 2: Location Search (Should Work)

**Goal:** Verify location-based search still functions

**Steps:**
1. Go to homepage or main search page
2. Click on the location search field
3. Enter a city name (e.g., "Los Angeles")
4. Select a location from autocomplete
5. Add date filters (optional)
6. Submit search

**Expected Result:**
- ✅ Location search box works
- ✅ Autocomplete suggestions appear
- ✅ Results page loads with events
- ✅ URL contains valid coordinates: `?city=Los+Angeles&lat=34.0522&lng=-118.2437`
- ✅ Map centers on the selected location

**Red Flags:**
- ❌ Search doesn't submit
- ❌ No results appear
- ❌ Coordinates missing or invalid

---

### Test 3: Invalid URL Parameters (Should Be Handled Gracefully)

**Goal:** Verify that invalid coordinates are rejected safely

**Steps:**
1. Manually craft a URL with invalid coordinates:
   ```
   /index/search?searchType=inPerson&city=Test&lat=NaN&lng=NaN&NElat=NaN&NElng=NaN&SWlat=NaN&SWlng=NaN
   ```
2. Open DevTools Console BEFORE navigating
3. Paste the URL in browser and hit Enter
4. Watch the console for warnings

**Expected Result:**
- ✅ Page loads (doesn't crash)
- ✅ Console shows warning: `"Invalid boundary coordinates in URL, ignoring"`
- ✅ Map loads with default/fallback coordinates (0,0 or no geo filter)
- ✅ Search results appear (without geo filtering)
- ✅ Laravel log shows: `"Invalid geo boundary coordinates received"`
- ✅ No Elasticsearch errors in Laravel log

**Red Flags:**
- ❌ Page crashes or shows error (500 Internal Server Error)
- ❌ White screen
- ❌ TypeError about ParameterFactory::makeQuery()
- ❌ No console warnings (means validation isn't running)

---

### Test 4: MapStore Validation (Developer Test)

**Goal:** Verify MapStore rejects invalid bounds

**Steps:**
1. Open your search page
2. Open DevTools Console
3. Manually trigger invalid bounds:
   ```javascript
   // Access MapStore (adjust import path if needed)
   const MapStore = window.MapStore || {};
   
   // Try to update with invalid bounds
   MapStore.boundsUpdate({
       _northEast: { lat: 'invalid', lng: 'bad' },
       _southWest: { lat: 'nope', lng: 'wrong' }
   }, { lat: 'test', lng: 'test' });
   ```
4. Check console for warning

**Expected Result:**
- ✅ Console shows: `"MapStore: Invalid bounds data received, skipping update"`
- ✅ URL doesn't change
- ✅ No network requests fired

**Red Flags:**
- ❌ No warning appears
- ❌ URL updates with `NaN` values
- ❌ Error thrown instead of handled

---

### Test 5: Backend Validation (Laravel)

**Goal:** Ensure backend properly handles invalid requests

**Steps:**
1. Make a direct API request with invalid coordinates:
   ```bash
   curl "http://localhost:8000/index/search?searchType=inPerson&lat=invalid&lng=bad&NElat=NaN&NElng=NaN&SWlat=test&SWlng=wrong"
   ```
2. Check Laravel logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

**Expected Result:**
- ✅ Request completes (no 500 error)
- ✅ Laravel log shows: `"Invalid geo boundary coordinates received"`
- ✅ Results returned (without geo filtering)
- ✅ No Elasticsearch errors

**Red Flags:**
- ❌ 500 Internal Server Error
- ❌ Elasticsearch exception in logs
- ❌ No warning logged

---

### Test 6: Mobile View (If Applicable)

**Goal:** Ensure mobile map search works

**Steps:**
1. Open DevTools → Toggle device toolbar (mobile view)
2. Navigate to search page
3. Test map interactions
4. Test location search

**Expected Result:**
- ✅ Mobile map loads correctly
- ✅ Touch interactions work
- ✅ Same validation applies

---

## Monitoring After Deploy

### Check Laravel Logs

```bash
# Watch for new warnings (should see these for invalid requests)
tail -f storage/logs/laravel.log | grep "Invalid geo"

# Count occurrences over time
grep -c "Invalid geo" storage/logs/laravel.log
```

### Check Browser Console in Production

1. Navigate to your production site
2. Open DevTools Console
3. Use the site normally
4. Watch for any:
   - Warnings about invalid coordinates (expected for bad links)
   - Errors (not expected - needs investigation)

### Monitor Google Analytics

After deploying, check GA for:
1. **Error tracking** - Drop in Elasticsearch errors (if tracked)
2. **Behavior flow** - Users successfully completing searches
3. **Site Search** - Search terms and results

---

## Test Checklist

Before deploying to production:

- [ ] `npm run build` completes successfully
- [ ] Test 1: Normal map search works
- [ ] Test 2: Location search works
- [ ] Test 3: Invalid URL parameters handled gracefully
- [ ] Test 4: MapStore validation works
- [ ] Test 5: Backend validation works
- [ ] Test 6: Mobile view works (if applicable)
- [ ] No console errors in normal use
- [ ] Laravel logs show warnings for invalid coordinates (not errors)
- [ ] Search results still appear correctly

## Rollback Plan

If something breaks:

1. **Revert backend changes:**
   ```bash
   git checkout app/Http/Controllers/Search/ListingsController.php
   ```

2. **Revert frontend changes:**
   ```bash
   git checkout resources/js/PageComponents/Nav/nav-search.vue
   git checkout resources/js/Stores/MapStore.vue
   git checkout resources/js/PageComponents/EventListings/container.vue
   npm run build
   ```

3. **Clear caches:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   ```

## Success Criteria

✅ **All tests pass**
✅ **No breaking changes to existing functionality**
✅ **Invalid coordinates are logged but don't crash the app**
✅ **Search results still appear correctly**
✅ **Map interactions work smoothly**

