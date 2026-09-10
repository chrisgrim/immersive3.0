<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Scopes\LatestPublishedFirstScope;
use App\Services\ImageHandler;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Remove one image from an event you can manage — for moderators and admins that is any event on the platform. Identify the image by its id (from get-event or the attach-event-image response), never by rank: removing a gallery image shifts the later ones up, so ranks change but ids do not. Rank 0 is the primary image and cannot be removed once the event has been submitted, published or embargoed — replace it with attach-event-image instead. The file is deleted from storage, so this cannot be undone — confirm with the user first.')]
class RemoveEventImage extends Tool
{
    use FormatsEvents;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_slug' => 'required|string',
            'image_id' => 'required|integer',
        ]);

        $event = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('slug', $validated['event_slug'])
            ->first();

        // One message whether the slug is unknown or the event is someone
        // else's — see GetEvent.
        if (! $event || ! $user->can('manage', $event)) {
            return Response::error('No event with that slug that you can edit. Slugs come from list-my-events.');
        }

        // See UpdateEvent: a long-finished published event is read-only to
        // its organizers.
        if ($event->isEditLockedFor($user)) {
            return Response::error(self::EDIT_LOCKED_MESSAGE);
        }

        // Site rule: once submitted, an event is locked until an admin
        // approves or rejects it (moderators can still edit).
        if ($event->status === 'r' && ! $user->isModerator()) {
            return Response::error('This event is under review and cannot be edited until an admin approves or rejects it.');
        }

        // Address the row by id, scoped to this event. A rank would be unsafe
        // here: after one gallery removal every later rank shifts down, so a
        // second call using ranks read before the first would delete a
        // different (irreplaceable) file.
        //
        // The whole lookup → delete → renumber runs under a row lock so two
        // overlapping calls for the same event serialize instead of both
        // deleting the same row and double-shifting the survivors.
        $result = DB::transaction(function () use ($event, $validated) {
            $image = $event->images()->whereKey((int) $validated['image_id'])->lockForUpdate()->first();

            if (! $image) {
                return Response::error('No image with that id on this event. Current images: '.$this->describeImages($event));
            }

            $rank = (int) $image->rank;

            // The cover is the rank-0 row — or, on legacy rows, whichever
            // image the event's own cover columns point at.
            $backsCover = $rank === 0
                || (filled($event->largeImagePath) && $image->large_image_path === $event->largeImagePath);

            // The website never lets a submitted or live event lose its
            // primary image: the wizard refuses to save the images step
            // without one, and approval publishes whatever is there. Mirror
            // that here — the public page, cards and map popup all assume a
            // cover — and point at the replace path instead. Moderators
            // included: this protects the listing, it is not a permission
            // question.
            if ($backsCover && in_array($event->status, ['r', 'p', 'e'], true)) {
                return Response::error('An event that has been submitted, published or embargoed must keep a primary image. To change it, attach the new image with attach-event-image at rank 0 — it replaces the current one.');
            }

            // Same helper as the web wizard and attach-event-image: deletes
            // every stored variant, the row, and (for rank 0) the event's own
            // primary image columns.
            ImageHandler::deleteImage($image);

            // deleteImage only clears the cover columns for rank 0; a legacy
            // cover backed by another rank must not leave them pointing at
            // files that are now gone.
            if ($backsCover && $rank !== 0) {
                $event->update(['largeImagePath' => null, 'thumbImagePath' => null]);
            }

            // The wizard renumbers the whole gallery 1..n on every save, so
            // it never has a hole in it. Do the same here (ids and files
            // untouched — only the rank column moves) so an event edited by
            // both paths looks identical, and so a repeated run is a no-op.
            $event->images()->where('rank', '>', 0)->orderBy('rank')->orderBy('id')->get()
                ->each(function ($survivor, $index) {
                    if ((int) $survivor->rank !== $index + 1) {
                        $survivor->update(['rank' => $index + 1]);
                    }
                });

            return $rank;
        });

        if ($result instanceof Response) {
            return $result;
        }

        $rank = $result;

        $event->refresh();

        return Response::json([
            'message' => $rank === 0
                ? 'Primary image removed. The event needs a new primary image (attach-event-image, rank 0) before it can be submitted.'
                : "Gallery image at rank {$rank} removed.",
            'images' => $event->images()->orderBy('rank')->get(['id', 'rank', 'large_image_path']),
        ]);
    }

    /**
     * "id 12 (rank 0), id 15 (rank 1)" or "none" — for the error when the id
     * is unknown, so the caller can pick a real one without another round trip.
     */
    protected function describeImages(Event $event): string
    {
        $images = $event->images()->orderBy('rank')->get(['id', 'rank']);

        return $images->isEmpty()
            ? 'none.'
            : $images->map(fn ($i) => "id {$i->id} (rank {$i->rank})")->implode(', ').'.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The event slug.')->required(),
            'image_id' => $schema->integer()->description('The id of the image to remove, as listed by get-event or returned by attach-event-image. Ids are stable; ranks shift after a removal, so never guess an id from a rank.')->required(),
        ];
    }
}
