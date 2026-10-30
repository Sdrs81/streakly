import { ref, watch } from 'vue'
import { defineStore } from 'pinia'

import api from '@/lib/api'
import { parseValidationErrors } from '@/lib/validationErrors'
import { useAuthStore } from '@/stores/auth'
import type { Habit, HabitPayload, ResourceResponse } from '@/types/api'

const GENERIC_ERROR_MESSAGE = 'Something went wrong, please try again.'
const HABIT_FORM_FIELDS = ['name', 'description']

export const useHabitsStore = defineStore('habits', () => {
  const auth = useAuthStore()
  const habits = ref<Habit[]>([])
  const isLoading = ref(false)
  const errorMessage = ref('')
  let sessionGeneration = 0

  // The store outlives a logout, so the next user must never see (or be overwritten by) the
  // previous user's habits, including a response that is still in flight. The sync flush bumps
  // the generation before any request that starts in the same tick can capture the old one.
  watch(
    () => auth.token,
    () => {
      sessionGeneration += 1
      habits.value = []
      errorMessage.value = ''
      isLoading.value = false
    },
    { flush: 'sync' },
  )

  function replaceHabit(updatedHabit: Habit): void {
    habits.value = habits.value.map((habit) =>
      habit.id === updatedHabit.id ? updatedHabit : habit,
    )
  }

  // The form shows 422 field errors itself. A 422 it cannot show would fail silently, so that
  // one goes to the banner too.
  function showBannerUnlessValidation(error: unknown): void {
    if (parseValidationErrors(error, HABIT_FORM_FIELDS) === null) {
      errorMessage.value = GENERIC_ERROR_MESSAGE
    }
  }

  async function fetchHabits(): Promise<void> {
    const requestedGeneration = sessionGeneration
    isLoading.value = true
    errorMessage.value = ''

    try {
      const { data } = await api.get<ResourceResponse<Habit[]>>('/habits')
      if (requestedGeneration === sessionGeneration) habits.value = data.data
    } catch {
      if (requestedGeneration === sessionGeneration) errorMessage.value = GENERIC_ERROR_MESSAGE
    } finally {
      if (requestedGeneration === sessionGeneration) isLoading.value = false
    }
  }

  async function createHabit(payload: HabitPayload): Promise<void> {
    const requestedGeneration = sessionGeneration
    errorMessage.value = ''

    try {
      const { data } = await api.post<ResourceResponse<Habit>>('/habits', payload)
      if (requestedGeneration === sessionGeneration) habits.value = [...habits.value, data.data]
    } catch (error) {
      if (requestedGeneration === sessionGeneration) showBannerUnlessValidation(error)
      throw error
    }
  }

  async function updateHabit(habit: Habit, payload: HabitPayload): Promise<void> {
    const requestedGeneration = sessionGeneration
    errorMessage.value = ''

    try {
      const { data } = await api.put<ResourceResponse<Habit>>(`/habits/${habit.id}`, payload)
      replaceHabit(data.data)
    } catch (error) {
      if (requestedGeneration === sessionGeneration) showBannerUnlessValidation(error)
      throw error
    }
  }

  async function deleteHabit(habit: Habit): Promise<void> {
    const requestedGeneration = sessionGeneration
    errorMessage.value = ''

    try {
      await api.delete(`/habits/${habit.id}`)
      habits.value = habits.value.filter(({ id }) => id !== habit.id)
    } catch {
      if (requestedGeneration === sessionGeneration) errorMessage.value = GENERIC_ERROR_MESSAGE
    }
  }

  async function toggleToday(habit: Habit): Promise<void> {
    const requestedGeneration = sessionGeneration
    errorMessage.value = ''
    const url = `/habits/${habit.id}/completions/today`

    try {
      const { data } = habit.is_completed_today
        ? await api.delete<ResourceResponse<Habit>>(url)
        : await api.put<ResourceResponse<Habit>>(url)
      replaceHabit(data.data)
    } catch {
      if (requestedGeneration === sessionGeneration) errorMessage.value = GENERIC_ERROR_MESSAGE
    }
  }

  return {
    habits,
    isLoading,
    errorMessage,
    fetchHabits,
    createHabit,
    updateHabit,
    deleteHabit,
    toggleToday,
  }
})
