import { describe, expect, it } from 'vitest'

import { formatDayCount } from '../formatDayCount'

describe('formatDayCount', () => {
  it('uses the singular for one day', () => {
    expect(formatDayCount(1)).toBe('1 day')
  })

  it('uses the plural for zero days', () => {
    expect(formatDayCount(0)).toBe('0 days')
  })

  it('uses the plural for several days', () => {
    expect(formatDayCount(3)).toBe('3 days')
  })

  it('rejects a negative count', () => {
    expect(() => formatDayCount(-1)).toThrow(RangeError)
  })

  it('rejects a fractional count', () => {
    expect(() => formatDayCount(1.5)).toThrow(RangeError)
  })
})
