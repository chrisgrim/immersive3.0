<?php

namespace App\Mcp\Tools;

use App\Models\Organizer;
use App\Services\ImageHandler;
use App\Services\ImageIngestException;
use App\Services\RemoteImageIngest;
use App\Support\Validation\OrganizerRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an organizer you belong to: description, contact email, website, social handles, Patreon, or logo. Send only the fields you are changing. Not available while the organizer is under admin review (locked until approved or rejected), and renaming a PUBLISHED organizer goes through a name-change request on the website instead.')]
class UpdateOrganizer extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'organizer_slug' => 'required|string|exists:organizers,slug',
            'name' => ['sometimes', ...OrganizerRules::name()],
            'description' => 'sometimes|'.OrganizerRules::description(),
            'image_url' => 'nullable|url',
            'remove_image' => 'nullable|boolean',
        ] + OrganizerRules::contact(), OrganizerRules::messages());

        $organizer = Organizer::where('slug', $validated['organizer_slug'])->first();

        if (! $user->can('edit', $organizer)) {
            return Response::error('You do not have permission to edit this organizer.');
        }

        // Site rule: once submitted for review, locked until an admin
        // approves or rejects it (moderators can still edit).
        if ($organizer->status === 'r' && ! $user->isModerator()) {
            return Response::error('This organizer is under review and cannot be edited until an admin approves or rejects it.');
        }

        // Same rule as the web flow: published organizers rename via the
        // name-change request system, not a direct edit.
        if ($organizer->status === 'p' && isset($validated['name']) && $validated['name'] !== $organizer->name) {
            return Response::error('This organizer is published, so its name can only be changed through a name-change request on the website. Other fields can still be updated here.');
        }

        $data = collect($validated)->only([
            'name', 'description', 'email', 'website',
            'instagramHandle', 'twitterHandle', 'facebookHandle', 'patreon',
        ])->all();

        if (! empty($validated['image_url'])) {
            try {
                $image = app(RemoteImageIngest::class)->fetch($validated['image_url'], 8 * 1024 * 1024);
            } catch (ImageIngestException $e) {
                return Response::error('Image download failed: '.$e->getMessage());
            }

            try {
                foreach ($organizer->images as $existing) {
                    ImageHandler::deleteImage($existing);
                }
                ImageHandler::saveImage($image, $organizer, 800, 800, 'organizer-images');
            } finally {
                @unlink($image->getRealPath());
            }
        } elseif (! empty($validated['remove_image'])) {
            foreach ($organizer->images as $existing) {
                ImageHandler::deleteImage($existing);
            }
        }

        if ($data !== []) {
            $organizer->update($data);
        }

        $organizer->refresh();

        return Response::json([
            'message' => 'Organizer updated.',
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'status' => $organizer->status,
                'description' => $organizer->description,
                'email' => $organizer->email,
                'website' => $organizer->website,
                'instagramHandle' => $organizer->instagramHandle,
                'twitterHandle' => $organizer->twitterHandle,
                'facebookHandle' => $organizer->facebookHandle,
                'patreon' => $organizer->patreon,
                'has_logo' => filled($organizer->largeImagePath) || $organizer->images()->exists(),
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_slug' => $schema->string()->description('The organizer slug (see whoami).')->required(),
            'name' => $schema->string()->description('New name, max 80 chars. Only works while the organizer is not yet published.'),
            'description' => $schema->string()->description('What this organizer does (1-2000 chars).'),
            'email' => $schema->string()->description('Public contact email.'),
            'website' => $schema->string()->description('Website URL, must start with https://.'),
            'instagramHandle' => $schema->string()->description('Instagram handle without @.'),
            'twitterHandle' => $schema->string()->description('Twitter/X handle without @.'),
            'facebookHandle' => $schema->string()->description('Facebook page handle.'),
            'patreon' => $schema->string()->description('Patreon handle.'),
            'image_url' => $schema->string()->description('Public URL of a new logo (jpeg/png/webp, max 8 MB, cropped square). Replaces the current one.'),
            'remove_image' => $schema->boolean()->description('Remove the current logo without adding a new one.'),
        ];
    }
}
