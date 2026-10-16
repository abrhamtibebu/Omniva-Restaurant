
import type { DefineComponent, SlotsType } from 'vue'
type IslandComponent<T> = DefineComponent<{}, {refresh: () => Promise<void>}, {}, {}, {}, {}, {}, {}, {}, {}, {}, {}, SlotsType<{ fallback: { error: unknown } }>> & T

type HydrationStrategies = {
  hydrateOnVisible?: IntersectionObserverInit | true
  hydrateOnIdle?: number | true
  hydrateOnInteraction?: keyof HTMLElementEventMap | Array<keyof HTMLElementEventMap> | true
  hydrateOnMediaQuery?: string
  hydrateAfter?: number
  hydrateWhen?: boolean
  hydrateNever?: true
}
type LazyComponent<T> = DefineComponent<HydrationStrategies, {}, {}, {}, {}, {}, {}, { hydrated: () => void }> & T

interface _GlobalComponents {
  AppButton: typeof import("../../app/components/common/AppButton.vue")['default']
  AppModal: typeof import("../../app/components/common/AppModal.vue")['default']
  AppPagination: typeof import("../../app/components/common/AppPagination.vue")['default']
  AppSpinner: typeof import("../../app/components/common/AppSpinner.vue")['default']
  AppTable: typeof import("../../app/components/common/AppTable.vue")['default']
  AppToaster: typeof import("../../app/components/common/AppToaster.vue")['default']
  ConfirmDialog: typeof import("../../app/components/common/ConfirmDialog.vue")['default']
  EmptyState: typeof import("../../app/components/common/EmptyState.vue")['default']
  ErrorState: typeof import("../../app/components/common/ErrorState.vue")['default']
  FormField: typeof import("../../app/components/common/FormField.vue")['default']
  LanguageSelector: typeof import("../../app/components/common/LanguageSelector.vue")['default']
  PageHeader: typeof import("../../app/components/common/PageHeader.vue")['default']
  StatusBadge: typeof import("../../app/components/common/StatusBadge.vue")['default']
  ChartsBarChart: typeof import("../../app/components/charts/BarChart.vue")['default']
  ChartsDonutChart: typeof import("../../app/components/charts/DonutChart.vue")['default']
  ChartsSparklineChart: typeof import("../../app/components/charts/SparklineChart.vue")['default']
  LayoutSidebar: typeof import("../../app/components/layout/Sidebar.vue")['default']
  LayoutTopbar: typeof import("../../app/components/layout/Topbar.vue")['default']
  StaffAccountDirectory: typeof import("../../app/components/staff/AccountDirectory.vue")['default']
  NuxtWelcome: typeof import("../../node_modules/nuxt/dist/app/components/welcome.vue")['default']
  NuxtLayout: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-layout")['default']
  NuxtErrorBoundary: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-error-boundary.vue")['default']
  ClientOnly: typeof import("../../node_modules/nuxt/dist/app/components/client-only")['default']
  DevOnly: typeof import("../../node_modules/nuxt/dist/app/components/dev-only")['default']
  ServerPlaceholder: typeof import("../../node_modules/nuxt/dist/app/components/server-placeholder")['default']
  NuxtLink: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-link")['default']
  NuxtLoadingIndicator: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-loading-indicator")['default']
  NuxtTime: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-time.vue")['default']
  NuxtRouteAnnouncer: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-route-announcer")['default']
  NuxtAnnouncer: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-announcer")['default']
  NuxtImg: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-stubs")['NuxtImg']
  NuxtPicture: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-stubs")['NuxtPicture']
  NuxtLinkLocale: typeof import("../../node_modules/@nuxtjs/i18n/dist/runtime/components/NuxtLinkLocale")['default']
  SwitchLocalePathLink: typeof import("../../node_modules/@nuxtjs/i18n/dist/runtime/components/SwitchLocalePathLink")['default']
  NuxtPage: typeof import("../../node_modules/nuxt/dist/pages/runtime/page")['default']
  NoScript: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['NoScript']
  Link: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Link']
  Base: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Base']
  Title: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Title']
  Meta: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Meta']
  Style: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Style']
  Head: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Head']
  Html: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Html']
  Body: typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Body']
  NuxtIsland: typeof import("../../node_modules/nuxt/dist/app/components/nuxt-island")['default']
  LazyAppButton: LazyComponent<typeof import("../../app/components/common/AppButton.vue")['default']>
  LazyAppModal: LazyComponent<typeof import("../../app/components/common/AppModal.vue")['default']>
  LazyAppPagination: LazyComponent<typeof import("../../app/components/common/AppPagination.vue")['default']>
  LazyAppSpinner: LazyComponent<typeof import("../../app/components/common/AppSpinner.vue")['default']>
  LazyAppTable: LazyComponent<typeof import("../../app/components/common/AppTable.vue")['default']>
  LazyAppToaster: LazyComponent<typeof import("../../app/components/common/AppToaster.vue")['default']>
  LazyConfirmDialog: LazyComponent<typeof import("../../app/components/common/ConfirmDialog.vue")['default']>
  LazyEmptyState: LazyComponent<typeof import("../../app/components/common/EmptyState.vue")['default']>
  LazyErrorState: LazyComponent<typeof import("../../app/components/common/ErrorState.vue")['default']>
  LazyFormField: LazyComponent<typeof import("../../app/components/common/FormField.vue")['default']>
  LazyLanguageSelector: LazyComponent<typeof import("../../app/components/common/LanguageSelector.vue")['default']>
  LazyPageHeader: LazyComponent<typeof import("../../app/components/common/PageHeader.vue")['default']>
  LazyStatusBadge: LazyComponent<typeof import("../../app/components/common/StatusBadge.vue")['default']>
  LazyChartsBarChart: LazyComponent<typeof import("../../app/components/charts/BarChart.vue")['default']>
  LazyChartsDonutChart: LazyComponent<typeof import("../../app/components/charts/DonutChart.vue")['default']>
  LazyChartsSparklineChart: LazyComponent<typeof import("../../app/components/charts/SparklineChart.vue")['default']>
  LazyLayoutSidebar: LazyComponent<typeof import("../../app/components/layout/Sidebar.vue")['default']>
  LazyLayoutTopbar: LazyComponent<typeof import("../../app/components/layout/Topbar.vue")['default']>
  LazyStaffAccountDirectory: LazyComponent<typeof import("../../app/components/staff/AccountDirectory.vue")['default']>
  LazyNuxtWelcome: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/welcome.vue")['default']>
  LazyNuxtLayout: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-layout")['default']>
  LazyNuxtErrorBoundary: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-error-boundary.vue")['default']>
  LazyClientOnly: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/client-only")['default']>
  LazyDevOnly: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/dev-only")['default']>
  LazyServerPlaceholder: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/server-placeholder")['default']>
  LazyNuxtLink: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-link")['default']>
  LazyNuxtLoadingIndicator: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-loading-indicator")['default']>
  LazyNuxtTime: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-time.vue")['default']>
  LazyNuxtRouteAnnouncer: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-route-announcer")['default']>
  LazyNuxtAnnouncer: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-announcer")['default']>
  LazyNuxtImg: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-stubs")['NuxtImg']>
  LazyNuxtPicture: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-stubs")['NuxtPicture']>
  LazyNuxtLinkLocale: LazyComponent<typeof import("../../node_modules/@nuxtjs/i18n/dist/runtime/components/NuxtLinkLocale")['default']>
  LazySwitchLocalePathLink: LazyComponent<typeof import("../../node_modules/@nuxtjs/i18n/dist/runtime/components/SwitchLocalePathLink")['default']>
  LazyNuxtPage: LazyComponent<typeof import("../../node_modules/nuxt/dist/pages/runtime/page")['default']>
  LazyNoScript: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['NoScript']>
  LazyLink: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Link']>
  LazyBase: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Base']>
  LazyTitle: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Title']>
  LazyMeta: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Meta']>
  LazyStyle: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Style']>
  LazyHead: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Head']>
  LazyHtml: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Html']>
  LazyBody: LazyComponent<typeof import("../../node_modules/nuxt/dist/head/runtime/components")['Body']>
  LazyNuxtIsland: LazyComponent<typeof import("../../node_modules/nuxt/dist/app/components/nuxt-island")['default']>
}

declare module 'vue' {
  export interface GlobalComponents extends _GlobalComponents { }
}

export {}
