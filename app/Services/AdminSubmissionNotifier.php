<?php

namespace App\Services;

use App\Mail\EventSubmittedNotification;
use App\Mail\OrganizerSubmittedNotification;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails admins when something enters the review queue, honoring each admin's
 * per-type notification opt-outs (User::wantsNotification, default ON).
 */
class AdminSubmissionNotifier
{
    public function organizerSubmitted(Organizer $organizer): void
    {
        $this->notifyAdmins('organizers', fn () => new OrganizerSubmittedNotification($organizer));
    }

    public function eventSubmitted(Event $event): void
    {
        $this->notifyAdmins('events', fn () => new EventSubmittedNotification($event));
    }

    private function notifyAdmins(string $prefKey, callable $makeMailable): void
    {
        // Tiny set (admins only) + opt-out default lives in wantsNotification(), so filter in PHP.
        $admins = User::where('type', 'a')->get()->filter(fn ($admin) => $admin->wantsNotification($prefKey));

        foreach ($admins as $admin) {
            try {
                Mail::to($admin)->send($makeMailable());
            } catch (\Exception $e) {
                Log::error('Failed to send admin submission notification:', [
                    'type' => $prefKey,
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
