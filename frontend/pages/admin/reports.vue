<template>
  <div class="mx-auto max-w-6xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold">Meldingen te reviewen</h1>
      <NuxtLink to="/admin" class="text-sm text-blue-600 hover:underline">Terug</NuxtLink>
    </div>

    <div v-if="loading" class="py-8 text-center text-gray-500">
      Laden...
    </div>

    <div v-else-if="reports.length === 0" class="rounded-lg bg-white p-8 text-center shadow">
      <p class="text-gray-500">Geen meldingen te reviewen</p>
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="report in reports"
        :key="report.id"
        class="rounded-lg bg-white p-6 shadow"
      >
        <div class="mb-4 flex items-start justify-between">
          <div>
            <h3 class="font-semibold">{{ report.subtype }}</h3>
            <p class="text-sm text-gray-500">{{ report.category }} · {{ formatDate(report.created_at) }}</p>
          </div>
          <span class="rounded-full bg-orange-100 px-2 py-1 text-xs font-medium text-orange-800">
            Review
          </span>
        </div>

        <p v-if="report.description" class="mb-4 text-gray-700">
          {{ report.description }}
        </p>

        <div class="mb-4 text-sm text-gray-500">
          <p>Locatie: {{ report.latitude.toFixed(4) }}, {{ report.longitude.toFixed(4) }}</p>
          <p v-if="report.incident">Incident #{{ report.incident.id }} ({{ report.incident.status }})</p>
        </div>

        <div class="flex gap-3">
          <button
            @click="approveReport(report)"
            class="rounded-md bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700"
          >
            Goedkeuren
          </button>
          <button
            @click="rejectReport(report)"
            class="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
          >
            Afwijzen
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'admin',
})

const api = useApi()

const reports = ref<any[]>([])
const loading = ref(true)

async function loadReports() {
  loading.value = true
  try {
    const response = await api.get<any>('/admin/reports/pending')
    reports.value = response.data
  } catch (error) {
    console.error('Failed to load reports:', error)
  } finally {
    loading.value = false
  }
}

async function approveReport(report: any) {
  try {
    await api.post(`/admin/reports/${report.id}/approve`)
    reports.value = reports.value.filter(r => r.id !== report.id)
  } catch (error) {
    console.error('Failed to approve:', error)
  }
}

async function rejectReport(report: any) {
  const reason = prompt('Reden voor afwijzing (optioneel):')
  try {
    await api.post(`/admin/reports/${report.id}/reject`, { reason: reason || 'spam' })
    reports.value = reports.value.filter(r => r.id !== report.id)
  } catch (error) {
    console.error('Failed to reject:', error)
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

onMounted(loadReports)
</script>
