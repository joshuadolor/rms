<section class="rms-hero" aria-labelledby="rms-hero-title">
    @if($bannerUrl ?? null)
        <div class="rms-hero__banner">
            <img src="{{ $bannerUrl }}" alt="" class="rms-hero__banner-img" width="1200" height="400" loading="eager">
        </div>
    @endif
    <div class="rms-hero__content">
        @if($logoUrl ?? null)
            <img src="{{ $logoUrl }}" alt="{{ e($restaurant->name) }}" class="rms-hero__logo" width="80" height="80" loading="eager">
        @endif
        <h1 id="rms-hero-title" class="rms-hero__title">{{ e($restaurant->name) }}</h1>
        @if($restaurant->tagline)
            <p class="rms-hero__tagline">{{ e($restaurant->tagline) }}</p>
        @endif
    </div>
</section>
