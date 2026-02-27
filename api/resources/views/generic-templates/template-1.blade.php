<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @yield('meta')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        :root { --rms-accent: {{ $primaryColor ?? '#2563eb' }}; }
        * { box-sizing: border-box; }
        body.rms-template-1 { margin: 0; font-family: 'DM Sans', system-ui, sans-serif; background: #e0f2fe; color: #1e293b; line-height: 1.5; }
        .rms-template-1 .rms-badge { position: fixed; top: 0.5rem; right: 0.5rem; background: var(--rms-accent); color: #fff; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.25rem 0.5rem; border-radius: 6px; z-index: 100; }
        .rms-template-1 .rms-header { background: #fff; padding: 1rem 1.5rem; border-bottom: 2px solid var(--rms-accent); box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .rms-template-1 .rms-header__inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .rms-template-1 .rms-header__logo { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; border: 2px solid var(--rms-accent); }
        .rms-template-1 .rms-header__title { margin: 0; font-size: 1.5rem; font-weight: 700; color: #0f172a; }
        .rms-template-1 .rms-header__tagline { margin: 0.25rem 0 0; font-size: 0.9375rem; color: #64748b; }
        .rms-template-1 .rms-main { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem 3rem; }
        .rms-template-1 .rms-hero { background: linear-gradient(135deg, var(--rms-accent) 0%, #1e40af 100%); border-radius: 20px; padding: 3rem 2rem; margin-bottom: 2.5rem; color: #fff; text-align: center; position: relative; overflow: hidden; }
        .rms-template-1 .rms-hero__banner { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.25; }
        .rms-template-1 .rms-hero__inner { position: relative; z-index: 1; }
        .rms-template-1 .rms-hero__logo { width: 80px; height: 80px; border-radius: 16px; object-fit: cover; border: 3px solid rgba(255,255,255,0.6); margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .rms-template-1 .rms-hero__name { margin: 0; font-size: 2rem; font-weight: 700; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
        .rms-template-1 .rms-hero__tagline { margin: 0.5rem 0 0; font-size: 1.125rem; opacity: 0.95; }
        .rms-template-1 .rms-menu { margin-bottom: 2.5rem; }
        .rms-template-1 .rms-menu__heading { margin: 0 0 1.5rem; font-size: 1.5rem; font-weight: 700; color: #0f172a; }
        .rms-template-1 .rms-menu__category { font-size: 1.25rem; font-weight: 700; color: var(--rms-accent); margin: 0 0 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--rms-accent); }
        .rms-template-1 .rms-menu__list { display: grid; gap: 1rem; list-style: none; padding: 0; margin: 0 0 2rem; }
        .rms-template-1 .rms-menu-item { background: #fff; border-radius: 14px; padding: 1.25rem 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-left: 4px solid var(--rms-accent); }
        .rms-template-1 .rms-menu-item__row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        .rms-template-1 .rms-menu-item__name { font-weight: 700; font-size: 1.0625rem; color: #0f172a; margin: 0 0 0.25rem; }
        .rms-template-1 .rms-menu-item__description { font-size: 0.9375rem; color: #64748b; margin: 0 0 0.5rem; }
        .rms-template-1 .rms-menu-item__price { font-weight: 700; color: var(--rms-accent); font-size: 1.125rem; white-space: nowrap; }
        .rms-template-1 .rms-menu-item--unavailable .rms-menu-item__name { opacity: 0.7; }
        .rms-template-1 .rms-about { background: #fff; border-radius: 16px; padding: 2rem; margin-bottom: 2.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .rms-template-1 .rms-about__title { margin: 0 0 1rem; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
        .rms-template-1 .rms-about__text { margin: 0; color: #475569; white-space: pre-line; }
        .rms-template-1 .rms-reviews__title { margin: 0 0 1rem; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
        .rms-template-1 .rms-review { background: #fff; border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .rms-template-1 .rms-review__stars { color: #f59e0b; font-size: 1rem; margin-bottom: 0.5rem; letter-spacing: 0.05em; }
        .rms-template-1 .rms-review__text { margin: 0 0 0.5rem; color: #475569; }
        .rms-template-1 .rms-review__meta { font-size: 0.875rem; color: #94a3b8; }
        .rms-template-1 .rms-footer { background: #0f172a; color: #94a3b8; padding: 1.5rem 1.5rem; text-align: center; font-size: 0.875rem; }
        .rms-template-1 .rms-footer__credit { margin: 0; }
    </style>
</head>
<body class="rms-public rms-template-1">
    <span class="rms-badge" aria-hidden="true">Template 1</span>
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
