<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show" class="fixed inset-0 z-[200] flex items-center justify-center p-4">

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-dark-900/70 backdrop-blur-md" @click="$emit('close')" />

        <!-- Card — fixed 560 × 580, so layout never collapses -->
        <Transition name="pop">
          <div
            v-if="show"
            class="glass-elevated relative z-10 flex flex-col overflow-hidden"
            style="width: 560px; height: 580px; max-width: calc(100vw - 32px); max-height: calc(100vh - 32px)"
          >

            <!-- ── Header (54px) ───────────────────────────────────── -->
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-white/[0.07] bg-gradient-to-b from-white/[0.03] to-transparent" style="flex-shrink:0">
              <div>
                <h2 class="text-sm font-bold text-white">Update Profile Photo</h2>
                <p class="text-xs text-dark-50/60 mt-0.5">Drag image to reposition · Scroll to zoom</p>
              </div>
              <button
                @click="$emit('close')"
                class="icon-btn"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- ── Cropper / Drop zone (fills remaining space) ─────── -->
            <div class="relative bg-[#0c0c0e]" style="flex:1 1 0; min-height:0">

              <!-- Drop zone -->
              <div
                v-if="!imageSrc"
                class="absolute inset-0 flex flex-col items-center justify-center gap-5 cursor-pointer select-none transition-colors duration-200"
                :class="isDragOver ? 'bg-brand-500/8' : ''"
                @dragover.prevent="isDragOver = true"
                @dragleave="isDragOver = false"
                @drop.prevent="handleDrop"
                @click="triggerFileInput"
              >
                <div class="relative">
                  <div
                    class="w-20 h-20 rounded-full flex items-center justify-center border-2 border-dashed transition-all duration-300"
                    :class="isDragOver ? 'border-brand-400 bg-brand-500/10 scale-110' : 'border-white/[0.14] bg-white/[0.04]'"
                  >
                    <svg
                      class="w-8 h-8 transition-colors"
                      :class="isDragOver ? 'text-brand-400' : 'text-dark-50/60'"
                      fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div v-if="isDragOver" class="absolute inset-0 rounded-full border-2 border-brand-400 animate-ping opacity-30" />
                </div>

                <div class="text-center">
                  <p class="text-white font-semibold">Drop your photo here</p>
                  <p class="text-dark-50/60 text-sm mt-1">or <span class="text-brand-400 underline">browse files</span></p>
                  <p class="text-dark-50/40 text-xs mt-3">JPG · PNG · GIF · WEBP · Max 5 MB</p>
                </div>
              </div>

              <!-- Active cropper -->
              <Cropper
                v-else
                ref="cropperRef"
                class="w-full h-full"
                :src="imageSrc"
                :stencil-component="CircleStencil"
                :stencil-props="{ aspectRatio: 1 }"
                :default-size="defaultStencilSize"
                :min-width="60"
                :min-height="60"
                background-class="cropper-bg"
                @change="onCropChange"
              />
            </div>

            <!-- ── Bottom panel (fixed 130px) ────────────────────────── -->
            <div style="flex-shrink:0" class="border-t border-white/[0.07]">

              <!-- Toolbar (only when image loaded) -->
              <div v-if="imageSrc" class="flex items-center gap-2 px-4 py-2.5 border-b border-white/[0.06]">
                <!-- Zoom -->
                <span class="text-[10px] font-semibold text-dark-50/50 uppercase tracking-wider">Zoom</span>
                <button @click="zoomBy(-0.15)" class="tbtn">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                  </svg>
                </button>
                <input
                  v-model.number="zoom" type="range" min="0.1" max="3" step="0.05"
                  class="w-28 h-1 accent-brand-500 cursor-pointer"
                  @input="applyZoom"
                />
                <button @click="zoomBy(0.15)" class="tbtn">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                  </svg>
                </button>

                <div class="w-px h-4 bg-white/[0.08] mx-0.5" />

                <!-- Rotate -->
                <button @click="rotateBy(-90)" class="tbtn" title="Rotate left">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                  </svg>
                </button>
                <button @click="rotateBy(90)" class="tbtn" title="Rotate right">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/>
                  </svg>
                </button>
                <button @click="flipH" class="tbtn" title="Flip H">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 3v18M3 12h5m8 0h5M16 3v18"/>
                  </svg>
                </button>
                <button @click="flipV" class="tbtn" title="Flip V">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8h18M12 3v5m0 8v5M3 16h18"/>
                  </svg>
                </button>

                <div class="w-px h-4 bg-white/[0.08] mx-0.5" />

                <button @click="triggerFileInput" class="text-[11px] text-brand-400 hover:text-brand-300 transition-colors">Change Photo</button>
                <button @click="resetAll" class="text-[11px] text-dark-50/60 hover:text-white transition-colors">Reset</button>
              </div>

              <!-- Preview + Action row -->
              <div class="flex items-center gap-3 px-4 py-3">

                <!-- Live preview circles -->
                <div class="flex items-center gap-2 flex-shrink-0">
                  <div class="relative">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-white/[0.05] ring-1 ring-white/10">
                      <canvas ref="previewLg" width="40" height="40" class="w-full h-full" />
                    </div>
                  </div>
                  <div class="w-7 h-7 rounded-full overflow-hidden bg-white/[0.05] ring-1 ring-white/10">
                    <canvas ref="previewMd" width="28" height="28" class="w-full h-full" />
                  </div>
                  <div class="w-5 h-5 rounded-full overflow-hidden bg-white/[0.05] ring-1 ring-white/10">
                    <canvas ref="previewSm" width="20" height="20" class="w-full h-full" />
                  </div>
                  <span class="text-[10px] text-dark-50/50 ml-1">Preview</span>
                </div>

                <div class="flex-1" />

                <!-- Upload progress -->
                <div v-if="uploading" class="flex items-center gap-2">
                  <div class="w-20 h-1 bg-white/[0.08] rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-brand-400 to-brand-600 rounded-full transition-all duration-300" :style="{ width: uploadProgress + '%' }" />
                  </div>
                  <span class="text-[10px] text-dark-50/70 tabular-nums">{{ uploadProgress }}%</span>
                </div>

                <!-- Cancel -->
                <button
                  @click="$emit('close')"
                  class="btn-ghost !px-4 !py-2 !text-xs"
                >
                  Cancel
                </button>

                <!-- SAVE CROP button — always prominent -->
                <button
                  :disabled="!imageSrc || uploading"
                  @click="uploadCropped"
                  class="btn-primary"
                >
                  <svg v-if="uploading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                  <svg v-else-if="imageSrc" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                  </svg>
                  <span>{{ uploading ? 'Saving…' : (imageSrc ? 'Save Crop' : 'Select a photo') }}</span>
                </button>
              </div>
            </div>

          </div>
        </Transition>

        <!-- Hidden file input (outside the card so it's never clipped) -->
        <input
          ref="fileInputRef"
          type="file"
          accept="image/jpeg,image/png,image/gif,image/webp"
          class="hidden"
          @change="handleFileSelect"
        />
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';
import { Cropper, CircleStencil } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import axios from 'axios';

const props = defineProps({ show: Boolean });
const emit  = defineEmits(['close', 'uploaded']);

const cropperRef    = ref(null);
const fileInputRef  = ref(null);
const previewLg     = ref(null);
const previewMd     = ref(null);
const previewSm     = ref(null);

const imageSrc       = ref(null);
const isDragOver     = ref(false);
const zoom           = ref(1);
let   lastSliderZoom = 1;
const uploading      = ref(false);
const uploadProgress = ref(0);

// Stencil = 65% of shorter image side, leaving room to drag
function defaultStencilSize({ imageSize }) {
  const side = Math.round(Math.min(imageSize.width, imageSize.height) * 0.65);
  return { width: side, height: side };
}

// Reset state when modal closes
watch(() => props.show, (open) => {
  if (!open) setTimeout(() => {
    imageSrc.value       = null;
    zoom.value           = 1;
    lastSliderZoom       = 1;
    uploading.value      = false;
    uploadProgress.value = 0;
  }, 300);
});

// ── File ───────────────────────────────────────────────────────────────
function triggerFileInput() { fileInputRef.value?.click(); }

function handleFileSelect(e) {
  const f = e.target.files?.[0];
  if (f) loadFile(f);
  e.target.value = '';
}

function handleDrop(e) {
  isDragOver.value = false;
  const f = e.dataTransfer?.files?.[0];
  if (f?.type.startsWith('image/')) loadFile(f);
}

function loadFile(file) {
  if (file.size > 5 * 1024 * 1024) { alert('File must be under 5 MB.'); return; }
  const r = new FileReader();
  r.onload = (e) => { imageSrc.value = e.target.result; zoom.value = 1; lastSliderZoom = 1; };
  r.readAsDataURL(file);
}

// ── Live preview ───────────────────────────────────────────────────────
function onCropChange({ canvas }) {
  if (!canvas) return;
  drawCircle(previewLg.value, canvas, 40);
  drawCircle(previewMd.value, canvas, 28);
  drawCircle(previewSm.value, canvas, 20);
}

function drawCircle(el, src, size) {
  if (!el) return;
  const ctx = el.getContext('2d');
  ctx.clearRect(0, 0, size, size);
  ctx.save();
  ctx.beginPath();
  ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
  ctx.clip();
  ctx.drawImage(src, 0, 0, size, size);
  ctx.restore();
}

// ── Zoom ───────────────────────────────────────────────────────────────
function applyZoom() {
  if (!cropperRef.value) return;
  const ratio = zoom.value / lastSliderZoom;
  lastSliderZoom = zoom.value;
  cropperRef.value.zoom(ratio);
}

function zoomBy(delta) {
  const next = Math.min(3, Math.max(0.1, zoom.value + delta));
  if (cropperRef.value) cropperRef.value.zoom(next / zoom.value);
  zoom.value = next;
  lastSliderZoom = next;
}

// ── Transform ──────────────────────────────────────────────────────────
function rotateBy(deg) { cropperRef.value?.rotate(deg); }
function flipH()       { cropperRef.value?.flip(true, false); }
function flipV()       { cropperRef.value?.flip(false, true); }

function resetAll() {
  const src = imageSrc.value;
  imageSrc.value = null;
  zoom.value = 1; lastSliderZoom = 1;
  nextTick(() => { imageSrc.value = src; });
}

// ── Upload ─────────────────────────────────────────────────────────────
async function uploadCropped() {
  if (!cropperRef.value) return;
  const { canvas } = cropperRef.value.getResult();
  if (!canvas) return;

  uploading.value      = true;
  uploadProgress.value = 0;

  canvas.toBlob(async (blob) => {
    const fd = new FormData();
    fd.append('avatar', blob, 'avatar.jpg');
    try {
      await axios.post('/profile/avatar', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          uploadProgress.value = Math.round((e.loaded * 100) / (e.total || 1));
        },
      });
      emit('uploaded', canvas.toDataURL('image/jpeg', 0.92));
      emit('close');
    } catch {
      alert('Upload failed. Please try again.');
    } finally {
      uploading.value = false;
    }
  }, 'image/jpeg', 0.92);
}
</script>

<style scoped>
/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity .2s ease; }
.fade-enter-from,  .fade-leave-to      { opacity: 0; }

.pop-enter-active, .pop-leave-active { transition: all .25s cubic-bezier(.16,1,.3,1); }
.pop-enter-from,   .pop-leave-to     { opacity: 0; transform: scale(.96) translateY(6px); }

/* Cropper full fill */
.w-full.h-full { display: block; }

/* Dark background tiles */
:deep(.cropper-bg),
:deep(.vue-advanced-cropper__background) {
  background-color: #0c0c0e;
  background-image: none;
}

/* Grab cursor on the image */
:deep(.vue-advanced-cropper__image) { cursor: grab; }
:deep(.vue-advanced-cropper__image:active) { cursor: grabbing; }

/* Toolbar button */
.tbtn {
  @apply flex items-center justify-center w-6 h-6 rounded-md
         text-dark-50/70 hover:text-white hover:bg-white/[0.08]
         transition-all duration-150 cursor-pointer flex-shrink-0;
}
</style>
