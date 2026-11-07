<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
import { SCIENCE_PRESETS } from '@/Utils/formulaRenderer';
import AutoTextarea from '@/Components/AutoTextarea.vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Ketik penjelasan naskah materi di sini. Gunakan toolbar di atas untuk mengatur ukuran huruf, gaya teks, warna, perataan, atau menyisipkan rumus & tabel...',
  },
  activeSlideIndex: {
    type: Number,
    default: 0,
  },
  slideLayout: {
    type: String,
    default: 'classic',
  },
  imagePreview: {
    type: String,
    default: null,
  },
});

const emit = defineEmits([
  'update:modelValue',
  'imageSelected',
  'removeImage',
]);

const editorRef = ref(null);
const mode = ref('visual'); // 'visual' | 'code'
const isFormulaDrawerOpen = ref(false);
const activeFormulaCategory = ref('matematika');
const activeColorDropdown = ref(false); // 'text' | 'highlight' | null
const activeHeadingDropdown = ref(false);
const activeSizeDropdown = ref(false);

const FONT_SIZES = [
  { label: 'Kecil (12px)', value: '12px' },
  { label: 'Normal (14px)', value: '14px' },
  { label: 'Sedang (16px)', value: '16px' },
  { label: 'Besar (18px)', value: '18px' },
  { label: 'Sangat Besar (22px)', value: '22px' },
  { label: 'Judul Sedang (28px)', value: '28px' },
  { label: 'Judul Utama (36px)', value: '36px' },
];

const TEXT_COLORS = [
  { name: 'Putih Terang', hex: '#FFFFFF' },
  { name: 'Cyan Edukasi', hex: '#38BDF8' },
  { name: 'Kuning Emas', hex: '#FACC15' },
  { name: 'Hijau Sukses', hex: '#4ADE80' },
  { name: 'Merah Penting', hex: '#F87171' },
  { name: 'Ungu Kreatif', hex: '#C084FC' },
  { name: 'Abu Lembut', hex: '#94A3B8' },
];

const HIGHLIGHT_COLORS = [
  { name: 'Tanpa Sorotan', hex: 'transparent' },
  { name: 'Stabilo Kuning', hex: 'rgba(250, 204, 21, 0.35)' },
  { name: 'Stabilo Cyan', hex: 'rgba(56, 189, 248, 0.35)' },
  { name: 'Stabilo Hijau', hex: 'rgba(74, 222, 128, 0.35)' },
  { name: 'Stabilo Pink', hex: 'rgba(244, 114, 182, 0.35)' },
  { name: 'Stabilo Ungu', hex: 'rgba(192, 132, 252, 0.35)' },
];

// Sinkronisasi Model -> Editor saat slide berubah atau diinisiasi
watch(
  () => props.modelValue,
  (newVal) => {
    if (editorRef.value && mode.value === 'visual') {
      const currentHtml = editorRef.value.innerHTML;
      if (currentHtml !== newVal) {
        // Hanya update jika berbeda dan editor tidak sedang fokus aktif mengetik
        if (document.activeElement !== editorRef.value) {
          editorRef.value.innerHTML = newVal || '';
        }
      }
    }
  }
);

// Saat slide berganti, paksa update innerHTML
watch(
  () => props.activeSlideIndex,
  () => {
    nextTick(() => {
      if (editorRef.value) {
        editorRef.value.innerHTML = props.modelValue || '';
      }
    });
  }
);

onMounted(() => {
  if (editorRef.value) {
    editorRef.value.innerHTML = props.modelValue || '';
  }
});

function onInput() {
  if (!editorRef.value) return;
  const html = editorRef.value.innerHTML;
  emit('update:modelValue', html);
}

function onCodeInput(val) {
  emit('update:modelValue', val);
  if (editorRef.value) {
    editorRef.value.innerHTML = val || '';
  }
}

function switchMode(newMode) {
  mode.value = newMode;
  nextTick(() => {
    if (newMode === 'visual' && editorRef.value) {
      editorRef.value.innerHTML = props.modelValue || '';
      editorRef.value.focus();
    }
  });
}

// =========================================================================
// RICH TEXT EXECUTION ENGINE (WYSIWYG)
// =========================================================================
function execCmd(command, value = null) {
  if (mode.value !== 'visual') switchMode('visual');
  nextTick(() => {
    if (editorRef.value) editorRef.value.focus();
    document.execCommand(command, false, value);
    onInput();
  });
}

function applyFontSize(sizePx) {
  activeSizeDropdown.value = false;
  if (mode.value !== 'visual') switchMode('visual');

  nextTick(() => {
    if (editorRef.value) editorRef.value.focus();
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return;

    const range = selection.getRangeAt(0);

    if (range.collapsed) {
      // Jika kursor tidak memblok teks, buat span kosong siap ketik
      const span = document.createElement('span');
      span.style.fontSize = sizePx;
      span.style.lineHeight = '1.4';
      span.innerHTML = '&#8203;'; // zero-width space
      range.insertNode(span);
      range.selectNodeContents(span);
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      // Bungkus teks yang diblok dengan span ukuran yang dipilih
      const span = document.createElement('span');
      span.style.fontSize = sizePx;
      span.style.lineHeight = '1.4';
      try {
        span.appendChild(range.extractContents());
        range.insertNode(span);
        const newRange = document.createRange();
        newRange.selectNodeContents(span);
        selection.removeAllRanges();
        selection.addRange(newRange);
      } catch (e) {
        document.execCommand('fontSize', false, '4');
      }
    }
    onInput();
  });
}

function stepFontSize(direction) {
  // A+ or A-
  const sizes = [12, 14, 16, 18, 22, 28, 36];
  const selection = window.getSelection();
  let currentSize = 14;

  if (selection && selection.rangeCount > 0) {
    const parent = selection.anchorNode?.parentElement;
    if (parent) {
      const computed = window.getComputedStyle(parent).fontSize;
      const parsed = parseInt(computed, 10);
      if (!isNaN(parsed)) currentSize = parsed;
    }
  }

  let nextSize;
  if (direction > 0) {
    nextSize = sizes.find((s) => s > currentSize) || 36;
  } else {
    nextSize = [...sizes].reverse().find((s) => s < currentSize) || 12;
  }

  applyFontSize(`${nextSize}px`);
}

function applyHeading(tag) {
  activeHeadingDropdown.value = false;
  execCmd('formatBlock', tag);
}

function applyTextColor(hex) {
  activeColorDropdown.value = null;
  execCmd('foreColor', hex);
}

function applyHighlight(hex) {
  activeColorDropdown.value = null;
  if (hex === 'transparent') {
    execCmd('removeFormat');
  } else {
    // Standard modern browser uses hiliteColor
    execCmd('hiliteColor', hex);
  }
}

function applyChecklist() {
  const html = `<div style="display: flex; align-items: flex-start; gap: 8px; margin: 6px 0;"><input type="checkbox" style="margin-top: 4px; width: 16px; height: 16px; accent-color: #06b6d4;" /> <span>Tugas / Evaluasi siswa...</span></div>`;
  execCmd('insertHTML', html);
}

function applyQuote() {
  const html = `<blockquote style="border-left: 4px solid #38bdf8; padding: 8px 14px; margin: 10px 0; background: rgba(56, 189, 248, 0.08); border-radius: 0 12px 12px 0; font-style: italic;">Catatan penting materi atau kutipan guru di sini...</blockquote><p><br></p>`;
  execCmd('insertHTML', html);
}

function applyTable() {
  const html = `
    <table style="width: 100%; border-collapse: collapse; margin: 12px 0; border: 1px solid rgba(255,255,255,0.2);">
      <thead>
        <tr style="background: rgba(255,255,255,0.08);">
          <th style="border: 1px solid rgba(255,255,255,0.15); padding: 8px; text-align: left; color: #38bdf8; font-size: 13px;">No</th>
          <th style="border: 1px solid rgba(255,255,255,0.15); padding: 8px; text-align: left; color: #38bdf8; font-size: 13px;">Konsep / Materi</th>
          <th style="border: 1px solid rgba(255,255,255,0.15); padding: 8px; text-align: left; color: #38bdf8; font-size: 13px;">Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="border: 1px solid rgba(255,255,255,0.1); padding: 8px; font-size: 13px;">1</td>
          <td style="border: 1px solid rgba(255,255,255,0.1); padding: 8px; font-size: 13px;">Poin pertama</td>
          <td style="border: 1px solid rgba(255,255,255,0.1); padding: 8px; font-size: 13px;">Penjelasan poin...</td>
        </tr>
        <tr>
          <td style="border: 1px solid rgba(255,255,255,0.1); padding: 8px; font-size: 13px;">2</td>
          <td style="border: 1px solid rgba(255,255,255,0.1); padding: 8px; font-size: 13px;">Poin kedua</td>
          <td style="border: 1px solid rgba(255,255,255,0.1); padding: 8px; font-size: 13px;">Penjelasan poin...</td>
        </tr>
      </tbody>
    </table>
    <p><br></p>
  `;
  execCmd('insertHTML', html);
}

function applyDivider() {
  execCmd('insertHorizontalRule');
}

function applyFormula(latex) {
  isFormulaDrawerOpen.value = false;
  const token = ` $$${latex.trim().replace(/^\$+|\$+$/g, '')}$$ `;
  execCmd('insertHTML', `&nbsp;${token}&nbsp;`);
}

function handleFileInput(e) {
  emit('imageSelected', e);
}
</script>

<template>
  <div class="space-y-2.5">
    <!-- TOOLBAR UTAMA WYSIWYG LENGKAP -->
    <div class="bg-slate-900 border border-white/10 rounded-2xl p-2.5 space-y-2 shadow-xl">
      <div class="flex flex-wrap items-center gap-1.5">

        <!-- TOGGLE MODE (VISUAL vs KODE/TEKS) -->
        <div class="flex items-center bg-black/40 rounded-xl p-0.5 border border-white/10 shrink-0">
          <button
            type="button"
            @mousedown.prevent
            @click="switchMode('visual')"
            class="px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
            :class="mode === 'visual' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
            title="Mode Visual (Tampilan Langsung Seperti Microsoft Word)"
          >
            <span>👁️</span>
            <span>Visual</span>
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="switchMode('code')"
            class="px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
            :class="mode === 'code' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
            title="Mode Naskah Cepat (Markdown & Kode)"
          >
            <span>📝</span>
            <span>Naskah</span>
          </button>
        </div>

        <div class="h-6 w-px bg-white/10 mx-0.5 hidden sm:block"></div>

        <!-- UNDO & REDO -->
        <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10 shrink-0">
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('undo')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Kembalikan Perubahan (Undo - Ctrl+Z)"
          >
            ↩
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('redo')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Ulangi Perubahan (Redo - Ctrl+Y)"
          >
            ↪
          </button>
        </div>

        <div class="h-6 w-px bg-white/10 mx-0.5 hidden sm:block"></div>

        <!-- PENGATURAN UKURAN FONT (SIZE DROPDOWN & STEPPERS) -->
        <div class="relative flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10 shrink-0">
          <button
            type="button"
            @mousedown.prevent
            @click="activeSizeDropdown = !activeSizeDropdown; activeHeadingDropdown = false; activeColorDropdown = null"
            class="px-2.5 h-8 rounded-lg hover:bg-white/15 text-cyan-300 hover:text-white text-xs font-bold transition flex items-center gap-1 active:scale-95"
            title="Atur Ukuran Huruf (Font Size)"
          >
            <span>Size</span>
            <span class="text-[10px] opacity-70">▼</span>
          </button>

          <!-- Stepper A- dan A+ -->
          <button
            type="button"
            @mousedown.prevent
            @click="stepFontSize(-1)"
            class="w-7 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-[11px] font-bold transition flex items-center justify-center active:scale-95 border-l border-white/10"
            title="Perkecil Huruf (A⁻)"
          >
            A⁻
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="stepFontSize(1)"
            class="w-7 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95 border-l border-white/10"
            title="Perbesar Huruf (A⁺)"
          >
            A⁺
          </button>

          <!-- Menu Dropdown Pilihan Ukuran -->
          <div
            v-if="activeSizeDropdown"
            class="absolute top-full left-0 mt-1.5 w-44 bg-slate-900 border border-cyan-500/40 rounded-xl shadow-2xl py-1.5 z-50 animate-in fade-in duration-100"
          >
            <div class="px-3 py-1 text-[10px] font-black text-cyan-400 uppercase tracking-wider border-b border-white/10">
              Ukuran Huruf
            </div>
            <button
              v-for="s in FONT_SIZES"
              :key="s.value"
              type="button"
              @mousedown.prevent
              @click="applyFontSize(s.value)"
              class="w-full text-left px-3 py-1.5 hover:bg-cyan-500/20 text-slate-200 hover:text-cyan-200 text-xs font-medium transition flex items-center justify-between"
            >
              <span>{{ s.label }}</span>
              <span class="text-[11px] font-mono text-slate-400">{{ s.value }}</span>
            </button>
          </div>
        </div>

        <!-- HEADING STYLES (H1, H2, H3, P) -->
        <div class="relative flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10 shrink-0">
          <button
            type="button"
            @mousedown.prevent
            @click="activeHeadingDropdown = !activeHeadingDropdown; activeSizeDropdown = false; activeColorDropdown = null"
            class="px-2.5 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center gap-1 active:scale-95"
            title="Pilih Format Judul / Paragraf"
          >
            <span>Gaya</span>
            <span class="text-[10px] opacity-70">▼</span>
          </button>

          <div
            v-if="activeHeadingDropdown"
            class="absolute top-full left-0 mt-1.5 w-40 bg-slate-900 border border-cyan-500/40 rounded-xl shadow-2xl py-1.5 z-50 animate-in fade-in duration-100"
          >
            <button
              type="button"
              @mousedown.prevent
              @click="applyHeading('<h1>')"
              class="w-full text-left px-3 py-1.5 hover:bg-cyan-500/20 text-white text-sm font-black transition"
            >
              H1 Judul Utama
            </button>
            <button
              type="button"
              @mousedown.prevent
              @click="applyHeading('<h2>')"
              class="w-full text-left px-3 py-1.5 hover:bg-cyan-500/20 text-slate-200 text-xs font-bold transition"
            >
              H2 Sub Judul
            </button>
            <button
              type="button"
              @mousedown.prevent
              @click="applyHeading('<h3>')"
              class="w-full text-left px-3 py-1.5 hover:bg-cyan-500/20 text-slate-300 text-xs font-semibold transition"
            >
              H3 Poin Bab
            </button>
            <button
              type="button"
              @mousedown.prevent
              @click="applyHeading('<p>')"
              class="w-full text-left px-3 py-1.5 hover:bg-cyan-500/20 text-slate-400 text-xs font-normal border-t border-white/10 transition"
            >
              Paragraf Normal
            </button>
          </div>
        </div>

        <div class="h-6 w-px bg-white/10 mx-0.5 hidden sm:block"></div>

        <!-- GAYA FONT DASAR (B, I, U, S) -->
        <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10 shrink-0">
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('bold')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-black transition flex items-center justify-center active:scale-95"
            title="Tebal (Bold - Ctrl+B)"
          >
            B
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('italic')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs italic font-serif transition flex items-center justify-center active:scale-95"
            title="Miring (Italic - Ctrl+I)"
          >
            I
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('underline')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs underline underline-offset-2 transition flex items-center justify-center active:scale-95"
            title="Garis Bawah (Underline - Ctrl+U)"
          >
            U
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('strikeThrough')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs line-through transition flex items-center justify-center active:scale-95"
            title="Coret (Strikethrough)"
          >
            S
          </button>
        </div>

        <!-- WARNA TEKS & STABILO (HIGHLIGHTER) -->
        <div class="relative flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10 shrink-0">
          <button
            type="button"
            @mousedown.prevent
            @click="activeColorDropdown = activeColorDropdown === 'text' ? null : 'text'; activeSizeDropdown = false; activeHeadingDropdown = false"
            class="px-2 h-8 rounded-lg hover:bg-white/15 text-amber-300 hover:text-white text-xs font-bold transition flex items-center gap-1 active:scale-95"
            title="Ubah Warna Huruf"
          >
            <span>🎨</span>
            <span class="text-[10px] opacity-70">▼</span>
          </button>

          <button
            type="button"
            @mousedown.prevent
            @click="activeColorDropdown = activeColorDropdown === 'highlight' ? null : 'highlight'; activeSizeDropdown = false; activeHeadingDropdown = false"
            class="px-2 h-8 rounded-lg hover:bg-white/15 text-yellow-300 hover:text-white text-xs font-bold transition flex items-center gap-1 active:scale-95 border-l border-white/10"
            title="Warna Stabilo / Sorotan (Highlight)"
          >
            <span>🖍️</span>
            <span class="text-[10px] opacity-70">▼</span>
          </button>

          <!-- Palette Dropdown Warna Teks -->
          <div
            v-if="activeColorDropdown === 'text'"
            class="absolute top-full left-0 mt-1.5 w-48 bg-slate-900 border border-cyan-500/40 rounded-xl shadow-2xl p-2 z-50 animate-in fade-in duration-100"
          >
            <div class="px-1.5 pb-1.5 text-[10px] font-black text-cyan-400 uppercase tracking-wider border-b border-white/10 mb-1.5">
              Pilih Warna Huruf
            </div>
            <div class="grid grid-cols-2 gap-1.5">
              <button
                v-for="c in TEXT_COLORS"
                :key="c.hex"
                type="button"
                @mousedown.prevent
                @click="applyTextColor(c.hex)"
                class="px-2 py-1 rounded-lg bg-black/40 hover:bg-white/10 text-left text-xs font-medium transition flex items-center gap-2 border border-white/5"
              >
                <span class="w-3.5 h-3.5 rounded-full border border-white/20 shrink-0" :style="{ backgroundColor: c.hex }"></span>
                <span class="truncate text-[11px] text-slate-200">{{ c.name }}</span>
              </button>
            </div>
          </div>

          <!-- Palette Dropdown Stabilo -->
          <div
            v-if="activeColorDropdown === 'highlight'"
            class="absolute top-full left-0 mt-1.5 w-48 bg-slate-900 border border-cyan-500/40 rounded-xl shadow-2xl p-2 z-50 animate-in fade-in duration-100"
          >
            <div class="px-1.5 pb-1.5 text-[10px] font-black text-yellow-400 uppercase tracking-wider border-b border-white/10 mb-1.5">
              Warna Stabilo (Sorot)
            </div>
            <div class="grid grid-cols-1 gap-1">
              <button
                v-for="h in HIGHLIGHT_COLORS"
                :key="h.hex"
                type="button"
                @mousedown.prevent
                @click="applyHighlight(h.hex)"
                class="px-2.5 py-1.5 rounded-lg bg-black/40 hover:bg-white/10 text-left text-xs font-medium transition flex items-center gap-2.5 border border-white/5"
              >
                <span class="w-4 h-4 rounded border border-white/20 shrink-0" :style="{ backgroundColor: h.hex }"></span>
                <span class="text-[11px] text-slate-200">{{ h.name }}</span>
              </button>
            </div>
          </div>
        </div>

        <div class="h-6 w-px bg-white/10 mx-0.5 hidden sm:block"></div>

        <!-- PERATAAN TEKS (LEFT, CENTER, RIGHT, JUSTIFY) -->
        <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10 shrink-0">
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('justifyLeft')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Rata Kiri"
          >
            ⇤
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('justifyCenter')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Rata Tengah"
          >
            ≡
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('justifyRight')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Rata Kanan"
          >
            ⇥
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('justifyFull')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Rata Kanan-Kiri (Justify)"
          >
            ☰
          </button>
        </div>

        <!-- DAFTAR (LISTS) & ELEMEN STRUKTUR -->
        <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10 shrink-0">
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('insertUnorderedList')"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Daftar Poin (Bullet List)"
          >
            •
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="execCmd('insertOrderedList')"
            class="px-2 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Daftar Angka (1, 2, 3)"
          >
            1.
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="applyChecklist"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-cyan-300 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Sisipkan Checklist Centang"
          >
            ☑
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="applyQuote"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95 border-l border-white/10"
            title="Kotak Catatan Khusus / Kutipan"
          >
            ”
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="applyTable"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Sisipkan Tabel Materi"
          >
            ⊞
          </button>
          <button
            type="button"
            @mousedown.prevent
            @click="applyDivider"
            class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
            title="Garis Pemisah"
          >
            ―
          </button>
        </div>

        <div class="h-6 w-px bg-white/10 mx-0.5 hidden sm:block"></div>

        <!-- SISIPKAN RUMUS ILMIAH (TOGGLE DRAWER) -->
        <button
          type="button"
          @click="isFormulaDrawerOpen = !isFormulaDrawerOpen; activeSizeDropdown = false; activeColorDropdown = null; activeHeadingDropdown = false"
          class="px-3 h-8 rounded-xl border text-xs font-bold transition flex items-center gap-1.5 active:scale-95 shadow-sm shrink-0"
          :class="isFormulaDrawerOpen
            ? 'bg-cyan-500 text-white border-cyan-400'
            : 'bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border-cyan-500/30'"
          title="Buka / Tutup Pilihan Rumus Ilmiah (KaTeX)"
        >
          <span>📐</span>
          <span>Rumus</span>
        </button>

        <!-- SISIPKAN FOTO SLIDE -->
        <input
          type="file"
          :id="'wysiwyg-slide-img-' + activeSlideIndex"
          @change="handleFileInput"
          accept="image/*"
          class="hidden"
        />
        <label
          :for="'wysiwyg-slide-img-' + activeSlideIndex"
          class="px-3 h-8 rounded-xl bg-teal-500/10 hover:bg-teal-500/20 border border-teal-500/30 text-teal-300 text-xs font-bold cursor-pointer transition flex items-center gap-1.5 active:scale-95 shadow-sm shrink-0"
          title="Tambahkan foto ilustrasi pada slide ini"
        >
          <span>📷</span>
          <span>{{ imagePreview ? 'Ganti Foto' : 'Foto' }}</span>
        </label>

      </div>

      <!-- POPUP / DRAWER RUMUS ILMIAH -->
      <div
        v-if="isFormulaDrawerOpen"
        class="p-3 bg-slate-950 border border-cyan-500/40 rounded-2xl space-y-2.5 shadow-2xl animate-in fade-in duration-150"
      >
        <div class="flex items-center justify-between">
          <div class="inline-flex rounded-xl bg-slate-900 p-1 border border-white/10">
            <button
              type="button"
              @click="activeFormulaCategory = 'matematika'"
              class="px-2.5 py-1 rounded-lg text-xs font-bold transition"
              :class="activeFormulaCategory === 'matematika' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
            >
              Matematika
            </button>
            <button
              type="button"
              @click="activeFormulaCategory = 'fisika'"
              class="px-2.5 py-1 rounded-lg text-xs font-bold transition"
              :class="activeFormulaCategory === 'fisika' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
            >
              Fisika
            </button>
            <button
              type="button"
              @click="activeFormulaCategory = 'kimia'"
              class="px-2.5 py-1 rounded-lg text-xs font-bold transition"
              :class="activeFormulaCategory === 'kimia' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
            >
              Kimia
            </button>
          </div>

          <button
            type="button"
            @click="isFormulaDrawerOpen = false"
            class="text-xs text-slate-400 hover:text-white px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10"
          >
            ✕ Tutup
          </button>
        </div>

        <div class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto pr-1">
          <button
            v-for="item in SCIENCE_PRESETS[activeFormulaCategory]"
            :key="item.label"
            type="button"
            @click="applyFormula(item.latex)"
            class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-400/40 text-xs text-cyan-200 transition active:scale-95 shadow-sm flex items-center gap-1.5"
            :title="item.label"
          >
            <span class="text-slate-400 text-[11px]">{{ item.label }}:</span>
            <span class="text-cyan-300 font-mono font-bold">{{ item.symbol }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- PREVIEW GAMBAR SLIDE TERLAMPIR (JIKA ADA) -->
    <div
      v-if="imagePreview"
      class="p-3 rounded-2xl bg-slate-950/80 border border-cyan-500/30 flex items-center justify-between gap-3 shadow-md"
    >
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-14 h-14 rounded-xl overflow-hidden border border-white/10 bg-black shrink-0">
          <img :src="imagePreview" class="w-full h-full object-cover" />
        </div>
        <div class="min-w-0">
          <p class="text-xs font-bold text-slate-200 truncate">Foto Slide Terlampir</p>
          <p class="text-[11px] text-slate-400">Layout: {{ slideLayout }}</p>
        </div>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <label
          :for="'wysiwyg-slide-img-' + activeSlideIndex"
          class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 cursor-pointer border border-white/10"
        >
          Ganti
        </label>
        <button
          type="button"
          @click="emit('removeImage')"
          class="px-2.5 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-300 text-xs font-bold transition"
        >
          Hapus
        </button>
      </div>
    </div>

    <!-- AREA EDIT: MODE VISUAL (WYSIWYG) -->
    <div
      v-show="mode === 'visual'"
      ref="editorRef"
      contenteditable="true"
      @input="onInput"
      @blur="onInput"
      :data-placeholder="placeholder"
      class="wysiwyg-canvas w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-500/30 rounded-2xl p-5 text-sm font-sans text-white leading-relaxed placeholder-slate-600 min-h-[380px] max-h-[600px] overflow-y-auto outline-none transition shadow-inner"
    ></div>

    <!-- AREA EDIT: MODE NASKAH CEPAT (TEXTAREA) -->
    <div v-show="mode === 'code'">
      <AutoTextarea
        :model-value="modelValue"
        @update:model-value="onCodeInput"
        :placeholder="placeholder"
        class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-500/30 rounded-2xl p-4 text-xs font-mono text-cyan-100 leading-relaxed placeholder-slate-600 min-h-[380px]"
      />
    </div>

    <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
      <span>💡 Tip: Sorot kata dan klik tombol <b>Size</b>, <b>🎨 Warna</b>, atau <b>🖍️ Stabilo</b> untuk memberi penekanan pada materi.</span>
      <span>Mode: {{ mode === 'visual' ? 'Visual (Word)' : 'Naskah Mentah' }}</span>
    </div>
  </div>
</template>

<style scoped>
/* Placeholder ketika editor kosong */
.wysiwyg-canvas:empty:before {
  content: attr(data-placeholder);
  color: #64748b;
  pointer-events: none;
  display: block;
  font-style: italic;
  font-size: 0.85rem;
}

/* Headings dalam area editor */
.wysiwyg-canvas :deep(h1) {
  font-size: 1.5rem;
  font-weight: 900;
  color: #ffffff;
  margin: 0.75rem 0 0.5rem;
}

.wysiwyg-canvas :deep(h2) {
  font-size: 1.25rem;
  font-weight: 800;
  color: #38bdf8;
  margin: 0.65rem 0 0.4rem;
}

.wysiwyg-canvas :deep(h3) {
  font-size: 1.1rem;
  font-weight: 700;
  color: #facc15;
  margin: 0.5rem 0 0.3rem;
}

.wysiwyg-canvas :deep(p) {
  margin-bottom: 0.65rem;
}

.wysiwyg-canvas :deep(ul) {
  list-style-type: disc;
  padding-left: 1.5rem;
  margin: 0.5rem 0;
}

.wysiwyg-canvas :deep(ol) {
  list-style-type: decimal;
  padding-left: 1.5rem;
  margin: 0.5rem 0;
}

.wysiwyg-canvas :deep(li) {
  margin-bottom: 0.25rem;
}

.wysiwyg-canvas :deep(blockquote) {
  border-left: 4px solid #38bdf8;
  padding: 0.5rem 1rem;
  margin: 0.75rem 0;
  background: rgba(56, 189, 248, 0.08);
  border-radius: 0 0.75rem 0.75rem 0;
  font-style: italic;
}

.wysiwyg-canvas :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 0.75rem 0;
}

.wysiwyg-canvas :deep(th),
.wysiwyg-canvas :deep(td) {
  border: 1px solid rgba(255, 255, 255, 0.15);
  padding: 0.5rem;
}

.wysiwyg-canvas :deep(th) {
  background: rgba(255, 255, 255, 0.08);
  color: #38bdf8;
  font-weight: 700;
}

.wysiwyg-canvas :deep(hr) {
  border: none;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  margin: 1rem 0;
}
</style>

