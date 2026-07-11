<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $isPublicLandingPage = ($page['component'] ?? null) === 'Dashboard';
            $canonicalUrl = rtrim((string) config('app.url'), '/');
            $seoDescription = 'Analyze your resume, measure ATS readiness, compare it with a job description, and get practical AI-assisted recommendations with Resumo.';
            $socialImage = asset('images/resumo-logo.png');
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="robots" content="{{ $isPublicLandingPage ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}">
        @if ($isPublicLandingPage)
            <link rel="canonical" href="{{ $canonicalUrl }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="Resumo">
            <meta property="og:title" content="Resumo — AI Resume Analysis and ATS Scoring">
            <meta property="og:description" content="{{ $seoDescription }}">
            <meta property="og:url" content="{{ $canonicalUrl }}">
            <meta property="og:image" content="{{ $socialImage }}">
            <meta property="og:image:alt" content="Resumo resume analysis platform">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="Resumo — AI Resume Analysis and ATS Scoring">
            <meta name="twitter:description" content="{{ $seoDescription }}">
            <meta name="twitter:image" content="{{ $socialImage }}">
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => 'Resumo',
                'url' => $canonicalUrl,
                'description' => $seoDescription,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'USD',
                ],
                'featureList' => [
                    'Resume analysis',
                    'ATS readiness scoring',
                    'Job description matching',
                    'AI-assisted resume recommendations',
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('images/resumo-icon.png') }}" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <meta name="app-base-path" content="{{ rtrim(parse_url((string) config('app.url'), PHP_URL_PATH) ?: '', '/') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
