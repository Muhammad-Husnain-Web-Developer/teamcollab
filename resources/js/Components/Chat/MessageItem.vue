<template>
  <!-- ═══════════ DM bubble layout (own → right, other → left) ═══════════ -->
  <div
    v-if="isDm"
    ref="messageEl"
    class="relative flex items-end gap-2 px-4 py-1 group"
    :class="isOwnMessage ? 'justify-end' : 'justify-start'"
  >
    <!-- Other user's avatar — on every message -->
    <Avatar
      v-if="!isOwnMessage"
      :src="message.user?.avatar_url"
      :name="message.user?.display_name || message.user?.name"
      size="sm"
      class="flex-shrink-0 mb-0.5"
    />

    <!-- Bubble column -->
    <div class="relative flex flex-col max-w-[72%] min-w-0" :class="isOwnMessage ? 'items-end' : 'items-start'">

      <!-- Deleted -->
      <div v-if="message.deleted_at" class="px-3.5 py-2 rounded-2xl bg-white/[0.03] border border-white/[0.06]">
        <em class="text-dark-50/50 text-xs">This message was deleted.</em>
      </div>

      <template v-else>
        <!-- Text bubble -->
        <div
          v-if="message.body || message.parent || forwardedFrom"
          class="px-3.5 py-2 rounded-2xl text-sm leading-relaxed break-words"
          :class="isOwnMessage
            ? 'bg-gradient-to-b from-brand-400 to-brand-600 text-white rounded-br-md shadow-lg shadow-brand-500/20'
            : 'bg-white/[0.06] border border-white/[0.06] text-dark-50 rounded-bl-md'"
        >
          <!-- Forwarded-from quote -->
          <div
            v-if="forwardedFrom"
            class="mb-1.5 px-2.5 py-1.5 rounded-lg border-l-2 text-xs"
            :class="isOwnMessage ? 'bg-black/15 border-white/50' : 'bg-black/20 border-brand-400'"
          >
            <p class="font-semibold mb-0.5 flex items-center gap-1" :class="isOwnMessage ? 'text-white/90' : 'text-brand-400'">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3m10-8v2a4 4 0 01-4 4H3" />
              </svg>
              Forwarded from {{ forwardedFrom.user_name || 'Unknown' }}
            </p>
            <p class="truncate max-w-[220px]" :class="isOwnMessage ? 'text-white/70' : 'text-dark-50/70'">
              {{ forwardedFrom.body || '📎 Attachment' }}
            </p>
          </div>

          <!-- Quoted reply -->
          <div
            v-if="message.parent"
            class="mb-1.5 px-2.5 py-1.5 rounded-lg border-l-2 text-xs"
            :class="isOwnMessage ? 'bg-black/15 border-white/50' : 'bg-black/20 border-brand-400'"
          >
            <p class="font-semibold mb-0.5" :class="isOwnMessage ? 'text-white/90' : 'text-brand-400'">
              {{ message.parent.user?.display_name || message.parent.user?.name || 'Unknown' }}
            </p>
            <p class="truncate max-w-[220px]" :class="isOwnMessage ? 'text-white/70' : 'text-dark-50/70'">
              {{ message.parent.body || '📎 Attachment' }}
            </p>
          </div>

          <span class="message-body" v-html="renderedBody" />
          <span
            class="inline-block text-[9px] ml-2 whitespace-nowrap align-baseline select-none"
            :class="isOwnMessage ? 'text-white/60' : 'text-dark-50/50'"
          >
            {{ formatTime(message.created_at) }}<template v-if="message.is_edited"> · edited</template>
          </span>
        </div>

        <!-- Voice message -->
        <VoiceMessagePlayer
          v-if="voiceFile"
          class="mt-1"
          :src="voiceFile.view_url"
          :initial-duration="message.metadata?.voice_duration_seconds ?? 0"
          :is-own-message="isOwnMessage"
          :is-dm="true"
        />

        <!-- GIF / sticker -->
        <div v-if="gifUrl" class="mt-1" :class="isOwnMessage ? 'flex justify-end' : ''">
          <img :src="gifUrl" alt="" class="max-w-[220px] max-h-56 rounded-xl object-contain" loading="lazy" />
        </div>

        <!-- Image attachments -->
        <div v-if="imageFiles.length" class="mt-1 flex flex-wrap gap-2" :class="isOwnMessage ? 'justify-end' : ''">
          <button
            v-for="img in imageFiles"
            :key="img.id"
            class="relative rounded-xl overflow-hidden border border-dark-600/50 hover:border-dark-500 cursor-zoom-in group/img transition-all"
            @click="lightboxFile = img"
          >
            <img
              :src="img.view_url"
              :alt="img.original_name"
              class="max-w-[240px] max-h-56 object-cover block"
              loading="lazy"
            />
            <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/25 transition-all flex items-center justify-center">
              <svg class="w-6 h-6 text-white opacity-0 group-hover/img:opacity-100 transition-opacity drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m6 6v-4.8m0 4.8h-4.8M3 3l6 6M3 3v4.8M3 3h4.8"/>
              </svg>
            </div>
          </button>
        </div>

        <!-- Non-image file cards -->
        <div v-if="otherFiles.length" class="mt-1 space-y-1.5">
          <div
            v-for="file in otherFiles"
            :key="file.id"
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 max-w-[280px] border transition-all"
            :class="isOwnMessage
              ? 'bg-gradient-to-b from-brand-400 to-brand-600 border-white/10 shadow-lg shadow-brand-500/20'
              : 'bg-white/[0.05] border-white/[0.07] hover:bg-white/[0.07]'"
          >
            <div
              class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold uppercase"
              :class="isOwnMessage ? 'bg-white/20 text-white' : fileBadgeClass(file)"
            >
              {{ file.extension?.slice(0, 4) || '?' }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium truncate text-white">{{ file.original_name }}</p>
              <p class="text-xs" :class="isOwnMessage ? 'text-white/70' : 'text-dark-50/60'">{{ file.size_human }}</p>
            </div>
            <a
              :href="file.download_url"
              class="flex-shrink-0 p-2 rounded-lg transition-all"
              :class="isOwnMessage ? 'text-white/70 hover:text-white hover:bg-white/15' : 'text-dark-50/70 hover:text-white hover:bg-white/[0.08]'"
              title="Download"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
            </a>
          </div>
        </div>

        <!-- Time for attachment-only messages -->
        <span v-if="!message.body && (imageFiles.length || otherFiles.length || voiceFile || gifUrl)" class="text-[9px] text-dark-500 mt-0.5 px-1 select-none">
          {{ formatTime(message.created_at) }}
        </span>

        <!-- Link previews -->
        <LinkPreviewCard
          v-for="preview in linkPreviews"
          :key="preview.id"
          :preview="preview"
        />

        <!-- Reactions -->
        <MessageReactions
          v-if="message.reactions?.length"
          :reactions="message.reactions"
          :message-id="message.id"
          class="mt-1"
          @add-reaction="togglePicker"
        />
      </template>

      <!-- Hover actions — floats above the bubble, takes no layout space -->
      <div
        v-if="!message.deleted_at"
        class="glass-elevated absolute -top-9 z-20 opacity-0 group-hover:opacity-100 transition-all duration-150 flex items-center gap-1 px-1.5 py-1"
        :class="isOwnMessage ? 'right-0' : 'left-0'"
      >
        <button @click="quickReact('👍')" class="action-btn" title="Thumbs up">👍</button>
        <button @click="quickReact('❤️')" class="action-btn" title="Heart">❤️</button>
        <button @click.stop="togglePicker" class="action-btn text-dark-100 hover:text-white emoji-toggle" title="React">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>
        <button @click="replyToMessage" class="action-btn text-dark-100 hover:text-white" title="Reply">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
          </svg>
        </button>
        <button @click="forwardMessage" class="action-btn text-dark-100 hover:text-white" title="Forward">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3m10-8v2a4 4 0 01-4 4H3" />
          </svg>
        </button>
        <button v-if="isOwnMessage" @click="startEdit" class="action-btn text-dark-100 hover:text-white" title="Edit">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button v-if="isOwnMessage" @click="deleteMessage" class="action-btn text-dark-100 hover:text-red-400" title="Delete">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>

      <!-- Emoji picker popover -->
      <EmojiPicker
        v-if="pickerOpen"
        class="absolute -top-2 z-30 -translate-y-full"
        :class="isOwnMessage ? 'right-0' : 'left-0'"
        @select="reactWith"
      />
    </div>

    <!-- Own avatar (right side) -->
    <Avatar
      v-if="isOwnMessage"
      :src="message.user?.avatar_url"
      :name="message.user?.display_name || message.user?.name"
      size="sm"
      class="flex-shrink-0 mb-0.5"
    />
  </div>

  <!-- ═══════════ Channel layout (Slack-style, unchanged) ═══════════ -->
  <div
    v-else
    ref="messageEl"
    class="relative flex gap-3 px-4 py-0.5 hover:bg-white/[0.03] rounded-lg group transition-colors duration-100"
    :class="{ 'pt-2': showHeader, 'mt-0': !showHeader }"
  >
    <!-- Avatar / spacer -->
    <div class="flex-shrink-0 w-9">
      <Avatar
        v-if="showHeader"
        :src="message.user?.avatar_url"
        :name="message.user?.name"
        size="sm"
        class="mt-0.5"
      />
    </div>

    <!-- Message body -->
    <div class="flex-1 min-w-0">
      <!-- Header (name + timestamp) -->
      <div v-if="showHeader" class="flex items-baseline gap-2 mb-0.5">
        <span class="font-semibold text-white text-sm hover:underline cursor-pointer">
          {{ message.user?.name ?? 'Unknown' }}
        </span>
        <span class="text-dark-50/50 text-xs">{{ formatTime(message.created_at) }}</span>
        <span v-if="message.is_pinned" class="text-xs text-amber-400 flex items-center gap-0.5">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
            <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
          Pinned
        </span>
      </div>

      <!-- Inline timestamp when header collapsed -->
      <span
        v-if="!showHeader"
        class="absolute left-4 top-1/2 -translate-y-1/2 text-dark-50/40 text-[10px] opacity-0 group-hover:opacity-100 transition-opacity w-9 text-right pr-1.5"
      >
        {{ formatTimeShort(message.created_at) }}
      </span>

      <!-- Deleted message -->
      <div v-if="message.deleted_at" class="flex items-center gap-2">
        <svg class="w-3.5 h-3.5 text-dark-50/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        <em class="text-dark-50/50 text-sm">This message was deleted.</em>
      </div>

      <!-- Normal message content -->
      <template v-else>
        <!-- Forwarded-from quote -->
        <div
          v-if="forwardedFrom"
          class="mb-1 px-2.5 py-1.5 rounded-lg border-l-2 border-brand-400 bg-white/[0.04] text-xs max-w-md"
        >
          <p class="font-semibold text-brand-400 mb-0.5 flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3m10-8v2a4 4 0 01-4 4H3" />
            </svg>
            Forwarded from {{ forwardedFrom.user_name || 'Unknown' }}
          </p>
          <p class="truncate text-dark-50/70">{{ forwardedFrom.body || '📎 Attachment' }}</p>
        </div>

        <!-- Quoted reply -->
        <div
          v-if="message.parent"
          class="mb-1 px-2.5 py-1.5 rounded-lg border-l-2 border-brand-400 bg-white/[0.04] text-xs max-w-md"
        >
          <p class="font-semibold text-brand-400 mb-0.5">
            {{ message.parent.user?.display_name || message.parent.user?.name || 'Unknown' }}
          </p>
          <p class="truncate text-dark-50/70">{{ message.parent.body || '📎 Attachment' }}</p>
        </div>

        <!-- Text content (div, not p: Markdown may emit block-level tags) -->
        <div
          v-if="message.body"
          class="message-body text-dark-50 text-sm leading-relaxed break-words"
          v-html="renderedBody"
        />

        <!-- Edited badge -->
        <span v-if="message.is_edited" class="text-dark-50/40 text-[10px] ml-1">(edited)</span>

        <!-- Voice message -->
        <VoiceMessagePlayer
          v-if="voiceFile"
          class="mt-2"
          :src="voiceFile.view_url"
          :initial-duration="message.metadata?.voice_duration_seconds ?? 0"
          :is-own-message="isOwnMessage"
          :is-dm="false"
        />

        <!-- GIF / sticker -->
        <div v-if="gifUrl" class="mt-2">
          <img :src="gifUrl" alt="" class="max-w-[280px] max-h-64 rounded-xl object-contain" loading="lazy" />
        </div>

        <!-- Image attachments (grid + lightbox) -->
        <div v-if="imageFiles.length" class="mt-2 flex flex-wrap gap-2">
          <button
            v-for="img in imageFiles"
            :key="img.id"
            class="relative rounded-xl overflow-hidden border border-dark-600/50 hover:border-dark-500 cursor-zoom-in group/img transition-all"
            @click="lightboxFile = img"
          >
            <img
              :src="img.view_url"
              :alt="img.original_name"
              class="max-w-[280px] max-h-64 object-cover block"
              loading="lazy"
            />
            <!-- Hover overlay with expand hint -->
            <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/25 transition-all flex items-center justify-center">
              <svg class="w-6 h-6 text-white opacity-0 group-hover/img:opacity-100 transition-opacity drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m6 6v-4.8m0 4.8h-4.8M3 3l6 6M3 3v4.8M3 3h4.8"/>
              </svg>
            </div>
          </button>
        </div>

        <!-- Non-image file cards -->
        <div v-if="otherFiles.length" class="mt-2 space-y-1.5">
          <div
            v-for="file in otherFiles"
            :key="file.id"
            class="group/file flex items-center gap-3 bg-white/[0.04] hover:bg-white/[0.07] border border-white/[0.07] hover:border-white/[0.12] rounded-xl px-3 py-2.5 max-w-sm transition-all"
          >
            <!-- Type badge -->
            <div
              class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold uppercase"
              :class="fileBadgeClass(file)"
            >
              {{ file.extension?.slice(0, 4) || '?' }}
            </div>

            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-white truncate">{{ file.original_name }}</p>
              <p class="text-xs text-dark-50/60">{{ file.size_human }}</p>
            </div>

            <a
              :href="file.download_url"
              class="flex-shrink-0 p-2 rounded-lg text-dark-50/70 hover:text-white hover:bg-white/[0.08] transition-all"
              title="Download"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
            </a>
          </div>
        </div>

        <!-- Link previews -->
        <LinkPreviewCard
          v-for="preview in linkPreviews"
          :key="preview.id"
          :preview="preview"
        />

        <!-- Reactions -->
        <MessageReactions
          v-if="message.reactions?.length"
          :reactions="message.reactions"
          :message-id="message.id"
          class="mt-1.5"
          @add-reaction="togglePicker"
        />

        <!-- Thread reply count -->
        <button
          v-if="replyCount > 0"
          class="mt-1 flex items-center gap-1.5 text-brand-400 hover:text-brand-300 text-xs font-medium transition-colors group/thread"
          @click="threadStore.open(message)"
        >
          <div class="flex -space-x-1">
            <Avatar
              v-for="u in message.thread_users?.slice(0, 3)"
              :key="u.id"
              :src="u.avatar_url"
              :name="u.name"
              size="xs"
              class="ring-1 ring-dark-800"
            />
          </div>
          {{ replyCount }} {{ replyCount === 1 ? 'reply' : 'replies' }}
          <svg class="w-3.5 h-3.5 opacity-0 group-hover/thread:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </button>
      </template>
    </div>

    <!-- Hover action bar -->
    <div
      v-if="!message.deleted_at"
      class="glass-elevated absolute right-4 top-1 opacity-0 group-hover:opacity-100 transition-all duration-150 flex items-center gap-1 px-1.5 py-1 z-10"
    >
      <!-- Quick react -->
      <button @click="quickReact('👍')" class="action-btn" title="Thumbs up">👍</button>
      <button @click="quickReact('❤️')" class="action-btn" title="Heart">❤️</button>
      <button @click.stop="togglePicker" class="action-btn text-dark-100 hover:text-white emoji-toggle" title="React">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </button>
      <!-- Reply -->
      <button @click="replyToMessage" class="action-btn text-dark-100 hover:text-white" title="Reply">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
        </svg>
      </button>
      <!-- Reply in thread -->
      <button @click="threadStore.open(message)" class="action-btn text-dark-100 hover:text-white" title="Reply in thread">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
      </button>
      <!-- Forward -->
      <button @click="forwardMessage" class="action-btn text-dark-100 hover:text-white" title="Forward">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3m10-8v2a4 4 0 01-4 4H3" />
        </svg>
      </button>
      <!-- Edit (own messages) -->
      <button v-if="isOwnMessage" @click="startEdit" class="action-btn text-dark-100 hover:text-white" title="Edit">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
      </button>
      <!-- Delete (own messages) -->
      <button v-if="isOwnMessage" @click="deleteMessage" class="action-btn text-dark-100 hover:text-red-400" title="Delete">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
      </button>
      <!-- Pin -->
      <button
        @click="togglePin"
        class="action-btn"
        :class="message.is_pinned ? 'text-amber-400' : 'text-dark-100 hover:text-amber-400'"
        :title="message.is_pinned ? 'Unpin message' : 'Pin message'"
      >
        <svg class="w-3.5 h-3.5" :fill="message.is_pinned ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
        </svg>
      </button>

      <!-- Emoji picker popover -->
      <EmojiPicker
        v-if="pickerOpen"
        class="absolute top-full right-0 mt-1 z-30"
        @select="reactWith"
      />
    </div>

  </div>

  <!-- Image lightbox (shared by both layouts) -->
  <ImageLightbox :file="lightboxFile" @close="lightboxFile = null" />
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { gsap } from 'gsap';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import Avatar from '../Common/Avatar.vue';
import ImageLightbox from '../Common/ImageLightbox.vue';
import MessageReactions from './MessageReactions.vue';
import LinkPreviewCard from './LinkPreviewCard.vue';
import VoiceMessagePlayer from './VoiceMessagePlayer.vue';
import EmojiPicker from './EmojiPicker.vue';
import { useAuthStore } from '../../Stores/useAuthStore';
import { useMessageStore } from '../../Stores/useMessageStore';
import { useUIStore } from '../../Stores/useUIStore';
import { useThreadStore } from '../../Stores/useThreadStore';
import { useEmojiStore } from '../../Stores/useEmojiStore';
import { renderMessageBody } from '../../Utils/messageFormat';

const props = defineProps({
  message: { type: Object, required: true },
  showHeader: { type: Boolean, default: true },
  isDm: { type: Boolean, default: false },
});

const authStore = useAuthStore();
const messageStore = useMessageStore();
const uiStore = useUIStore();
const threadStore = useThreadStore();
const emojiStore = useEmojiStore();

const messageEl = ref(null);

const isOwnMessage = computed(
  () => props.message.user_id === authStore.user?.id,
);

// Render body — Markdown subset + @mention highlighting + :shortcode: custom
// emoji, sanitised in the util.
const renderedBody = computed(() => {
  const mentionClass = props.isDm && isOwnMessage.value
    ? 'text-white font-semibold underline'
    : 'text-brand-400 font-medium cursor-pointer hover:underline';

  return renderMessageBody(props.message.body, { mentionClass, customEmoji: emojiStore.byShortcode });
});

const forwardedFrom = computed(() => props.message.metadata?.forwarded_from ?? null);
const linkPreviews = computed(() => props.message.metadata?.link_previews ?? []);

// File attachments — backend loads the `files` relation with view/download urls
const lightboxFile = ref(null);

const imageFiles = computed(() =>
  (props.message.files ?? []).filter(f => f.type === 'image'),
);

// A voice message's audio file gets the dedicated player, not the generic
// file-card treatment.
const voiceFile = computed(() =>
  props.message.type === 'voice' ? (props.message.files ?? [])[0] ?? null : null,
);

const gifUrl = computed(() =>
  props.message.type === 'gif' ? (props.message.metadata?.gif_url ?? null) : null,
);

const otherFiles = computed(() =>
  (props.message.files ?? []).filter(f => f.type !== 'image' && f.id !== voiceFile.value?.id),
);

function fileBadgeClass(file) {
  switch (file.type) {
    case 'video':    return 'bg-purple-500/15 text-purple-400';
    case 'audio':    return 'bg-pink-500/15 text-pink-400';
    case 'archive':  return 'bg-amber-500/15 text-amber-400';
    case 'document':
      if (file.extension === 'pdf')                          return 'bg-red-500/15 text-red-400';
      if (['xls', 'xlsx', 'csv'].includes(file.extension))   return 'bg-green-500/15 text-green-400';
      return 'bg-blue-500/15 text-blue-400';
    default:         return 'bg-dark-600/60 text-dark-300';
  }
}

function formatTime(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function formatTimeShort(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

async function quickReact(emoji) {
  try {
    const { data } = await axios.post(`/messages/${props.message.id}/react`, { emoji });
    messageStore.setReactions(props.message.id, data.reactions ?? []);
  } catch (e) {
    uiStore.toastError('Failed to react.');
  }
}

// ── Emoji picker popover ──────────────────────────────────────────────
const pickerOpen = ref(false);

function togglePicker() {
  pickerOpen.value = !pickerOpen.value;
}

async function reactWith(emoji) {
  pickerOpen.value = false;
  await quickReact(emoji);
}

// Close picker when clicking anywhere outside it
function onDocClick(e) {
  if (!pickerOpen.value) return;
  if (e.target.closest('.emoji-pop') || e.target.closest('.emoji-toggle')) return;
  pickerOpen.value = false;
}

// ── Reply ─────────────────────────────────────────────────────────────
function replyToMessage() {
  messageStore.setReplyTo(props.message);
}

// The API exposes the counter as replies_count; thread_count was never sent,
// which is why this affordance used to stay hidden.
const replyCount = computed(() => props.message.replies_count ?? 0);

function startEdit() {
  uiStore.openModal('editMessage', { message: props.message });
}

function forwardMessage() {
  uiStore.openModal('forwardMessage', { message: props.message });
}

async function togglePin() {
  try {
    const action = props.message.is_pinned ? 'unpin' : 'pin';
    const { data } = await axios.post(`/messages/${props.message.id}/${action}`);
    messageStore.updateMessage(data.message ?? { id: props.message.id, is_pinned: !props.message.is_pinned });
  } catch (e) {
    uiStore.toastError('Failed to update pinned message.');
  }
}

async function deleteMessage() {
  if (!confirm('Delete this message?')) return;
  try {
    await axios.delete(`/messages/${props.message.id}`);
    messageStore.removeMessage(props.message.id);
  } catch (e) {
    uiStore.toastError('Failed to delete message.');
  }
}

// Entrance animation for new messages
onMounted(() => {
  document.addEventListener('click', onDocClick, true);
  if (messageEl.value) {
    gsap.fromTo(
      messageEl.value,
      { opacity: 0, y: 8 },
      { opacity: 1, y: 0, duration: 0.25, ease: 'power2.out' },
    );
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick, true);
});
</script>

<style scoped>
.action-btn {
  @apply p-1 rounded text-sm hover:bg-white/[0.08] transition-colors leading-none;
}

/* Markdown output is injected with v-html, so it sits outside Vue's scoped
   attribute rewriting — :deep() is required for these rules to apply. */
.message-body :deep(p)          { @apply my-0; }
.message-body :deep(p + p)      { @apply mt-2; }
.message-body :deep(strong)     { @apply font-semibold; }
.message-body :deep(em)         { @apply italic; }
.message-body :deep(del),
.message-body :deep(s)          { @apply line-through opacity-70; }
.message-body :deep(a)          { @apply text-brand-400 underline underline-offset-2 hover:text-brand-300 break-all; }

.message-body :deep(img.custom-emoji) {
  @apply inline-block w-[1.3em] h-[1.3em] align-text-bottom;
}

.message-body :deep(code) {
  @apply bg-dark-900/70 text-brand-300 rounded px-1 py-0.5 text-[0.85em] font-mono;
}

.message-body :deep(pre) {
  @apply bg-dark-900/80 border border-dark-600/60 rounded-lg p-3 my-2 overflow-x-auto;
}

/* Inside a fenced block the <code> should not repeat the inline chip styling */
.message-body :deep(pre code) {
  @apply bg-transparent text-dark-100 p-0 text-xs leading-relaxed;
}

.message-body :deep(blockquote) {
  @apply border-l-2 border-dark-500 pl-3 my-2 text-dark-300 italic;
}

.message-body :deep(ul) { @apply list-disc pl-5 my-1 space-y-0.5; }
.message-body :deep(ol) { @apply list-decimal pl-5 my-1 space-y-0.5; }
.message-body :deep(hr) { @apply border-dark-600 my-3; }
</style>
