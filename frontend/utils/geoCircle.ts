/** Een cirkel in meters als GeoJSON-polygoon, zodat een straal op elke zoom klopt. */
export function circleFeature(latitude: number, longitude: number, meters: number, steps = 64) {
  const coordinates: [number, number][] = [];
  const latRadius = meters / 111_320;
  const lngRadius = meters / (111_320 * Math.cos((latitude * Math.PI) / 180));
  for (let i = 0; i <= steps; i++) {
    const angle = (i / steps) * Math.PI * 2;
    coordinates.push([longitude + lngRadius * Math.cos(angle), latitude + latRadius * Math.sin(angle)]);
  }
  return { type: 'Feature' as const, properties: {} as Record<string, unknown>, geometry: { type: 'Polygon' as const, coordinates: [coordinates] } };
}

/** Het kader rond een cirkel, voor fitBounds. */
export function circleBounds(latitude: number, longitude: number, meters: number): [[number, number], [number, number]] {
  const latRadius = meters / 111_320;
  const lngRadius = meters / (111_320 * Math.cos((latitude * Math.PI) / 180));
  return [[longitude - lngRadius, latitude - latRadius], [longitude + lngRadius, latitude + latRadius]];
}
