import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    /** When true, the route is reachable without a session. */
    public?: boolean
    /** User needs any one of these permissions. */
    permissions?: string[]
  }
}

export {}
