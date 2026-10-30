import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import api, { setAuthToken } from '@/lib/api'
import type {
  AuthResponse,
  LoginPayload,
  RegisterPayload,
  ResourceResponse,
  User,
} from '@/types/api'

const TOKEN_KEY = 'streakly_token'

function readStoredToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

function writeStoredToken(token: string | null): void {
  try {
    if (token) {
      localStorage.setItem(TOKEN_KEY, token)
    } else {
      localStorage.removeItem(TOKEN_KEY)
    }
  } catch (error) {
    // Storage can be unavailable (private mode); the session then lasts until reload.
    console.warn('Could not persist the auth token', error)
  }
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(readStoredToken())
  const isAuthenticated = computed(() => token.value !== null)

  setAuthToken(token.value)

  function startSession(response: AuthResponse): void {
    user.value = response.data
    token.value = response.token
    setAuthToken(response.token)
    writeStoredToken(response.token)
  }

  function clearSession(): void {
    user.value = null
    token.value = null
    setAuthToken(null)
    writeStoredToken(null)
  }

  async function register(payload: RegisterPayload): Promise<void> {
    const { data } = await api.post<AuthResponse>('/register', payload)
    startSession(data)
  }

  async function login(payload: LoginPayload): Promise<void> {
    const { data } = await api.post<AuthResponse>('/login', payload)
    startSession(data)
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/logout')
    } finally {
      clearSession()
    }
  }

  async function fetchCurrentUser(): Promise<void> {
    const requestedToken = token.value
    const { data } = await api.get<ResourceResponse<User>>('/user')
    // A late response must not put a user into a session that has ended or changed.
    if (token.value === requestedToken) user.value = data.data
  }

  return { user, token, isAuthenticated, register, login, logout, fetchCurrentUser, clearSession }
})
