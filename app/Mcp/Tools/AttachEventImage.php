<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Scopes\PublishedScope;
use App\Services\ImageHandler;
use App\Services\ImageIngestException;
use App\Services\RemoteImageIngest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Attach an image to one of your events by downloading it from a public URL (jpeg/png/webp, max 5 MB, max 5 images per event). Rank 0 is the primary portrait image (cropped 900x1200); ranks 1-4 are gallery landscape images (cropped 1200x800). An existing image at the same rank is replaced.')]
class AttachEventImage extends Tool
{
    use FormatsEvents;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_slug' => 'required|string',
            'image_url' => 'required|url',
            'rank' => 'required|integer|min:0|max:4',
        ]);

        $event = Event::withoutGlobalScope(PublishedScope::class)
            ->where('slug', $validated['event_slug'])
            ->first();

        if (! $event) {
            return Response::error('No event found with that slug.');
        }

        if (! $user->can('manage', $event)) {
            return Response::error('You do not have permission to edit this event.');
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
            // Same replace-then-save sequence as the web upload path.
            if ($existingAtRank) {
                ImageHandler::deleteImage($existingAtRank);
            }

            ImageHandler::saveImage(
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

        return Response::json([
            'message' => $rank === 0
                ? 'Primary image attached.'
                : "Gallery image attached at rank {$rank}.",
            'images' => $event->images()->orderBy('rank')->get(['id', 'rank', 'large_image_path']),
        ]);
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
