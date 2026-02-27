@extends('generic-templates.' . $template)

@section('meta')
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ e($metaDescription) }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    {{-- Open Graph (Facebook, LinkedIn, etc.) --}}
    <meta property="og:title" content="{{ e($metaTitle) }}">
    <meta property="og:description" content="{{ e($metaDescription) }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta property="og:type" content="website">
    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ e($metaTitle) }}">
    <meta name="twitter:description" content="{{ e($metaDescription) }}">
    @if($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
@endsection

@section('header')
    <div class="rms-header__inner">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ e($restaurant->name) }}" class="rms-header__logo" width="48" height="48">
        @endif
        <h1 class="rms-header__title">{{ e($restaurant->name) }}</h1>
        @if($restaurant->tagline)
            <p class="rms-header__tagline">{{ e($restaurant->tagline) }}</p>
        @endif
    </div>
@endsection

@section('main')
    {{-- Hero --}}
    <section class="rms-hero" aria-label="{{ e($restaurant->name) }}">
        @if($bannerUrl ?? null)
            <img src="{{ $bannerUrl }}" alt="" class="rms-hero__banner" width="1200" height="400">
        @endif
        <div class="rms-hero__inner">
            @if($logoUrl ?? null)
                <img src="{{ $logoUrl }}" alt="{{ e($restaurant->name) }}" class="rms-hero__logo" width="80" height="80">
            @endif
            <h2 class="rms-hero__name">{{ e($restaurant->name) }}</h2>
            @if($restaurant->tagline)
                <p class="rms-hero__tagline">{{ e($restaurant->tagline) }}</p>
            @endif
        </div>
    </section>

    {{-- Menu --}}
    <section class="rms-menu" id="menu" aria-labelledby="menu-heading">
        <h3 id="menu-heading" class="rms-menu__heading">{{ __('Menu') }}</h3>
        @foreach($menuGroups ?? [] as $group)
            <h4 class="rms-menu__category">{{ e($group['category_name']) }}</h4>
            <ul class="rms-menu__list">
                @foreach($group['items'] as $item)
                    <li class="rms-menu-item {{ ($item['is_available'] ?? true) ? '' : 'rms-menu-item--unavailable' }}">
                        <div class="rms-menu-item__row">
                            <span class="rms-menu-item__name">{{ e($item['name']) }}</span>
                            @if(isset($item['price']) && $item['price'] !== null)
                                <span class="rms-menu-item__price">{{ number_format((float) $item['price'], 2) }} {{ $restaurant->currency ?? 'USD' }}</span>
                            @endif
                        </div>
                        @if(!empty($item['description']))
                            <p class="rms-menu-item__description">{{ e($item['description']) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </section>

    {{-- About --}}
    @if(!empty($description))
    <section class="rms-about" id="about" aria-labelledby="about-heading">
        <h3 id="about-heading" class="rms-about__title">{{ __('About') }}</h3>
        <p class="rms-about__text">{{ e($description) }}</p>
    </section>
    @endif

    {{-- Reviews --}}
    @if(!empty($feedbacks))
    <section class="rms-reviews" id="reviews" aria-labelledby="reviews-heading">
        <h3 id="reviews-heading" class="rms-reviews__title">{{ __('Reviews') }}</h3>
        @foreach($feedbacks as $fb)
            <div class="rms-review">
                <div class="rms-review__stars" aria-hidden="true">{{ str_repeat('★', (int) ($fb['rating'] ?? 0)) }}{{ str_repeat('☆', 5 - (int) ($fb['rating'] ?? 0)) }}</div>
                @if(!empty($fb['text']))<p class="rms-review__text">{{ e($fb['text']) }}</p>@endif
                <p class="rms-review__meta">{{ e($fb['name'] ?? __('Anonymous')) }}</p>
            </div>
        @endforeach
    </section>
    @endif
@endsection

@section('footer')
    <p class="rms-footer__credit">&copy; {{ date('Y') }} {{ e($restaurant->name) }}</p>
@endsection
