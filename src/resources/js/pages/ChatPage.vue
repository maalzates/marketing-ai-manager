<script setup>
import { onMounted, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import LoadingState from '@/components/LoadingState.vue';
import ProposalCard from '@/components/ProposalCard.vue';
import { useChatStore } from '@/stores/chat';

const chat = useChatStore();
const draft = ref('');

onMounted(chat.fetchConversations);

async function send() {
    if (!draft.value.trim()) {
        return;
    }

    const content = draft.value;

    draft.value = '';
    await chat.send(content);
}
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        <aside class="space-y-3">
            <button
                type="button"
                class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                @click="chat.startConversation()"
            >
                Nueva conversación
            </button>
            <ul class="space-y-1">
                <li v-for="conversation in chat.conversations" :key="conversation.id">
                    <button
                        type="button"
                        class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-canvas"
                        :class="chat.current?.id === conversation.id ? 'bg-brand-50 text-brand-700' : 'text-muted'"
                        @click="chat.openConversation(conversation.id)"
                    >
                        {{ conversation.title ?? 'Conversación' }}
                    </button>
                </li>
            </ul>
        </aside>

        <section class="flex min-h-[32rem] flex-col rounded-card border border-line bg-surface">
            <div class="flex-1 space-y-4 overflow-y-auto p-5">
                <EmptyState
                    v-if="!chat.messages.length && !chat.loading"
                    title="Pregúntale al asistente"
                    description="Puede consultar tus estrategias, experimentos e insights, y proponer campañas que tú decides."
                />
                <LoadingState v-else-if="chat.loading && !chat.messages.length" />

                <div v-for="message in chat.messages" :key="message.id" class="flex flex-col gap-2">
                    <div
                        class="max-w-2xl rounded-card px-4 py-3 text-sm"
                        :class="message.role === 'user' ? 'self-end bg-brand-50 text-ink' : 'bg-canvas text-ink'"
                    >
                        {{ message.content }}
                    </div>
                    <ProposalCard v-if="message.proposal" :proposal="message.proposal" />
                </div>

                <p v-if="chat.sending" class="text-sm text-muted">El asistente está pensando…</p>
            </div>

            <form class="flex gap-3 border-t border-line p-4" @submit.prevent="send">
                <input
                    v-model="draft"
                    type="text"
                    class="flex-1 rounded-lg border border-line px-3 py-2 text-sm"
                    placeholder="¿Qué hipótesis probamos esta semana?"
                >
                <button
                    type="submit"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                    :disabled="chat.sending"
                >
                    Enviar
                </button>
            </form>
        </section>
    </div>
</template>
