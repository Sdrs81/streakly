export interface User {
  id: number
  name: string
  email: string
}

export interface Habit {
  id: number
  name: string
  description: string | null
  created_at: string
  current_streak: number
  is_completed_today: boolean
}

export interface HabitPayload {
  name: string
  description: string | null
}

export interface ResourceResponse<T> {
  data: T
}

export interface AuthResponse extends ResourceResponse<User> {
  token: string
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload extends LoginPayload {
  name: string
  password_confirmation: string
}

export interface ValidationErrorResponse {
  message: string
  errors: Record<string, string[]>
}
