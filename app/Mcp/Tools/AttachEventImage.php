<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Models\Image;
use App\Scopes\LatestPublishedFirstScope;
use App\Services\ImageHandler;
use App\Services\ImageIngestException;
use App\Services\RemoteImageIngest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Attach an image to an event you can manage — for moderators and admins that is any event on the platform — by downloading it from a public URL (jpeg/png/webp, max 5 MB, max 5 images per event). Rank 0 is the primary portrait image (cropped 900x1200); ranks 1-4 are gallery landscape images (cropped 1200x800). An existing image at the same rank is replaced. Returns a preview of the cropped result so the user can check the framing.')]
class AttachEventImage extends Tool
{
    use FormatsEvents;

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_slug' => 'required|string',
            'image_url' => 'required|url',
            'rank' => 'required|integer|min:0|max:4',
        ]);

        $event = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('slug', $validated['event_slug'])
            ->first();

        // One message whether the slug is unknown or the event is someone
        // else's — see GetEvent.
        if (! $event || ! $user->can('manage', $event)) {
            return Response::error('No event with that slug that you can edit. Slugs come from list-my-events.');
        }

        // Site rule: once submitted, an event is locked until an admin
        // approves or rejects it (moderators can still edit).
        if ($event->status === 'r' && ! $user->isModerator()) {
            return Response::error('This event is under review and cannot be edited until an admin approves or rejects it.');
        }

        $rank = (int) $validated['rank'];
        $existingAtRank = $event->images()->where('rank', $rank)->first();

        if (! $existingAtRank && $event->images()->count() >= 5) {
            return Response::error('This event already has 5 images. Re-use an existing rank (0-4) to replace one.');
        }

        try {
            $image = app(RemoteImageIngest::class)->fetch($validated['image_url'], 5 * 1024 * 1024);
        } catch (ImageIngestException $e) {
            return Response::error('Image download failed: '.$e->getMessage());
        }

        try {
            // Same delete-then-save sequence as the web upload path. The new
            // image was already downloaded, sniffed, and decode-verified by
            // RemoteImageIngest, so the remaining failure window (storage
            // outage mid-save) matches the web flow's existing behavior.
            if ($existingAtRank) {
                ImageHandler::deleteImage($existingAtRank);
            }

            $created = ImageHandler::saveImage(
                $image,
                $event,
                ($rank === 0) ? 900 : 1200,
                ($rank === 0) ? 1200 : 800,
                'event-images',
                $rank
            );
        } finally {
            @unlink($image->getRealPath());
        }

        $event->refresh();

        $summary = Response::json([
            'message' => $rank === 0
                ? 'Primary image attached.'
                : "Gallery image attached at rank {$rank}.",
            'images' => $event->images()->orderBy('rank')->get(['id', 'rank', 'large_image_path']),
        ]);

        $preview = $this->preview($created);

        return $preview ? Response::make([$summary, $preview]) : $summary;
    }

    /**
     * The cropped thumbnail, as an MCP image block, so the client can show the
     * user what the crop actually did rather than just a storage path.
     *
     * ImageHandler writes a JPEG twin of every WebP it saves; we send that one
     * because it is the format every MCP client renders. Half-size thumb, not
     * the full image: ~350 image tokens instead of ~1400, and framing is all
     * anyone is checking here.
     *
     * A preview is a nicety on top of a write that already succeeded, so any
     * failure to read it back is swallowed — never turn a saved image into a
     * failed tool call.
     */
    protected function preview(?Image $created): ?Response
    {
        if (! $created || blank($created->thumb_image_path)) {
            return null;
        }

        $path = '/public/'.preg_replace('/\.webp$/', '.jpg', $created->thumb_image_path);

        try {
            $data = Storage::disk('digitalocean')->get($path);
        } catch (\Throwable) {
            return null;
        }

        return ($data === null || $data === '') ? null : Response::image($data, 'image/jpeg');
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The event slug.')->required(),
            'image_url' => $schema->string()->description('Public https URL of the image (jpeg/png/webp, max 5 MB).')->required(),
            'rank' => $schema->integer()->description('0 = primary portrait image (900x1200 crop, required before submission); 1-4 = gallery landscape images (1200x800 crop).')->required(),
        ];
    }
}
