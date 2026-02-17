<template>
  <div class="min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
      <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 tracking-tight">Vending Machine Orchestrator</h1>
        <nav class="flex gap-1">
          <router-link
            to="/machines"
            class="px-4 py-2 text-sm font-medium rounded-md"
            :class="$route.path === '/machines' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
          >Machines</router-link>
          <router-link
            to="/inventory"
            class="px-4 py-2 text-sm font-medium rounded-md"
            :class="$route.path === '/inventory' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
          >Inventory</router-link>
          <router-link
            to="/buy"
            class="px-4 py-2 text-sm font-medium rounded-md"
            :class="$route.path === '/buy' ? 'bg-emerald-600 text-white' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'"
          >Buy Products</router-link>
        </nav>
      </div>
    </header>

    <!-- Content -->
    <main class="flex-1 max-w-5xl w-full mx-auto px-6 py-8">
      <router-view />
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 bg-white">
      <div class="max-w-5xl mx-auto px-6 py-3 text-center text-xs text-gray-400">
        Vending Machine Orchestrator &middot; Laravel + Vue
      </div>
    </footer>
  </div>
</template>
