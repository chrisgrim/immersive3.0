# AI search: research notes (2026-10-02)

Idea: an Airbnb-style "type what you want" search box, so people can write
"something spooky for a date night in LA this weekend under $50" instead of
clicking through categories and genres. Four agents looked at it from
different angles. Nothing is built yet.

## Agreed shape

1. **Weekly offline enrichment.** A local assistant (MC) runs once a week, reads
   new or changed events, and writes a short plain summary plus simple labels
   (kid friendly, scary level, good for a date, wheelchair accessible, etc.).
   These get sent up to the site and indexed so search can match them.
2. **Live search box.** Claude Haiku (`claude-haiku-4-5`) turns the typed
   sentence into the same filter params the site already uses. The server
   checks every value, and the results page shows removable chips for what it
   understood. If the AI fails, it falls back to normal search.

Enrichment comes first. Without it, the AI understands "not scary, kid
friendly" but has nothing to match against.

## How search works today

- Nav bar has three tabs: Location (city + date range), Name (autocomplete that
  jumps to one event or organizer page), At Home (pick one platform).
- The results page Filters panel has a price slider, Categories and Genres.
- `app/Actions/Search/EventSearchFilterBuilder.php` accepts exactly: in-person
  vs at-home, lat/lng or map box, category ids, genre ids, one remote platform,
  price min/max, start/end date. That is the whole search vocabulary.
- There is **no free-text search of results**. Keyword matching only exists in
  nav autocomplete (`SearchActions::eventSearch`, name fields only).
- `Event::toSearchableArray` indexes name, category, genres, shows, price,
  coordinates, platform ids. **Not** description, tagline, advisories,
  interactive level, age limit or the wheelchair flag.
- Finding "escape room in LA under $50 this weekend" takes 6 or 7 clicks today.

## Why categories and genres feel clunky

- 23 categories, split by attendance type (16 in-person, 7 at-home). VR lives in
  several places: an in-person VR category, the at-home "Virtual Worlds"
  category, VR Chat platforms, and the genres "VR", "Virtual Reality",
  "Social VR".
- 1,677 genres, all typed by organizers (1,589 used by the ~4,630 published
  events). Only ~60 are flagged `admin=1` and shown in the filter panel.
- Lots of synonyms: Kid-Friendly / Family-Friendly / For Kids / Kidmersive /
  Family; Horror / Spooky / Spooky Season / Halloween / Thriller; Theatre /
  theater. Zoom and Gather are both platforms and genres. "Immersive" and
  "Participatory" are on almost everything, so they filter nothing.
- Advisories (content, mobility, interactive level, age limit) are the most
  useful "is this right for me" data, and none of them are searchable.

## Example queries and the gaps

| What someone types | Maps to today | Gap |
|---|---|---|
| Something spooky for a date night in LA this weekend under $50 | LA, Sat/Sun, $0-50, Horror/Spooky genres | "date night" |
| VR I can do from home with friends | at-home, VR Chat / Virtual Worlds, Social VR | group size |
| Not scary, kid friendly, Saturday afternoon in Chicago | Chicago, date, kid genres | NOT filter, advisories, show times |
| Escape room for 6 people, bachelorette | Escape Rooms category | group size, occasion |
| Free things to do in NYC tonight | price 0, NYC, today | none |
| Wheelchair accessible immersive theatre in London | category + location | wheelchair flag not indexed |
| Something like Sleep No More | nothing | no similarity or description search |
| Audio walk I can do on my own, under an hour | at-home Audio, Solo genre | no duration field |

## Cost (Haiku 4.5)

- Price: $1 per million input tokens, $5 per million output. Cache reads ~$0.10/M.
  Haiku 4.5 only caches a prompt of 4,096+ tokens, so a short prompt never caches.
- Volume (GA4, last 90 days): ~37k users, ~50k sessions. Search page
  `/index/search`: ~26.6k views, ~8.9k sessions, so roughly 9,000 search
  pageviews and 3,000 search sessions a month. About 19% of users are the
  Singapore bot.
- Per call: lean prompt (23 categories + ~59 admin genres + instructions) is
  about 800 tokens in, 100 out, so **~$0.0013 per search**. A prompt with all
  1,677 genres is ~6,500 in, so ~$0.007 uncached and ~$0.0016 cached.
- Monthly: realistic (5k to 10k AI calls) **$7 to $15**. 10x traffic $130 to
  $160 (lean). Bot worst case: one IP at today's 120/min throttle is 5.2M
  calls/month, about $6,800 lean. Rotating proxies multiply that.
- Verdict: cost is not the problem for real visitors. Bot abuse is the only
  real risk, and guardrails fix it.

### Required guardrails

1. Hard monthly spend cap in the Anthropic Console, plus our own Redis counter
   that switches to plain search when it is hit.
2. Per-IP throttle of 5 to 20 per minute plus a global daily ceiling (e.g. 2,000/day).
3. Cache normalized query to filters in Redis. TTL until local midnight,
   because "this weekend" changes meaning.
4. Skip the AI when the query is a direct hit on an event name, category,
   genre or city.
5. `max_tokens` about 200 to 400, strict JSON output.
6. ALTCHA proof-of-work (already used by the suggest-an-event form) rather than
   login-only, since nearly all searchers are guests.
7. Log every call with token counts for a weekly cost check.

### Cheaper alternatives considered

- Elasticsearch synonyms mapping phrases to categories and genres (free).
- A rule-based parser for "this weekend", city names and category keywords.
- Embeddings / semantic search need a non-Anthropic model (Voyage or a local
  sentence-transformer) and RAM the single droplet does not have. ELSER needs
  an Elastic license.

## Architecture (live box)

- Seam: `/index/search` is `ListingsController::index/apiIndex`.
  `buildCriteria()` turns the request into a criteria array (`searchType`,
  `lat/lng`, `live`, `NE*/SW*`, `categoryIds`, `tagIds`, `remoteLocationId`,
  `priceMin/Max`, `start/end`). `EventSearchFilterBuilder` turns that into ES
  filters. `BuildSearchUrlAction::handle($criteria)` already turns a criteria
  array back into a `/index/search?...` URL.
- So v1 is **AI -> criteria array -> BuildSearchUrlAction -> navigate**, with no
  change to the search pipeline.
- New `App\Actions\Search\InterpretSearchQueryAction`: one non-streaming call
  using structured output (JSON schema, `additionalProperties: false`)
  returning something like:
  `{searchType, category_slugs[], genre_slugs[], remote_location_slug,
  place_text, near_me, date: {kind: today|tonight|this_weekend|next_week|range,
  start, end}, price_max, keywords}`.
  The server resolves slugs against the real categories, admin genres and
  remote locations, and drops anything unknown.
- Reuse the shape of `App\Services\EventScheduleAssistant::callAnthropic` (raw
  `Http::post` to `/v1/messages`, `services.anthropic.api_key`). No PHP SDK is
  installed. Use `claude-haiku-4-5`, no thinking, 6s timeout, stable
  category/genre list first in `system` with `cache_control`.
- Latency: about 0.7 to 1.5s for Haiku with a small prompt (Airbnb takes 3 to
  5s). No streaming; spinner on the input, then navigate (same flow as
  `handleLocationSearch` in `nav-search.vue`).

### Failure handling

- API down, timeout or error: return null, and the frontend falls back to the
  normal name search and says so.
- Nonsense output: the schema plus id checks make it a harmless filters-only search.
- Prompt injection: the user's text is data, the output is a closed list of
  values, and no model text is ever shown.
- Dates: the model returns a *kind* ("this_weekend"), and the server works out
  the real dates with Carbon in the browser's timezone. The model never does date math.
- "Near me": a flag only; browser geolocation supplies lat/lng.
- Place names: `GeocodingService::search` (cache it; Nominatim allows 1 req/s).
  If that fails, search worldwide and show a "couldn't find X" chip.

### Open decision: words that match no filter

- A) Filters only (unmatched words are dropped, but shown as "ignored").
- B) Add a `keywords` param to `ListingsController` that matches event names only (small).
- C) Index description / tagline / the new summaries and reindex prod for real content search.

Recommendation: A, then B, and decide C with data. The weekly summaries make C
much more useful.

## What Airbnb does (tried hands-on 2026-10-02)

Launched in the US on 2026-09-30 after a summer test. It sits behind a toggle,
and the classic search is still there.

- One big box replaces the location/date/guest fields. You type a sentence,
  and 3 to 5 seconds later you get a normal results page where your words have
  become ordinary, hand-editable filter chips (place, dates, guests, price,
  tags), plus a one-line summary ("These experiences in Los Angeles are good
  for kids").
- Follow-ups refine the same search ("Add to your search"; a thread id lives in
  the URL). "Actually make it San Francisco" swapped the city and kept the
  rest. A brand-new sentence resets the old filters.
- Best result: "cozy cabin with a hot tub near Lake Tahoe for 4 people next
  weekend under $300 a night" became Lake Tahoe / dates / 4 guests / price
  converted to a 2-night total / Entire home / Hot tub.
- Weak spot: words outside its tags get silently dropped or mis-mapped.
  "Spooky/haunted" became "Landmarks" (zero results, no explanation). "Cozy",
  "next month" and "with friends" vanished. "Surprise me" asked nothing and
  just showed homes nearby.
- Inside filters you can type to find an option instead of scrolling.

**EI should copy:**
1. Words become visible, editable chips plus one summary sentence, not a chat window.
2. Refinement in the same box, with a smart reset when the topic changes.
3. Show what was dropped or guessed ("couldn't use: cozy"). This is the gap
   that hurt Airbnb most.
4. Type-to-find inside the genre filter list (a cheap win even without AI).

**EI should skip:**
- AI-rewritten event titles (organizers chose those names).
- Voice input, and an always-on homepage banner.
- Quietly forcing vague input into a default location. Ask one question or show
  a curated "surprise me" pick instead.

## Biggest risk

Confident wrong answers. Returning a horror show for "kid friendly" even once
is worse than a clunky checkbox. Visible chips plus "showing X because you
said Y" are the safety net.

## Suggested build order (small steps)

1. Pick the short label list the weekly MC enrichment will write for each event.
2. Store the labels and summary on events and add them to the search index.
3. MC weekly job: read new/changed events, write labels and summary, send them up.
4. `InterpretSearchQueryAction` + tests (fake HTTP responses).
5. `POST /api/search/interpret` with throttle, Redis cache and fallback.
6. One "Describe it" box in `nav-search.vue`, spinner, chips of what was understood.
7. Dates and near-me.
8. Log one row per interpretation (like `mcp_tool_calls`) to tune the prompt.
