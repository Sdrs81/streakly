import axios from 'axios'
import type { ValidationErrorResponse } from '@/types/api'

/**
 * Maps a 422 response to the first message per field. Returns null for any other failure, when no
 * field message exists, or when a message targets a field the form does not show.
 */
export function parseValidationErrors(
  error: unknown,
  shownFields: string[],
): Record<string, string> | null {
  if (!axios.isAxiosError<ValidationErrorResponse>(error) || error.response?.status !== 422) {
    return null
  }

  const messagesByField: Record<string, string> = {}

  for (const [field, messages] of Object.entries(error.response.data?.errors ?? {})) {
    const [firstMessage] = messages
    if (firstMessage !== undefined) messagesByField[field] = firstMessage
  }

  const fieldsWithMessages = Object.keys(messagesByField)
  const isEveryMessageShown = fieldsWithMessages.every((field) => shownFields.includes(field))

  // Otherwise the user would see nothing, so the caller falls back to the generic error.
  return fieldsWithMessages.length > 0 && isEveryMessageShown ? messagesByField : null
}
