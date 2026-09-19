<template>
  <div class="flex-shrink-0 px-4 pb-4 pt-2">
    <!-- Reply banner -->
    <Transition name="reply-slide">
      <div
        v-if="messageStore.replyTo"
        class="mb-2 flex items-center gap-3 px-3 py-2 glass-panel border-l-2 border-l-brand-400"
      >
        <svg class="w-4 h-4 text-brand-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
        </svg>
        <div class="flex-1 min-w-0">
          <p class="text-xs font-semibold text-brand-400">
            Replying to {{ messageStore.replyTo.user?.display_name || messageStore.replyTo.user?.name || 'message' }}
          </p>
          <p class="text-xs text-dark-50/60 truncate">
            {{ messageStore.replyTo.body || '📎 Attachment' }}
          </p>
        </div>
        <button
          @click="messageStore.clearReplyTo()"
          class="flex-shrink-0 p-1.5 rounded-lg text-dark-100 hover:text-white hover:bg-white/[0.08] transition-all"
          title="Cancel reply"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </Transition>

    <!-- File preview area -->
    <div v-if="pendingFiles.length" class="mb-2 flex flex-wrap gap-2.5 p-3 glass-panel">
      <div
        v-for="(pf, idx) in pendingFiles"
        :key="pf.key"
        class="group/file relative flex items-center gap-2.5 bg-white/[0.05] border border-white/[0.08] rounded-xl p-2 pr-3 transition-all hover:border-white/[0.15]"
      >
        <!-- Image thumbnail or type icon -->
        <div class="relative w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-white/[0.06] flex items-center justify-center">
          <img v-if="pf.previewUrl" :src="pf.previewUrl" class="w-full h-full object-cover" alt="" />
          <span v-else class="text-lg leading-none">{{ fileEmoji(pf.file) }}</span>

          <!-- Upload progress ring overlay -->
          <div
            v-if="pf.uploading"
            class="absolute inset-0 bg-black/60 flex items-center justify-center"
          >
            <span class="text-[9px] font-bold text-white tabular-nums">{{ pf.progress }}%</span>
          </div>
        </div>

        <div class="min-w-0">
          <p class="text-xs font-medium text-dark-50 truncate max-w-[130px]">{{ pf.file.name }}</p>
          <p class="text-[10px] text-dark-50/50">{{ humanSize(pf.file.size) }}</p>
          <!-- Progress bar -->
          <div v-if="pf.uploading" class="mt-1 h-0.5 w-full bg-white/10 rounded-full overflow-hidden">
            <div class="h-full bg-brand-400 rounded-full transition-all duration-200" :style="{ width: pf.progress + '%' }" />
          </div>
        </div>

        <!-- Remove button -->
        <button
          v-if="!pf.uploading"
          @click="removeFile(idx)"
          class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-dark-500 hover:bg-red-500 text-white flex items-center justify-center opacity-0 group-hover/file:opacity-100 transition-all shadow-md"
          title="Remove"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Main input container -->
    <div
      class="relative rounded-2xl border backdrop-blur-xl transition-all duration-200"
      :class="[
        isDragOver ? 'border-brand-400 bg-brand-500/5' : 'border-white/[0.08] bg-white/[0.04]',
        isFocused ? 'border-brand-500/60 ring-2 ring-brand-500/15' : '',
      ]"
      @dragover.prevent="isDragOver = true"
      @dragleave="isDragOver = false"
      @drop.prevent="handleDrop"
    >
      <!-- Drag overlay -->
      <div v-if="isDragOver" class="absolute inset-0 flex items-center justify-center rounded-xl bg-brand-500/10 z-10 pointer-events-none">
        <div class="text-center">
          <svg class="w-8 h-8 text-brand-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          <p class="text-brand-400 font-medium text-sm">Drop files to upload</p>
        </div>
      </div>

      <!-- Toolbar -->
      <div class="flex items-center gap-1 px-3 pt-2.5">
        <!-- Bold -->
        <button @click="insertFormatting('**')" type="button" class="toolbar-btn" title="Bold">
          <svg class="w-3.5 h-3.5 font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z" />
          </svg>
        </button>
        <!-- Italic -->
        <button @click="insertFormatting('_')" type="button" class="toolbar-btn" title="Italic">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <line x1="19" y1="4" x2="10" y2="4" stroke-linecap="round" stroke-width="2" />
            <line x1="14" y1="20" x2="5" y2="20" stroke-linecap="round" stroke-width="2" />
            <line x1="15" y1="4" x2="9" y2="20" stroke-linecap="round" stroke-width="2" />
          </svg>
        </button>
        <!-- Code -->
        <button @click="insertFormatting('`')" type="button" class="toolbar-btn" title="Code">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
          </svg>
        </button>
        <div class="w-px h-4 bg-white/10 mx-0.5" />
        <!-- Emoji picker toggle -->
        <div class="relative">
          <button @click.stop="toggleEmojiPicker" type="button" class="toolbar-btn text-amber-400/80 hover:text-amber-400 emoji-toggle" title="Emoji">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </button>
          <EmojiPicker
            v-if="emojiPickerOpen"
            class="absolute bottom-full left-0 mb-2 z-50"
            @select="insertEmoji"
          />
        </div>
        <!-- File upload -->
        <label class="toolbar-btn cursor-pointer" title="Attach file">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
          </svg>
          <input type="file" multiple class="hidden" ref="fileInput" @change="handleFileSelect" />
        </label>
        <!-- Voice message -->
        <VoiceRecorderButton @recorded="sendVoiceMessage" />
        <!-- GIF / sticker -->
        <div class="relative">
          <button @click.stop="toggleGifPicker" type="button" class="toolbar-btn text-dark-100 hover:text-white gif-toggle" title="GIF / sticker">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <rect x="3" y="5" width="18" height="14" rx="2" stroke-width="2" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10v4M11 10v4M11 12h1.5a1.5 1.5 0 000-3H11m6 0h-2v4h2m-2-2h1.5" />
            </svg>
          </button>
          <GifPicker
            v-if="gifPickerOpen"
            class="absolute bottom-full left-0 mb-2 z-50"
            :initial-query="gifPickerInitialQuery"
            @select-gif="handleGifSelected"
            @select-sticker="handleStickerSelected"
          />
        </div>
      </div>

      <!-- Textarea -->
      <div class="relative px-3 pb-2 pt-1">
        <textarea
          ref="textareaEl"
          v-model="body"
          :placeholder="`Message ${channel?.type === 'dm' ? '' : '#'}${channel?.name ?? '…'}`"
          rows="1"
          class="w-full bg-transparent text-white text-sm placeholder-dark-50/40 resize-none outline-none leading-relaxed max-h-48 overflow-y-auto"
          @keydown="handleKeydown"
          @input="handleInput"
          @focus="isFocused = true"
          @blur="isFocused = false"
        />

        <!-- @mention autocomplete dropdown -->
        <div
          v-if="mentionResults.length > 0"
          class="glass-elevated absolute bottom-full left-0 mb-2 w-64 overflow-hidden z-50"
        >
          <div class="p-1">
            <button
              v-for="(user, idx) in mentionResults"
              :key="user.id"
              @click="selectMention(user)"
              class="flex items-center gap-2.5 w-full px-3 py-2 rounded-lg hover:bg-white/[0.06] transition-colors"
              :class="mentionIndex === idx ? 'bg-brand-500/10' : ''"
            >
              <Avatar :src="user.avatar_url" :name="user.name" size="xs" />
              <div class="text-left min-w-0">
                <p class="text-sm font-medium text-white truncate">{{ user.name }}</p>
                <p class="text-xs text-dark-50/60 truncate">{{ user.title ?? user.email }}</p>
              </div>
            </button>
          </div>
        </div>

        <!-- "/" slash-command autocomplete dropdown -->
        <div
          v-if="slashResults.length > 0"
          class="glass-elevated absolute bottom-full left-0 mb-2 w-72 overflow-hidden z-50"
        >
          <div class="p-1">
            <button
              v-for="(cmd, idx) in slashResults"
              :key="cmd.command"
              @click="selectSlashCommand(cmd)"
              class="flex flex-col items-start w-full px-3 py-2 rounded-lg hover:bg-white/[0.06] transition-colors"
              :class="slashIndex === idx ? 'bg-brand-500/10' : ''"
            >
              <span class="text-sm font-medium text-white">{{ cmd.usage }}</span>
              <span class="text-xs text-dark-50/60">{{ cmd.description }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom bar: char count + send button -->
      <div class="flex items-center justify-between px-3 pb-2.5">
        <span class="text-xs text-dark-50/50">
          {{ body.length > 0 ? `${body.length}/4000` : 'Enter to send · Shift+Enter for new line' }}
        </span>
        <div class="flex items-center gap-1.5">
          <!-- Schedule send -->
          <div class="relative">
            <button
              @click.stop="toggleSchedulePicker"
              :disabled="!canSend"
              type="button"
              class="toolbar-btn schedule-toggle"
              :class="canSend ? '' : 'opacity-30 cursor-not-allowed'"
              title="Schedule send"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
            <ScheduleSendPopover
              v-if="schedulePickerOpen"
              class="absolute bottom-full right-0 mb-2 z-50"
              @close="schedulePickerOpen = false"
              @schedule="scheduleMessage"
            />
          </div>

          <button
            @click="sendMessage"
            :disabled="!canSend"
            type="button"
            class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200"
            :class="
              canSend
                ? 'bg-gradient-to-b from-brand-400 to-brand-600 text-white shadow-md shadow-brand-500/30 hover:shadow-glow-brand'
                : 'bg-white/[0.06] text-dark-50/40 cursor-not-allowed'
            "
          >
            <svg v-if="isUploading" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
            {{ isUploading ? 'Uploading…' : 'Send' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Avatar from '../Common/Avatar.vue';
import VoiceRecorderButton from './VoiceRecorderButton.vue';
import EmojiPicker from './EmojiPicker.vue';
import GifPicker from './GifPicker.vue';
import ScheduleSendPopover from './ScheduleSendPopover.vue';
import { extensionForMimeType } from '../../Composables/useAudioRecorder';
import { matchSlashCommands, isKnownSlashCommand } from '../../Utils/slashCommands';
import { filterMentionCandidates } from '../../Utils/mentions';
import { useAuthStore } from '../../Stores/useAuthStore';
import { useMessageStore } from '../../Stores/useMessageStore';
import { useUIStore } from '../../Stores/useUIStore';
import { useScheduledMessageStore } from '../../Stores/useScheduledMessageStore';

const props = defineProps({
  channel: { type: Object, required: true },
  isDm: { type: Boolean, default: false },
  sendUrl: { type: String, default: null },
  typingUrl: { type: String, default: null },
  scheduleUrl: { type: String, default: null },
});

const emit = defineEmits(['message-sent']);

const page = usePage();
const authStore = useAuthStore();
const messageStore = useMessageStore();
const uiStore = useUIStore();
const scheduledMessageStore = useScheduledMessageStore();

const body = ref('');
const textareaEl = ref(null);
const fileInput = ref(null);
const pendingFiles = ref([]);
const isDragOver = ref(false);
const isFocused = ref(false);
const emojiPickerOpen = ref(false);
const gifPickerOpen = ref(false);
const gifPickerInitialQuery = ref('');
const schedulePickerOpen = ref(false);
const slashResults = ref([]);
const slashIndex = ref(0);
const mentionResults = ref([]);
const mentionIndex = ref(0);
const mentionQuery = ref('');

const isUploading = ref(false);

const canSend = computed(
  () => !isUploading.value
    && (body.value.trim().length > 0 || pendingFiles.value.length > 0),
);

const MAX_FILES     = 10;
const MAX_FILE_SIZE = 50 * 1024 * 1024; // matches backend UploadFileRequest

// Auto-resize textarea
watch(body, () => {
  nextTick(() => {
    if (!textareaEl.value) return;
    textareaEl.value.style.height = 'auto';
    textareaEl.value.style.height = `${Math.min(textareaEl.value.scrollHeight, 192)}px`;
  });
});

// Focus the composer when a reply target is picked
watch(() => messageStore.replyTo, (v) => {
  if (v) nextTick(() => textareaEl.value?.focus());
});

// Debounced typing indicator. DMs pass an explicit typingUrl — the channel
// path would 404 on a `dm_<id>` store key.
const sendTypingIndicator = useDebounceFn(() => {
  const url = props.typingUrl ?? `/channels/${props.channel.id}/typing`;
  axios.post(url).catch(() => {});
}, 500);

function handleKeydown(e) {
  // Mention navigation
  if (mentionResults.value.length > 0) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      mentionIndex.value = Math.min(mentionIndex.value + 1, mentionResults.value.length - 1);
      return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      mentionIndex.value = Math.max(mentionIndex.value - 1, 0);
      return;
    }
    if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault();
      selectMention(mentionResults.value[mentionIndex.value]);
      return;
    }
    if (e.key === 'Escape') {
      mentionResults.value = [];
      return;
    }
  }

  // Slash-command navigation
  if (slashResults.value.length > 0) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      slashIndex.value = Math.min(slashIndex.value + 1, slashResults.value.length - 1);
      return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      slashIndex.value = Math.max(slashIndex.value - 1, 0);
      return;
    }
    if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault();
      selectSlashCommand(slashResults.value[slashIndex.value]);
      return;
    }
    if (e.key === 'Escape') {
      slashResults.value = [];
      return;
    }
  }

  // Send on Enter (not Shift+Enter)
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

async function handleInput() {
  sendTypingIndicator();

  const val = body.value;
  const cursor = textareaEl.value?.selectionStart ?? 0;
  const textBefore = val.slice(0, cursor);
  const mentionMatch = textBefore.match(/@(\w*)$/);
  // Only while the whole body is still just "/command" being typed — not a
  // "/" that shows up later in a longer message.
  const slashMatch = val.match(/^\/(\w*)$/);

  if (mentionMatch) {
    mentionQuery.value = mentionMatch[1];
    slashResults.value = [];
    searchMentions(mentionQuery.value);
  } else if (slashMatch) {
    mentionResults.value = [];
    slashResults.value = matchSlashCommands(slashMatch[1]);
    slashIndex.value = 0;
  } else {
    mentionResults.value = [];
    slashResults.value = [];
  }
}

function selectSlashCommand(cmd) {
  if (!cmd) return;
  body.value = `/${cmd.command} `;
  slashResults.value = [];
  nextTick(() => textareaEl.value?.focus());
}

// Filters the member list Inertia shares on every page — the same source
// NewDmModal / ForwardMessageModal use — instead of a search endpoint.
function searchMentions(query) {
  mentionResults.value = filterMentionCandidates(page.props.workspaceMembers, query, 6);
  mentionIndex.value = 0;
}

function selectMention(user) {
  if (!user) return;
  const val = body.value;
  const cursor = textareaEl.value?.selectionStart ?? 0;
  const before = val.slice(0, cursor).replace(/@\w*$/, `@${user.name} `);
  const after = val.slice(cursor);
  body.value = before + after;
  mentionResults.value = [];
  nextTick(() => textareaEl.value?.focus());
}

async function sendMessage() {
  if (!canSend.value) return;

  const content = body.value.trim();
  const hasFiles = pendingFiles.value.length > 0;

  // /giphy is a pure client-side action (open the picker) — never sent to
  // the server as a message.
  const giphyMatch = !hasFiles && content.match(/^\/giphy(?:\s+(.*))?$/i);
  if (giphyMatch) {
    body.value = '';
    slashResults.value = [];
    gifPickerInitialQuery.value = (giphyMatch[1] || '').trim();
    gifPickerOpen.value = true;
    return;
  }

  // Other recognized slash commands (/invite, /mute, /remind) are executed
  // server-side and never become a message — skip the optimistic bubble for
  // them so there's no flash of a "/mute" message that then disappears.
  const isSlashCommand = !hasFiles && isKnownSlashCommand(content);

  // ── Step 1: upload pending files first, collecting their ids ─────────
  let fileIds = [];
  if (hasFiles) {
    isUploading.value = true;
    try {
      for (const pf of pendingFiles.value) {
        pf.uploading = true;
        const fd = new FormData();
        fd.append('file', pf.file);

        const { data } = await axios.post('/files', fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: (e) => {
            pf.progress = Math.round((e.loaded * 100) / (e.total || 1));
          },
        });
        fileIds.push(data.file.id);
        pf.uploading = false;
      }
    } catch (err) {
      pendingFiles.value.forEach(pf => { pf.uploading = false; pf.progress = 0; });
      isUploading.value = false;
      const msg = err.response?.data?.message
        ?? err.response?.data?.errors?.file?.[0]
        ?? 'File upload failed. Please try again.';
      uiStore.toastError(msg);
      return; // keep body + files so the user can retry
    }
    isUploading.value = false;
  }

  // ── Step 2: send the message referencing the uploaded file ids ───────
  body.value = '';
  slashResults.value = [];
  mentionResults.value = [];

  const replyTarget = messageStore.replyTo;
  messageStore.clearReplyTo();

  let optimistic = null;
  if (!isSlashCommand) {
    optimistic = {
      id: `temp-${Date.now()}`,
      user_id: authStore.user?.id,
      user: authStore.user,
      body: content,
      parent: replyTarget
        ? { id: replyTarget.id, body: replyTarget.body, user: replyTarget.user }
        : null,
      created_at: new Date().toISOString(),
      reactions: [],
      files: [],
      is_optimistic: true,
    };
    messageStore.appendMessage(props.channel.id, optimistic);
    emit('message-sent');
  }

  const sentFiles = pendingFiles.value;
  pendingFiles.value = [];

  try {
    const { data } = await axios.post(
      props.sendUrl ?? `/channels/${props.channel.id}/messages`,
      { body: content, files: fileIds, parent_id: replyTarget?.id ?? null },
    );

    if (data.handled) {
      // A slash command ran server-side — surface its result, not a message.
      if (data.is_error) {
        uiStore.toastError(data.message);
        body.value = content; // let the user fix and resend
      } else {
        uiStore.toastSuccess(data.message);
      }
      return;
    }

    // Discard the optimistic placeholder, then show the real message.
    // Echo will also deliver it; appendMessage deduplicates by id.
    if (optimistic) {
      messageStore.discardOptimistic(optimistic.id);
      if (data.message) {
        messageStore.appendMessage(props.channel.id, data.message);
      }
    }
    sentFiles.forEach(pf => pf.previewUrl && URL.revokeObjectURL(pf.previewUrl));
  } catch (err) {
    if (optimistic) {
      messageStore.discardOptimistic(optimistic.id);
    }
    body.value = content;
    pendingFiles.value = sentFiles; // restore so the user can retry
    if (replyTarget) messageStore.setReplyTo(replyTarget);
    uiStore.toastError('Failed to send message. Please try again.');
  }
}

async function scheduleMessage(scheduledForIso) {
  if (!canSend.value) return;

  const content = body.value.trim();
  const hasFiles = pendingFiles.value.length > 0;

  let fileIds = [];
  if (hasFiles) {
    isUploading.value = true;
    try {
      for (const pf of pendingFiles.value) {
        const fd = new FormData();
        fd.append('file', pf.file);
        const { data } = await axios.post('/files', fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        fileIds.push(data.file.id);
      }
    } catch (err) {
      isUploading.value = false;
      uiStore.toastError('File upload failed. Please try again.');
      return;
    }
    isUploading.value = false;
  }

  try {
    const url = props.scheduleUrl ?? `/channels/${props.channel.id}/messages/schedule`;
    const { data } = await axios.post(url, {
      body: content,
      files: fileIds,
      scheduled_for: scheduledForIso,
    });

    if (data.scheduled_message) {
      scheduledMessageStore.addPending(data.scheduled_message);
    }

    body.value = '';
    pendingFiles.value.forEach(pf => pf.previewUrl && URL.revokeObjectURL(pf.previewUrl));
    pendingFiles.value = [];
    schedulePickerOpen.value = false;
    uiStore.toastSuccess('Message scheduled.');
  } catch (err) {
    const msg = err.response?.data?.errors?.scheduled_for?.[0]
      ?? err.response?.data?.errors?.body?.[0]
      ?? 'Failed to schedule message. Please try again.';
    uiStore.toastError(msg);
  }
}

async function sendVoiceMessage({ blob, durationSec, mimeType }) {
  if (isUploading.value) return;

  const replyTarget = messageStore.replyTo;
  messageStore.clearReplyTo();

  const objectUrl = URL.createObjectURL(blob);
  const optimistic = {
    id: `temp-${Date.now()}`,
    user_id: authStore.user?.id,
    user: authStore.user,
    body: '',
    type: 'voice',
    parent: replyTarget
      ? { id: replyTarget.id, body: replyTarget.body, user: replyTarget.user }
      : null,
    created_at: new Date().toISOString(),
    reactions: [],
    files: [{ id: 'temp', type: 'audio', view_url: objectUrl }],
    metadata: { voice_duration_seconds: durationSec },
    is_optimistic: true,
  };
  messageStore.appendMessage(props.channel.id, optimistic);
  emit('message-sent');

  isUploading.value = true;
  try {
    const ext = extensionForMimeType(mimeType);
    const fd = new FormData();
    fd.append('file', blob, `voice-note-${Date.now()}.${ext}`);

    const { data: fileData } = await axios.post('/files', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    const { data } = await axios.post(
      props.sendUrl ?? `/channels/${props.channel.id}/messages`,
      {
        body: '',
        type: 'voice',
        files: [fileData.file.id],
        voice_duration_seconds: durationSec,
        parent_id: replyTarget?.id ?? null,
      },
    );

    messageStore.discardOptimistic(optimistic.id);
    if (data.message) {
      messageStore.appendMessage(props.channel.id, data.message);
    }
  } catch (err) {
    messageStore.discardOptimistic(optimistic.id);
    if (replyTarget) messageStore.setReplyTo(replyTarget);
    uiStore.toastError('Failed to send voice message. Please try again.');
  } finally {
    isUploading.value = false;
    URL.revokeObjectURL(objectUrl);
  }
}

function handleGifSelected(gif) {
  gifPickerOpen.value = false;
  sendGifMessage({
    gif_url: gif.url,
    gif_preview_url: gif.preview_url,
    gif_provider: gif.provider,
    gif_width: gif.width,
    gif_height: gif.height,
  });
}

function handleStickerSelected(sticker) {
  gifPickerOpen.value = false;
  sendGifMessage({
    gif_url: sticker.image_url,
    gif_preview_url: sticker.image_url,
    gif_provider: 'workspace',
    gif_width: null,
    gif_height: null,
  });
}

async function sendGifMessage(gifData) {
  const replyTarget = messageStore.replyTo;
  messageStore.clearReplyTo();

  const optimistic = {
    id: `temp-${Date.now()}`,
    user_id: authStore.user?.id,
    user: authStore.user,
    body: '',
    type: 'gif',
    parent: replyTarget
      ? { id: replyTarget.id, body: replyTarget.body, user: replyTarget.user }
      : null,
    created_at: new Date().toISOString(),
    reactions: [],
    files: [],
    metadata: {
      gif_url: gifData.gif_url,
      gif_preview_url: gifData.gif_preview_url,
      gif_width: gifData.gif_width,
      gif_height: gifData.gif_height,
    },
    is_optimistic: true,
  };
  messageStore.appendMessage(props.channel.id, optimistic);
  emit('message-sent');

  try {
    const { data } = await axios.post(
      props.sendUrl ?? `/channels/${props.channel.id}/messages`,
      { body: '', type: 'gif', parent_id: replyTarget?.id ?? null, ...gifData },
    );
    messageStore.discardOptimistic(optimistic.id);
    if (data.message) {
      messageStore.appendMessage(props.channel.id, data.message);
    }
  } catch (err) {
    messageStore.discardOptimistic(optimistic.id);
    if (replyTarget) messageStore.setReplyTo(replyTarget);
    uiStore.toastError('Failed to send GIF. Please try again.');
  }
}

function humanSize(bytes) {
  if (!bytes) return '0 B';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function fileEmoji(file) {
  const mime = file.type ?? '';
  const ext  = file.name?.split('.').pop()?.toLowerCase() ?? '';
  if (mime === 'application/pdf' || ext === 'pdf')             return '📕';
  if (mime.startsWith('video/'))                                return '🎬';
  if (mime.startsWith('audio/'))                                return '🎵';
  if (['zip', 'rar', '7z'].includes(ext))                       return '🗜️';
  if (['doc', 'docx'].includes(ext))                            return '📘';
  if (['xls', 'xlsx', 'csv'].includes(ext))                     return '📗';
  if (['ppt', 'pptx'].includes(ext))                            return '📙';
  return '📄';
}

function insertFormatting(marker) {
  const el = textareaEl.value;
  if (!el) return;
  const start = el.selectionStart;
  const end = el.selectionEnd;
  const selected = body.value.slice(start, end);
  const before = body.value.slice(0, start);
  const after = body.value.slice(end);
  body.value = `${before}${marker}${selected || 'text'}${marker}${after}`;
  nextTick(() => {
    el.focus();
    const newPos = start + marker.length + (selected.length || 4);
    el.setSelectionRange(newPos, newPos);
  });
}

function toggleEmojiPicker() {
  emojiPickerOpen.value = !emojiPickerOpen.value;
}

function insertEmoji(value) {
  const el = textareaEl.value;
  const start = el?.selectionStart ?? body.value.length;
  const end = el?.selectionEnd ?? body.value.length;
  body.value = body.value.slice(0, start) + value + body.value.slice(end);
  emojiPickerOpen.value = false;
  nextTick(() => {
    el?.focus();
    const newPos = start + value.length;
    el?.setSelectionRange(newPos, newPos);
  });
}

function onDocClick(e) {
  if (emojiPickerOpen.value && !e.target.closest('.emoji-pop') && !e.target.closest('.emoji-toggle')) {
    emojiPickerOpen.value = false;
  }
  if (gifPickerOpen.value && !e.target.closest('.gif-pop') && !e.target.closest('.gif-toggle')) {
    gifPickerOpen.value = false;
  }
  if (schedulePickerOpen.value && !e.target.closest('.schedule-pop') && !e.target.closest('.schedule-toggle')) {
    schedulePickerOpen.value = false;
  }
}

function toggleGifPicker() {
  gifPickerOpen.value = !gifPickerOpen.value;
}

function toggleSchedulePicker() {
  if (!canSend.value) return;
  schedulePickerOpen.value = !schedulePickerOpen.value;
}

onMounted(() => {
  document.addEventListener('click', onDocClick, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick, true);
});

function addFiles(files) {
  for (const file of files) {
    if (pendingFiles.value.length >= MAX_FILES) {
      uiStore.toastError(`You can attach at most ${MAX_FILES} files per message.`);
      break;
    }
    if (file.size > MAX_FILE_SIZE) {
      uiStore.toastError(`"${file.name}" is larger than 50 MB.`);
      continue;
    }
    pendingFiles.value.push({
      key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
      file,
      previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
      uploading: false,
      progress: 0,
    });
  }
}

function handleFileSelect(e) {
  addFiles(Array.from(e.target.files ?? []));
  if (fileInput.value) fileInput.value.value = '';
}

function handleDrop(e) {
  isDragOver.value = false;
  addFiles(Array.from(e.dataTransfer?.files ?? []));
}

function removeFile(idx) {
  const [removed] = pendingFiles.value.splice(idx, 1);
  if (removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl);
}
</script>

<style scoped>
.reply-slide-enter-active,
.reply-slide-leave-active { transition: all 0.15s ease; }
.reply-slide-enter-from,
.reply-slide-leave-to     { opacity: 0; transform: translateY(4px); }
</style>
