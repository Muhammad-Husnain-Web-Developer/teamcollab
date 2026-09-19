<template>
  <transition name="typing-fade">
    <div
      v-if="typingText"
      class="flex items-center gap-2 px-4 py-1 text-xs text-dark-50/60 h-6"
    >
      <!-- Animated dots -->
      <div class="flex items-center gap-0.5">
        <span class="typing-dot" style="animation-delay: 0ms" />
        <span class="typing-dot" style="animation-delay: 200ms" />
        <span class="typing-dot" style="animation-delay: 400ms" />
      </div>
      <span>{{ typingText }}</span>
    </div>
    <div v-else class="h-6" />
  </transition>
</template>

<script setup>
import { computed } from 'vue';
import { useMessageStore } from '../../Stores/useMessageStore';
import { useAuthStore } from '../../Stores/useAuthStore';

const props = defineProps({
  channelId: { type: [Number, String], required: true },
});

const messageStore = useMessageStore();
const authStore = useAuthStore();

const typingText = computed(() => {
  const typers = messageStore
    .getTypingUsers(props.channelId)
    .filter(t => t.userId !== authStore.user?.id);

  if (typers.length === 0) return '';
  if (typers.length === 1) return `${typers[0].userName} is typing…`;
  if (typers.length === 2) return `${typers[0].userName} and ${typers[1].userName} are typing…`;
  return 'Several people are typing…';
});
</script>

<style scoped>
.typing-dot {
  display: inline-block;
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background-color: #6b7280;
  animation: typing-bounce 1s infinite ease-in-out;
}

@keyframes typing-bounce {
  0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
  40% { transform: scale(1); opacity: 1; }
}

.typing-fade-enter-active,
.typing-fade-leave-active {
  transition: opacity 0.2s ease;
}
.typing-fade-enter-from,
.typing-fade-leave-to {
  opacity: 0;
}
</style>
