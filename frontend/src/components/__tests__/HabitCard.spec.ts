import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import type { Habit } from '@/types/api'
import HabitCard from '../HabitCard.vue'

vi.mock('@/lib/api', () => ({ default: {}, setAuthToken: vi.fn<(token: string | null) => void>() }))

function buildHabit(overrides: Partial<Habit> = {}): Habit {
  return {
    id: 1,
    name: 'Read',
    description: 'Twenty pages',
    created_at: '2026-10-01T00:00:00Z',
    current_streak: 3,
    is_completed_today: false,
    ...overrides,
  }
}

describe('HabitCard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
  })

  it('renders the streak label in plural form', () => {
    const wrapper = mount(HabitCard, { props: { habit: buildHabit({ current_streak: 3 }) } })

    expect(wrapper.text()).toContain('3 days streak')
  })

  it('renders the streak label in singular form', () => {
    const wrapper = mount(HabitCard, { props: { habit: buildHabit({ current_streak: 1 }) } })

    expect(wrapper.text()).toContain('1 day streak')
  })

  it('emits toggle when the done button is clicked', async () => {
    const wrapper = mount(HabitCard, { props: { habit: buildHabit() } })

    await wrapper.get('button[aria-pressed]').trigger('click')

    expect(wrapper.emitted('toggle')).toHaveLength(1)
  })

  it('emits delete after the user confirms', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mount(HabitCard, { props: { habit: buildHabit() } })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Delete')!
      .trigger('click')

    expect(wrapper.emitted('delete')).toHaveLength(1)
  })

  it('does not emit delete when the user cancels', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const wrapper = mount(HabitCard, { props: { habit: buildHabit() } })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Delete')!
      .trigger('click')

    expect(wrapper.emitted('delete')).toBeUndefined()
  })
})
