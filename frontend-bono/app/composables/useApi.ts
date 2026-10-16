import type { NitroFetchOptions } from 'nitropack'
import { ApiError, type ApiErrorBody } from '~/types/api'

type RequestOptions = Omit<NitroFetchOptions<string>, 'method' | 'baseURL'>

/**
 * The only place the app talks to the API.
 *
 * Every module composable goes through this so the base URL, auth header and
 * error normalization exist in exactly one place. Errors always surface as
 * `ApiError`, and an expired session is handled centrally.
 */
const PRODUCTION_API = 'https://api.esmusstrading.com/api/v1'

/** Resolve API base; never keep a retired Render host. */
function resolveApiBase(raw: unknown): string {
  const value = String(raw || PRODUCTION_API).trim().replace(/\/+$/, '')
  if (!value || value.includes('onrender.com')) return PRODUCTION_API
  return value
}

export function useApi() {
  const config = useRuntimeConfig()
  const token = useAuthToken()
  const workingBranchId = useWorkingBranchId()
  const { t, locale } = useI18nContext()
  const apiBase = resolveApiBase(config.public.apiBase)

  function normalize(error: unknown): ApiError {
    const candidate = error as { status?: number; statusCode?: number; data?: ApiErrorBody }
    const status = candidate?.status ?? candidate?.statusCode ?? 0
    const body = candidate?.data

    if (status === 0) {
      return new ApiError(t('api.cannotReach'), 0)
    }

    const message = body?.message
      || (status === 403 ? t('api.forbidden') : null)
      || (status === 404 ? t('api.notFound') : null)
      || (status === 429 ? t('api.tooMany') : null)
      || (status >= 500 ? t('api.serverError') : t('api.requestFailed'))

    return new ApiError(message, status, body?.errors ?? {})
  }

  async function request<T>(
    method: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE',
    path: string,
    options: RequestOptions = {},
  ): Promise<T> {
    const { isTelegram } = useTelegramWebApp()
    const lang = locale.value === 'am' ? 'am' : 'en'
    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Accept-Language': lang,
      'X-Locale': lang,
      'X-Client': isTelegram.value ? 'telegram' : 'bono',
      ...(options.headers as Record<string, string> | undefined),
    }

    if (token.value) {
      headers.Authorization = `Bearer ${token.value}`
    }

    if (workingBranchId.value) {
      headers['X-Branch-Id'] = String(workingBranchId.value)
    }

    try {
      // Content-Type is deliberately left unset so FormData uploads still work.
      return await $fetch<T>(path, {
        ...options,
        method,
        baseURL: apiBase,
        headers,
      } as NitroFetchOptions<string>)
    }
    catch (raw) {
      const error = normalize(raw)

      // A 401 while holding a token means the session died rather than that
      // the caller sent bad credentials, so tear it down once, here.
      if (error.isUnauthenticated && token.value) {
        token.value = null
        const auth = useAuthStore()
        auth.clear()

        if (import.meta.client) {
          const route = useRoute()
          if (route.path !== '/login') {
            useToast().warning(t('api.sessionExpired'), t('api.signInAgain'))
            await navigateTo({ path: '/login', query: { redirect: route.fullPath } })
          }
        }
      }

      throw error
    }
  }

  return {
    get: <T>(path: string, options?: RequestOptions) => request<T>('GET', path, options),
    post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
      request<T>('POST', path, { ...options, body: body as RequestOptions['body'] }),
    patch: <T>(path: string, body?: unknown, options?: RequestOptions) =>
      request<T>('PATCH', path, { ...options, body: body as RequestOptions['body'] }),
    put: <T>(path: string, body?: unknown, options?: RequestOptions) =>
      request<T>('PUT', path, { ...options, body: body as RequestOptions['body'] }),
    destroy: <T>(path: string, options?: RequestOptions) => request<T>('DELETE', path, options),
  }
}
