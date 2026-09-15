/**
 * Display helpers.
 *
 * Monetary amounts arrive from the API as decimal strings (never floats). These
 * helpers only format them for display; no arithmetic happens on the client
 * that the backend has not already computed.
 */

export function toNumber(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === '') return 0
  const parsed = typeof value === 'number' ? value : Number.parseFloat(value)
  return Number.isFinite(parsed) ? parsed : 0
}

export function formatMoney(
  value: string | number | null | undefined,
  currency = 'ETB',
): string {
  const amount = toNumber(value)
  const formatted = amount.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })

  return `${formatted} ${currency}`
}

/** Bare amount with no currency suffix, for tight table cells. */
export function formatAmount(value: string | number | null | undefined): string {
  return toNumber(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

export function formatQuantity(value: string | number | null | undefined): string {
  const amount = toNumber(value)
  // Trim trailing zeros so "2.000 kg" reads as "2 kg".
  return String(Number.parseFloat(amount.toFixed(3)))
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

export function formatTime(value: string | null | undefined): string {
  if (!value) return '-'
  return new Date(value).toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '-'
  return `${formatDate(value)}, ${formatTime(value)}`
}

/** Whole minutes since a timestamp, floored at zero. */
export function minutesSince(value: string | null | undefined, now = Date.now()): number {
  if (!value) return 0
  const elapsed = Math.floor((now - new Date(value).getTime()) / 60000)
  return Math.max(elapsed, 0)
}

/** Compact elapsed label for kitchen tickets: "4 min", "1h 12m". */
export function formatElapsed(value: string | null | undefined, now = Date.now()): string {
  const minutes = minutesSince(value, now)
  if (minutes < 60) return `${minutes} min`

  return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`
}

/** "cash" -> "Cash", "mobile_money" -> "Mobile money" */
export function humanize(value: string | null | undefined): string {
  if (!value) return '-'
  const spaced = value.replace(/[_-]+/g, ' ').trim()
  return spaced.charAt(0).toUpperCase() + spaced.slice(1)
}

export function formatDateForInput(date: Date = new Date()): string {
  return date.toISOString().slice(0, 10)
}
