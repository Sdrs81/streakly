export function formatDayCount(dayCount: number): string {
  if (!Number.isInteger(dayCount) || dayCount < 0) {
    throw new RangeError(`Day count must be a non-negative integer, got ${dayCount}`)
  }

  return dayCount === 1 ? '1 day' : `${dayCount} days`
}
