<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Home Page -->
    <url>
        <loc>{{ url('/') }}</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    
    <!-- Search Page -->
    <url>
        <loc>{{ route('search') }}</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    
    <!-- Static Pages (no lastmod — we can't know when these actually changed,
         and a false value undermines crawler trust in lastmod everywhere) -->
    <url>
        <loc>{{ route('terms') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>{{ route('privacy') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>{{ route('sitemap') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.5</priority>
    </url>
    
    <!-- Upcoming events (and always-available ones) -->
    @foreach ($upcomingEvents as $event)
    <url>
        <loc>{{ route('events.show', $event) }}</loc>
        <lastmod>{{ $event->updated_at->toIso8601String() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    @endforeach

    <!-- Past events: still listed, so they stay findable, but below upcoming ones -->
    @foreach ($pastEvents as $event)
    <url>
        <loc>{{ route('events.show', $event) }}</loc>
        <lastmod>{{ $event->updated_at->toIso8601String() }}</lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    @endforeach
    
    <!-- Organizers - Only include canonical plural URLs -->
    @foreach ($organizers as $organizer)
    <url>
        <loc>{{ route('organizers.show', $organizer) }}</loc>
        <lastmod>{{ $organizer->updated_at->toIso8601String() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
    
    <!-- Communities — fresh when the community itself or any of its posts changed -->
    @foreach ($communities as $community)
    <url>
        <loc>{{ url('/communities/' . $community->slug) }}</loc>
        <lastmod>{{ max($community->updated_at, \Carbon\Carbon::parse($community->posts_max_updated_at ?? $community->updated_at))->toIso8601String() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach

    <!-- Community posts -->
    @foreach ($posts as $post)
    <url>
        <loc>{{ url('/communities/' . $post->community->slug . '/posts/' . $post->slug) }}</loc>
        <lastmod>{{ $post->updated_at->toIso8601String() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach

    <!-- Category pages (no lastmod: their content is whatever is on now) -->
    @foreach ($categories as $category)
    <url>
        <loc>{{ url('/index/search?category=' . $category->id . '&searchType=allEvents') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
</urlset> 