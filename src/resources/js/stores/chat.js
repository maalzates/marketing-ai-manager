import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
    createConversation,
    listConversations,
    sendMessage,
    showConversation,
} from '@/repositories/chatRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useChatStore = defineStore('chat', () => {
    const { loading, error, run } = useAsyncState();

    const conversations = ref([]);
    const current = ref(null);
    const messages = ref([]);
    const sending = ref(false);

    async function fetchConversations() {
        const result = await run(listConversations);

        conversations.value = result?.data ?? result ?? [];
    }

    async function openConversation(id) {
        const result = await run(() => showConversation(id));

        current.value = result ?? null;
        messages.value = result?.messages ?? [];
    }

    async function startConversation() {
        const result = await run(createConversation);

        if (!result) {
            return;
        }

        conversations.value = [result, ...conversations.value];
        current.value = result;
        messages.value = [];
    }

    async function send(content) {
        messages.value = [...messages.value, { id: `local-${Date.now()}`, role: 'user', content }];
        sending.value = true;

        const result = await run(() => sendMessage({ conversation_id: current.value?.id ?? null, message: content }));

        sending.value = false;

        if (!result) {
            return;
        }

        current.value = result.conversation ?? current.value;
        messages.value = result.messages ?? [...messages.value, result.message];
    }

    return { loading, error, conversations, current, messages, sending, fetchConversations, openConversation, startConversation, send };
});
