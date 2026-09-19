<template>
  <a
    :href="preview.url"
    target="_blank"
    rel="noopener noreferrer"
    class="mt-1.5 flex gap-3 rounded-xl border border-white/[0.07] bg-white/[0.03] hover:bg-white/[0.05] transition-all overflow-hidden max-w-sm"
  >
    <img
      v-if="preview.image_url"
      :src="preview.image_url"
      class="w-20 h-20 object-cover flex-shrink-0"
      loading="lazy"
      alt=""
    />
    <div class="min-w-0 py-2 pr-3" :class="preview.image_url ? '' : 'pl-3'">
      <p v-if="preview.site_name" class="text-[10px] uppercase tracking-wide text-dark-50/40 truncate">
        {{ preview.site_name }}
      </p>
      <p class="text-sm font-medium text-dark-50 truncate">{{ preview.title || preview.url }}</p>
      <p v-if="preview.description" class="text-xs text-dark-50/60 line-clamp-2 mt-0.5">
        {{ preview.description }}
      </p>
    </div>
  </a>
</template>

<script setup>
defineProps({
  preview: { type: Object, required: true },
});
</script>
