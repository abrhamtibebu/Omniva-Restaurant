export type ToastKind = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id: number
  kind: ToastKind
  title: string
  description?: string
}

let nextId = 1

/**
 * App-wide toast queue. Backed by `useState` so every caller shares one list
 * and `AppToaster` (mounted once in `app.vue`) renders it.
 */
export function useToast() {
  const toasts = useState<Toast[]>('toasts', () => [])

  function dismiss(id: number) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  function push(kind: ToastKind, title: string, description?: string) {
    const id = nextId++
    toasts.value = [...toasts.value, { id, kind, title, description }]

    // Errors stay longer since they usually carry something to read.
    setTimeout(() => dismiss(id), kind === 'error' ? 7000 : 4000)

    return id
  }

  return {
    toasts,
    dismiss,
    success: (title: string, description?: string) => push('success', title, description),
    error: (title: string, description?: string) => push('error', title, description),
    info: (title: string, description?: string) => push('info', title, description),
    warning: (title: string, description?: string) => push('warning', title, description),
  }
}
