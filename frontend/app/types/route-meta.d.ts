declare module 'vue-router' {
  interface RouteMeta {
    /** Reachable without a session. Only the login screen sets this. */
    public?: boolean
    /** Any one of these permissions grants access. Empty means "signed in". */
    permissions?: string[]
  }
}

export {}
