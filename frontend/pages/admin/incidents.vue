<template>
  <div class="mx-auto max-w-6xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold">Incidenten</h1>
      <NuxtLink to="/admin" class="text-sm text-blue-600 hover:underline">Terug</NuxtLink>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-4">
      <select v-model="filters.status" class="rounded-md border px-3 py-2">
        <option value="">Alle statussen</option>
        <option value="gemeld">Gemeld</option>
        <option value="bevestigd">Bevestigd</option>
        <option value="officieel">Officieel</option>
        <option value="opgelost">Opgelost</option>
      </select>

      <select v-model="filters.visibility" class="rounded-md border px-3 py-2">
        <option value="">Alle zichtbaarheid</option>
        <option value="public">Publiek</option>
        <option value="review">Review</option>
        <option value="archived">Gearchiveerd</option>
      </select>

      <select v-model="filters.source" class="rounded-md border px-3 py-2">
        <option value="">Alle bronnen</option>
        <option value="user">Gebruiker</option>
        <option value="p2000_zwaailicht">P2000</option>
        <option value="knmi">KNMI</option>
        <option value="ndw">NDW</option>
      </select>

      <button @click="loadIncidents" class="rounded-md bg-blue-600 px-4 py-2 text-white">
        Filteren
      </button>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded-lg bg-white shadow">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">ID</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Type</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Status</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Bron</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Datum</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Acties</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="incident in incidents" :key="incident.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ incident.id }}</td>
            <td class="px-4 py-3">
              <span class="font-medium">{{ incident.subtype }}</span>
              <span class="block text-xs text-gray-500">{{ incident.category }}</span>
            </td>
            <td class="px-4 py-3">
              <span :class="statusClass(incident.status)" class="rounded-full px-2 py-1 text-xs font-medium">
                {{ incident.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ incident.source }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(incident.created_at) }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button
                  v-if="incident.visibility !== 'archived'"
                  @click="archiveIncident(incident)"
                  class="text-sm text-red-600 hover:underline"
                >
                  Verwijderen
                </button>
                <button
                  v-if="incident.visibility === 'review'"
                  @click="approveIncident(incident)"
                  class="text-sm text-green-600 hover:underline"
                >
                  Goedkeuren
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="incidents.length === 0" class="py-8 text-center text-gray-500">
        Geen incidenten gevonden
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.lastPage > 1" class="mt-4 flex justify-center gap-2">
      <button
        v-for="page in pagination.lastPage"
        :key="page"
        @click="goToPage(page)"
        :class="[
          'rounded px-3 py-1 text-sm',
          page === pagination.currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200'
        ]"
      >
        {{ page }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'admin',
})

const api = useApi()

const incidents = ref<any[]>([])
const filters = reactive({
  status: '',
  visibility: '',
  source: '',
})
const pagination = reactive({
  currentPage: 1,
  lastPage: 1,
})

async function loadIncidents() {
  try {
    const params = new URLSearchParams()
    params.append('page', String(pagination.currentPage))
    if (filters.status) params.append('status', filters.status)
    if (filters.visibility) params.append('visibility', filters.visibility)
    if (filters.source) params.append('source', filters.source)

    const response = await api.get<any>(`/admin/incidents?${params}`)
    incidents.value = response.data
    pagination.currentPage = response.current_page
    pagination.lastPage = response.last_page
  } catch (error) {
    console.error('Failed to load incidents:', error)
  }
}

function goToPage(page: number) {
  pagination.currentPage = page
  loadIncidents()
}

async function archiveIncident(incident: any) {
  if (!confirm('Weet je zeker dat je dit incident wilt verwijderen?')) return

  try {
    await api.delete(`/admin/incidents/${incident.id}`)
    loadIncidents()
  } catch (error) {
    console.error('Failed to archive:', error)
  }
}

async function approveIncident(incident: any) {
  try {
    await api.put(`/admin/incidents/${incident.id}`, { visibility: 'public' })
    loadIncidents()
  } catch (error) {
    console.error('Failed to approve:', error)
  }
}

function statusClass(status: string) {
  switch (status) {
    case 'officieel': return 'bg-blue-100 text-blue-800'
    case 'bevestigd': return 'bg-green-100 text-green-800'
    case 'gemeld': return 'bg-yellow-100 text-yellow-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString('nl-NL', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(loadIncidents)
</script>
