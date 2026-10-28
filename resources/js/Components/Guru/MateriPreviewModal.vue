<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { renderFormulaContent } from '@/Utils/formulaRenderer';

const props = defineProps({
  show: { type: Boolean, default: false },
  materi: { type: Object, default: () => null },
});

const emit = defineEmits(['close']);

const previewActiveSlide = ref(0);

watch(() => props.materi, () => {
  previewActiveSlide.value = 0;
});

watch(() => props.show, (newVal) => {
  if (newVal) {
    previewActiveSlide.value = 0;
  }
});

function handleKeyDown(e) {
  if (!props.show) return;
  if (e.key === 'Escape') {
    emit('close');
  } else if (e.key === 'ArrowRight' && props.materi?.kegiatan?.length) {
    if (previewActiveSlide.value < props.materi.kegiatan.length - 1) {
      previewActiveSlide.value++;
    }
  } else if (e.key === 'ArrowLeft' && props.materi?.kegiatan?.length) {
    if (previewActiveSlide.value > 0) {
      previewActiveSlide.value--;
    }
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeyDown);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleKeyDown);
  }
});

function getTypeBadge(tipe) {
  switch (tipe) {
    case 'Slider PPT':
      return { label: 'Slider Interaktif', color: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30' };
    case 'PDF':
      return { label: 'Dokumen PDF', color: 'bg-rose-500/10 text-rose-400 border-rose-500/30' };
    case 'PPT':
      return { label: 'Slide Presentasi PPT', color: 'bg-amber-500/10 text-amber-400 border-amber-500/30' };
    case 'Video':
      return { label: 'Video Pembelajaran', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' };
    default:
      return { label: tipe || 'Materi', color: 'bg-blue-500/10 text-blue-400 border-blue-500/30' };
  }
}

function getPreviewThemeConfig(theme) {
  switch (theme) {
    case 'light':
      return {
        wrapper: 'bg-white text-slate-900 border-slate-300 shadow-xl',
        badge: 'bg-slate-100 text-slate-700 border-slate-300',
        title: 'text-slate-900',
        prose: 'prose prose-slate max-w-none text-xs sm:text-sm leading-relaxed text-slate-800',
        footer: 'border-slate-200 text-slate-500',
      };
    case 'navy':
      return {
        wrapper: 'bg-blue-950 text-blue-50 border-blue-800 shadow-xl shadow-blue-950/50',
        badge: 'bg-blue-900/60 text-blue-300 border-blue-700',
        title: 'text-blue-100',
        prose: 'prose prose-invert max-w-none text-xs sm:text-sm leading-relaxed text-blue-100',
        footer: 'border-blue-900 text-blue-400',
      };
    case 'emerald':
      return {
        wrapper: 'bg-emerald-950 text-emerald-50 border-emerald-800 shadow-xl shadow-emerald-950/50',
        badge: 'bg-emerald-900/60 text-emerald-300 border-emerald-700',
        title: 'text-emerald-100',
        prose: 'prose prose-invert max-w-none text-xs sm:text-sm leading-relaxed text-emerald-100',
        footer: 'border-emerald-900 text-emerald-400',
      };
    case 'aurora':
      return {
        wrapper: 'bg-gradient-to-br from-slate-950 via-purple-950/80 to-slate-900 text-purple-100 border-purple-500/30 shadow-xl shadow-purple-950/50',
        badge: 'bg-purple-500/20 text-purple-300 border-purple-500/40',
        title: 'text-white',
        prose: 'prose prose-invert max-w-none text-xs sm:text-sm leading-relaxed text-purple-100',
        footer: 'border-purple-500/20 text-purple-300',
      };
    case 'monochrome':
      return {
        wrapper: 'bg-zinc-900 text-zinc-100 border-zinc-700 shadow-xl',
        badge: 'bg-zinc-800 text-zinc-200 border-zinc-600',
        title: 'text-white',
        prose: 'prose prose-invert max-w-none text-xs sm:text-sm leading-relaxed text-zinc-200',
        footer: 'border-zinc-800 text-zinc-400',
      };
    case 'dark':
    default:
      return {
        wrapper: 'bg-slate-950 text-slate-100 border-cyan-500/30 shadow-xl shadow-black/80',
        badge: 'bg-cyan-500/10 text-cyan-300 border-cyan-500/30',
        title: 'text-white',
        prose: 'prose prose-invert max-w-none text-xs sm:text-sm leading-relaxed text-slate-200',
        footer: 'border-white/10 text-slate-400',
      };
  }
}
</script>

<template>
  <div
    v-if="show && materi"
    class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-3 sm:p-6"
    @click.self="emit('close')"
  >
    <div class="bg-slate-900 border border-white/15 rounded-3xl max-w-4xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in duration-200">
      
      <!-- Modal Header -->
      <div class="p-4 sm:p-5 bg-slate-950 border-b border-white/10 flex items-center justify-between gap-4">
        <div class="min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border inline-block', getTypeBadge(materi.tipe).color]">
              {{ getTypeBadge(materi.tipe).label }}
            </span>
            <span v-if="materi.kelas" class="text-[10px] font-semibold text-cyan-300 bg-cyan-500/10 px-2 py-0.5 rounded-full border border-cyan-500/20">
              {{ materi.kelas }}
            </span>
          </div>
          <h2 class="text-base sm:text-lg font-black text-white truncate">{{ materi.judul }}</h2>
        </div>
        <button
          @click="emit('close')"
          class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold flex items-center justify-center transition shrink-0"
          title="Tutup (Esc)"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body -->
      <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6">
        
        <!-- Slider Mode Preview -->
        <div v-if="materi.tipe === 'Slider PPT' && materi.kegiatan?.length" class="space-y-4">
          
          <!-- Slide Navigation Strip -->
          <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-white/10">
            <button
              v-for="(sl, sIdx) in materi.kegiatan"
              :key="sl.id || sIdx"
              @click="previewActiveSlide = sIdx"
              class="px-3 py-1.5 rounded-xl text-xs font-bold border transition shrink-0 flex items-center gap-1.5"
              :class="previewActiveSlide === sIdx ? 'bg-cyan-500 text-white border-cyan-400 shadow-md shadow-cyan-500/20' : 'bg-white/5 border-white/10 text-slate-400 hover:text-white'"
            >
              <span class="w-4 h-4 rounded-full bg-black/20 flex items-center justify-center text-[10px]">{{ sIdx + 1 }}</span>
              <span class="max-w-[120px] truncate">{{ sl.judul || sl.label || ('Slide ' + (sIdx + 1)) }}</span>
            </button>
          </div>

          <!-- Slide Content Card -->
          <div
            class="p-5 sm:p-6 rounded-2xl border space-y-4 min-h-[320px] transition-all flex flex-col justify-between"
            :class="getPreviewThemeConfig(materi.theme).wrapper"
          >
            <div>
              <div class="flex items-center justify-between pb-2 border-b mb-3" :class="getPreviewThemeConfig(materi.theme).footer">
                <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded" :class="getPreviewThemeConfig(materi.theme).badge">
                  {{ materi.kegiatan[previewActiveSlide]?.label || 'Konsep' }}
                </span>
                <span class="text-xs font-mono opacity-75">
                  Slide {{ previewActiveSlide + 1 }} / {{ materi.kegiatan.length }}
                </span>
              </div>

              <h3 class="text-base sm:text-lg font-black mb-4 leading-snug" :class="getPreviewThemeConfig(materi.theme).title">
                {{ materi.kegiatan[previewActiveSlide]?.judul }}
              </h3>

              <!-- Layout: image_left -->
              <div v-if="materi.kegiatan[previewActiveSlide]?.layout === 'image_left'" class="grid grid-cols-1 md:grid-cols-12 gap-5 items-center">
                <div class="md:col-span-5 rounded-2xl overflow-hidden bg-black/40 border border-white/15 max-h-72 flex items-center justify-center">
                  <img v-if="materi.kegiatan[previewActiveSlide]?.fileUrl" :src="materi.kegiatan[previewActiveSlide].fileUrl" class="w-full object-contain" />
                </div>
                <div class="md:col-span-7">
                  <div
                    class="slide-markdown-content text-xs sm:text-sm leading-relaxed"
                    :class="getPreviewThemeConfig(materi.theme).prose"
                    v-html="renderFormulaContent(materi.kegiatan[previewActiveSlide]?.konten)"
                  ></div>
                </div>
              </div>

              <!-- Layout: image_right -->
              <div v-else-if="materi.kegiatan[previewActiveSlide]?.layout === 'image_right'" class="grid grid-cols-1 md:grid-cols-12 gap-5 items-center">
                <div class="md:col-span-7 order-2 md:order-1">
                  <div
                    class="slide-markdown-content text-xs sm:text-sm leading-relaxed"
                    :class="getPreviewThemeConfig(materi.theme).prose"
                    v-html="renderFormulaContent(materi.kegiatan[previewActiveSlide]?.konten)"
                  ></div>
                </div>
                <div class="md:col-span-5 order-1 md:order-2 rounded-2xl overflow-hidden bg-black/40 border border-white/15 max-h-72 flex items-center justify-center">
                  <img v-if="materi.kegiatan[previewActiveSlide]?.fileUrl" :src="materi.kegiatan[previewActiveSlide].fileUrl" class="w-full object-contain" />
                </div>
              </div>

              <!-- Layout: highlight -->
              <div v-else-if="materi.kegiatan[previewActiveSlide]?.layout === 'highlight'" class="p-5 rounded-2xl border border-amber-400/40 bg-amber-500/10 space-y-4">
                <div
                  class="slide-markdown-content text-xs sm:text-sm leading-relaxed"
                  :class="getPreviewThemeConfig(materi.theme).prose"
                  v-html="renderFormulaContent(materi.kegiatan[previewActiveSlide]?.konten)"
                ></div>
                <div v-if="materi.kegiatan[previewActiveSlide]?.fileUrl" class="max-w-md mx-auto rounded-xl overflow-hidden border border-white/20">
                  <img :src="materi.kegiatan[previewActiveSlide].fileUrl" class="w-full object-contain max-h-64" />
                </div>
              </div>

              <!-- Layout: default standard -->
              <div v-else class="space-y-4">
                <div v-if="materi.kegiatan[previewActiveSlide]?.fileUrl" class="max-w-xl mx-auto rounded-2xl overflow-hidden border border-white/20">
                  <img :src="materi.kegiatan[previewActiveSlide].fileUrl" class="w-full object-contain max-h-72" />
                </div>
                <div
                  class="slide-markdown-content text-xs sm:text-sm leading-relaxed"
                  :class="getPreviewThemeConfig(materi.theme).prose"
                  v-html="renderFormulaContent(materi.kegiatan[previewActiveSlide]?.konten)"
                ></div>
              </div>
            </div>

            <!-- Slide Navigation Footer Buttons -->
            <div class="pt-4 mt-6 border-t flex items-center justify-between gap-3" :class="getPreviewThemeConfig(materi.theme).footer">
              <button
                @click="previewActiveSlide--"
                :disabled="previewActiveSlide === 0"
                class="px-3 py-1.5 rounded-xl border text-xs font-bold transition flex items-center gap-1.5 disabled:opacity-30 disabled:cursor-not-allowed"
                :class="getPreviewThemeConfig(materi.theme).badge"
              >
                <span>← Sebelumnya</span>
              </button>

              <span class="text-[11px] font-mono opacity-60">Gunakan panah ← / → di keyboard</span>

              <button
                @click="previewActiveSlide++"
                :disabled="previewActiveSlide >= materi.kegiatan.length - 1"
                class="px-3 py-1.5 rounded-xl border text-xs font-bold transition flex items-center gap-1.5 disabled:opacity-30 disabled:cursor-not-allowed"
                :class="getPreviewThemeConfig(materi.theme).badge"
              >
                <span>Selanjutnya →</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Document / Video Preview -->
        <div v-else class="space-y-4">
          <div v-if="materi.fileUrl" class="p-4 rounded-2xl bg-slate-950 border border-white/10 flex items-center justify-between">
            <span class="text-xs text-slate-300 font-bold truncate">Berkas / Tautan Pembelajaran</span>
            <a
              :href="materi.fileUrl"
              target="_blank"
              class="px-4 py-2 rounded-xl bg-cyan-500 text-slate-950 font-black text-xs hover:bg-cyan-400 transition"
            >
              Buka Dokumen
            </a>
          </div>

          <div v-if="materi.deskripsiSingkat" class="p-5 rounded-2xl bg-slate-950/60 border border-white/10">
            <h4 class="text-xs font-bold text-slate-400 mb-2 uppercase">Petunjuk & Deskripsi</h4>
            <p class="text-sm text-slate-200 leading-relaxed whitespace-pre-line">{{ materi.deskripsiSingkat }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.slide-markdown-content u) {
  text-decoration: underline;
}
:deep(.slide-markdown-content strong) {
  font-weight: 800;
}
:deep(.katex-display) {
  margin: 0.75em 0;
  overflow-x: auto;
  overflow-y: hidden;
  padding: 0.25em 0;
}
</style>