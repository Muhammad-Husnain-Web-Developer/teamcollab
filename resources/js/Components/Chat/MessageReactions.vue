<template>
  <div class="flex flex-wrap gap-1">
    <div
      v-for="reaction in reactions"
      :key="reaction.emoji"
      class="relative group/reaction"
    >
      <button
        @click="toggleReaction(reaction.emoji)"
        class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border transition-all duration-150"
        :class="
          reaction.reacted_by_me
            ? 'bg-brand-500/20 border-brand-500/50 text-brand-300'
            : 'bg-white/[0.05] border-white/[0.08] text-dark-50 hover:border-white/20 hover:bg-white/[0.08]'
        "
      >
        <img v-if="customEmojiUrl(reaction.emoji)" :src="customEmojiUrl(reaction.emoji)" class="w-4 h-4 object-contain" :alt="reaction.emoji" />
        <span v-else class="text-sm leading-none">{{ reaction.emoji }}</span>
        <span>{{ reaction.count }}</span>
      </button>

      <!-- Tooltip with names -->
      <div
        v-if="reaction.users?.length"
        class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover/reaction:block z-50 pointer-events-none"
      >
        <div class="glass-elevated px-3 py-2 text-xs text-dark-50 whitespace-nowrap max-w-[200px]">
          <div class="flex flex-wrap gap-1 justify-center mb-1">
            <img v-if="customEmojiUrl(reaction.emoji)" :src="customEmojiUrl(reaction.emoji)" class="w-4 h-4 object-contain" :alt="reaction.emoji" />
            <span v-else class="text-base">{{ reaction.emoji }}</span>
          </div>
          {{ reactionNames(reaction) }}
        </div>
      </div>
    </div>

    <!-- Add reaction button -->
    <button
      @click="emit('add-reaction')"
      class="flex items-center px-2 py-0.5 rounded-full text-xs border border-transparent text-dark-50/50 hover:border-white/[0.1] hover:bg-white/[0.06] hover:text-dark-50 transition-all duration-150"
    >
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import axios from 'axios';
import { useUIStore } from '../../Stores/useUIStore';
import { useMessageStore } from '../../Stores/useMessageStore';
import { useEmojiStore } from '../../Stores/useEmojiStore';

const props = defineProps({
  reactions: { type: Array, default: () => [] },
  messageId: { type: [Number, String], required: true },
});

const emit = defineEmits(['add-reaction']);
const uiStore = useUIStore();
const messageStore = useMessageStore();
const emojiStore = useEmojiStore();

const SHORTCODE_RE = /^:([a-z0-9_]{2,32}):$/i;

function customEmojiUrl(emoji) {
  const match = SHORTCODE_RE.exec(emoji ?? '');
  if (!match) return null;
  return emojiStore.byShortcode.get(match[1].toLowerCase()) ?? null;
}

async function toggleReaction(emoji) {
  try {
    const { data } = await axios.post(`/messages/${props.messageId}/react`, { emoji });
    messageStore.setReactions(props.messageId, data.reactions ?? []);
  } catch {
    uiStore.toastError('Failed to update reaction.');
  }
}

function reactionNames(reaction) {
  const names = reaction.users?.map(u => u.name) ?? [];
  if (names.length === 0) return '';
  if (names.length <= 3) return names.join(', ');
  return `${names.slice(0, 3).join(', ')} and ${names.length - 3} more`;
}
</script>
