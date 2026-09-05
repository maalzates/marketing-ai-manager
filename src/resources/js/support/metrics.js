// El glosario documenta catorce métricas; sólo estas cinco sirven de métrica norte, y son
// las que el enum NorthStarMetric valida en el servidor.
export const NORTH_STAR_METRICS = [
    { value: 'conversions', label: 'Conversiones' },
    { value: 'roas', label: 'ROAS' },
    { value: 'cpa', label: 'Costo por adquisición' },
    { value: 'cpl', label: 'Costo por lead' },
    { value: 'cost_per_follower', label: 'Costo por seguidor' },
];

// Las estrategias creadas antes de la lista cerrada guardan texto libre: se muestra tal cual.
export function northStarLabel(value) {
    return NORTH_STAR_METRICS.find((metric) => metric.value === value)?.label ?? value ?? '—';
}

// El modelo contesta texto libre; sólo se acepta si nombra una de las cinco, por clave o
// por etiqueta. Devuelve null cuando no coincide, para que la pantalla lo pueda decir.
export function matchNorthStar(suggestion) {
    const needle = String(suggestion ?? '').trim().toLowerCase();

    return NORTH_STAR_METRICS.find(
        (metric) => needle === metric.value || needle === metric.label.toLowerCase(),
    )?.value ?? null;
}
