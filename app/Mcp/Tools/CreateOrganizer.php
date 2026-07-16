<?php

namespace App\Mcp\Tools;

use App\Actions\Organizers\CreateOrganizerAction;
use App\Models\Organizer;
use App\Services\ImageIngestException;
use App\Services\RemoteImageIngest;
use App\Support\Validation\OrganizerRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new organizer (the group that hosts events) owned by the authenticated user. The organizer goes into admin review, but you can create event drafts under it immediately. If a similarly-named organizer already exists you must confirm with the user and retry with acknowledge_duplicate=true.')]
class CreateOrganizer extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate(
            [
                'name' => OrganizerRules::name(),
                'description' => OrganizerRules::description(),
                'image_url' => 'nullable|url',
                'acknowledge_duplicate' => 'nullable|boolean',
            ] + OrganizerRules::contact(),
            OrganizerRules::messages()
        );

        // Same duplicate signal as the web flow's organizers/check-name endpoint.
        if (empty($validated['acknowledge_duplicate'])) {
            $existing = Organizer::where('name', 'LIKE', $validated['name'])
                ->where('status', '!=', 'd')
                ->get(['id', 'name', 'slug', 'user_id', 'status']);

            if ($existing->isNotEmpty()) {
                return Response::json([
                    'error' => 'duplicate_name',
                    'message' => 'One or more organizations with this name already exist. Ask the user whether this is really a new organization. If one of these is theirs, they may be able to claim it on its page on the website instead. To proceed anyway, call this tool again with acknowledge_duplicate=true.',
                    'existing_organizations' => $existing->map(fn ($organizer) => [
                        'id' => $organizer->id,
                        'name' => $organizer->name,
                        'slug' => $organizer->slug,
                        'claimable' => $organizer->isClaimable(),
                    ])->values(),
                ]);
            }
        }

        $image = null;
        if (! empty($validated['image_url'])) {
            try {
                $image = app(RemoteImageIngest::class)->fetch($validated['image_url'], 8 * 1024 * 1024);
            } catch (ImageIngestException $e) {
                return Response::error('Image download failed: '.$e->getMessage());
            }
        }

        try {
            $organizer = app(CreateOrganizerAction::class)->handle(
                $user,
                collect($validated)->only([
                    'name', 'description', 'email', 'website',
                    'instagramHandle', 'twitterHandle', 'facebookHandle', 'patreon',
                ])->all(),
                $image
            );
        } finally {
            if ($image) {
                @unlink($image->getRealPath());
            }
        }

        return Response::json([
            'message' => 'Organizer created and submitted for admin review. You can create event drafts under it right away.',
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'status' => $organizer->status,
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Organizer name (max 80 chars, must contain a letter or number).')->required(),
            'description' => $schema->string()->description('What this organizer does (1-2000 chars).')->required(),
            'email' => $schema->string()->description('Public contact email.'),
            'website' => $schema->string()->description('Website URL, must start with https://.'),
            'instagramHandle' => $schema->string()->description('Instagram handle without @.'),
            'twitterHandle' => $schema->string()->description('Twitter/X handle without @.'),
            'facebookHandle' => $schema->string()->description('Facebook page handle.'),
            'patreon' => $schema->string()->description('Patreon handle.'),
            'image_url' => $schema->string()->description('Optional public URL of a logo/profile image (jpeg/png/webp, max 8 MB). It will be cropped square.'),
            'acknowledge_duplicate' => $schema->boolean()->description('Set true only after the user confirms creating despite an existing similarly-named organizer.'),
        ];
    }
}
