<script setup lang="ts">
import { RouterView } from 'vue-router'

import AppHeader from '@/components/AppHeader.vue'
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-900">
    <AppHeader />
    <main class="mx-auto max-w-2xl px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>
