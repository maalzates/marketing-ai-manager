<script setup>
import { computed } from 'vue';

const props = defineProps({
    values: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    width: { type: Number, default: 160 },
    height: { type: Number, default: 40 },
});

const points = computed(() => {
    const series = props.values.map(Number).filter((value) => Number.isFinite(value));

    if (series.length < 2) {
        return '';
    }

    const min = Math.min(...series);
    const span = Math.max(...series) - min || 1;
    const step = props.width / (series.length - 1);

    return series
        .map((value, index) => `${(index * step).toFixed(1)},${(props.height - ((value - min) / span) * props.height).toFixed(1)}`)
        .join(' ');
});
</script>

<template>
    <figure class="inline-flex flex-col gap-1">
        <svg
            v-if="points"
            :viewBox="`0 0 ${width} ${height}`"
            :width="width"
            :height="height"
            role="img"
            :aria-label="label"
            preserveAspectRatio="none"
        >
            <polyline :points="points" fill="none" stroke="currentColor" stroke-width="2" class="text-brand-600" />
        </svg>
        <span v-else class="text-xs text-muted">Sin datos suficientes</span>
        <figcaption v-if="label" class="text-xs text-muted">{{ label }}</figcaption>
    </figure>
</template>
