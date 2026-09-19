<template>
  <Modal :show="show" title="Edit message" size="md" @close="close">
    <div class="px-6 py-4 space-y-3">
      <textarea
        ref="textareaEl"
        v-model="body"
        rows="4"
        maxlength="10000"
        class="input-field resize-none"
        placeholder="Message…"
        @keydown="onKeydown"
      />
      <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
      <p v-else class="text-xs text-dark-50/50">Enter to save · Shift+Enter for a new line · Esc to cancel</p>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-white/[0.07]">
        <button type="button" class="btn-ghost" :disabled="saving" @click="close">Cancel</button>
        <button
          type="button"
          class="btn-primary"
          :disabled="!canSave || saving"
          @click="save"
        >
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import Modal from '../Common/Modal.vue';
import { useMessageStore } from '../../Stores/useMessageStore';
import { useUIStore } from '../../Stores/useUIStore';

const props = defineProps({
  show:    { type: Boolean, default: false },
  message: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const messageStore = useMessageStore();
const uiStore      = useUIStore();

const textareaEl = ref(null);
const body       = ref('');
const saving     = ref(false);
const error      = ref(null);

const trimmed = computed(() => body.value.trim());
const canSave = computed(() =>
  trimmed.value.length > 0 && trimmed.value !== (props.message?.body ?? '').trim(),
);

// Reload the draft every time the modal opens for a (possibly different) message.
watch(() => props.show, (open) => {
  if (!open) return;
  body.value  = props.message?.body ?? '';
  error.value = null;
  saving.value = false;
  nextTick(() => {
    const el = textareaEl.value;
    if (!el) return;
    el.focus();
    el.setSelectionRange(el.value.length, el.value.length);
  });
});

function onKeydown(e) {
  if (e.key === 'Escape') {
    e.preventDefault();
    close();
  } else if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    save();
  }
}

async function save() {
  if (!canSave.value || saving.value || !props.message) return;
  saving.value = true;
  error.value  = null;

  try {
    const { data } = await axios.put(`/messages/${props.message.id}`, { body: trimmed.value });
    // The response carries the fresh row; other clients get the same patch
    // over `.message.updated`.
    messageStore.updateMessage(data.message ?? { id: props.message.id, body: trimmed.value, is_edited: true });
    close();
  } catch (e) {
    error.value = e?.response?.data?.message
      ?? e?.response?.data?.errors?.body?.[0]
      ?? 'Could not save your edit.';
    if (e?.response?.status === 403) uiStore.toastError('You can only edit your own messages.');
  } finally {
    saving.value = false;
  }
}

function close() {
  emit('close');
}
</script>
