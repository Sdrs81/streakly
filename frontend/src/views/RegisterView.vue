<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { parseValidationErrors } from '@/lib/validationErrors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const fieldErrors = ref<Record<string, string>>({})
const errorMessage = ref('')
const isSubmitting = ref(false)

async function submit() {
  fieldErrors.value = {}
  errorMessage.value = ''
  isSubmitting.value = true

  try {
    await auth.register(form)
  } catch (error) {
    const validation = parseValidationErrors(error, Object.keys(form))
    if (validation) {
      fieldErrors.value = validation
    } else {
      errorMessage.value = 'Something went wrong, please try again.'
    }
    isSubmitting.value = false
    return
  }

  // Outside the try block: a failed navigation must not look like a failed register.
  // The button stays disabled until navigation settles so a double click cannot register twice.
  try {
    await router.push({ name: 'habits' })
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <section class="mx-auto max-w-sm">
    <h1 class="text-2xl font-bold">Create an account</h1>

    <p v-if="errorMessage" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700" role="alert">
      {{ errorMessage }}
    </p>

    <form class="mt-6 space-y-4" @submit.prevent="submit">
      <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          autocomplete="name"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
        />
        <p v-if="fieldErrors.name" class="mt-1 text-sm text-red-600">{{ fieldErrors.name }}</p>
      </div>

      <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
        />
        <p v-if="fieldErrors.email" class="mt-1 text-sm text-red-600">{{ fieldErrors.email }}</p>
      </div>

      <div>
        <label for="password" class="block text-sm font-medium">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          autocomplete="new-password"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
        />
        <p v-if="fieldErrors.password" class="mt-1 text-sm text-red-600">
          {{ fieldErrors.password }}
        </p>
      </div>

      <div>
        <label for="password_confirmation" class="block text-sm font-medium">
          Confirm password
        </label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          required
          class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
        />
        <p v-if="fieldErrors.password_confirmation" class="mt-1 text-sm text-red-600">
          {{ fieldErrors.password_confirmation }}
        </p>
      </div>

      <button
        type="submit"
        :disabled="isSubmitting"
        class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        Register
      </button>
    </form>

    <p class="mt-4 text-sm text-gray-600">
      Already registered?
      <RouterLink :to="{ name: 'login' }" class="font-medium text-indigo-600">Log in</RouterLink>
    </p>
  </section>
</template>
