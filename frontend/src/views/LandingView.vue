<template>
  <div class="flex min-h-screen overflow-hidden font-display bg-background-light dark:bg-background-dark">
    <!-- Left: brand / hero (hidden on mobile) -->
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
            Menus, QR codes, and opening hours in one place. We’re offering this app to restaurant owners who care about clarity.
          </p>
        </div>
        <p class="text-sm text-white/50">© Restaurant Management System. Free for restaurant owners.</p>
      </div>
    </div>
    <!-- Right: CTAs -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 md:p-12 lg:p-24">
      <div class="w-full max-w-md space-y-8">
        <div class="lg:hidden flex items-center gap-2 mb-8">
          <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
            <span class="material-icons text-white">restaurant</span>
          </div>
          <span class="text-xl font-bold tracking-tight text-primary">RMS</span>
        </div>
        <div class="space-y-4">
          <h2 class="text-3xl font-bold text-charcoal dark:text-white">
            We’re offering this app
          </h2>
          <p class="text-charcoal/70 dark:text-white/70">
            Sign in to manage your restaurant, or create an account to get started for free.
          </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-4">
          <router-link
            :to="{ name: 'Login' }"
            class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-lg font-semibold border-2 border-primary/20 text-primary hover:bg-primary/5 transition-colors"
          >
            <span class="material-icons text-lg">login</span>
            Login
          </router-link>
          <router-link
            :to="{ name: 'App' }"
            class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-lg font-semibold bg-primary text-white shadow-sm shadow-primary/20 hover:bg-primary/90 transition-colors"
          >
            <span class="material-icons text-lg">arrow_forward</span>
            Go to the app
          </router-link>
        </div>
        <p class="text-sm text-charcoal/60 dark:text-white/60">
          Don’t have an account?
          <router-link :to="{ name: 'Register' }" class="font-medium text-primary hover:text-primary/80">
            Create one
          </router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>
