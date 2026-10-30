import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})

let unauthorizedHandler: (() => void) | null = null

export function setAuthToken(token: string | null): void {
  if (token) {
    api.defaults.headers.common.Authorization = `Bearer ${token}`
  } else {
    delete api.defaults.headers.common.Authorization
  }
}

export function onUnauthorized(handler: () => void): void {
  unauthorizedHandler = handler
}

api.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      // A late 401 for a request sent with an old token must not end a newer session.
      const sentAuthorization = error.config?.headers.Authorization
      const isCurrentSession =
        sentAuthorization !== undefined &&
        sentAuthorization === api.defaults.headers.common.Authorization
      if (isCurrentSession) unauthorizedHandler?.()
    }
    return Promise.reject(error)
  },
)

export default api
