<?php

namespace App\Mcp\Tools;

use App\Models\Organizer;
use App\Services\ImageHandler;
use App\Services\ImageIngestException;
use App\Services\NameChangeRequestService;
use App\Services\RemoteImageIngest;
use App\Support\Validation\OrganizerRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an organizer: description, contact email, website, social handles, Patreon, or logo. Works on organizers you belong to — and for moderators and admins, on ANY organizer, including ones you are not a member of. Send only the fields you are changing. Not available while the organizer is under admin review (locked until approved or rejected). Renaming a PUBLISHED organizer does not apply immediately: it files a name-change request for admin review (the current name stays live until approved), while any other fields in the same call still update right away.')]
class UpdateOrganizer extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            // Not `exists:` — that validated (and so disclosed) slug existence
            // before the authorization check below had its say.
            'organizer_slug' => 'required|string',
            'name' => ['sometimes', ...OrganizerRules::name()],
            'description' => 'sometimes|'.OrganizerRules::description(),
            'image_url' => 'nullable|url',
            'remove_image' => 'nullable|boolean',
        ] + OrganizerRules::contact(), OrganizerRules::messages());

        $organizer = Organizer::where('slug', $validated['organizer_slug'])->first();

        // One message whether the slug is unknown or the organizer is someone
        // else's — see GetEvent.
        if (! $organizer || ! $user->can('edit', $organizer)) {
            return Response::error('No organizer with that slug that you can edit. Slugs come from whoami.');
        }

        // Site rule: once submitted for review, locked until an admin
        // approves or rejects it (moderators can still edit).
        if ($organizer->status === 'r' && ! $user->isModerator()) {
            return Response::error('This organizer is under review and cannot be edited until an admin approves or rejects it.');
        }

        // Fetch the new logo FIRST: it is the only network-fallible step, and a
        // failure here must not leave a partial update behind — neither a filed
        // name-change request nor persisted field writes with an error returned.
        $image = null;
        if (! empty($validated['image_url'])) {
            try {
                $image = app(RemoteImageIngest::class)->fetch($validated['image_url'], 8 * 1024 * 1024);
            } catch (ImageIngestException $e) {
                return Response::error('Image download failed: '.$e->getMessage());
            }
        }

        $data = collect($validated)->only([
            'name', 'description', 'email', 'website',
            'instagramHandle', 'twitterHandle', 'facebookHandle', 'patreon',
        ])->all();

        // See OrganizerRules::normalizeHandle() — strips a leading "@" an
        // LLM caller may pass despite the schema saying "without @".
        foreach (['instagramHandle', 'twitterHandle'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = OrganizerRules::normalizeHandle($data[$field]);
            }
        }

        // Never write a published organizer's name directly — once published,
        // the name changes only through the name-change request filed below.
        if ($organizer->status === 'p') {
            unset($data['name']);
        }

        if ($image !== null) {
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

        // File the name-change request LAST — same path as the web flow, but
        // only after every other mutation has succeeded, so a failure above can
        // never orphan a pending request. The service self-guards against a
        // second pending request; drafts and in-review orgs are renamed
        // directly via $data above.
        $nameChange = null;
        if ($organizer->status === 'p' && isset($validated['name']) && $validated['name'] !== $organizer->name) {
            $nameChange = app(NameChangeRequestService::class)
                ->handleNameChange($organizer, $validated['name']);
        }

        $organizer->refresh();

        $otherFieldsChanged = $data !== []
            || ! empty($validated['image_url'])
            || ! empty($validated['remove_image']);

        $payload = [
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
        ];

        if ($nameChange !== null) {
            $payload['name_change'] = [
                'requested_name' => $validated['name'],
                'submitted' => $nameChange['success'],
                'detail' => $nameChange['message'],
            ];

            if ($nameChange['success']) {
                $payload['message'] = $otherFieldsChanged
                    ? 'Organizer updated. The name change was submitted for admin review — the current name stays live until an admin approves it.'
                    : 'Name change submitted for admin review — the current name stays live until an admin approves it.';
            } else {
                // e.g. a pending name-change request already exists.
                $payload['message'] = $otherFieldsChanged
                    ? 'Organizer updated, but the name change was not submitted: '.$nameChange['message'].'.'
                    : 'Name change not submitted: '.$nameChange['message'].'.';
            }
        }

        return Response::json($payload);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_slug' => $schema->string()->description('The organizer slug (see whoami).')->required(),
            'name' => $schema->string()->description('New name, max 80 chars. Applied directly while the organizer is a draft or in review; once published it files a name-change request for admin approval instead (the current name stays live until approved).'),
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
