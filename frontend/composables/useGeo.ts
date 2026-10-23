const EARTH_RADIUS_M = 6371000

/**
 * Calculate distance in meters between two coordinates using Haversine formula.
 */
export function distanceMeters(
  lat1: number,
  lon1: number,
  lat2: number,
  lon2: number
): number {
  const latDelta = toRad(lat2 - lat1)
  const lonDelta = toRad(lon2 - lon1)

  const a =
    Math.sin(latDelta / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(lonDelta / 2) ** 2

  return EARTH_RADIUS_M * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

/**
 * Format distance for display.
 */
export function formatDistance(meters: number): string {
  if (meters < 1000) {
    return `${Math.round(meters)} m`
  }
  return `${(meters / 1000).toFixed(1).replace('.', ',')} km`
}

/**
 * Check if coordinate is within Netherlands bounds.
 */
export function isInNetherlands(lat: number, lon: number): boolean {
  return lat >= 50.75 && lat <= 53.55 && lon >= 3.35 && lon <= 7.25
}

function toRad(deg: number): number {
  return (deg * Math.PI) / 180
}

export function useGeo() {
  return {
    distanceMeters,
    formatDistance,
    isInNetherlands,
  }
}
