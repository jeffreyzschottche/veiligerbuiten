<template>
  <div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="mb-8 text-2xl font-bold">Mijn Account</h1>

    <!-- Profile Section -->
    <section class="mb-8 rounded-lg bg-white p-6 shadow">
      <h2 class="mb-4 text-lg font-semibold">Profiel</h2>

      <form @submit.prevent="updateProfile" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Naam</label>
          <input
            v-model="profileForm.name"
            type="text"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input
            v-model="profileForm.email"
            type="email"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
          />
          <p v-if="authStore.user && !authStore.user.email_verified_at" class="mt-1 text-sm text-yellow-600">
            Email niet geverifieerd
          </p>
        </div>

        <div class="flex items-center gap-4">
          <button
            type="submit"
            :disabled="savingProfile"
            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {{ savingProfile ? 'Opslaan...' : 'Opslaan' }}
          </button>
          <span v-if="profileSuccess" class="text-sm text-green-600">{{ profileSuccess }}</span>
          <span v-if="profileError" class="text-sm text-red-600">{{ profileError }}</span>
        </div>
      </form>
    </section>

    <!-- Password Section -->
    <section class="mb-8 rounded-lg bg-white p-6 shadow">
      <h2 class="mb-4 text-lg font-semibold">Wachtwoord wijzigen</h2>

      <form @submit.prevent="updatePassword" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Huidig wachtwoord</label>
          <input
            v-model="passwordForm.current_password"
            type="password"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Nieuw wachtwoord</label>
          <input
            v-model="passwordForm.password"
            type="password"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Bevestig nieuw wachtwoord</label>
          <input
            v-model="passwordForm.password_confirmation"
            type="password"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2"
          />
        </div>

        <div class="flex items-center gap-4">
          <button
            type="submit"
            :disabled="savingPassword"
            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {{ savingPassword ? 'Wijzigen...' : 'Wachtwoord wijzigen' }}
          </button>
          <span v-if="passwordSuccess" class="text-sm text-green-600">{{ passwordSuccess }}</span>
          <span v-if="passwordError" class="text-sm text-red-600">{{ passwordError }}</span>
        </div>
      </form>
    </section>

    <!-- My Reports Section -->
    <section class="mb-8 rounded-lg bg-white p-6 shadow">
      <h2 class="mb-4 text-lg font-semibold">Mijn meldingen</h2>

      <div v-if="loadingReports" class="py-8 text-center text-gray-500">
        Laden...
      </div>

      <div v-else-if="reports.length === 0" class="py-8 text-center text-gray-500">
        Je hebt nog geen meldingen geplaatst.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="report in reports"
          :key="report.id"
          class="flex items-center justify-between rounded-md border p-3"
        >
          <div>
            <p class="font-medium">{{ report.subtype }}</p>
            <p class="text-sm text-gray-500">
              {{ report.category }} · {{ formatDate(report.created_at) }}
            </p>
          </div>
          <span
            :class="statusClass(report.incident?.status)"
            class="rounded-full px-2 py-1 text-xs font-medium"
          >
            {{ report.incident?.status || 'onbekend' }}
          </span>
        </div>
      </div>
    </section>

    <!-- Danger Zone -->
    <section class="rounded-lg border border-red-200 bg-red-50 p-6">
      <h2 class="mb-4 text-lg font-semibold text-red-800">Account verwijderen</h2>
      <p class="mb-4 text-sm text-red-700">
        Als je je account verwijdert, worden al je gegevens permanent gewist. Dit kan niet ongedaan worden gemaakt.
      </p>

      <button
        v-if="!showDeleteConfirm"
        @click="showDeleteConfirm = true"
        class="rounded-md bg-red-600 px-4 py-2 text-white hover:bg-red-700"
      >
        Account verwijderen
      </button>

      <div v-else class="space-y-3">
        <p class="text-sm font-medium text-red-800">Voer je wachtwoord in om te bevestigen:</p>
        <input
          v-model="deletePassword"
          type="password"
          placeholder="Wachtwoord"
          class="w-full rounded-md border border-red-300 px-3 py-2"
        />
        <div class="flex gap-2">
          <button
            @click="deleteAccount"
            :disabled="deletingAccount"
            class="rounded-md bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:opacity-50"
          >
            {{ deletingAccount ? 'Verwijderen...' : 'Definitief verwijderen' }}
          </button>
          <button
            @click="showDeleteConfirm = false"
            class="rounded-md border px-4 py-2 hover:bg-gray-50"
          >
            Annuleren
          </button>
        </div>
        <p v-if="deleteError" class="text-sm text-red-600">{{ deleteError }}</p>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const authStore = useAuthStore()
const api = useApi()
const router = useRouter()

// Profile form
const profileForm = reactive({
  name: authStore.user?.name || '',
  email: authStore.user?.email || '',
})
const savingProfile = ref(false)
const profileSuccess = ref('')
const profileError = ref('')

async function updateProfile() {
  savingProfile.value = true
  profileSuccess.value = ''
  profileError.value = ''

  try {
    const response = await api.put<{ message: string; user: any }>('/profile', profileForm)
    authStore.setSession(authStore.token!, response.user)
    profileSuccess.value = response.message
  } catch (error: any) {
    profileError.value = error.message || 'Kon profiel niet bijwerken'
  } finally {
    savingProfile.value = false
  }
}

// Password form
const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const savingPassword = ref(false)
const passwordSuccess = ref('')
const passwordError = ref('')

async function updatePassword() {
  savingPassword.value = true
  passwordSuccess.value = ''
  passwordError.value = ''

  try {
    const response = await api.put<{ message: string }>('/profile/password', passwordForm)
    passwordSuccess.value = response.message
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  } catch (error: any) {
    passwordError.value = error.message || 'Kon wachtwoord niet wijzigen'
  } finally {
    savingPassword.value = false
  }
}

// Reports
const reports = ref<any[]>([])
const loadingReports = ref(true)

async function loadReports() {
  try {
    const response = await api.get<{ data: any[] }>('/profile/reports')
    reports.value = response.data
  } catch (error) {
    console.error('Failed to load reports:', error)
  } finally {
    loadingReports.value = false
  }
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString('nl-NL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function statusClass(status: string) {
  switch (status) {
    case 'officieel':
    case 'bevestigd':
      return 'bg-green-100 text-green-800'
    case 'gemeld':
      return 'bg-yellow-100 text-yellow-800'
    case 'opgelost':
    case 'verlopen':
      return 'bg-gray-100 text-gray-800'
    default:
      return 'bg-gray-100 text-gray-600'
  }
}

// Delete account
const showDeleteConfirm = ref(false)
const deletePassword = ref('')
const deletingAccount = ref(false)
const deleteError = ref('')

async function deleteAccount() {
  deletingAccount.value = true
  deleteError.value = ''

  try {
    await api.delete('/profile', { password: deletePassword.value })
    authStore.logout()
    router.push('/')
  } catch (error: any) {
    deleteError.value = error.message || 'Kon account niet verwijderen'
  } finally {
    deletingAccount.value = false
  }
}

onMounted(() => {
  loadReports()
})
</script>
