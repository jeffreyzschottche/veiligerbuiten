<template>
  <div class="mx-auto max-w-6xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold">Gebruikers</h1>
      <NuxtLink to="/admin" class="text-sm text-blue-600 hover:underline">Terug</NuxtLink>
    </div>

    <!-- Search -->
    <div class="mb-6">
      <input
        v-model="search"
        @input="debouncedSearch"
        type="text"
        placeholder="Zoek op naam of email..."
        class="w-full max-w-md rounded-md border px-4 py-2"
      />
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded-lg bg-white shadow">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">ID</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Naam</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Email</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Meldingen</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Status</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Acties</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">{{ user.id }}</td>
            <td class="px-4 py-3 font-medium">{{ user.name }}</td>
            <td class="px-4 py-3 text-sm">
              {{ user.email }}
              <span v-if="user.email_verified_at" class="ml-1 text-green-600">✓</span>
            </td>
            <td class="px-4 py-3 text-sm">{{ user.reports_count || 0 }}</td>
            <td class="px-4 py-3">
              <span v-if="user.is_admin" class="rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-800">
                Admin
              </span>
            </td>
            <td class="px-4 py-3">
              <button
                @click="toggleAdmin(user)"
                class="text-sm text-blue-600 hover:underline"
              >
                {{ user.is_admin ? 'Verwijder admin' : 'Maak admin' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="users.length === 0" class="py-8 text-center text-gray-500">
        Geen gebruikers gevonden
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

const users = ref<any[]>([])
const search = ref('')
const pagination = reactive({
  currentPage: 1,
  lastPage: 1,
})

let searchTimeout: ReturnType<typeof setTimeout>

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    pagination.currentPage = 1
    loadUsers()
  }, 300)
}

async function loadUsers() {
  try {
    const params = new URLSearchParams()
    params.append('page', String(pagination.currentPage))
    if (search.value) params.append('search', search.value)

    const response = await api.get<any>(`/admin/users?${params}`)
    users.value = response.data
    pagination.currentPage = response.current_page
    pagination.lastPage = response.last_page
  } catch (error) {
    console.error('Failed to load users:', error)
  }
}

function goToPage(page: number) {
  pagination.currentPage = page
  loadUsers()
}

async function toggleAdmin(user: any) {
  const action = user.is_admin ? 'admin rechten verwijderen van' : 'admin maken van'
  if (!confirm(`Weet je zeker dat je ${action} ${user.name}?`)) return

  try {
    await api.put(`/admin/users/${user.id}`, { is_admin: !user.is_admin })
    loadUsers()
  } catch (error) {
    console.error('Failed to update user:', error)
  }
}

onMounted(loadUsers)
</script>
