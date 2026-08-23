<script setup>
defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    errors: { type: Array, default: () => [] },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <label class="block">
        <span class="flex items-center gap-1 text-sm font-medium text-ink">
            {{ label }}
            <span v-if="required" class="text-danger-500">*</span>
            <slot name="term" />
        </span>
        <span v-if="hint" class="mt-1 block text-xs text-muted">{{ hint }}</span>
        <span class="mt-2 block">
            <slot />
        </span>
        <span v-for="message in errors" :key="message" class="mt-1 block text-xs text-danger-700">{{ message }}</span>
    </label>
</template>
