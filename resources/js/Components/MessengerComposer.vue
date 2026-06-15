<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    canSend: {
        type: Boolean,
        default: false,
    },
    sending: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    sendAction: {
        type: Function,
        required: true,
    },
});

const copy = {
    composerHint: 'Write your message here...',
    attach: 'Attach File',
    locked: 'This order is completed. Chat is now view only.',
    removeFile: 'Remove',
};

const body = ref('');
const attachment = ref(null);
const fileInput = ref(null);
const textareaRef = ref(null);

const syncTextareaHeight = async () => {
    await nextTick();

    if (!textareaRef.value) {
        return;
    }

    textareaRef.value.style.height = '0px';
    const nextHeight = Math.min(textareaRef.value.scrollHeight, 96);
    textareaRef.value.style.height = `${Math.max(nextHeight, 46)}px`;
};

const chooseFile = () => {
    fileInput.value?.click();
};

const onFileChange = (event) => {
    attachment.value = event.target.files?.[0] ?? null;
};

const removeFile = () => {
    attachment.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = async () => {
    if (!props.canSend || props.sending) {
        return;
    }

    const success = await props.sendAction({
        body: body.value,
        attachment: attachment.value,
    });

    if (success) {
        body.value = '';
        removeFile();
        await syncTextareaHeight();
    }
};

const onTextareaKeydown = async (event) => {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
        return;
    }

    event.preventDefault();
    await submit();
};

watch(body, () => {
    void syncTextareaHeight();
});
</script>

<template>
    <div class="messenger-composer">
        <p v-if="!canSend" class="messenger-composer-lock">
            {{ copy.locked }}
        </p>

        <div v-if="attachment" class="composer-file-chip">
            <span>{{ attachment.name }}</span>
            <button type="button" @click="removeFile">
                {{ copy.removeFile }}
            </button>
        </div>

        <div class="composer-input-row">
            <textarea
                ref="textareaRef"
                v-model="body"
                class="composer-textarea"
                :placeholder="copy.composerHint"
                :disabled="!canSend || sending"
                rows="1"
                @input="syncTextareaHeight"
                @keydown="onTextareaKeydown"
            ></textarea>

            <input
                ref="fileInput"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.webp"
                class="composer-file-input"
                :disabled="!canSend || sending"
                @change="onFileChange"
            >
            <button
                type="button"
                class="composer-attach-button"
                :title="copy.attach"
                :aria-label="copy.attach"
                :disabled="!canSend || sending"
                @click="chooseFile"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.2-9.19a4 4 0 1 1 5.65 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.83l8.48-8.48" />
                </svg>
            </button>
        </div>

        <p v-if="error" class="messenger-composer-error">
            {{ error }}
        </p>
    </div>
</template>

<style scoped>
.messenger-composer{display:grid;gap:8px;flex-shrink:0;padding:12px 16px;border-top:1px solid rgba(148,163,184,.18);background:#fff}
.messenger-composer-lock,.messenger-composer-error{margin:0;font-size:.85rem;line-height:1.55}
.messenger-composer-lock{color:#64748b}
.messenger-composer-error{color:#9f1239}
.composer-file-chip{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:12px;background:#eff6ff;color:#1d4ed8;font-size:.82rem;font-weight:600}
.composer-file-chip button{border:0;background:transparent;color:#2563eb;font:inherit;cursor:pointer}
.composer-input-row{display:flex;align-items:flex-end;gap:10px}
.composer-textarea{flex:1 1 auto;min-width:0;min-height:42px;max-height:96px;resize:none;overflow-y:auto;padding:10px 14px;border:1px solid rgba(148,163,184,.3);border-radius:14px;background:#fff;color:#0f172a;font-size:.9rem;line-height:1.45}
.composer-file-input{display:none}
.composer-attach-button{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;width:42px;height:42px;padding:0;border-radius:12px;border:1px solid transparent;font-size:.88rem;font-weight:700;cursor:pointer}
.composer-attach-button{background:#fff;border-color:#cbd5e1;color:#0f172a}
.composer-attach-button:disabled{opacity:.55;cursor:not-allowed;box-shadow:none}
.composer-attach-button svg{width:18px;height:18px}
.composer-textarea::placeholder{color:#64748b}
@media (max-width: 720px){
    .composer-input-row{align-items:flex-start}
}
</style>
