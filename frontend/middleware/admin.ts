export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()

  if (!authStore.isLoggedIn) {
    return navigateTo('/login?redirect=' + encodeURIComponent(to.fullPath))
  }

  if (!authStore.user?.is_admin) {
    return navigateTo('/app')
  }
})
