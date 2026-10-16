/**
 * Track in-flight async work for a button (or a keyed set of row actions).
 */
export function useBusyAction() {
  const busy = ref(false)

  async function run<T>(fn: () => Promise<T>): Promise<T | undefined> {
    if (busy.value) return undefined
    busy.value = true
    try {
      return await fn()
    }
    finally {
      busy.value = false
    }
  }

  return { busy, run }
}

/** Per-row / per-id busy tracking (approve row 12 while row 13 stays idle). */
export function useBusyMap() {
  const busyIds = ref(new Set<number | string>())

  function isBusy(id: number | string): boolean {
    return busyIds.value.has(id)
  }

  async function run<T>(id: number | string, fn: () => Promise<T>): Promise<T | undefined> {
    if (busyIds.value.has(id)) return undefined
    busyIds.value = new Set(busyIds.value).add(id)
    try {
      return await fn()
    }
    finally {
      const next = new Set(busyIds.value)
      next.delete(id)
      busyIds.value = next
    }
  }

  return { busyIds, isBusy, run }
}
