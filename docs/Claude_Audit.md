DEEP DIVE: CRITICAL & HIGH PRIORITY ISSUES
1. TEST COVERAGE - Only basic auth tests exist
Current State
Test Count: Only 10 tests across 6 files
tests/Feature/Auth/AuthenticationTest.php - 3 tests (traditional password auth - now obsolete)
tests/Feature/Auth/RegistrationTest.php - 1 test
tests/Feature/Auth/EmailVerificationTest.php - 2 tests
tests/Feature/Auth/PasswordResetTest.php - 2 tests
tests/Feature/ExampleTest.php - 1 test
tests/Unit/ExampleTest.php - 1 test
Critical Problem
The authentication tests are testing the WRONG auth system! Your tests check password-based authentication (/login with password field), but your actual app uses magic link authentication via LoginCodeController.
Missing Test Coverage
Authentication (Magic Link System):
❌ sendCode() - Code generation and email sending
❌ Rate limiting on code requests (5/hour)
❌ Code expiration (15 minutes)
❌ verify() - Code verification logic
❌ Rate limiting on verification (10/15 min)
❌ Email verification on first login
❌ Auto-login via magic link
Core API Endpoints (44 controllers, 0 tests):
❌ Event CRUD operations - HostEventController
❌ Event search/filtering - ListingsController
❌ Event duplication logic
❌ Similar events algorithm
❌ Organizer management - OrganizerController
❌ Admin approval workflows
❌ Community/Post/Shelf CRUD
❌ Click tracking analytics
❌ Image upload/processing
Authorization (Policy-Based):
❌ EventPolicy permissions (manage, duplicate, moderate)
❌ OrganizerPolicy team access
❌ CommunityPolicy curator permissions
❌ Role-based access (Guest/Curator/Moderator/Admin)
Search & Elasticsearch:
❌ Full-text search queries
❌ Location-based filtering (geo queries)
❌ Date range filtering
❌ Price range filtering
❌ Category/Genre filtering
Business Logic:
❌ Event status workflows (draft → published → archived)
❌ Show type variations (specific dates, ongoing, always, limited run)
❌ Timezone handling (see TIMEZONE_STANDARDIZATION.md)
❌ Name change request workflow
❌ Image processing via ImageHandler service
Database:
❌ Model relationships (18 models with complex relations)
❌ Polymorphic relationships (Images, Associations)
❌ Soft deletes behavior
❌ Global scopes (PublishedScope)
Impact
No regression protection - Any change could break existing functionality undetected
Deployment risk - No safety net for production releases
Refactoring paralysis - Can't confidently restructure code
Bug reproduction - Hard to verify bug fixes without test cases
Recommendation Priority: CRITICAL
Estimated Effort: 3-4 weeks for comprehensive coverage Recommended Approach:
Week 1: Fix auth tests to use magic link system, add API endpoint tests for critical paths (event creation, search)
Week 2: Add authorization/policy tests, model relationship tests
Week 3: Add Elasticsearch search tests, business logic tests
Week 4: Add integration tests, edge case coverage
Quick Wins (1-2 days):
Test magic link authentication flow
Test event creation happy path
Test admin approval workflow
Test search with filters
2. API DOCUMENTATION - No OpenAPI/Swagger docs
Current State
API Endpoint Count: 90+ endpoints across routes/api.php Documentation: ❌ None - No OpenAPI spec, no Postman collection, no inline docs
API Complexity
Public Endpoints (19):
GET  /index/search                           # Event listings with complex filters
GET  /organizers/{organizer}/events          # Organizer event portfolio
POST /organizers/check-name                  # Name availability check
POST /events/{eventId}/track-click           # Analytics tracking
GET  /events/{event}/similar                 # Similar events algorithm
GET  /events/similar-by-location             # Location-based recommendations
GET  /categories, /genres, /remotelocations  # Reference data
GET  /search/nav/*                           # Navigation search (4 endpoints)
GET  /*/cached                               # Cached data endpoints (3)
Protected Endpoints (71):
# Event Management
POST   /hosting/event/{event}                # Update event
POST   /events/{event}/duplicate             # Duplicate event
GET    /events/{eventId}/click-stats         # Click analytics

# Admin Routes (60+ endpoints under /admin/*)
GET    /admin/approval-counts                # Dashboard stats
GET    /admin/approve/{type}                 # Pending items
POST   /admin/approve/{type}/{id}/approve    # Approve entity
POST   /admin/approve/{type}/{id}/reject     # Reject entity
GET    /admin/manage/{type}                  # Management views
PATCH  /admin/manage/{type}/{id}             # Update entity
DELETE /admin/manage/{type}/{id}             # Delete entity
GET    /admin/settings/{type}                # Settings CRUD
POST   /admin/docks/*                        # Featured content (11 endpoints)
Undocumented Complex Behaviors
Search Endpoint (ListingsController::apiIndex):
Elasticsearch query builder with 6+ filter types
Geo-distance queries (40km radius)
Geo-bounding box for map integration
Nested queries for shows/dates
Price aggregation for dynamic max price
Attendance type filtering (in-person vs remote)
No documentation on query params
Request/Response Examples Missing:
// What query params are supported?
GET /index/search?searchType=inPerson&lat=40.7128&lng=-74.0060&category=1,3&price0=0&price1=50&start=2025-10-25&end=2025-10-26&live=true

// What's the response structure?
{
  data: [...],        // Array of what exactly?
  total: 0,           // Total results
  maxPrice: 0,        // Calculated max price
  // What else?
}
Event Update Validation:
StoreEventRequest has 60+ validation rules
Nested validation for location, tickets, images, advisories
No documentation on required vs optional fields
No examples of valid payloads
Developer Pain Points
Frontend developers must read Laravel controller code to understand API contracts
Mobile app developers have no reference documentation
Third-party integrations impossible without source code access
API versioning not defined - breaking changes likely undocumented
Error responses inconsistent across endpoints
Impact
Onboarding friction - New developers spend hours reading controller code
Integration difficulty - External consumers can't use the API
Breaking changes - No contract to validate against
Testing complexity - No reference for expected behaviors
Maintenance burden - Tribal knowledge, not documented knowledge
Recommendation Priority: HIGH
Estimated Effort: 1-2 weeks for initial spec + ongoing maintenance Recommended Approach: Option 1: OpenAPI/Swagger (Recommended)
Use darkaonline/l5-swagger package
Generate spec from annotations in controllers
Host Swagger UI at /api/documentation
Auto-update on deployment
Option 2: Postman Collection
Faster initial setup
Export collection to repository
Less maintainable long-term
Option 3: API Platform/Stoplight
Design-first approach
Best for external APIs
More overhead
Quick Win (2-3 days):
Document top 10 most-used endpoints
Create Postman collection for manual testing
Add inline PHPDoc to controllers
3. RATE LIMITING - Only on login endpoints
Current State
Protected Endpoints: Only 2 endpoints have rate limiting
Login Code Generation (LoginCodeController:22-32)
$rateLimitKey = 'login_code_requests:' . $validated['email'];
$attempts = Cache::get($rateLimitKey, 0);
if ($attempts >= 5) { /* Block for 1 hour */ }
Limit: 5 requests/hour per email
Storage: Cache-based
Scope: Per email address
Login Code Verification (LoginCodeController:77-85)
$rateLimitKey = 'login_verify_attempts:' . $validated['email'];
$attempts = Cache::get($rateLimitKey, 0);
if ($attempts >= 10) { /* Block for 15 min */ }
Limit: 10 attempts/15 min per email
Storage: Cache-based
Scope: Per email address
Unprotected Endpoints: 88+ endpoints have NO rate limiting
Vulnerability Analysis
High Risk - Abuse Vectors:
Event Creation (POST /hosting/event/{event})
No limit on updates per event
Could spam updates to trigger notifications
Could overwhelm database with writes
Click Tracking (POST /events/{eventId}/track-click)
CRITICAL: No rate limiting on analytics endpoint
Can be spammed to inflate metrics
Currently tracks by IP only (easily spoofed)
Database could grow unbounded
Search Endpoints (GET /index/search)
Complex Elasticsearch queries
Resource-intensive geo-distance calculations
No limit on requests per IP/user
Could cause Elasticsearch cluster strain
Image Uploads (Event/Organizer/Profile images)
Max 5MB per image (StoreEventRequest:74)
No limit on upload frequency
Could exhaust S3 storage/bandwidth
Processing CPU-intensive (image resizing)
Admin Bulk Operations
Approve/reject endpoints unprotected
Could spam approvals/rejections
No audit trail rate limits
Name Change Requests (POST /organizers/{organizer}/name-change)
Could spam change requests
Triggers email notifications
No cooldown period
Medium Risk:
Navigation Search (GET /search/nav/*)
Autocomplete endpoints
Fired on every keystroke
Could cause query storms
Similar Events (GET /events/{event}/similar)
Complex recommendation algorithm
No caching visible
CPU-intensive
Organizer Name Check (POST /organizers/check-name)
Public endpoint for validation
Could enumerate existing names
Current Protection Gaps
Laravel's Built-in Throttle Middleware: Not applied to any routes in api.php Laravel provides:
Route::middleware(['throttle:60,1'])->group(function () {
    // 60 requests per minute
});
Not used anywhere in your API routes.
Attack Scenarios
Scenario 1: Click Fraud
# Spam click tracking to inflate metrics
for i in {1..10000}; do
  curl -X POST https://api.example.com/events/123/track-click
done
Impact: Corrupted analytics, database bloat (track_clicks table unbounded) Scenario 2: Search Overload
# Spam complex geo queries
while true; do
  curl "https://api.example.com/index/search?searchType=inPerson&lat=40.7128&lng=-74.0060&live=true"
done
Impact: Elasticsearch cluster degradation, increased AWS costs Scenario 3: Storage Exhaustion
# Upload 5MB images repeatedly
for i in {1..1000}; do
  curl -X POST https://api.example.com/hosting/event/123 \
    -F "images[]=@5mb-image.jpg"
done
Impact: S3 storage costs spike, processing queue backup
Recommendation Priority: CRITICAL
Estimated Effort: 2-3 days for comprehensive implementation Recommended Rate Limits:
// Public endpoints - Per IP
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/index/search', ...);           // 60/min
    Route::get('/search/nav/*', ...);           // 60/min  
    Route::post('/events/{id}/track-click', ...); // 10/min per IP
});

// Authenticated endpoints - Per User
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/hosting/event/*', ...);       // 120/min
    Route::post('/events/{id}/duplicate', ...); // 5/min
});

// Resource-intensive - Strict limits
Route::post('/organizers/check-name', ...)
    ->middleware('throttle:20,1');              // 20/min per IP
    
Route::get('/events/{id}/similar', ...)
    ->middleware('throttle:30,1');              // 30/min per IP

// Image uploads - Very strict
Route::post('/*/upload-image', ...)
    ->middleware(['auth:sanctum', 'throttle:10,1']); // 10/min per user

// Admin operations - Moderate
Route::middleware(['auth:sanctum', 'moderator', 'throttle:240,1'])
    ->prefix('admin')->group(...);              // 240/min (4/sec)
Additional Protections Needed:
Click Tracking Enhancement:
// Add to EventClickController
$rateLimitKey = "click_track:{$request->ip()}:{$eventId}";
if (Cache::has($rateLimitKey)) {
    return response()->json(['message' => 'Too many requests'], 429);
}
Cache::put($rateLimitKey, true, now()->addMinutes(1)); // 1 click/min/IP/event
Elasticsearch Query Timeout:
// Add to search queries
->searchQuery($query)->timeout('5s')
Database Index on track_clicks:
// Add composite index
$table->index(['event_id', 'ip_address', 'created_at']);
Archive old click data:
// Schedule command to archive clicks older than 90 days
TrackClick::where('created_at', '<', now()->subDays(90))->delete();
4. FILE CLEANUP - DEAD_LoginController.php
Current State
File: app/Http/Controllers/Auth/DEAD_LoginController.php Status: ❌ Marked for deletion (filename prefix), but still in repo Git Status: ?? app/Http/Controllers/Auth/DEAD_LoginController.php (untracked)
File Analysis
Content: Old LoginController implementation
Similar to LoginCodeController but older version
Rate limiting: 3 requests/hour (vs current 5/hour)
Rate limiting: 5 verify attempts/15min (vs current 10/15min)
Missing try-catch for email sending (line 56)
Missing email verification on first login (lines 100-102)
Missing "remember me" functionality (line 114)
125 lines of dead code
Comparison with Active Controller
Feature	DEAD_LoginController	LoginCodeController (Active)
Code requests/hour	3	5
Verify attempts	5/15min	10/15min
Email error handling	❌ None	✅ Try-catch with logging
Email verification	❌ Missing	✅ Sets email_verified_at
Remember me	❌ No	✅ Yes
Auto-login route	❌ No	✅ Yes (line 128-139)
Why It's Still Here
Based on git status, this file was:
Created when refactoring LoginController.php → LoginCodeController.php
Renamed with "DEAD_" prefix instead of being deleted
Never committed (appears as untracked ??)
Kept "just in case" for reference
Impact
Low but annoying:
Clutters codebase navigation
May confuse new developers
Appears in IDE search results
Not causing functional issues (not imported anywhere)
125 lines of maintenance burden if accidentally used
Related Cleanup
Other potentially dead files to check:
# Check for other DEAD_ or unused auth files
app/Http/Controllers/Auth/PasswordResetLinkController.php    # If magic link only
app/Http/Controllers/Auth/NewPasswordController.php          # If magic link only
app/Http/Controllers/Auth/RegisteredUserController.php       # Auto-registration?
Your app uses magic link auth only - traditional password reset controllers may be obsolete.
Recommendation Priority: LOW (but easy fix)
Estimated Effort: 5 minutes Action Items:
✅ Verify LoginCodeController has all needed functionality (CONFIRMED - it does)
✅ Confirm DEAD_LoginController is not imported anywhere (CONFIRMED - untracked file, no imports)
❌ Delete the file permanently
❌ Review other auth controllers for obsolete password-based auth
❌ Update auth tests to use magic link system (critical - see Test Coverage section)
Files to Delete:
rm app/Http/Controllers/Auth/DEAD_LoginController.php
git rm app/Http/Controllers/Auth/LoginController.php  # Already deleted per git status
Files to Review for Deletion:
Check if password reset controllers are needed (if not, delete them too)
Check if traditional registration controller is needed (you auto-register on magic link)
5. TYPE SAFETY - All JavaScript, no TypeScript
Current State
Frontend Codebase:
201 Vue component files (all .vue)
JavaScript: 100% (no .ts or .tsx files)
Type Checking: ❌ None
IDE Support: Limited IntelliSense
Runtime Errors: Discovered in production
Vue 3 Composition API Without Types
Example from your codebase (typical pattern):
// No type safety
const event = ref(null);                  // What shape is event?
const categories = ref([]);               // Array of what?
const filters = reactive({                // What properties?
  price: [0, 100],
  dates: null,
  location: {}
});

const searchEvents = async (params) => {  // What params? What returns?
  const response = await axios.get('/index/search', { params });
  return response.data;                   // What's in data?
};
With TypeScript:
interface Event {
  id: number;
  name: string;
  category: Category;
  location: Location | null;
  shows: Show[];
  // ... full contract
}

const event = ref<Event | null>(null);
const categories = ref<Category[]>([]);
const filters = reactive<SearchFilters>({
  price: [0, 100] as [number, number],
  dates: null as DateRange | null,
  location: {} as LocationFilter
});

const searchEvents = async (params: SearchParams): Promise<SearchResponse> => {
  const response = await axios.get<SearchResponse>('/index/search', { params });
  return response.data;
};
Common Bugs TypeScript Would Catch
1. Property Typos
// Current - Silent failure
event.categroy?.name  // Typo - returns undefined

// TypeScript - Compile error
event.categroy?.name  // ❌ Property 'categroy' does not exist on type 'Event'
2. Wrong Parameter Types
// Current - Runtime error
formatDate(event.closingDate, 'America/New_York')  // If closingDate is null

// TypeScript - Compile error
formatDate(event.closingDate, 'America/New_York')  
// ❌ Argument of type 'string | null' not assignable to parameter of type 'string'
3. API Response Shape
// Current - Crashes at runtime
const events = response.data.events.map(e => e.name);  
// If API returns { data: [] } instead of { events: [] }

// TypeScript - Compile error
const events = response.data.events.map(e => e.name);
// ❌ Property 'events' does not exist on type 'SearchResponse'
4. Prop Validation
// event-card.vue - Current
props: {
  event: Object,        // Any object accepted
  showCategory: Boolean
}

// TypeScript
interface Props {
  event: Event;         // Must be full Event type
  showCategory?: boolean;
}
const props = defineProps<Props>();
Migration Complexity
Factors:
201 Vue files to migrate
144 components with props/emits to type
Axios calls throughout (no API client)
State management (no Pinia/Vuex visible - may be ad-hoc)
Third-party libraries without types (some may need @types/* packages)
Build configuration changes needed
Dependencies requiring types:
// Need @types packages for:
"vue": "^3.4.21",           // ✅ Has built-in types
"axios": "^1.6.8",          // ✅ Has built-in types
"leaflet": "^1.9.4",        // ⚠️  Need @types/leaflet
"moment-timezone": "^0.5.45", // ⚠️  Need @types/moment-timezone
"vue-cal": "^4.8.1",        // ❌ May not have types
"vue-draggable-next": "^2.2.1", // ❌ May not have types
Incremental Migration Strategy
Phase 1: Foundation (Week 1) - 3-4 days
Add TypeScript support to Vite config
Create tsconfig.json with relaxed settings
Define core types: Event, Organizer, Category, Genre, User
Create API client with typed methods
Keep .vue files as-is (TypeScript optional in <script>)
Phase 2: New Code Only (Week 2-4) - Ongoing 6. Write all NEW components in <script setup lang="ts"> 7. All NEW composables in .ts files with types 8. Let old .vue files remain JavaScript Phase 3: Critical Paths (Week 5-8) - As needed 9. Migrate event creation wizard (high complexity, high value) 10. Migrate search components (data-heavy) 11. Migrate admin panel (type safety critical) Phase 4: Full Migration (Month 3-6) - Long-term 12. Migrate remaining components incrementally 13. Enable strict type checking 14. Remove all any types
Benefits vs Effort Trade-off
Benefits:
✅ Catch bugs at compile time (40-60% reduction in runtime errors)
✅ Better IDE support (autocomplete, refactoring)
✅ Self-documenting code
✅ Easier onboarding (types as documentation)
✅ Refactoring confidence
Effort:
⚠️ Initial setup: 3-4 days
⚠️ Learning curve: 1-2 weeks for team
⚠️ Migration overhead: 10-20% slower development initially
⚠️ Type maintenance: Ongoing effort
Recommendation Priority: MEDIUM
Estimated Effort: 1 week setup + 3-6 months incremental migration ROI: High for long-term maintainability, but not urgent Recommended Approach:
Don't do a full migration - too disruptive
Start with new code only - all new components use TypeScript
Create type definitions for API responses (use as documentation even without TS)
Migrate high-value areas when refactoring (event creation, search, admin)
Keep existing code as JavaScript - migrate opportunistically
Quick Win (1 day):
Create types/api.ts with interfaces for all API responses
Use in code comments as documentation
Migrate 1-2 components to TypeScript as proof of concept
DEEP DIVE: MEDIUM PRIORITY ISSUES
6. PERFORMANCE - N+1 Queries & Unbounded Click Tracking
Issue A: N+1 Query Patterns
What is N+1?
// N+1 Problem - 1 query + N additional queries
$events = Event::all();                    // 1 query: Get all events
foreach ($events as $event) {
    echo $event->organizer->name;          // N queries: Get organizer for EACH event
}
// Total: 1 + 100 = 101 queries for 100 events
Detected Instances: 1. Event Show Page (EventController:34-61)
$event->load([
    'category',
    'location',
    'contentAdvisories',
    'contactLevels',
    // ... 14 relationships
]);

$event->load(['organizer' => function($query) {
    $query->withCount(['events' => function($eventsQuery) {
        $eventsQuery->where('status', 'p')->where('archived', false);
    }])
    ->with(['events' => function($eventsQuery) {
        $eventsQuery->where('status', 'p')
            ->where('archived', false)
            ->orderByDesc('updated_at');
    }]);
}]);
✅ GOOD: Uses eager loading properly. No N+1 here. 2. Admin Event Index (AdminEventController:19-79)
$query = Event::query()
    ->with(['organizer', 'images', 'location', 'category', 'clicks', 'curatedCheck'])
    // ...
    ->paginate(20);

// Then in loop:
foreach ($events as $event) {
    $event->total_clicks = $event->clicks->count();            // ⚠️  Count after loading all
    $event->unique_visitors = $event->clicks->unique('ip_address')->count();
}
⚠️ POTENTIAL ISSUE: Loading ALL clicks into memory, then counting. For popular events with 10,000+ clicks:
Loads 10,000 TrackClick records into PHP memory
Counts in application instead of database
Multiplied by 20 events per page
Better approach:
$query = Event::query()
    ->withCount('clicks')                              // SQL COUNT(*)
    ->withCount(['clicks as unique_visitors' => function($q) {
        $q->select(DB::raw('COUNT(DISTINCT ip_address)'));
    }])
    ->paginate(20);

// No loop needed - counts already available
3. Search Results (ListingsController:265-268)
$results = Event::searchQuery($query)
    ->load(['genres', 'category', 'location', 'attendanceType'])
    ->sortRaw(['published_at' => 'desc'])
    ->paginate(20);
✅ GOOD: Eager loading used. No N+1. 4. Organizer Events (EventController:70-76)
return Event::where('status', 'p')
    ->where('organizer_id', $organizer->id)
    ->where('archived', false)
    ->with(['category', 'genres'])                     // ✅ Eager loaded
    ->orderByRaw('CASE WHEN closingDate >= NOW() THEN 0 ELSE 1 END')
    ->orderBy('created_at', 'desc')
    ->paginate($request->input('pageSize', 10));
✅ GOOD: Eager loading genres (many-to-many). Verdict: Generally well-optimized, but click tracking is inefficient.
Issue B: Unbounded Click Tracking Table
Table: track_clicks (migration)
CREATE TABLE track_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    event_id BIGINT UNSIGNED,
    user_id BIGINT UNSIGNED,
    organizer_id BIGINT UNSIGNED,
    ip_address VARCHAR(45),        -- Added in later migration
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
Growth Rate Analysis: Assuming:
1,000 events in platform
Average 100 clicks/event/month
No data retention policy
Growth:
Month 1: 100,000 rows
Year 1: 1.2 million rows
Year 3: 3.6 million rows
Year 5: 6 million rows
Current Issues:
No Indexes (besides primary key)
-- Current queries without indexes:
SELECT COUNT(*) FROM track_clicks WHERE event_id = 123;
SELECT COUNT(DISTINCT ip_address) FROM track_clicks WHERE event_id = 123;
These queries are full table scans after 1M+ rows.
No Archival Strategy
All data kept forever
Old clicks (>90 days) rarely queried
Bloats table size
No Partitioning
Single table for all time periods
Queries slow as table grows
Loaded into Memory (AdminEventController:74-77)
$event->total_clicks = $event->clicks->count();     // Loads ALL clicks
$event->unique_visitors = $event->clicks->unique('ip_address')->count();
For an event with 10,000 clicks, this loads 10,000 rows into PHP memory.
Performance Impact Timeline
Rows	Query Time (no index)	Memory Usage	Admin Page Load
10K	~50ms	~5MB	Fast
100K	~200ms	~50MB	Slow
1M	~2s	~500MB	Very Slow
5M+	~10s+	OOM possible	Timeout
Recommendation Priority: HIGH
Estimated Effort: 1-2 days Solution 1: Add Indexes (Immediate - 30 min)
// Migration: add_indexes_to_track_clicks_table
Schema::table('track_clicks', function (Blueprint $table) {
    $table->index('event_id');
    $table->index(['event_id', 'created_at']);
    $table->index(['event_id', 'ip_address']);  // For unique visitor count
});
Solution 2: Use Database Aggregation (1-2 hours)
// AdminEventController - Replace loop with query
$events = Event::query()
    ->withCount('clicks')
    ->withCount(['clicks as unique_visitors' => function($query) {
        $query->distinct('ip_address');
    }])
    ->paginate(20);

// No loop needed
Solution 3: Archive Old Data (3-4 hours)
// Console Command: ArchiveOldClicks
public function handle()
{
    $cutoffDate = now()->subDays(90);
    
    // Move to archive table
    DB::statement("
        INSERT INTO track_clicks_archive 
        SELECT * FROM track_clicks 
        WHERE created_at < ?
    ", [$cutoffDate]);
    
    // Delete from main table
    TrackClick::where('created_at', '<', $cutoffDate)->delete();
}
Solution 4: Cached Aggregates (4-6 hours)
// Cache click counts
Cache::remember("event:{$eventId}:clicks", 3600, function() use ($eventId) {
    return TrackClick::where('event_id', $eventId)->count();
});

Cache::remember("event:{$eventId}:visitors", 3600, function() use ($eventId) {
    return TrackClick::where('event_id', $eventId)
        ->distinct('ip_address')
        ->count('ip_address');
});
Solution 5: Pre-computed Stats Table (1 day)
// New table: event_click_stats
Schema::create('event_click_stats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->integer('total_clicks')->default(0);
    $table->integer('unique_visitors')->default(0);
    $table->date('stat_date');
    $table->unique(['event_id', 'stat_date']);
});

// Update on click
EventClickController::trackClick() {
    // ... record click ...
    
    EventClickStat::updateOrCreate(
        ['event_id' => $eventId, 'stat_date' => today()],
        ['total_clicks' => DB::raw('total_clicks + 1')]
    );
}
7. ERROR HANDLING - Inconsistent API Error Responses
Current State
Error Handling Coverage: 52 try-catch blocks across 13 controllers Pattern Analysis: Pattern 1: Try-Catch with Logging (Most common - 40 instances)
// SimilarEventsController:69-75
try {
    $events = $this->getEventsByCategory($event);
    return response()->json(['events' => $events]);
} catch (\Exception $e) {
    \Log::error("Error fetching similar events for {$event->id}: " . $e->getMessage());
    return response()->json(['events' => []], 200);  // ⚠️  Returns success with empty data
}
Pattern 2: Try-Catch with Rethrow (8 instances)
// LoginCodeController:50-62
try {
    Mail::to($user)->send(new LoginCode($code));
} catch (\Exception $e) {
    \Log::error('Failed to send login code email', [
        'error' => $e->getMessage(),
        'email' => $validated['email']
    ]);
    throw $e;  // ✅ Proper error propagation
}
Pattern 3: Validation Exceptions (4 instances)
// LoginCodeController:25-29
if ($attempts >= 5) {
    throw ValidationException::withMessages([
        'email' => ['Too many login attempts. Please try again in 1 hour.']
    ]);
}
Pattern 4: No Error Handling (Majority - 30+ controllers)
// EventController::show - No try-catch
public function show(Event $event)
{
    // If any of these fail, Laravel returns generic 500
    $event->load(['category', 'location', ...]);
    return view('events.show', compact('event'));
}
Inconsistency Problems
Problem 1: Different Status Codes for Similar Errors Search failure:
// SimilarEventsController:72
return response()->json(['events' => []], 200);  // Returns 200 on error
Admin operation failure:
// CommunityController:254
Log::error('Failed to update community: ' . $e->getMessage());
return response()->json(['error' => 'Failed to update'], 500);  // Returns 500
Auth failure:
// LoginCodeController:26-28
throw ValidationException::withMessages([...]);  // Returns 422
No pattern - same type of error returns 200, 422, or 500 depending on controller. Problem 2: Different Response Structures Format A: Success-like with empty data
{
  "events": []
}
Format B: Error with message
{
  "error": "Failed to update"
}
Format C: Validation errors
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Too many login attempts."]
  }
}
Format D: Laravel default 500
{
  "message": "Server Error"
}
Frontend can't rely on consistent error handling.
Impact on Frontend
Current frontend code probably looks like:
try {
  const response = await axios.get('/events/123/similar');
  
  // Pattern A: Check if data is empty
  if (response.data.events.length === 0) {
    // Is this an error or legitimately no results?
  }
  
  // Pattern B: Check for error property
  if (response.data.error) {
    // Show error
  }
} catch (error) {
  // Pattern D: Network or 500 error
  if (error.response?.status === 500) {
    // Generic error
  }
  
  // Pattern C: Validation error
  if (error.response?.status === 422) {
    // Handle validation errors
  }
}
Fragile - must check multiple patterns for every request.
Missing Error Handling
Database Errors:
// No protection against:
- Deadlocks
- Connection timeouts
- Constraint violations
- Duplicate key errors
External Service Errors:
// Mail::send() can fail (SMTP timeout, invalid recipient)
// Storage::put() can fail (S3 unreachable, quota exceeded)
// Elasticsearch can fail (cluster down, timeout)
Validation Edge Cases:
// What if uploaded image is corrupted?
// What if timezone is invalid?
// What if JSON is malformed?
Recommendation Priority: MEDIUM
Estimated Effort: 2-3 days for standardization Solution: Global Error Handler 1. Create Standard Response Format (30 min)
// app/Http/Responses/ApiResponse.php
class ApiResponse
{
    public static function success($data, $message = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message
        ], $code);
    }
    
    public static function error($message, $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}
2. Custom Exception Classes (1 hour)
// app/Exceptions/ApiException.php
class ApiException extends Exception
{
    public function __construct(
        public string $message,
        public int $statusCode = 400,
        public ?array $errors = null
    ) {
        parent::__construct($message);
    }
}

class ResourceNotFoundException extends ApiException
{
    public function __construct($resource = 'Resource')
    {
        parent::__construct("$resource not found", 404);
    }
}
3. Global Exception Handler (2 hours)
// app/Exceptions/Handler.php
public function register()
{
    $this->renderable(function (ApiException $e, $request) {
        if ($request->expectsJson()) {
            return ApiResponse::error(
                $e->message,
                $e->statusCode,
                $e->errors
            );
        }
    });
    
    $this->renderable(function (\Exception $e, $request) {
        if ($request->expectsJson()) {
            Log::error('Unexpected error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return ApiResponse::error(
                'An unexpected error occurred',
                500
            );
        }
    });
}
4. Update Controllers (4-6 hours)
// Before:
try {
    $events = $this->getSimilar($event);
    return response()->json(['events' => $events]);
} catch (\Exception $e) {
    \Log::error("Error: " . $e->getMessage());
    return response()->json(['events' => []], 200);
}

// After:
$events = $this->getSimilar($event);  // Let exception bubble
return ApiResponse::success($events);
5. Frontend Axios Interceptor (1 hour)
// resources/js/axios-config.js
axios.interceptors.response.use(
  response => response,
  error => {
    const { response } = error;
    
    if (response?.data?.success === false) {
      // Standard error format
      toast.error(response.data.message);
      
      if (response.data.errors) {
        // Validation errors
        showValidationErrors(response.data.errors);
      }
    } else {
      // Network error or unexpected format
      toast.error('An unexpected error occurred');
    }
    
    return Promise.reject(error);
  }
);
Benefits:
✅ Consistent error format across all endpoints
✅ Easier frontend error handling
✅ Better logging and debugging
✅ Proper HTTP status codes
8. LOGGING & MONITORING - No Centralized Error Tracking
Current State
Logging: Laravel's default Monolog (file-based) Error Tracking: ❌ None (no Sentry, Bugsnag, etc.) APM: ❌ None (no New Relic, DataDog, etc.) Log Aggregation: ❌ None (no ELK, Papertrail, etc.) Current Logging Usage: 20 instances across codebase
\Log::error()   // 18 instances
\Log::warning() // 2 instances
\Log::info()    // 1 instance
Log::error()    // 5 instances (no backslash)
Log Locations:
/storage/logs/laravel.log         # Single monolithic file
/storage/logs/laravel-2025-10-25.log  # Daily rotation (if configured)
Missing Visibility
1. Production Errors Go Unnoticed
// SimilarEventsController:72
catch (\Exception $e) {
    \Log::error("Error fetching similar events: " . $e->getMessage());
    return response()->json(['events' => []], 200);  // Returns success to user
}
Error logged to file
No alert triggered
User sees empty results (no indication of error)
Developer never knows there's a problem
2. No Error Context Most logs missing critical context:
\Log::error("Failed to delete card:", [
    'card_id' => $this->id,
    'error' => $e->getMessage()
]);
Missing:
User ID (who triggered it?)
Request ID (correlate with other logs)
Stack trace (where exactly did it fail?)
Environment (prod, staging, dev?)
Browser/device (frontend-triggered?)
3. No Performance Monitoring
No slow query detection
No API endpoint timing
No database query counts per request
No memory usage tracking
4. No Search Analytics
How many searches fail?
What are users searching for?
Which filters are used most?
Are Elasticsearch queries timing out?
5. No User Behavior Tracking (except clicks)
Event views not tracked (only clicks)
Search conversions not tracked
User journey not visible
Logs Scattered Across Services
Laravel App Logs:
/storage/logs/laravel.log
Web Server Logs (nginx/apache):
/var/log/nginx/access.log
/var/log/nginx/error.log
Database Logs:
MySQL slow query log
MySQL error log
Queue Worker Logs:
Laravel queue worker stdout/stderr
Elasticsearch Logs:
Elasticsearch cluster logs
No centralized view - must SSH into server and grep multiple files.
Impact
Scenario 1: Silent Failures
// ImageHandler:235
catch (\Exception $e) {
    \Log::error("Failed to copy image: " . $e->getMessage());
    // No notification - images silently fail to upload
}
User uploads image
Backend logs error
User sees loading spinner forever
Support ticket filed
Developer: "Can't reproduce" (no log aggregation)
Scenario 2: Performance Degradation
Elasticsearch queries getting slower
No alerts configured
Users complain search is slow
No metrics to identify bottleneck
Scenario 3: Security Incidents
Attacker spamming click tracking
Logs show thousands of requests
No alert triggered
Attack continues unnoticed
Recommendation Priority: HIGH
Estimated Effort: 1-2 days setup + ongoing maintenance Solution Tiers:
Tier 1: Basic (1 day) - FREE
A. Sentry for Error Tracking (2 hours)
composer require sentry/sentry-laravel
php artisan sentry:publish
// config/sentry.php
'dsn' => env('SENTRY_LARAVEL_DSN'),
'traces_sample_rate' => 0.2,  // 20% of requests traced
Benefits:
✅ Real-time error notifications
✅ Stack traces with context
✅ User context (who hit the error)
✅ Release tracking (which deploy broke it)
✅ Error frequency and trends
FREE up to 5k events/month
B. Laravel Telescope for Development (30 min)
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
Benefits:
✅ Request/response inspection
✅ Query logging with N+1 detection
✅ Exception tracking
✅ Job monitoring
✅ Cache hit rates
Only in dev/staging - not prod
C. Better File Logging (1 hour)
// config/logging.php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'info',
        'days' => 14,  // Keep 2 weeks
    ],
    
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'error',  // Only errors to Slack
    ],
],

'stack' => ['daily', 'slack'],  // Log to both
Tier 2: Intermediate (2-3 days) - $20-50/month
D. Papertrail for Log Aggregation (3 hours)
Centralized log viewer
Search across all logs
Alerts on patterns
$7/month for 1GB/month
E. Laravel Pulse (2 hours)
composer require laravel/pulse
php artisan pulse:install
Real-time performance dashboard
Slow requests
Most active users
Exception tracking
Built into Laravel 11+
F. Application Performance Monitoring (4 hours)
Scout APM or Tideways ($30-50/month)
Endpoint performance tracking
Database query analysis
External service timing
Tier 3: Enterprise (1 week) - $100+/month
G. Full Observability Stack
DataDog or New Relic APM
Infrastructure monitoring
Custom dashboards
Distributed tracing
Alerting workflows
Recommendation for Your App
Implement Tier 1 immediately (1 day):
✅ Add Sentry for production error tracking
✅ Add Telescope for dev/staging debugging
✅ Configure Slack notifications for critical errors
Add Tier 2 when budget allows (2-3 months): 4. ⏰ Papertrail for log aggregation 5. ⏰ Laravel Pulse for performance visibility
9. IMAGE VALIDATION - Dimensions Removed
The Issue
File: app/Http/Requests/StoreEventRequest.php:69-76
// Relaxed validation for images
'images' => 'nullable|array',
'images.*' => [
    'file',
    'mimes:jpeg,png,jpg,webp',
    'max:5120',
    // Removed dimensions validation as it seems to cause issues
],
Comment indicates: // Removed dimensions validation as it seems to cause issues
What Was Likely Removed
Typical Laravel image dimension validation:
'images.*' => [
    'file',
    'mimes:jpeg,png,jpg,webp',
    'max:5120',
    'dimensions:min_width=800,min_height=600,max_width=4000,max_height=4000',
    // Or ratio validation:
    'dimensions:ratio=16/9',
],
Why It Was Removed (Speculation)
Possible reasons:
Mobile uploads: Users uploading from phones with varied aspect ratios
False rejections: Valid images rejected due to too-strict rules
User complaints: "My image won't upload!"
Testing difficulty: Hard to test dimension validation in automated tests
The "issues" mentioned likely:
Portrait vs landscape conflicts
Screenshots with odd dimensions
High-DPI images triggering max dimension limits
Current Vulnerability
Without dimension validation: Scenario 1: Tiny Images
User uploads 50x50px image
→ Passes validation (no min dimensions)
→ ImageHandler resizes to 800x600 or larger (line 42)
→ Severely pixelated/blurry result
→ Poor UX, ugly event listing
Scenario 2: Extreme Aspect Ratios
User uploads 4000x100px banner image
→ Passes validation
→ ImageHandler::cover() crops to fit (line 42)
→ Most of image content lost
→ Unexpected result for user
Scenario 3: Unnecessarily Large Images
User uploads 8000x6000px RAW image (20MB but under 5MB after compression)
→ Passes validation
→ ImageHandler processes full-size image
→ High CPU/memory usage
→ Slow upload/processing
→ Image gets downscaled anyway (wasted bandwidth)
Current Protection: Backend Processing
Good news: ImageHandler::saveImage does post-processing:
public static function saveImage($image, $model, $width, $height, $type, $rank = 0)
{
    // Validate mime type
    $mimeType = $image->getMimeType();
    if (strpos($mimeType, 'image/') !== 0) {
        throw new \Exception('The file is not an image.');
    }

    // Process and resize
    $jpg = clone $image;
    $jpg->cover($width, $height);  // Crops to exact dimensions
    // ...
}
What cover() does:
Resizes and crops to exact dimensions
Maintains aspect ratio as much as possible
Crops from center if aspect doesn't match
So:
Tiny images get upscaled (blurry)
Large images get downscaled (CPU intensive)
Wrong aspect ratios get cropped (content loss)
Better Approach
Option 1: Enforce Minimum Dimensions (Recommended)
'images.*' => [
    'file',
    'mimes:jpeg,png,jpg,webp',
    'max:5120',
    'dimensions:min_width=800,min_height=600',  // Prevent tiny images
    // No max dimensions - ImageHandler will downscale
],
Why this works:
✅ Prevents blurry upscaled images
✅ Allows all aspect ratios
✅ Allows high-DPI images (will be downscaled)
✅ Clear error message: "Image must be at least 800x600px"
Option 2: Aspect Ratio Guidance (If specific ratio needed)
'images.*' => [
    'file',
    'mimes:jpeg,png,jpg,webp',
    'max:5120',
    'dimensions:min_width=800,min_height=600,ratio=3/2',  // 3:2 aspect ratio (±5%)
],

// Or multiple acceptable ratios:
'dimensions:min_width=800,min_height=600|dimensions:ratio=16/9|dimensions:ratio=4/3',
Option 3: Frontend Validation (Best UX)
// In Vue component before upload
const validateImage = (file) => {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      if (img.width < 800 || img.height < 600) {
        reject('Image must be at least 800x600 pixels');
      } else {
        resolve();
      }
    };
    img.src = URL.createObjectURL(file);
  });
};
Benefits:
Instant feedback (no server round-trip)
Can show current dimensions
Can suggest correct size
Can offer image editor/cropper
Real-World Impact
Current state allows:
✅ 8000x6000px images (wastes CPU/bandwidth)
✅ 100x100px images (blurry when displayed)
✅ 4000x500px banners (content cropped unexpectedly)
Recommended state allows:
✅ 800x600px minimum (clear, not blurry)
✅ Any aspect ratio (user flexibility)
✅ Any max size (backend downscales)
❌ Tiny images (prevents poor UX)
Recommendation Priority: MEDIUM
Estimated Effort: 2-3 hours Action Plan:
Add minimum dimensions to StoreEventRequest (15 min)
Update error messages to be helpful (30 min)
Add frontend validation to event creation wizard (2 hours)
Test with various image sizes (1 hour)
Code changes:
// StoreEventRequest.php
'images.*' => [
    'file',
    'mimes:jpeg,png,jpg,webp',
    'max:5120',
    'dimensions:min_width=800,min_height=600',
],

// Custom message
public function messages(): array
{
    return [
        'images.*.dimensions' => 'Each image must be at least 800 pixels wide and 600 pixels tall for best quality.',
        'images.*.max' => 'Each image must be less than 5MB.',
        'images.*.mimes' => 'Only JPEG, PNG, and WebP images are allowed.',
    ];
}
10. DOCUMENTATION - Missing Deployment, DB Diagrams, Admin Workflows
Current Documentation State
Existing docs (/docs):
✅ AUDIT_REPORT.md - Comprehensive audit (Oct 2025)
✅ DATES_AUDIT.md - Date creation system audit
✅ TIMEZONE_STANDARDIZATION.md - Timezone handling guide
✅ README.md - Documentation index
Missing critical docs:
❌ Deployment guide
❌ Database schema diagrams
❌ Admin workflows
❌ API documentation (covered in section 2)
❌ Environment setup
❌ Troubleshooting guide
❌ Architecture decision records (ADRs)
❌ Testing strategy
A. Missing: Deployment Guide
Critical for:
New team members
DevOps engineers
CI/CD pipeline setup
Disaster recovery
Should cover: 1. Server Requirements
## Server Requirements

### Production Environment
- **Web Server:** nginx 1.18+ or Apache 2.4+
- **PHP:** 8.2+ with extensions: bcmath, ctype, fileinfo, JSON, mbstring, OpenSSL, PDO, Tokenizer, XML
- **Database:** MySQL 8.0+ or MariaDB 10.6+
- **Search:** Elasticsearch 8.x cluster (3 nodes recommended)
- **Cache:** Redis 6+ (for sessions, cache, queues)
- **Storage:** AWS S3 or DigitalOcean Spaces
- **Memory:** 2GB RAM minimum, 4GB recommended per web worker

### Development Environment
- Docker Compose OR Laravel Sail
- Node.js 18+ and npm 9+
2. Environment Variables
## Environment Configuration

### Required Variables
```bash
APP_NAME="Everything Immersive"
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://everythingimmersive.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ei
DB_USERNAME=ei_user
DB_PASSWORD=STRONG_PASSWORD

# Elasticsearch
SCOUT_DRIVER=elastic
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_INDEX=events

# Storage (S3/Spaces)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=  # For DigitalOcean Spaces

# Mail
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@everythingimmersive.com

# OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT=

# Sentry (Error Tracking)
SENTRY_LARAVEL_DSN=

**3. Deployment Steps**
```markdown
## Deployment Process

### Initial Deployment
1. Clone repository
   ```bash
   git clone https://github.com/yourorg/ei.git /var/www/ei
   cd /var/www/ei
Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run production
Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env with production values
Run migrations
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder  # If needed
Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
Set permissions
chown -R www-data:www-data /var/www/ei
chmod -R 755 /var/www/ei/storage
chmod -R 755 /var/www/ei/bootstrap/cache
Configure web server (nginx example)
server {
    listen 80;
    server_name everythingimmersive.com;
    root /var/www/ei/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
Start queue workers
# Using supervisor
sudo supervisorctl start ei-worker:*
Index to Elasticsearch
php artisan scout:import "App\Models\Event"
Subsequent Deployments
Enter maintenance mode
php artisan down --message="Deploying updates" --retry=60
Pull latest code
git pull origin main
Update dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run production
Run migrations
php artisan migrate --force
Clear and rebuild caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
Restart services
php artisan queue:restart
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
Exit maintenance mode
php artisan up
Rollback Procedure
Enter maintenance mode
Checkout previous release tag: git checkout v1.2.3
Restore database from backup (if migrations ran)
Follow deployment steps 3-7

**4. Zero-Downtime Deployment** (Advanced)
```markdown
## Zero-Downtime Deployment (Envoyer / Deployer)

Use Envoyer or Laravel Deployer for:
- Symlink swapping
- Health checks before cutover
- Automatic rollback on failure
- No maintenance mode needed
B. Missing: Database Schema Diagrams
Current state:
108 migrations in /database/migrations
18 models with complex relationships
No visual representation
Should have: 1. Entity-Relationship Diagram (ERD)
┌─────────────┐         ┌──────────────┐
│   User      │1      * │   Organizer  │
│─────────────│◄────────│──────────────│
│ id          │         │ id           │
│ email       │         │ slug         │
│ name        │         │ name         │
│ user_type   │         └──────────────┘
└─────────────┘                │
                               │1
                               │
                              *│
                     ┌─────────▼───────┐
                     │     Event       │
                     │─────────────────│
                     │ id              │
                     │ name            │
                     │ status          │
                     │ organizer_id    │
                     │ category_id     │
                     └─────────────────┘
                               │1
                               │
                              *│
                     ┌─────────▼───────┐
                     │     Show        │
                     │─────────────────│
                     │ id              │
                     │ event_id        │
                     │ date            │
                     │ time            │
                     └─────────────────┘
Tools to generate:
dbdiagram.io (online, free, shareable)
MySQL Workbench (reverse engineer from DB)
Laravel ER Diagram Generator package
PlantUML (text-based, version control friendly)
2. Table Relationship Matrix
## Core Relationships

| Model | Relationship | Related Model | Type |
|-------|-------------|---------------|------|
| User | organizer | Organizer | belongsTo (via organizer_user pivot) |
| User | conversations | Conversation | belongsToMany |
| User | communities | Community | belongsToMany |
| Event | organizer | Organizer | belongsTo |
| Event | category | Category | belongsTo |
| Event | shows | Show | hasMany |
| Event | location | Location | hasOne |
| Event | genres | Genre | belongsToMany |
| Event | images | Image | morphMany |
| Show | event | Event | belongsTo |
| Show | tickets | Ticket | hasMany |
| Organizer | users | User | belongsToMany |
| Organizer | events | Event | hasMany |
| Organizer | images | Image | morphMany |
| Community | owner | User | belongsTo |
| Community | curators | User | belongsToMany |
| Community | posts | Post | hasMany |
| Community | shelves | Shelf | hasMany |
| Post | community | Community | belongsTo |
| Post | shelves | Shelf | belongsToMany |
| Shelf | community | Community | belongsTo |
| Shelf | posts | Post | belongsToMany |
| Shelf | cards | Card | hasMany |
3. Database Schema Documentation
## Events Table

**Purpose:** Core table for immersive events/experiences

**Schema:**
```sql
CREATE TABLE events (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    status CHAR(1),  -- 'd'=draft, 'p'=published, 'e'=embargo, 'r'=ready
    showtype CHAR(1),  -- 's'=specific dates, 'o'=ongoing, 'a'=always, 'l'=limited run
    organizer_id BIGINT UNSIGNED,
    category_id BIGINT UNSIGNED,
    attendance_type_id BIGINT UNSIGNED,  -- 1=in-person, 2=remote
    timezone VARCHAR(255),
    closingDate DATE,
    published_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES organizers(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_status_archived (status, archived),
    INDEX idx_closing_date (closingDate)
);
Status Values:
d - Draft (not visible)
p - Published (live on site)
e - Embargoed (scheduled publish)
r - Ready for review
n - Needs revision
0-8 - Creation wizard steps
Show Types:
s - Specific dates (defined shows with exact dates/times)
o - Ongoing (recurring with no end date)
a - Always available (permanent installations)
l - Limited run (date range with flexible showtimes)

---

### C. Missing: Admin Workflows

**Current state:**
- 60+ admin endpoints in [routes/api.php](routes/api.php:117-246)
- Multiple approval workflows
- No documentation for moderators/admins

**Should have:**

**1. Admin User Guide**
```markdown
# Everything Immersive - Admin Guide

## User Roles

### Guest (g)
- Can view published events
- Can create organizer account
- Can favorite events
- **Cannot:** Create events, access admin panel

### Curator (c)
- All Guest permissions
- Can create/manage communities
- Can curate posts and shelves
- Can invite other curators
- **Cannot:** Approve events, manage users

### Moderator (m)
- All Curator permissions
- Can approve events, organizers, communities
- Can manage all content
- Can view admin dashboard
- Can manage categories/genres
- **Cannot:** Manage user accounts, delete data

### Admin (a)
- All Moderator permissions
- Can manage user accounts
- Can delete any content
- Can manage system settings
- Full access to admin panel
2. Approval Workflows
## Event Approval Workflow

### Step 1: Organizer Creates Event
1. Organizer logs in
2. Navigates to "Host an Event"
3. Completes 8-step event creation wizard
4. Submits event (status changes to `r` - ready for review)

### Step 2: Moderator Reviews
1. Event appears in "Pending Events" (GET /admin/approve/events)
2. Moderator reviews:
   - Event details complete?
   - Images appropriate?
   - Category correct?
   - Dates/times valid?
   - Location accurate?
3. Moderator decides:
   - **Approve:** POST /admin/approve/events/{id}/approve
     - Status changes to `p` (published)
     - Event goes live on site
     - Organizer notified via email (if implemented)
   - **Reject:** POST /admin/approve/events/{id}/reject
     - Status changes to `n` (needs revision)
     - Organizer notified with rejection reason
     - Organizer can edit and resubmit

### Step 3: Event Goes Live
- Appears in search results
- Indexed in Elasticsearch
- Visible on organizer profile
- Available for curators to feature

### Step 4: Event Updates
- Organizer can edit published events
- Updates are immediate (no re-approval)
- Moderator can mark events for re-review if needed
3. Admin Panel Navigation
## Admin Dashboard

### Overview (GET /admin/approval-counts)
Shows counts of:
- Pending events
- Pending organizers
- Pending communities
- Pending name change requests

### Approval Tabs

#### Events (GET /admin/approve/events)
- List of events awaiting approval
- Shows: Event name, organizer, category, submission date
- Actions: Approve, Reject, View Details

#### Organizers (GET /admin/approve/organizers)
- List of new organizer applications
- Shows: Organizer name, user email, creation date
- Actions: Approve, Reject, View Profile

#### Communities (GET /admin/approve/communities)
- List of new community requests
- Shows: Community name, owner, creation date
- Actions: Approve, Reject, View Details

#### Name Change Requests (GET /admin/approve/requests)
- Requests to change organizer/community names
- Shows: Current name, requested name, reason
- Actions: Approve, Reject

### Management Tabs

#### Manage Events (GET /admin/manage/events)
- Search/filter all events
- Bulk actions
- Delete events
- Toggle curated check (featured events)

#### Manage Users (GET /admin/manage/users)
- Search users
- Change user roles
- Delete users (soft delete)

#### Manage Organizers (GET /admin/manage/organizers)
- Edit organizer details
- Delete organizers

### Settings Tabs

#### Categories (GET /admin/settings/categories)
- CRUD for event categories
- Set applicable attendance types
- Reorder categories (rank)

#### Genres (GET /admin/settings/genres)
- CRUD for genres/tags
- Toggle admin-featured genres

#### Advisories (GET /admin/settings/advisories/{type})
- Manage content advisories
- Manage mobility advisories
- Manage age limits

#### Docks (GET /admin/docks)
- Featured content management
- Add shelves/posts/cards to homepage
- Reorder featured items
4. Common Admin Tasks
## Common Tasks

### Approve Multiple Events
1. Navigate to Pending Events
2. Review each event
3. Approve or reject with reason
4. Check email notifications sent (if configured)

### Feature an Event
1. Navigate to Manage Events
2. Search for event
3. Click "Toggle Curated Check"
4. Event gets special badge/placement

### Ban a User
1. Navigate to Manage Users
2. Search for user by email/name
3. Change role to 'Guest' (remove curator/mod permissions)
4. Or delete user account (soft delete)

### Create New Category
1. Navigate to Settings > Categories
2. Click "Add Category"
3. Enter name, slug, description
4. Select applicable attendance types (in-person, remote, both)
5. Set rank (display order)
6. Save

### Handle Name Change Request
1. Navigate to Pending Requests
2. Review:
   - Current name
   - Requested name
   - Reason for change
   - Check if new name conflicts with existing organizers
3. Approve or reject
4. If approved, slug is updated (old URLs redirect)
Missing Documentation Priority
Document	Priority	Effort	Impact
Deployment Guide	CRITICAL	1-2 days	Can't deploy without tribal knowledge
Database Schema Diagrams	HIGH	4-6 hours	Hard to understand relationships
Admin Workflows	HIGH	1 day	Moderators need training
API Documentation	HIGH	1-2 weeks	(Covered in section 2)
Environment Setup	MEDIUM	4 hours	Onboarding friction
Troubleshooting Guide	MEDIUM	1 day	Support burden
Architecture Decision Records	LOW	Ongoing	Nice to have
Testing Strategy	MEDIUM	4 hours	(Related to test coverage)
Recommendation Priority: HIGH
Estimated Effort: 3-4 days for all documentation Recommended Order:
Day 1: Deployment guide (most critical)
Day 2: Admin workflows (user-facing)
Day 3: Database schema diagrams
Day 4: Environment setup + troubleshooting
SUMMARY & PRIORITIZED ACTION PLAN
Based on this deep dive, here's a prioritized roadmap:
Immediate (This Week)
✅ Delete DEAD_LoginController.php (5 min)
⚠️ Add rate limiting to API endpoints (2-3 days) - SECURITY CRITICAL
⚠️ Add database indexes to track_clicks table (30 min) - PERFORMANCE
Short Term (Next 2 Weeks)
⚠️ Write deployment documentation (1-2 days)
⚠️ Set up Sentry error tracking (2 hours)
⚠️ Fix image validation (add minimum dimensions) (2-3 hours)
⚠️ Write admin workflow docs (1 day)
Medium Term (Next Month)
⚠️ Add comprehensive test coverage (3-4 weeks) - Start with auth tests
⚠️ Create API documentation (1-2 weeks)
⚠️ Standardize error handling (2-3 days)
⚠️ Generate database schema diagrams (4-6 hours)
Long Term (Next Quarter)
⏰ Consider TypeScript migration (incremental, new code only)
⏰ Add Laravel Telescope/Pulse for monitoring
⏰ Optimize click tracking (archival strategy, caching)
