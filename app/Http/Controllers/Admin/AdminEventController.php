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
            ->with(['organizer', 'images', 'location', 'category', 'clicks', 'curatedCheck'])
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

        $events = $query->paginate(20);
        
        // Calculate total clicks for each event
        foreach ($events as $event) {
            $event->total_clicks = $event->clicks->count();
            $event->unique_visitors = $event->clicks->unique('ip_address')->count();
        }

        return $events;
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
            'videos',
            'staffpick',
            'clicks'
        ]);

        // Calculate total clicks and unique visitors
        $event->total_clicks = $event->clicks->count();
        $event->unique_visitors = $event->clicks->unique('ip_address')->count();

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
        
        // Handle organizer update action
        if ($request->action === 'update_organizer') {
            $validated = $request->validate([
                'organizer_id' => ['required', 'exists:organizers,id'],
            ]);
            
            $event->update([
                'organizer_id' => $validated['organizer_id']
            ]);
            
            return $event->fresh(['organizer', 'images', 'category', 'location']);
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

        try {
            // Approve organizer if not already approved
            if ($event->organizer->status !== 'p') {
                $event->organizer->update(['status' => 'p']);
            }

            // Create curated check record
            $event->curatedCheck()->create();

            // Generate final slug
            $slug = Event::finalSlug($event);
            
            // Update event with slug first before finalizing images
            $event->slug = $slug;
            $event->save();

            // Finalize images with the new slug
            ImageHandler::finalize($event, $slug, 'event');

            // Determine status based on embargo date
            $status = $event->embargo_date && $event->embargo_date > Carbon::now() 
                ? 'e' 
                : 'p';

            // Format the date explicitly to match Elasticsearch mapping
            $event->update([
                'status' => $status,
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
                'event' => $event->fresh(['images', 'organizer'])
            ]);
        } catch (\Exception $e) {
            \Log::error('Error approving event: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error approving event: ' . $e->getMessage()
            ], 500);
        }
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

    public function toggleCheck(Request $request, Event $event)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:curated,social,newsletter'
        ]);

        // Load the event with its curated check
        $event->load('curatedCheck');

        // If no curated check exists yet, create one
        if (!$event->curatedCheck) {
            $event->curatedCheck()->create([
                'curated' => null,
                'social' => null,
                'newsletter' => null
            ]);
            $event->refresh();
        }

        // Cycle through the three states: null -> false -> true -> null
        $type = $validated['type'];
        $currentValue = $event->curatedCheck->$type;
        
        // Determine the next state
        $nextValue = null;
        if ($currentValue === null) {
            $nextValue = false;
        } elseif ($currentValue === false) {
            $nextValue = true;
        } else {
            $nextValue = null;
        }
        
        // Update the check
        $event->curatedCheck->update([
            $type => $nextValue
        ]);

        return response()->json([
            'message' => $type . ' status updated successfully',
            'check' => $event->curatedCheck->fresh(),
            'event' => $event->fresh(['curatedCheck', 'organizer', 'images', 'category', 'location'])
        ]);
    }
}
