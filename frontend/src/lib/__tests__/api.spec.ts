import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { AxiosAdapter, InternalAxiosRequestConfig } from 'axios'
import { AxiosError } from 'axios'

import api, { onUnauthorized, setAuthToken } from '../api'

function rejectWithStatus(status: number): AxiosAdapter {
  return (config: InternalAxiosRequestConfig) =>
    Promise.reject(
      new AxiosError('failed', undefined, config, undefined, {
        status,
        data: {},
        statusText: '',
        headers: {},
        config,
      }),
    )
}

describe('api instance', () => {
  const handleUnauthorized = vi.fn<() => void>()

  beforeEach(() => {
    handleUnauthorized.mockClear()
    onUnauthorized(handleUnauthorized)
  })

  afterEach(() => {
    setAuthToken(null)
    api.defaults.adapter = undefined
  })

  it('sends the bearer token once it is set and stops after it is cleared', () => {
    setAuthToken('abc')
    expect(api.defaults.headers.common.Authorization).toBe('Bearer abc')

    setAuthToken(null)
    expect(api.defaults.headers.common.Authorization).toBeUndefined()
  })

  it('calls the unauthorized handler on a 401 for the current token', async () => {
    setAuthToken('abc')
    api.defaults.adapter = rejectWithStatus(401)

    await expect(api.get('/user')).rejects.toBeInstanceOf(AxiosError)

    expect(handleUnauthorized).toHaveBeenCalledOnce()
  })

  it('ignores a 401 that belongs to a token that is no longer current', async () => {
    setAuthToken('old')
    api.defaults.adapter = (config) => {
      setAuthToken('new')
      return rejectWithStatus(401)(config)
    }

    await expect(api.get('/user')).rejects.toBeInstanceOf(AxiosError)

    expect(handleUnauthorized).not.toHaveBeenCalled()
  })

  it('ignores a 401 for a request that was sent without a token', async () => {
    api.defaults.adapter = rejectWithStatus(401)

    await expect(api.get('/user')).rejects.toBeInstanceOf(AxiosError)

    expect(handleUnauthorized).not.toHaveBeenCalled()
  })

  it('does not call the handler for other failures', async () => {
    setAuthToken('abc')
    api.defaults.adapter = rejectWithStatus(422)

    await expect(api.get('/user')).rejects.toBeInstanceOf(AxiosError)

    expect(handleUnauthorized).not.toHaveBeenCalled()
  })
})
