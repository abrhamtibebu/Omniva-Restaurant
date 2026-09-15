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
export function useApi() {
  const config = useRuntimeConfig()
  const token = useAuthToken()
  const workingBranchId = useWorkingBranchId()

  function normalize(error: unknown): ApiError {
    const candidate = error as { status?: number; statusCode?: number; data?: ApiErrorBody }
    const status = candidate?.status ?? candidate?.statusCode ?? 0
    const body = candidate?.data

    if (status === 0) {
      return new ApiError('Cannot reach the server. Check your connection.', 0)
    }

    const message = body?.message
      || (status === 403 ? 'You are not allowed to do that.' : null)
      || (status === 404 ? 'Not found.' : null)
      || (status === 429 ? 'Too many attempts. Please wait a moment.' : null)
      || (status >= 500 ? 'Something went wrong on the server.' : 'Request failed.')

    return new ApiError(message, status, body?.errors ?? {})
  }

  async function request<T>(
    method: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE',
    path: string,
    options: RequestOptions = {},
  ): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
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
        baseURL: config.public.apiBase,
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
            useToast().warning('Session expired', 'Please sign in again.')
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
