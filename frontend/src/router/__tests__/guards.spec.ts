import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { createAppRouter } from '../index'

const { get } = vi.hoisted(() => ({
  get: vi.fn<(url: string) => Promise<unknown>>(),
}))

vi.mock('@/lib/api', () => ({
  default: { post: vi.fn<(url: string) => Promise<unknown>>(), get },
  setAuthToken: vi.fn<(token: string | null) => void>(),
}))

const user = { id: 1, name: 'Ada', email: 'ada@example.com' }

describe('router guards', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('redirects a guest from the habits page to login', async () => {
    const router = createAppRouter(createMemoryHistory())

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('redirects a logged-in user away from login', async () => {
    const auth = useAuthStore()
    auth.token = 'abc'
    auth.user = user
    const router = createAppRouter(createMemoryHistory())

    await router.push('/login')

    expect(router.currentRoute.value.name).toBe('habits')
  })

  it('loads the current user for a stored token before showing the habits page', async () => {
    localStorage.setItem('streakly_token', 'stored')
    get.mockResolvedValue({ data: { data: user } })
    const auth = useAuthStore()
    const router = createAppRouter(createMemoryHistory())

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('habits')
    expect(auth.user).toEqual(user)
  })

  it('redirects to login when the stored token is rejected', async () => {
    localStorage.setItem('streakly_token', 'expired')
    const auth = useAuthStore()
    // The real 401 interceptor clears the session through the unauthorized handler.
    get.mockImplementation(() => {
      auth.clearSession()
      return Promise.reject(new Error('Unauthorized'))
    })
    const router = createAppRouter(createMemoryHistory())

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('keeps the habits page when loading the current user fails for another reason', async () => {
    localStorage.setItem('streakly_token', 'stored')
    get.mockRejectedValue(new Error('Network Error'))
    vi.spyOn(console, 'warn').mockImplementation(() => undefined)
    const auth = useAuthStore()
    const router = createAppRouter(createMemoryHistory())

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('habits')
    expect(auth.isAuthenticated).toBe(true)
  })
})
