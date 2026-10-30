<script setup lang="ts">
import { ref } from 'vue'

import HabitForm from '@/components/HabitForm.vue'
import { formatDayCount } from '@/lib/formatDayCount'
import { useHabitsStore } from '@/stores/habits'
import type { Habit, HabitPayload } from '@/types/api'

const props = defineProps<{ habit: Habit }>()

const emit = defineEmits<{ toggle: []; delete: [] }>()

const habits = useHabitsStore()
const isEditing = ref(false)

function saveEdit(payload: HabitPayload): Promise<void> {
  return habits.updateHabit(props.habit, payload)
}

function confirmDelete() {
  if (window.confirm(`Delete "${props.habit.name}"?`)) emit('delete')
}
</script>

<template>
  <li class="rounded-lg border border-gray-200 p-4">
    <HabitForm
      v-if="isEditing"
      submit-label="Save"
      :initial-values="{ name: habit.name, description: habit.description }"
      :save="saveEdit"
      @saved="isEditing = false"
      @cancel="isEditing = false"
    />

    <template v-else>
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="font-semibold">{{ habit.name }}</h2>
          <p v-if="habit.description" class="mt-1 text-sm text-gray-600">
            {{ habit.description }}
          </p>
        </div>
        <span
          class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800"
        >
          {{ formatDayCount(habit.current_streak) }} streak
        </span>
      </div>

      <div class="mt-4 flex flex-wrap gap-2">
        <button
          type="button"
          :aria-pressed="habit.is_completed_today"
          class="rounded-md px-4 py-2 text-sm font-medium"
          :class="
            habit.is_completed_today
              ? 'bg-green-600 text-white hover:bg-green-700'
              : 'border border-gray-300 hover:bg-gray-50'
          "
          @click="emit('toggle')"
        >
          {{ habit.is_completed_today ? 'Done today ✓' : 'Done today' }}
        </button>
        <button
          type="button"
          class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50"
          @click="isEditing = true"
        >
          Edit
        </button>
        <button
          type="button"
          class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
          @click="confirmDelete"
        >
          Delete
        </button>
      </div>
    </template>
  </li>
</template>
