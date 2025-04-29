		<meta charset="utf-8">
		<meta http-equiv="x-ua-compatible" content="ie=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        {{-- Primary Meta Tags for SEO --}}
        @if(!View::hasSection('meta'))
            <meta name="title" content="{{ isset($title) ? $title : config('app.name') . ' - Discover Immersive Events' }}">
            <meta name="description" content="{{ isset($description) ? $description : 'Find and explore immersive events, experiences, and performances. Everything Immersive connects you with unique entertainment around the world.' }}">
            
            {{-- Open Graph / Facebook Meta Tags --}}
            <meta property="og:type" content="{{ isset($ogType) ? $ogType : 'website' }}">
            <meta property="og:url" content="{{ url()->current() }}">
            <meta property="og:title" content="{{ isset($title) ? $title : config('app.name') . ' - Discover Immersive Events' }}">
            <meta property="og:description" content="{{ isset($description) ? $description : 'Find and explore immersive events, experiences, and performances. Everything Immersive connects you with unique entertainment around the world.' }}">
            <meta property="og:image" content="{{ isset($ogImage) ? $ogImage : asset('storage/website-files/Everything_Immersive_logo_Short.png') }}">
            
            {{-- Twitter Meta Tags --}}
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:url" content="{{ url()->current() }}">
            <meta name="twitter:title" content="{{ isset($title) ? $title : config('app.name') . ' - Discover Immersive Events' }}">
            <meta name="twitter:description" content="{{ isset($description) ? $description : 'Find and explore immersive events, experiences, and performances. Everything Immersive connects you with unique entertainment around the world.' }}">
            <meta name="twitter:image" content="{{ isset($ogImage) ? $ogImage : asset('storage/website-files/Everything_Immersive_logo_Short.png') }}">
            
            {{-- Canonical URL to prevent duplicate content issues --}}
            <link rel="canonical" href="{{ url()->current() }}">
        @endif
        
        {{-- Favicon and App Icons --}}
        <link rel="apple-touch-icon" sizes="180x180" href="/storage/website-files/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/storage/website-files/favicons/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/storage/website-files/favicons/favicon-16x16.png">
        <link rel="manifest" href="/storage/website-files/favicons/site.webmanifest">
        <link rel="mask-icon" href="/storage/website-files/favicons/safari-pinned-tab.svg" color="#f7653b">
        <meta name="msapplication-TileColor" content="#da532c">
        <meta name="theme-color" content="#f7653b">
        
        {{-- Language and Locale information for international SEO --}}
        @if(!View::hasSection('meta'))
            <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
            <link rel="alternate" href="{{ url()->current() }}" hreflang="x-default">
            <link rel="alternate" href="{{ url()->current() }}" hreflang="{{ str_replace('_', '-', app()->getLocale()) }}">
        @endif
        
        @stack('head')
        @yield('head')

        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            rel="preload"
            href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600&display=swap"
            as="style"
            onload="this.onload=null;this.rel='stylesheet'"
        />
        <noscript>
            <link
                href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600&display=swap"
                rel="stylesheet"
                type="text/css"
            />
        </noscript>
        
        {{-- Analytics with IP Anonymization and Consent Compliance --}}
        <script async rel="preconnect" src="https://www.googletagmanager.com/gtag/js?id={{Config::get('services.analytics.id')}}"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '{{Config::get('services.analytics.id')}}', {
              'anonymize_ip': true,
              'page_title': document.title,
              'page_path': window.location.pathname + window.location.search
          });
        </script>
        
        <script>
            window.Laravel = {
                user: {!! Auth::check() ? json_encode(Auth::user()->forClientSide()) : 'null' !!},
                isMobile: {!! Browser::isMobile() ? 'true' : 'false' !!}
            };
        </script>
        <style type="text/css">html{font-size:62.5%;font-family:'Montserrat',sans-serif;height:100%}body{font-size:1.6rem;line-height:2rem;font-family:'Montserrat',sans-serif;margin:0;height:100%;color:#000}</style>

        @vite(['resources/js/app.js'])