<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Scopes\PublishedScope;
use Illuminate\Support\Facades\Mail;
use App\Mail\Comments;
use App\Models\Messaging\Message;
use App\Services\ImageHandler;
use Illuminate\Support\Facades\Cache;


class AdminEventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query()
            ->with(['organizer', 'images', 'location', 'category', 'clicks'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                switch ($status) {
                    case 'published':
                        $query->whereIn('status', ['p', 'e']);
                        break;
                    case 'in_progress':
                        $query->whereNotIn('status', ['p', 'e'])
                              ->whereNull('deleted_at');
                        break;
                    case 'deleted':
                        $query->onlyTrashed();
                        break;
                }
            })
            ->when($request->ending_soon, function ($query) {
                $query->where(function($q) {
                    $q->where('closingDate', '<=', now()->addDays(10))
                      ->where('closingDate', '>', now())
                      ->where(function($subQ) {
                          $subQ->where('showtype', 'a')
                               ->orWhereHas('shows', function($showQ) {
                                   $showQ->select('event_id')
                                        ->groupBy('event_id')
                                        ->havingRaw('COUNT(*) > 100');
                               });
                      });
                });
            })
            ->when($request->sort, function ($query, $sort) {
                switch ($sort) {
                    case 'oldest':
                        $query->oldest();
                        break;
                    case 'newest':
                    default:
                        $query->latest();
                        break;
                }
            }, function ($query) {
                $query->latest(); // Default sort
            });

        return $query->paginate(20);
    }

    public function show(Event $event)
    {
        $event->load([
            'location',
            'contentAdvisories',
            'contactLevels',
            'mobilityAdvisories',
            'advisories',
            'interactive_level',
            'remotelocations',
            'genres',
            'priceranges',
            'shows.tickets',
            'age_limits',
            'images',
            'category',
            'organizer',
            'eventreviews',
            'staffpick'
        ]);

        // Find any events with the same name (case-insensitive)
        $duplicateEvents = Event::whereRaw('LOWER(name) = ?', [strtolower($event->name)])
            ->where('id', '!=', $event->id)
            ->select('id', 'name', 'slug')
            ->get();

        $event->duplicateEvents = $duplicateEvents;

        return response()->json($event);
    }

    public function getPending()
    {
        return Event::where('status', 'r')
            ->with(['organizer', 'images', 'category', 'location'])
            ->withoutGlobalScope(PublishedScope::class)
            ->latest()
            ->paginate(20);
    }

    public function update(Request $request, $id)
    {
        // Find the event even if it's deleted
        $event = Event::withTrashed()->findOrFail($id);
        
        if ($request->has('restore')) {
            $event->restore();
            return response()->json(['message' => 'Event restored successfully']);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'in:0,p,r,e,n'],
        ]);
        
        $event->update($validated);
        
        return $event->fresh(['organizer', 'images', 'category', 'location']);
    }

    public function approve(Event $event)
    {
        $event->load(['user', 'organizer']);

        // Approve organizer if not already approved
        if ($event->organizer->status !== 'p') {
            $event->organizer->update(['status' => 'p']);
        }

        // Create curated check record
        $event->curatedCheck()->create();

        // Generate final slug
        $slug = Event::finalSlug($event);

        // Finalize images
        ImageHandler::finalize($event, $slug, 'event');

        // Determine status based on embargo date
        $status = $event->embargo_date && $event->embargo_date > Carbon::now() 
            ? 'e' 
            : 'p';

        // Format the date explicitly to match Elasticsearch mapping
        $event->update([
            'status' => $status,
            'slug' => $slug,
            'published_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // Clear caches since we're publishing a new event
        Cache::forget('active-categories');
        Cache::forget('active-genres');

        // Send notifications if not self-approving
        if (auth()->id() !== $event->user->id) {
            $message = $event->status === 'e' 
                ? Message::MESSAGES['APPROVED_EMBARGOED']
                : Message::MESSAGES['APPROVED'];
            
            Message::notification($event, $message, $event->slug);
            Mail::to($event->user)->send(new Comments($event, $message, 'approved'));
        }

        return response()->json([
            'message' => 'Event approved successfully',
            'event' => $event->fresh()
        ]);
    }

    public function reject(Event $event, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        // Update event status
        $event->update([
            'status' => 'n',
            'rejection_reason' => $validated['reason']
        ]);

        // Create rejection message with reason
        $message = "We've reviewed your event and have some feedback that needs to be addressed.\n\nFeedback: {$validated['reason']}";
        $inAppMessage = "We've reviewed your event and have some feedback that needs to be addressed.\n\nFeedback: {$validated['reason']}";

        if(auth()->id() !== $event->user->id) {
            $message = Message::MESSAGES['REJECTED'] . "\n\nReason: {$validated['reason']}";
            
            // Send in-app notification
            Message::notification($event, $inAppMessage, $event->slug);
            
            // Send email notification
            Mail::to($event->user)->send(new Comments($event, $message, 'rejected'));
        }

        return response()->json([
            'message' => 'Event rejected successfully',
            'event' => $event->fresh()
        ]);
    }

    public function destroy(Event $event)
    {
        $event->delete();
        return response()->json(['message' => 'Event deleted successfully']);
    }
}
