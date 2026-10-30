import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { AxiosError } from 'axios'
import { nextTick } from 'vue'

import type { Habit } from '@/types/api'
import { useAuthStore } from '../auth'
import { useHabitsStore } from '../habits'

const { get, post, put, del } = vi.hoisted(() => ({
  get: vi.fn<(url: string) => Promise<unknown>>(),
  post: vi.fn<(url: string, body?: unknown) => Promise<unknown>>(),
  put: vi.fn<(url: string, body?: unknown) => Promise<unknown>>(),
  del: vi.fn<(url: string) => Promise<unknown>>(),
}))

vi.mock('@/lib/api', () => ({
  default: { get, post, put, delete: del },
  setAuthToken: vi.fn<(token: string | null) => void>(),
}))

function buildHabit(overrides: Partial<Habit> = {}): Habit {
  return {
    id: 1,
    name: 'Read',
    description: null,
    created_at: '2026-10-01T00:00:00Z',
    current_streak: 0,
    is_completed_today: false,
    ...overrides,
  }
}

function buildHttpError(status: number, data: unknown = {}): AxiosError {
  return new AxiosError('failed', undefined, undefined, undefined, {
    status,
    data,
  } as AxiosError['response'])
}

describe('habits store', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('loads the habits from the API', async () => {
    const habit = buildHabit()
    get.mockResolvedValue({ data: { data: [habit] } })
    const habits = useHabitsStore()

    await habits.fetchHabits()

    expect(get).toHaveBeenCalledWith('/habits')
    expect(habits.habits).toEqual([habit])
    expect(habits.isLoading).toBe(false)
  })

  it('marks today with PUT when the habit is not completed', async () => {
    const habit = buildHabit()
    const completedHabit = buildHabit({ is_completed_today: true, current_streak: 1 })
    get.mockResolvedValue({ data: { data: [habit] } })
    put.mockResolvedValue({ data: { data: completedHabit } })
    const habits = useHabitsStore()
    await habits.fetchHabits()

    await habits.toggleToday(habit)

    expect(put).toHaveBeenCalledWith('/habits/1/completions/today')
    expect(habits.habits).toEqual([completedHabit])
  })

  it('unmarks today with DELETE when the habit is completed', async () => {
    const completedHabit = buildHabit({ is_completed_today: true, current_streak: 1 })
    const uncompletedHabit = buildHabit()
    get.mockResolvedValue({ data: { data: [completedHabit] } })
    del.mockResolvedValue({ data: { data: uncompletedHabit } })
    const habits = useHabitsStore()
    await habits.fetchHabits()

    await habits.toggleToday(completedHabit)

    expect(del).toHaveBeenCalledWith('/habits/1/completions/today')
    expect(habits.habits).toEqual([uncompletedHabit])
  })

  it('removes a deleted habit from the state', async () => {
    const keptHabit = buildHabit({ id: 2, name: 'Run' })
    const deletedHabit = buildHabit()
    get.mockResolvedValue({ data: { data: [deletedHabit, keptHabit] } })
    del.mockResolvedValue({ data: null })
    const habits = useHabitsStore()
    await habits.fetchHabits()

    await habits.deleteHabit(deletedHabit)

    expect(del).toHaveBeenCalledWith('/habits/1')
    expect(habits.habits).toEqual([keptHabit])
  })

  it('appends a created habit', async () => {
    const createdHabit = buildHabit()
    post.mockResolvedValue({ data: { data: createdHabit } })
    const habits = useHabitsStore()

    await habits.createHabit({ name: 'Read', description: null })

    expect(post).toHaveBeenCalledWith('/habits', { name: 'Read', description: null })
    expect(habits.habits).toEqual([createdHabit])
  })

  it('replaces an updated habit', async () => {
    const habit = buildHabit()
    const renamedHabit = buildHabit({ name: 'Read more' })
    get.mockResolvedValue({ data: { data: [habit] } })
    put.mockResolvedValue({ data: { data: renamedHabit } })
    const habits = useHabitsStore()
    await habits.fetchHabits()

    await habits.updateHabit(habit, { name: 'Read more', description: null })

    expect(put).toHaveBeenCalledWith('/habits/1', { name: 'Read more', description: null })
    expect(habits.habits).toEqual([renamedHabit])
  })

  it('sets an error message when loading fails', async () => {
    get.mockRejectedValue(new Error('network'))
    const habits = useHabitsStore()

    await habits.fetchHabits()

    expect(habits.errorMessage).not.toBe('')
    expect(habits.habits).toEqual([])
  })

  it('keeps the habit and sets an error message when a toggle fails', async () => {
    const habit = buildHabit()
    get.mockResolvedValue({ data: { data: [habit] } })
    put.mockRejectedValue(buildHttpError(500))
    const habits = useHabitsStore()
    await habits.fetchHabits()

    await habits.toggleToday(habit)

    expect(habits.errorMessage).not.toBe('')
    expect(habits.habits).toEqual([habit])
  })

  it('keeps the habit and sets an error message when a delete fails', async () => {
    const habit = buildHabit()
    get.mockResolvedValue({ data: { data: [habit] } })
    del.mockRejectedValue(buildHttpError(500))
    const habits = useHabitsStore()
    await habits.fetchHabits()

    await habits.deleteHabit(habit)

    expect(habits.errorMessage).not.toBe('')
    expect(habits.habits).toEqual([habit])
  })

  it('shows the banner for a validation error the form cannot display', async () => {
    post.mockRejectedValue(buildHttpError(422, { errors: { habit: ['Not allowed.'] } }))
    const habits = useHabitsStore()

    await expect(habits.createHabit({ name: 'Read', description: null })).rejects.toBeDefined()

    expect(habits.errorMessage).not.toBe('')
  })

  it('clears the habits when the session ends', async () => {
    localStorage.setItem('streakly_token', 'stored')
    setActivePinia(createPinia())
    get.mockResolvedValue({ data: { data: [buildHabit()] } })
    const auth = useAuthStore()
    const habits = useHabitsStore()
    await habits.fetchHabits()

    auth.clearSession()
    await nextTick()

    expect(habits.habits).toEqual([])
  })

  it('ignores a habits response that arrives after the session ended', async () => {
    localStorage.setItem('streakly_token', 'stored')
    setActivePinia(createPinia())
    let respond: (response: unknown) => void = () => {}
    get.mockReturnValue(new Promise((resolve) => (respond = resolve)))
    const auth = useAuthStore()
    const habits = useHabitsStore()

    const pendingFetch = habits.fetchHabits()
    auth.clearSession()
    await nextTick()
    respond({ data: { data: [buildHabit()] } })
    await pendingFetch

    expect(habits.habits).toEqual([])
    expect(habits.isLoading).toBe(false)
  })

  it('ignores a created habit that arrives after the session ended', async () => {
    localStorage.setItem('streakly_token', 'stored')
    setActivePinia(createPinia())
    let respond: (response: unknown) => void = () => {}
    post.mockReturnValue(new Promise((resolve) => (respond = resolve)))
    const auth = useAuthStore()
    const habits = useHabitsStore()

    const pendingCreate = habits.createHabit({ name: 'Read', description: null })
    auth.clearSession()
    respond({ data: { data: buildHabit() } })
    await pendingCreate

    expect(habits.habits).toEqual([])
  })

  it('does not show the banner for a failure that arrives after the session ended', async () => {
    localStorage.setItem('streakly_token', 'stored')
    setActivePinia(createPinia())
    const habit = buildHabit()
    let fail: (error: unknown) => void = () => {}
    put.mockReturnValue(new Promise((_, reject) => (fail = reject)))
    const auth = useAuthStore()
    const habits = useHabitsStore()

    const pendingToggle = habits.toggleToday(habit)
    auth.clearSession()
    fail(buildHttpError(500))
    await pendingToggle

    expect(habits.errorMessage).toBe('')
  })

  it('rethrows a validation error without setting the banner', async () => {
    const validationError = buildHttpError(422, { errors: { name: ['Name is required.'] } })
    post.mockRejectedValue(validationError)
    const habits = useHabitsStore()

    await expect(habits.createHabit({ name: '', description: null })).rejects.toBe(validationError)

    expect(habits.errorMessage).toBe('')
  })

  it('sets the banner when creating fails for another reason', async () => {
    post.mockRejectedValue(buildHttpError(500))
    const habits = useHabitsStore()

    await expect(habits.createHabit({ name: 'Read', description: null })).rejects.toBeDefined()

    expect(habits.errorMessage).not.toBe('')
  })
})
