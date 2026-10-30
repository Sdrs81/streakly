<script setup lang="ts">
import { onMounted } from 'vue'

import HabitCard from '@/components/HabitCard.vue'
import HabitForm from '@/components/HabitForm.vue'
import { useHabitsStore } from '@/stores/habits'

const habits = useHabitsStore()

onMounted(() => habits.fetchHabits())
</script>

<template>
  <section class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-bold">Your habits</h1>

    <p
      v-if="habits.errorMessage"
      class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700"
      role="alert"
    >
      {{ habits.errorMessage }}
    </p>

    <div class="mt-6 rounded-lg border border-gray-200 p-4">
      <h2 class="mb-3 font-semibold">New habit</h2>
      <HabitForm submit-label="Add habit" :save="habits.createHabit" />
    </div>

    <p v-if="habits.isLoading" class="mt-6 text-gray-600">Loading…</p>
    <p v-else-if="habits.habits.length === 0 && !habits.errorMessage" class="mt-6 text-gray-600">
      No habits yet. Add your first one above.
    </p>
    <ul v-else class="mt-6 space-y-3">
      <HabitCard
        v-for="habit in habits.habits"
        :key="habit.id"
        :habit="habit"
        @toggle="habits.toggleToday(habit)"
        @delete="habits.deleteHabit(habit)"
      />
    </ul>
  </section>
</template>
