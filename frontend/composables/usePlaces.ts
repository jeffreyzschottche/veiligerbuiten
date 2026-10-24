import type { Place } from '~/types/Incident';
import { storage } from '~/utils/storage';

export const MAX_PLACES = 10;
export const RADIUS_STEPS = [100, 250, 500, 1000, 2000, 5000];

/** Je opgeslagen plekken. De server bewaakt ze en stuurt een push als er iets speelt. */
export function usePlaces() {
  const places = useState<Place[]>('places', () => []);
  const loaded = useState('places-loaded', () => false);

  async function load() {
    if (!loaded.value) places.value = await storage.get<Place[]>('vb_places') ?? [];
    try {
      const { data } = await useApi().get<{ data: Place[] }>('/places');
      places.value = data;
      loaded.value = true;
      await persist();
    } catch { /* offline: we tonen de bewaarde lijst */ }
  }

  async function save(place: Partial<Place> & Pick<Place, 'name' | 'latitude' | 'longitude'>) {
    const body = { name: place.name, latitude: place.latitude, longitude: place.longitude, radius_m: place.radius_m ?? 500, alerts_enabled: place.alerts_enabled ?? true };
    const { data } = place.id
      ? await useApi().put<{ data: Place }>(`/places/${place.id}`, body)
      : await useApi().post<{ data: Place }>('/places', body);
    places.value = place.id ? places.value.map((item) => item.id === data.id ? data : item) : [...places.value, data];
    await persist();
    return data;
  }

  async function toggle(place: Place) {
    return save({ ...place, alerts_enabled: !place.alerts_enabled });
  }

  async function remove(place: Place) {
    await useApi().delete(`/places/${place.id}`);
    places.value = places.value.filter((item) => item.id !== place.id);
    await persist();
  }

  async function persist() {
    await storage.set('vb_places', places.value);
    await configureRunner(places.value);
  }

  return { places, load, save, toggle, remove };
}
