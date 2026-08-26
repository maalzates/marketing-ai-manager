<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
});

// Every glyph is a list of `d` strings so one `<path v-for>` draws all of them.
// Circles are arcs rather than <circle> for the same reason.
const PATHS = {
    dashboard: [
        'M3.5 3.5h7v6h-7z',
        'M13.5 3.5h7v9.5h-7z',
        'M3.5 13h7v7.5h-7z',
        'M13.5 16.5h7v4h-7z',
    ],
    strategies: [
        'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17',
        'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8',
        'M12 11.25a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5',
    ],
    experiments: [
        'M9 3.5h6',
        'M10 3.5v5.2L5.6 17a3 3 0 0 0 2.6 4.5h7.6a3 3 0 0 0 2.6-4.5L14 8.7V3.5',
        'M7.4 14.5h9.2',
    ],
    proposals: [
        'M8 3.5h8a1.5 1.5 0 0 1 1.5 1.5v15.5l-5.5-3-5.5 3V5A1.5 1.5 0 0 1 8 3.5',
        'M9.5 9.5l2 2 3.5-3.5',
    ],
    content: [
        'M4.5 5.5h15a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1',
        'M3.5 10h17',
        'M8 3.5v4',
        'M16 3.5v4',
        'M8 14h3',
        'M8 17.5h8',
    ],
    competitors: [
        'M2.5 12s3.5-6.5 9.5-6.5S21.5 12 21.5 12s-3.5 6.5-9.5 6.5S2.5 12 2.5 12',
        'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
    ],
    assets: [
        'M3.5 6.5A1.5 1.5 0 0 1 5 5h4l2 2.5h8A1.5 1.5 0 0 1 20.5 9v9A1.5 1.5 0 0 1 19 19.5H5A1.5 1.5 0 0 1 3.5 18z',
        'M8 15.5l2.5-3 2 2.3 2-2.5 2.5 3.2',
    ],
    chat: [
        'M20.5 12.5a7.5 7.5 0 0 1-10.9 6.7L4.5 20.5l1.4-4.6A7.5 7.5 0 1 1 20.5 12.5',
        'M9 11h6',
        'M9 14.5h3.5',
    ],
    reports: [
        'M3.5 20.5h17',
        'M6.5 20.5v-7',
        'M11 20.5V7.5',
        'M15.5 20.5v-4.5',
        'M20 20.5V11',
    ],
    settings: [
        'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
        'M19.4 14.5a1.4 1.4 0 0 0 .3 1.6l.1.1a1.6 1.6 0 1 1-2.3 2.3l-.1-.1a1.4 1.4 0 0 0-1.6-.3 1.4 1.4 0 0 0-.9 1.3v.2a1.6 1.6 0 1 1-3.2 0v-.1a1.4 1.4 0 0 0-.9-1.3 1.4 1.4 0 0 0-1.6.3l-.1.1a1.6 1.6 0 1 1-2.3-2.3l.1-.1a1.4 1.4 0 0 0 .3-1.6 1.4 1.4 0 0 0-1.3-.9h-.2a1.6 1.6 0 1 1 0-3.2h.1a1.4 1.4 0 0 0 1.3-.9 1.4 1.4 0 0 0-.3-1.6l-.1-.1a1.6 1.6 0 1 1 2.3-2.3l.1.1a1.4 1.4 0 0 0 1.6.3h.1a1.4 1.4 0 0 0 .9-1.3v-.2a1.6 1.6 0 1 1 3.2 0v.1a1.4 1.4 0 0 0 .9 1.3 1.4 1.4 0 0 0 1.6-.3l.1-.1a1.6 1.6 0 1 1 2.3 2.3l-.1.1a1.4 1.4 0 0 0-.3 1.6v.1a1.4 1.4 0 0 0 1.3.9h.2a1.6 1.6 0 1 1 0 3.2h-.1a1.4 1.4 0 0 0-1.3.9',
    ],
    admin: [
        'M12 3l7 3v5.5c0 4.4-2.9 8.3-7 9.5-4.1-1.2-7-5.1-7-9.5V6z',
        'M9 12l2 2 4-4',
    ],
};

const FALLBACK = ['M12 4.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15'];

const paths = computed(() => PATHS[props.name] ?? FALLBACK);
</script>

<template>
    <svg
        class="h-[18px] w-[18px] shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <path v-for="d in paths" :key="d" :d="d" />
    </svg>
</template>
