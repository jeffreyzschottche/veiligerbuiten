<template>
  <div class="mx-auto max-w-6xl px-4 py-8">
    <h1 class="mb-8 text-2xl font-bold">Admin Dashboard</h1>

    <!-- Stats Grid -->
    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg bg-white p-6 shadow">
        <p class="text-sm text-gray-500">Actieve incidenten</p>
        <p class="text-3xl font-bold">{{ stats?.incidents?.active || 0 }}</p>
        <p class="text-xs text-gray-400">{{ stats?.incidents?.today || 0 }} vandaag</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <p class="text-sm text-gray-500">Te reviewen</p>
        <p class="text-3xl font-bold text-orange-600">{{ stats?.reports?.pending_review || 0 }}</p>
        <NuxtLink to="/admin/reports" class="text-xs text-blue-600 hover:underline">Bekijken</NuxtLink>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <p class="text-sm text-gray-500">Gebruikers</p>
        <p class="text-3xl font-bold">{{ stats?.users?.total || 0 }}</p>
        <p class="text-xs text-gray-400">{{ stats?.users?.verified || 0 }} geverifieerd</p>
      </div>

      <div class="rounded-lg bg-white p-6 shadow">
        <p class="text-sm text-gray-500">Installaties</p>
        <p class="text-3xl font-bold">{{ stats?.installations?.total || 0 }}</p>
        <p class="text-xs text-gray-400">{{ stats?.installations?.blocked || 0 }} geblokkeerd</p>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="grid gap-4 sm:grid-cols-3">
      <NuxtLink
        to="/admin/incidents"
        class="rounded-lg bg-white p-6 shadow transition hover:shadow-md"
      >
        <h3 class="font-semibold">Incidenten beheren</h3>
        <p class="text-sm text-gray-500">Bekijk, bewerk en verwijder incidenten</p>
      </NuxtLink>

      <NuxtLink
        to="/admin/users"
        class="rounded-lg bg-white p-6 shadow transition hover:shadow-md"
      >
        <h3 class="font-semibold">Gebruikers beheren</h3>
        <p class="text-sm text-gray-500">Beheer accounts en rechten</p>
      </NuxtLink>

      <NuxtLink
        to="/admin/reports"
        class="rounded-lg bg-white p-6 shadow transition hover:shadow-md"
      >
        <h3 class="font-semibold">Meldingen reviewen</h3>
        <p class="text-sm text-gray-500">Beoordeel nieuwe meldingen</p>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'admin',
})

const api = useApi()
const stats = ref<any>(null)

onMounted(async () => {
  try {
    stats.value = await api.get('/admin/stats')
  } catch (error) {
    console.error('Failed to load stats:', error)
  }
})
</script>
