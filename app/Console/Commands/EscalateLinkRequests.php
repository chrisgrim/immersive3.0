<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\ProjectLinkRequest;
use App\Services\AdminSubmissionNotifier;
use Illuminate\Console\Command;

/**
 * A link request left unanswered for 72 hours goes to the moderators, once.
 * The clock is submitted_at, not created_at: an unsubmitted duplicate has none,
 * and one sent back to its organizer is not waiting on anyone.
 */
class EscalateLinkRequests extends Command
{
    protected $signature = 'ei:escalate-link-requests';

    protected $description = 'Send link requests unanswered for 72 hours to the moderators';

    public function handle(AdminSubmissionNotifier $notifier): int
    {
        $escalated = 0;

        ProjectLinkRequest::pending()
            ->whereNull('escalated_at')
            ->where('submitted_at', '<=', now()->subHours(ProjectLinkRequest::ESCALATE_AFTER_HOURS))
            ->whereHas('event', fn ($q) => $q->whereIn('status', [Event::STATUS_IN_REVIEW, ...Event::LIVE_STATUSES]))
            ->with(['event.organizer', 'project', 'sourceEvent'])
            ->chunkById(100, function ($requests) use ($notifier, &$escalated) {
                foreach ($requests as $request) {
                    // Marked first, and only by the run that wins it: once.
                    $won = ProjectLinkRequest::whereKey($request->id)->whereNull('escalated_at')->update(['escalated_at' => now()]);

                    if ($won) {
                        $notifier->linkRequestEscalated($request);
                        $escalated++;
                    }
                }
            });

        $this->info("Escalated {$escalated} link request(s).");

        return self::SUCCESS;
    }
}
