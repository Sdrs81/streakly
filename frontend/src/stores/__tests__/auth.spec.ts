import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { useAuthStore } from '../auth'

const { post, get, setAuthToken } = vi.hoisted(() => ({
  post: vi.fn<(url: string, body?: unknown) => Promise<unknown>>(),
  get: vi.fn<(url: string) => Promise<unknown>>(),
  setAuthToken: vi.fn<(token: string | null) => void>(),
}))

vi.mock('@/lib/api', () => ({ default: { post, get }, setAuthToken }))

const user = { id: 1, name: 'Ada', email: 'ada@example.com' }

describe('auth store', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('stores the token and user after login', async () => {
    post.mockResolvedValue({ data: { data: user, token: 'abc' } })
    const auth = useAuthStore()

    await auth.login({ email: user.email, password: 'secret' })

    expect(auth.user).toEqual(user)
    expect(auth.token).toBe('abc')
    expect(auth.isAuthenticated).toBe(true)
    expect(localStorage.getItem('streakly_token')).toBe('abc')
    expect(setAuthToken).toHaveBeenLastCalledWith('abc')
  })

  it('clears the token and user on logout', async () => {
    post.mockResolvedValueOnce({ data: { data: user, token: 'abc' } })
    const auth = useAuthStore()
    await auth.login({ email: user.email, password: 'secret' })

    post.mockResolvedValueOnce({ data: null })
    await auth.logout()

    expect(post).toHaveBeenLastCalledWith('/logout')
    expect(auth.user).toBeNull()
    expect(auth.token).toBeNull()
    expect(localStorage.getItem('streakly_token')).toBeNull()
    expect(setAuthToken).toHaveBeenLastCalledWith(null)
  })

  it('clears the session on logout even when the request fails', async () => {
    localStorage.setItem('streakly_token', 'abc')
    const auth = useAuthStore()
    post.mockRejectedValue(new Error('network'))

    await expect(auth.logout()).rejects.toThrow('network')

    expect(auth.token).toBeNull()
    expect(localStorage.getItem('streakly_token')).toBeNull()
  })

  it('rehydrates a token found in localStorage', () => {
    localStorage.setItem('streakly_token', 'stored')

    const auth = useAuthStore()

    expect(auth.token).toBe('stored')
    expect(auth.isAuthenticated).toBe(true)
    expect(setAuthToken).toHaveBeenCalledWith('stored')
  })

  it('loads the current user', async () => {
    localStorage.setItem('streakly_token', 'stored')
    get.mockResolvedValue({ data: { data: user } })
    const auth = useAuthStore()

    await auth.fetchCurrentUser()

    expect(get).toHaveBeenCalledWith('/user')
    expect(auth.user).toEqual(user)
  })

  it('ignores a current user response that arrives after logout', async () => {
    localStorage.setItem('streakly_token', 'stored')
    let respond: (response: unknown) => void = () => {}
    get.mockReturnValue(new Promise((resolve) => (respond = resolve)))
    post.mockResolvedValue({ data: null })
    const auth = useAuthStore()

    const pendingFetch = auth.fetchCurrentUser()
    await auth.logout()
    respond({ data: { data: user } })
    await pendingFetch

    expect(auth.user).toBeNull()
  })
})
