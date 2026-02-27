<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @yield('meta')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --rms-accent: {{ $primaryColor ?? '#0f172a' }}; }
        * { box-sizing: border-box; }
        body.rms-template-2 { margin: 0; font-family: 'IBM Plex Sans', system-ui, sans-serif; background: #fafafa; color: #0f172a; line-height: 1.6; }
        .rms-template-2 .rms-badge { position: fixed; top: 0.5rem; right: 0.5rem; background: #0f172a; color: #fff; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.25rem 0.5rem; border-radius: 2px; z-index: 100; }
        .rms-template-2 .rms-header { background: #fff; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .rms-template-2 .rms-header__inner { max-width: 800px; margin: 0 auto; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .rms-template-2 .rms-header__logo { width: 48px; height: 48px; border-radius: 4px; object-fit: cover; }
        .rms-template-2 .rms-header__title { margin: 0; font-size: 1.25rem; font-weight: 600; color: #0f172a; letter-spacing: -0.02em; }
        .rms-template-2 .rms-header__tagline { margin: 0.2rem 0 0; font-size: 0.875rem; color: #64748b; font-weight: 400; }
        .rms-template-2 .rms-main { max-width: 800px; margin: 0 auto; padding: 3rem 1.5rem 4rem; }
        .rms-template-2 .rms-hero { padding: 0 0 2.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 2.5rem; }
        .rms-template-2 .rms-hero__banner { width: 100%; max-height: 280px; object-fit: cover; display: block; margin-bottom: 1.5rem; }
        .rms-template-2 .rms-hero__inner { }
        .rms-template-2 .rms-hero__logo { width: 64px; height: 64px; border-radius: 4px; object-fit: cover; margin-bottom: 0.75rem; display: block; }
        .rms-template-2 .rms-hero__name { margin: 0; font-size: 1.75rem; font-weight: 700; color: #0f172a; letter-spacing: -0.03em; }
        .rms-template-2 .rms-hero__tagline { margin: 0.5rem 0 0; font-size: 1rem; color: #64748b; font-weight: 400; }
        .rms-template-2 .rms-menu { margin-bottom: 2.5rem; }
        .rms-template-2 .rms-menu__heading { margin: 0 0 1.5rem; font-size: 1rem; font-weight: 600; color: #0f172a; }
        .rms-template-2 .rms-menu__category { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; margin: 0 0 1rem; }
        .rms-template-2 .rms-menu__list { list-style: none; padding: 0; margin: 0 0 2.5rem; }
        .rms-template-2 .rms-menu-item { padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
        .rms-template-2 .rms-menu-item:last-child { border-bottom: none; }
        .rms-template-2 .rms-menu-item__row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        .rms-template-2 .rms-menu-item__name { font-weight: 600; font-size: 1rem; color: #0f172a; margin: 0; }
        .rms-template-2 .rms-menu-item__price { font-weight: 500; color: #0f172a; font-size: 1rem; white-space: nowrap; }
        .rms-template-2 .rms-menu-item__description { font-size: 0.875rem; color: #64748b; margin: 0.25rem 0 0; }
        .rms-template-2 .rms-menu-item--unavailable .rms-menu-item__name { color: #94a3b8; }
        .rms-template-2 .rms-about { margin-bottom: 2.5rem; }
        .rms-template-2 .rms-about__title { margin: 0 0 0.75rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; }
        .rms-template-2 .rms-about__text { margin: 0; font-size: 0.9375rem; color: #475569; white-space: pre-line; }
        .rms-template-2 .rms-reviews__title { margin: 0 0 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; }
        .rms-template-2 .rms-review { padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
        .rms-template-2 .rms-review:last-child { border-bottom: none; }
        .rms-template-2 .rms-review__stars { color: #0f172a; font-size: 0.875rem; margin-bottom: 0.35rem; letter-spacing: 0.02em; }
        .rms-template-2 .rms-review__text { margin: 0 0 0.35rem; font-size: 0.9375rem; color: #475569; }
        .rms-template-2 .rms-review__meta { font-size: 0.8125rem; color: #94a3b8; }
        .rms-template-2 .rms-footer { background: #f8fafc; padding: 1.5rem 1.5rem; text-align: center; font-size: 0.8125rem; color: #64748b; border-top: 1px solid #e2e8f0; }
        .rms-template-2 .rms-footer__credit { margin: 0; }
    </style>
</head>
<body class="rms-public rms-template-2">
    <span class="rms-badge" aria-hidden="true">Template 2</span>
    <header class="rms-header" role="banner">
        @yield('header')
    </header>
    <main class="rms-main" id="main" role="main">
        @yield('main')
    </main>
    <footer class="rms-footer" role="contentinfo">
        @yield('footer')
    </footer>
    @stack('scripts')
</body>
</html>
