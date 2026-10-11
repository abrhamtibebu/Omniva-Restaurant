/** Envelope used by every single-resource endpoint. */
export interface ApiResource<T> {
  data: T
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

/** Envelope used by every paginated index endpoint. */
export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface ApiErrorBody {
  message: string
  errors?: Record<string, string[]>
}

/**
 * Normalized error shape thrown by `useApi`. Callers get a single predictable
 * object instead of having to dig through `FetchError` internals.
 */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  /** First validation message for a field, if the server rejected it. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  get isForbidden(): boolean {
    return this.status === 403
  }
}
