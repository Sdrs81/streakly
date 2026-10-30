<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const isLoggingOut = ref(false)

async function logout() {
  isLoggingOut.value = true

  try {
    await auth.logout()
  } catch (error) {
    // The store clears the local session even when the request fails.
    console.warn('Logout request failed', error)
  }

  try {
    await router.push({ name: 'login' })
  } finally {
    isLoggingOut.value = false
  }
}
</script>

<template>
  <header class="border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-2xl items-center justify-between px-4 py-3">
      <span class="text-lg font-bold text-indigo-600">Streakly</span>
      <button
        v-if="auth.isAuthenticated"
        type="button"
        :disabled="isLoggingOut"
        class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-50"
        @click="logout"
      >
        Log out
      </button>
    </div>
  </header>
</template>
