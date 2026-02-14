<template>
  <div class="flex min-h-screen overflow-hidden font-display bg-background-light dark:bg-background-dark">
    <!-- Left: brand image (hidden on mobile) -->
    <div class="hidden lg:flex lg:w-1/2 relative">
      <div class="absolute inset-0 bg-primary/10 mix-blend-multiply z-10" />
      <div class="absolute inset-0 bg-gradient-to-t from-background-dark/80 via-transparent to-background-dark/20 z-10" />
      <img
        src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=1200"
        alt=""
        role="presentation"
        class="absolute inset-0 w-full h-full object-cover"
      />
      <div class="relative z-20 flex flex-col justify-between h-full p-12 text-white">
        <div class="flex items-center gap-2">
          <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-icons text-white">restaurant</span>
          </div>
          <span class="text-xl font-bold tracking-tight">RMS</span>
        </div>
        <div class="bg-background-dark/80 p-6 rounded-lg">
          <h1 class="text-5xl font-bold leading-tight mb-4">
            Manage your restaurant.<br />
            <span class="text-primary">Simple and free.</span>
          </h1>
          <p class="text-lg text-white/80 max-w-md">
            Menus, QR codes, and opening hours in one place. For owners who care about clarity.
          </p>
        </div>
        <p class="text-sm text-white/50">© Restaurant Management System. Free for restaurant owners.</p>
      </div>
    </div>
    <!-- Right: form slot -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 md:p-12 lg:p-24">
      <div class="w-full max-w-md">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
</script>
