<script setup lang="ts">
import { reactive, ref, useId } from 'vue'

import { parseValidationErrors } from '@/lib/validationErrors'
import type { HabitPayload } from '@/types/api'

const props = defineProps<{
  submitLabel: string
  initialValues?: HabitPayload
  save: (payload: HabitPayload) => Promise<void>
}>()

const emit = defineEmits<{ saved: []; cancel: [] }>()

const form = reactive({
  name: props.initialValues?.name ?? '',
  description: props.initialValues?.description ?? '',
})
const fieldErrors = ref<Record<string, string>>({})
const isSubmitting = ref(false)
// The create form and any open edit forms share a page, so label targets must be unique.
const nameId = useId()
const descriptionId = useId()

async function submit() {
  fieldErrors.value = {}
  isSubmitting.value = true

  try {
    await props.save({ name: form.name, description: form.description.trim() || null })
  } catch (error) {
    // Non-422 failures are already shown as a banner by the store.
    fieldErrors.value = parseValidationErrors(error, Object.keys(form)) ?? {}
    isSubmitting.value = false
    return
  }

  isSubmitting.value = false
  if (!props.initialValues) {
    form.name = ''
    form.description = ''
  }
  emit('saved')
}
</script>

<template>
  <form class="space-y-3" @submit.prevent="submit">
    <div>
      <label :for="nameId" class="block text-sm font-medium">Name</label>
      <input
        :id="nameId"
        v-model="form.name"
        name="name"
        type="text"
        maxlength="100"
        required
        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
      />
      <p v-if="fieldErrors.name" class="mt-1 text-sm text-red-600">{{ fieldErrors.name }}</p>
    </div>

    <div>
      <label :for="descriptionId" class="block text-sm font-medium">Description</label>
      <textarea
        :id="descriptionId"
        v-model="form.description"
        name="description"
        maxlength="500"
        rows="2"
        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
      />
      <p v-if="fieldErrors.description" class="mt-1 text-sm text-red-600">
        {{ fieldErrors.description }}
      </p>
    </div>

    <div class="flex gap-2">
      <button
        type="submit"
        :disabled="isSubmitting"
        class="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {{ submitLabel }}
      </button>
      <button
        v-if="initialValues"
        type="button"
        class="rounded-md border border-gray-300 px-4 py-2 font-medium hover:bg-gray-50"
        @click="emit('cancel')"
      >
        Cancel
      </button>
    </div>
  </form>
</template>
