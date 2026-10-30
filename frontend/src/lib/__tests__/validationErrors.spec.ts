import { describe, expect, it } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'

import { parseValidationErrors } from '../validationErrors'

function axiosError(status: number, data: unknown): AxiosError {
  return new AxiosError('failed', undefined, undefined, undefined, {
    status,
    data,
  } as AxiosResponse)
}

describe('parseValidationErrors', () => {
  it('maps a 422 response to the first message per field', () => {
    const error = axiosError(422, {
      message: 'The given data was invalid.',
      errors: { email: ['Taken.', 'Also invalid.'], password: ['Too short.'] },
    })

    expect(parseValidationErrors(error, ['email', 'password'])).toEqual({ email: 'Taken.', password: 'Too short.' })
  })

  it('returns null for a 422 without field messages', () => {
    expect(parseValidationErrors(axiosError(422, { message: 'Invalid.' }), ['email'])).toBeNull()
  })

  it('returns null when a message targets a field the form does not show', () => {
    const error = axiosError(422, { message: 'Invalid.', errors: { nickname: ['Taken.'] } })

    expect(parseValidationErrors(error, ['email'])).toBeNull()
  })

  it('returns null for other statuses', () => {
    expect(parseValidationErrors(axiosError(500, {}), ['email'])).toBeNull()
  })

  it('returns null for errors that are not axios errors', () => {
    expect(parseValidationErrors(new Error('network'), ['email'])).toBeNull()
  })
})
