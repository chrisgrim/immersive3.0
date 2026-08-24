<?php

namespace App\Mail;

use App\Actions\Search\BuildSearchUrlAction;
use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\SerializesModels;

class SavedSearchNewEventsMail extends Mailable
{
    use Queueable, SerializesModels;

    public SavedSearch $savedSearch;
    public Collection $events;
    public string $searchUrl;

    public function __construct(SavedSearch $savedSearch, Collection $events)
    {
        $this->savedSearch = $savedSearch;
        $this->events = $events->load('images');
        $this->searchUrl = (new BuildSearchUrlAction)->handle($savedSearch->criteria);
    }

    public function build()
    {
        $count = $this->events->count();
        $subject = $count === 1
            ? "1 new event matches \"{$this->savedSearch->name}\""
            : "{$count} new events match \"{$this->savedSearch->name}\"";

        return $this->subject($subject)
            ->markdown('emails.saved-search-new-events', [
                'savedSearch' => $this->savedSearch,
                'events' => $this->events,
                'searchUrl' => $this->searchUrl,
            ]);
    }
}
