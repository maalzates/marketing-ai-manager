import { ref } from 'vue';
import { useUiStore } from '@/stores/ui';

// Every store action shares the same three-way outcome: loading, a message the
// user is shown, and the 422 field errors a form needs to paint next to inputs.
export function useAsyncState() {
    const loading = ref(false);
    const error = ref(null);
    const fieldErrors = ref({});

    async function run(action, successMessage = null) {
        loading.value = true;
        error.value = null;
        fieldErrors.value = {};

        try {
            const result = await action();

            if (successMessage) {
                useUiStore().success(successMessage);
            }

            return result;
        } catch (failure) {
            error.value = failure.message;
            fieldErrors.value = failure.errors?.fields ?? {};
            useUiStore().error(failure.message);

            return undefined;
        } finally {
            loading.value = false;
        }
    }

    return { loading, error, fieldErrors, run };
}
