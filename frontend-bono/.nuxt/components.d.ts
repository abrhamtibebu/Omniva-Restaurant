
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


export const AppButton: typeof import("../app/components/common/AppButton.vue")['default']
export const AppModal: typeof import("../app/components/common/AppModal.vue")['default']
export const AppPagination: typeof import("../app/components/common/AppPagination.vue")['default']
export const AppSpinner: typeof import("../app/components/common/AppSpinner.vue")['default']
export const AppTable: typeof import("../app/components/common/AppTable.vue")['default']
export const AppToaster: typeof import("../app/components/common/AppToaster.vue")['default']
export const ConfirmDialog: typeof import("../app/components/common/ConfirmDialog.vue")['default']
export const EmptyState: typeof import("../app/components/common/EmptyState.vue")['default']
export const ErrorState: typeof import("../app/components/common/ErrorState.vue")['default']
export const FormField: typeof import("../app/components/common/FormField.vue")['default']
export const LanguageSelector: typeof import("../app/components/common/LanguageSelector.vue")['default']
export const PageHeader: typeof import("../app/components/common/PageHeader.vue")['default']
export const StatusBadge: typeof import("../app/components/common/StatusBadge.vue")['default']
export const ChartsBarChart: typeof import("../app/components/charts/BarChart.vue")['default']
export const ChartsDonutChart: typeof import("../app/components/charts/DonutChart.vue")['default']
export const ChartsSparklineChart: typeof import("../app/components/charts/SparklineChart.vue")['default']
export const LayoutSidebar: typeof import("../app/components/layout/Sidebar.vue")['default']
export const LayoutTopbar: typeof import("../app/components/layout/Topbar.vue")['default']
export const StaffAccountDirectory: typeof import("../app/components/staff/AccountDirectory.vue")['default']
export const NuxtWelcome: typeof import("../node_modules/nuxt/dist/app/components/welcome.vue")['default']
export const NuxtLayout: typeof import("../node_modules/nuxt/dist/app/components/nuxt-layout")['default']
export const NuxtErrorBoundary: typeof import("../node_modules/nuxt/dist/app/components/nuxt-error-boundary.vue")['default']
export const ClientOnly: typeof import("../node_modules/nuxt/dist/app/components/client-only")['default']
export const DevOnly: typeof import("../node_modules/nuxt/dist/app/components/dev-only")['default']
export const ServerPlaceholder: typeof import("../node_modules/nuxt/dist/app/components/server-placeholder")['default']
export const NuxtLink: typeof import("../node_modules/nuxt/dist/app/components/nuxt-link")['default']
export const NuxtLoadingIndicator: typeof import("../node_modules/nuxt/dist/app/components/nuxt-loading-indicator")['default']
export const NuxtTime: typeof import("../node_modules/nuxt/dist/app/components/nuxt-time.vue")['default']
export const NuxtRouteAnnouncer: typeof import("../node_modules/nuxt/dist/app/components/nuxt-route-announcer")['default']
export const NuxtAnnouncer: typeof import("../node_modules/nuxt/dist/app/components/nuxt-announcer")['default']
export const NuxtImg: typeof import("../node_modules/nuxt/dist/app/components/nuxt-stubs")['NuxtImg']
export const NuxtPicture: typeof import("../node_modules/nuxt/dist/app/components/nuxt-stubs")['NuxtPicture']
export const NuxtLinkLocale: typeof import("../node_modules/@nuxtjs/i18n/dist/runtime/components/NuxtLinkLocale")['default']
export const SwitchLocalePathLink: typeof import("../node_modules/@nuxtjs/i18n/dist/runtime/components/SwitchLocalePathLink")['default']
export const NuxtPage: typeof import("../node_modules/nuxt/dist/pages/runtime/page")['default']
export const NoScript: typeof import("../node_modules/nuxt/dist/head/runtime/components")['NoScript']
export const Link: typeof import("../node_modules/nuxt/dist/head/runtime/components")['Link']
export const Base: typeof import("../node_modules/nuxt/dist/head/runtime/components")['Base']
export const Title: typeof import("../node_modules/nuxt/dist/head/runtime/components")['Title']
export const Meta: typeof import("../node_modules/nuxt/dist/head/runtime/components")['Meta']
export const Style: typeof import("../node_modules/nuxt/dist/head/runtime/components")['Style']
export const Head: typeof import("../node_modules/nuxt/dist/head/runtime/components")['Head']
export const Html: typeof import("../node_modules/nuxt/dist/head/runtime/components")['Html']
export const Body: typeof import("../node_modules/nuxt/dist/head/runtime/components")['Body']
export const NuxtIsland: typeof import("../node_modules/nuxt/dist/app/components/nuxt-island")['default']
export const LazyAppButton: LazyComponent<typeof import("../app/components/common/AppButton.vue")['default']>
export const LazyAppModal: LazyComponent<typeof import("../app/components/common/AppModal.vue")['default']>
export const LazyAppPagination: LazyComponent<typeof import("../app/components/common/AppPagination.vue")['default']>
export const LazyAppSpinner: LazyComponent<typeof import("../app/components/common/AppSpinner.vue")['default']>
export const LazyAppTable: LazyComponent<typeof import("../app/components/common/AppTable.vue")['default']>
export const LazyAppToaster: LazyComponent<typeof import("../app/components/common/AppToaster.vue")['default']>
export const LazyConfirmDialog: LazyComponent<typeof import("../app/components/common/ConfirmDialog.vue")['default']>
export const LazyEmptyState: LazyComponent<typeof import("../app/components/common/EmptyState.vue")['default']>
export const LazyErrorState: LazyComponent<typeof import("../app/components/common/ErrorState.vue")['default']>
export const LazyFormField: LazyComponent<typeof import("../app/components/common/FormField.vue")['default']>
export const LazyLanguageSelector: LazyComponent<typeof import("../app/components/common/LanguageSelector.vue")['default']>
export const LazyPageHeader: LazyComponent<typeof import("../app/components/common/PageHeader.vue")['default']>
export const LazyStatusBadge: LazyComponent<typeof import("../app/components/common/StatusBadge.vue")['default']>
export const LazyChartsBarChart: LazyComponent<typeof import("../app/components/charts/BarChart.vue")['default']>
export const LazyChartsDonutChart: LazyComponent<typeof import("../app/components/charts/DonutChart.vue")['default']>
export const LazyChartsSparklineChart: LazyComponent<typeof import("../app/components/charts/SparklineChart.vue")['default']>
export const LazyLayoutSidebar: LazyComponent<typeof import("../app/components/layout/Sidebar.vue")['default']>
export const LazyLayoutTopbar: LazyComponent<typeof import("../app/components/layout/Topbar.vue")['default']>
export const LazyStaffAccountDirectory: LazyComponent<typeof import("../app/components/staff/AccountDirectory.vue")['default']>
export const LazyNuxtWelcome: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/welcome.vue")['default']>
export const LazyNuxtLayout: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-layout")['default']>
export const LazyNuxtErrorBoundary: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-error-boundary.vue")['default']>
export const LazyClientOnly: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/client-only")['default']>
export const LazyDevOnly: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/dev-only")['default']>
export const LazyServerPlaceholder: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/server-placeholder")['default']>
export const LazyNuxtLink: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-link")['default']>
export const LazyNuxtLoadingIndicator: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-loading-indicator")['default']>
export const LazyNuxtTime: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-time.vue")['default']>
export const LazyNuxtRouteAnnouncer: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-route-announcer")['default']>
export const LazyNuxtAnnouncer: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-announcer")['default']>
export const LazyNuxtImg: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-stubs")['NuxtImg']>
export const LazyNuxtPicture: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-stubs")['NuxtPicture']>
export const LazyNuxtLinkLocale: LazyComponent<typeof import("../node_modules/@nuxtjs/i18n/dist/runtime/components/NuxtLinkLocale")['default']>
export const LazySwitchLocalePathLink: LazyComponent<typeof import("../node_modules/@nuxtjs/i18n/dist/runtime/components/SwitchLocalePathLink")['default']>
export const LazyNuxtPage: LazyComponent<typeof import("../node_modules/nuxt/dist/pages/runtime/page")['default']>
export const LazyNoScript: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['NoScript']>
export const LazyLink: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['Link']>
export const LazyBase: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['Base']>
export const LazyTitle: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['Title']>
export const LazyMeta: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['Meta']>
export const LazyStyle: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['Style']>
export const LazyHead: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['Head']>
export const LazyHtml: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['Html']>
export const LazyBody: LazyComponent<typeof import("../node_modules/nuxt/dist/head/runtime/components")['Body']>
export const LazyNuxtIsland: LazyComponent<typeof import("../node_modules/nuxt/dist/app/components/nuxt-island")['default']>

export const componentNames: string[]
