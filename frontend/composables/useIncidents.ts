import type { Incident, IncidentCategory } from '~/types/Incident';
import { apiFetch } from '~/services/apiFetch';

export function useIncidents() {
  const incidents = useState<Incident[]>('incidents', () => []);
  const loading = useState('incidents-loading', () => false);
  const loadError = useState<string | null>('incidents-load-error', () => null);

  async function refresh(latitude?: number, longitude?: number) {
    loading.value = true;
    try {
      const query = latitude !== undefined && longitude !== undefined ? `?latitude=${latitude}&longitude=${longitude}` : '';
      const response = await apiFetch<{ data: Incident[] }>(`/incidents${query}`);
      incidents.value = response.data ?? [];
      loadError.value = null;
    } catch (error) {
      incidents.value = [];
      const detail = error instanceof Error ? error.message : 'Onbekende fout';
      console.error('[VeiligBuiten] Incidenten laden mislukt:', error);
      loadError.value = `Actuele meldingen konden niet worden geladen (${detail}).`;
    } finally {
      loading.value = false;
    }
  }

  function byCategories(categories: IncidentCategory[]) {
    return categories.length === 0
      ? incidents.value
      : incidents.value.filter((incident) => categories.includes(incident.category));
  }

  function addLocalIncident(incident: Omit<Incident, 'id' | 'left' | 'top' | 'confirmations'>) {
    incidents.value = [{ ...incident, id: Date.now(), confirmations: 1, left: 49, top: 52 }, ...incidents.value];
  }

  return { incidents, loading, loadError, refresh, byCategories, addLocalIncident };
}
