<script setup>
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import LoadingState from '@/components/LoadingState.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: [Array, Object], default: () => [] },
    loading: { type: Boolean, default: false },
    perPage: { type: Number, default: 10 },
    emptyTitle: { type: String, default: 'Sin resultados' },
    emptyDescription: { type: String, default: '' },
});

const emit = defineEmits(['page', 'sort']);

const sortKey = ref(null);
const sortDirection = ref('asc');
const localPage = ref(1);

// A Laravel paginator arrives as an object with `data`; a plain list arrives as
// an array and is then paginated in the browser.
const paginator = computed(() => (Array.isArray(props.rows) ? null : props.rows));
const allRows = computed(() => (Array.isArray(props.rows) ? props.rows : props.rows?.data ?? []));

const totalPages = computed(() => (
    paginator.value
        ? paginator.value.last_page ?? paginator.value.meta?.last_page ?? 1
        : Math.max(Math.ceil(allRows.value.length / props.perPage), 1)
));

const currentPage = computed(() => (
    paginator.value
        ? paginator.value.current_page ?? paginator.value.meta?.current_page ?? 1
        : localPage.value
));

const sortedRows = computed(() => {
    if (!sortKey.value) {
        return allRows.value;
    }

    const direction = sortDirection.value === 'asc' ? 1 : -1;

    return [...allRows.value].sort((left, right) => {
        const a = left[sortKey.value];
        const b = right[sortKey.value];

        if (a === b) {
            return 0;
        }

        return (a > b ? 1 : -1) * direction;
    });
});

const visibleRows = computed(() => (
    paginator.value
        ? sortedRows.value
        : sortedRows.value.slice((localPage.value - 1) * props.perPage, localPage.value * props.perPage)
));

watch(() => props.rows, () => { localPage.value = 1; });

function toggleSort(column) {
    if (!column.sortable) {
        return;
    }

    sortDirection.value = sortKey.value === column.key && sortDirection.value === 'asc' ? 'desc' : 'asc';
    sortKey.value = column.key;
    emit('sort', { key: sortKey.value, direction: sortDirection.value });
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) {
        return;
    }

    if (paginator.value) {
        emit('page', page);

        return;
    }

    localPage.value = page;
}
</script>

<template>
    <LoadingState v-if="loading" />
    <EmptyState v-else-if="!allRows.length" :title="emptyTitle" :description="emptyDescription" />
    <div v-else class="overflow-hidden rounded-card border border-line bg-surface">
        <table class="w-full text-left text-sm">
            <thead class="bg-canvas text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th v-for="column in columns" :key="column.key" scope="col" class="px-4 py-3 font-medium">
                        <button
                            v-if="column.sortable"
                            type="button"
                            class="flex items-center gap-1 hover:text-ink"
                            @click="toggleSort(column)"
                        >
                            {{ column.label }}
                            <span v-if="sortKey === column.key">{{ sortDirection === 'asc' ? '▲' : '▼' }}</span>
                        </button>
                        <span v-else>{{ column.label }}</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                <tr v-for="(row, index) in visibleRows" :key="row.id ?? index" class="hover:bg-canvas">
                    <td v-for="column in columns" :key="column.key" class="px-4 py-3 text-ink">
                        <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                            {{ row[column.key] ?? '—' }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-line px-4 py-3 text-sm">
            <span class="text-muted">Página {{ currentPage }} de {{ totalPages }}</span>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-line px-3 py-1.5 text-ink disabled:opacity-40"
                    :disabled="currentPage <= 1"
                    @click="goToPage(currentPage - 1)"
                >
                    Anterior
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-line px-3 py-1.5 text-ink disabled:opacity-40"
                    :disabled="currentPage >= totalPages"
                    @click="goToPage(currentPage + 1)"
                >
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</template>
