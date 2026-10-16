/**
 * Locale-aware display helpers wrapping format.ts.
 */
export function useLocaleFormat() {
  const { locale, t, te } = useI18n()

  const intlLocale = computed(() => (locale.value === 'am' ? 'am-ET' : 'en-US'))
  const dateLocale = computed(() => (locale.value === 'am' ? 'am-ET' : 'en-GB'))

  function formatMoney(value: string | number | null | undefined, currency = 'ETB'): string {
    const amount = toNumber(value)
    const formatted = amount.toLocaleString(intlLocale.value, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
    return `${formatted} ${currency}`
  }

  function formatAmount(value: string | number | null | undefined): string {
    return toNumber(value).toLocaleString(intlLocale.value, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  }

  function formatDate(value: string | null | undefined): string {
    if (!value) return t('common.dash')
    return new Date(value).toLocaleDateString(dateLocale.value, {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    })
  }

  function formatTime(value: string | null | undefined): string {
    if (!value) return t('common.dash')
    return new Date(value).toLocaleTimeString(dateLocale.value, {
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  function formatDateTime(value: string | null | undefined): string {
    if (!value) return t('common.dash')
    return `${formatDate(value)}, ${formatTime(value)}`
  }

  function formatElapsed(value: string | null | undefined, now = Date.now()): string {
    const minutes = minutesSince(value, now)
    if (minutes < 60) return t('common.elapsedMinutes', { n: minutes })
    return t('common.elapsedHours', {
      h: Math.floor(minutes / 60),
      m: String(minutes % 60).padStart(2, '0'),
    })
  }

  function statusLabel(status: string): string {
    const key = `status.${status}`
    return te(key) ? t(key) : humanize(status)
  }

  function roleLabel(role: string | null | undefined): string {
    if (!role) return t('common.dash')
    const key = `roles.${role}`
    return te(key) ? t(key) : humanize(role)
  }

  return {
    formatMoney,
    formatAmount,
    formatDate,
    formatTime,
    formatDateTime,
    formatElapsed,
    statusLabel,
    roleLabel,
    intlLocale,
  }
}
