<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run once when venue ownership goes live: every venue that nobody owns and no
 * team keeps is given to one person (an admin), so no team inherits a live
 * venue it never added. The real venue can still claim it afterwards: an
 * approved claim hands it over (claimed_at stays empty until then).
 *
 * Dry run unless --apply. The user is named by id, never by email, so nothing
 * personal needs to be typed into a deploy script or the repo.
 */
class AssignVenueOwner extends Command
{
    protected $signature = 'ei:venues-assign-owner {user : The id of the user who will own them} {--apply : Write the change}';

    protected $description = 'Give every unowned, unkept venue to one user. Dry run unless --apply.';

    public function handle(): int
    {
        $user = User::find((int) $this->argument('user'));

        if (! $user) {
            $this->error('No user with that id.');

            return self::FAILURE;
        }

        if (! $user->isAdmin()) {
            $this->error("{$user->name} is not an admin. Venues go to an admin until their real owner claims them.");

            return self::FAILURE;
        }

        $query = Venue::query()->whereNull('user_id')->whereNull('organizer_id')->whereNull('merged_into_id');
        $count = $query->count();

        if (! $this->option('apply')) {
            $this->info("{$count} venues would go to {$user->name} (#{$user->id}). Run again with --apply.");

            return self::SUCCESS;
        }

        $given = 0;
        $query->select('id')->chunkById(500, function ($venues) use ($user, &$given) {
            $given += DB::table('venues')
                ->whereIn('id', $venues->pluck('id'))
                ->whereNull('user_id')
                ->whereNull('organizer_id')
                ->update(['user_id' => $user->id]);
        });

        AuditLog::record($user, 'venues.assigned_owner', $user, ['count' => $given]);

        $this->info("{$given} venues now belong to {$user->name} (#{$user->id}).");

        return self::SUCCESS;
    }
}
