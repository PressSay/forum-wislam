<template>
  <div class="flex items-center justify-center">
    <div class="text-center">
      <h1 class="text-6xl font-bold text-indigo-600">404</h1>
      <p class="text-2xl mt-4 text-gray-700">Oops! The page you are looking for does not exist.</p>
      <p class="text-gray-500 mt-2">Maybe the URL is wrong or the page has been deleted.</p>
      <Link
        :href="route('categories.explore')"
        class="inline-block mt-6 px-6 py-2 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 transition"
      >
        Back to Home
      </Link>
    </div>
  </div>
</template>

<script setup>
    import { Link } from '@inertiajs/vue3';
</script>
