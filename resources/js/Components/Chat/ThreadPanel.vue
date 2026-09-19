<template>
  <Transition name="thread-slide">
    <aside
      v-if="threadStore.isOpen"
      class="flex flex-col min-h-0 bg-dark-800/80 backdrop-blur-2xl border-l border-white/[0.06]
             fixed inset-0 z-40
             lg:static lg:inset-auto lg:z-auto lg:w-[400px] xl:w-[440px] lg:flex-shrink-0"
    >
      <!-- Header -->
      <header class="flex-shrink-0 flex items-center justify-between px-4 py-3.5 border-b border-white/[0.06]">
        <div class="min-w-0">
          <h2 class="text-sm font-semibold text-white">Thread</h2>
          <p class="text-xs text-dark-50/60 truncate">
            {{ threadStore.repliesCount }}
            {{ threadStore.repliesCount === 1 ? 'reply' : 'replies' }}
          </p>
        </div>
        <button
          class="icon-btn"
          title="Close thread"
          @click="threadStore.close()"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </header>

      <!-- Body -->
      <div ref="scrollEl" class="flex-1 min-h-0 overflow-y-auto px-2 py-3">
        <div v-if="threadStore.loading" class="px-2 space-y-3">
          <SkeletonLoader v-for="n in 3" :key="n" />
        </div>

        <p v-else-if="threadStore.error" class="px-3 py-4 text-sm text-red-400">
          {{ threadStore.error }}
        </p>

        <template v-else>
          <!-- Root message -->
          <MessageItem v-if="threadStore.root" :message="threadStore.root" :show-header="true" :is-dm="isDm" />

          <div class="flex items-center gap-3 px-3 my-3">
            <span class="h-px flex-1 bg-white/[0.07]"></span>
            <span class="text-[11px] uppercase tracking-wide text-dark-50/50">
              {{ threadStore.repliesCount }}
              {{ threadStore.repliesCount === 1 ? 'reply' : 'replies' }}
            </span>
            <span class="h-px flex-1 bg-white/[0.07]"></span>
          </div>

          <p v-if="threadStore.repliesCount === 0" class="px-3 py-2 text-sm text-dark-50/50">
            No replies yet — start the conversation.
          </p>

          <MessageItem
            v-for="reply in threadStore.replies"
            :key="reply.id"
            :message="reply"
            :show-header="true"
            :is-dm="isDm"
          />
        </template>
      </div>

      <!-- Composer -->
      <div class="flex-shrink-0 border-t border-white/[0.06] p-3">
        <div class="flex items-end gap-2">
          <textarea
            ref="inputEl"
            v-model="body"
            rows="1"
            placeholder="Reply in thread…"
            class="input-field flex-1 !rounded-xl resize-none max-h-32"
            @keydown.enter.exact.prevent="submit"
          ></textarea>
          <button
            class="flex-shrink-0 p-2.5 rounded-xl bg-gradient-to-b from-brand-400 to-brand-600 text-white disabled:opacity-40
                   disabled:cursor-not-allowed hover:shadow-glow-brand transition-all"
            :disabled="!canSend"
            title="Send reply"
            @click="submit"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
          </button>
        </div>
      </div>
    </aside>
  </Transition>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import MessageItem from './MessageItem.vue';
import SkeletonLoader from '../Common/SkeletonLoader.vue';
import { useThreadStore } from '../../Stores/useThreadStore';
import { useUIStore } from '../../Stores/useUIStore';

defineProps({
  isDm: { type: Boolean, default: false },
});

const threadStore = useThreadStore();
const uiStore = useUIStore();

const body = ref('');
const inputEl = ref(null);
const scrollEl = ref(null);

const canSend = computed(() => body.value.trim().length > 0 && !threadStore.sending);

async function submit() {
  if (!canSend.value) return;

  const text = body.value.trim();
  body.value = '';

  try {
    await threadStore.sendReply(text);
    scrollToBottom();
  } catch {
    body.value = text; // restore so the reply isn't lost
    uiStore.toastError('Could not send that reply.');
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
  });
}

// Focus the composer whenever a thread is opened
watch(() => threadStore.isOpen, (open) => {
  if (open) nextTick(() => inputEl.value?.focus());
});

watch(() => threadStore.replies.length, scrollToBottom);

// Auto-resize the composer
watch(body, () => {
  nextTick(() => {
    if (!inputEl.value) return;
    inputEl.value.style.height = 'auto';
    inputEl.value.style.height = `${Math.min(inputEl.value.scrollHeight, 128)}px`;
  });
});
</script>

<style scoped>
.thread-slide-enter-active,
.thread-slide-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.thread-slide-enter-from,
.thread-slide-leave-to     { opacity: 0; transform: translateX(12px); }
</style>
