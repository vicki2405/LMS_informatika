<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { renderFormulaContent } from '@/Utils/formulaRenderer';
import { showToast } from '@/Utils/sweetalert';

const props = defineProps({
  materiList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  siswaObj: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['change-view']);

// 1. Shared Class Account Student Identity Management
const activeSiswaId = ref(null);
const showIdentityModal = ref(false);
const studentSearchQuery = ref('');

onMounted(() => {
  try {
    const savedId = localStorage.getItem('siswacloud_active_siswa_id');
    if (savedId && props.siswaList.some(s => String(s.id) === String(savedId))) {
      activeSiswaId.value = Number(savedId);
    } else if (props.siswaList.length > 0) {
      showIdentityModal.value = true;
    }
  } catch (e) {
    if (props.siswaList.length > 0) activeSiswaId.value = props.siswaList[0].id;
  }
});

const activeSiswa = computed(() => {
  if (!activeSiswaId.value) return null;
  return props.siswaList.find(s => String(s.id) === String(activeSiswaId.value)) || null;
});

function selectStudent(student) {
  activeSiswaId.value = student.id;
  try {
    localStorage.setItem('siswacloud_active_siswa_id', String(student.id));
  } catch (e) {}
  showIdentityModal.value = false;
  showToast(`Halo ${student.nama}, selamat belajar!`, 'success');
}

function switchStudent() {
  studentSearchQuery.value = '';
  showIdentityModal.value = true;
}

const filteredSiswaList = computed(() => {
  if (!studentSearchQuery.value.trim()) return props.siswaList;
  const q = studentSearchQuery.value.toLowerCase();
  return props.siswaList.filter(s => s.nama.toLowerCase().includes(q) || (s.nis && s.nis.toLowerCase().includes(q)));
});

// 2. Filter & Search Materi
const selectedSemester = ref('Ganjil');
const selectedTipe = ref('Semua');
const searchQuery = ref('');

const filteredMateri = computed(() => {
  let list = props.materiList || [];
  if (selectedSemester.value !== 'Semua') {
    list = list.filter(m => (m.semester || 'Ganjil') === selectedSemester.value);
  }

  if (selectedTipe.value !== 'Semua') {
    list = list.filter(m => m.tipe === selectedTipe.value);
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(m => m.judul.toLowerCase().includes(q) || (m.deskripsi && m.deskripsi.toLowerCase().includes(q)) || (m.mapel && m.mapel.toLowerCase().includes(q)));
  }

  return list;
});

function getStudentReadStatus(materi) {
  if (!activeSiswaId.value || !materi.reads) return { status: 'belum_baca', progress: 0 };
  const record = materi.reads.find(r => String(r.siswaId) === String(activeSiswaId.value));
  if (!record) return { status: 'belum_baca', progress: 0 };
  return {
    status: record.status || 'sedang_membaca',
    progress: record.progressPersen || (record.status === 'selesai' ? 100 : 25),
  };
}

// 3. Presentation State
const showReader = ref(false);
const activeMateri = ref(null);
const currentSlideIndex = ref(0);
const isMarkingComplete = ref(false);
const isCompletedForActiveSiswa = ref(false);
const userSelectedTheme = ref(null);

// Lock body scrolling when reader is active to prevent viewport pushing
watch(showReader, (val) => {
  if (typeof document !== 'undefined') {
    if (val) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  }
});

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = '';
  }
});

// Mobile Swipe Gestures for Slides
let touchStartX = 0;
let touchStartY = 0;

function handleTouchStart(e) {
  if (!e.changedTouches || e.changedTouches.length === 0) return;
  touchStartX = e.changedTouches[0].clientX;
  touchStartY = e.changedTouches[0].clientY;
}

function handleTouchEnd(e) {
  if (!e.changedTouches || e.changedTouches.length === 0) return;
  const deltaX = e.changedTouches[0].clientX - touchStartX;
  const deltaY = e.changedTouches[0].clientY - touchStartY;
  if (Math.abs(deltaX) > 40 && Math.abs(deltaX) > Math.abs(deltaY) * 1.3) {
    if (deltaX < 0) {
      nextSlide();
    } else {
      prevSlide();
    }
  }
}
const isFullscreenMode = ref(false);

const activeTheme = computed(() => {
  return userSelectedTheme.value || activeMateri.value?.theme || 'dark';
});

// Theme Definitions for True Executive Presentation Slides
const THEME_STYLES = {
  dark: {
    bg: 'bg-slate-950 text-slate-100',
    card: 'bg-gradient-to-b from-slate-900/95 via-slate-900/90 to-slate-950/95 border-cyan-500/30 text-white shadow-[0_20px_60px_-15px_rgba(6,182,212,0.15)]',
    header: 'bg-slate-950/95 border-white/10 text-white',
    accentText: 'text-cyan-400',
    accentBg: 'bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 font-black',
    highlightBox: 'bg-cyan-950/40 border-cyan-400/40 text-cyan-200',
    badge: 'bg-cyan-500/15 border-cyan-400/40 text-cyan-300',
    glow: 'rgba(6, 182, 212, 0.12)',
  },
  light: {
    bg: 'bg-slate-100 text-slate-900',
    card: 'bg-gradient-to-b from-white via-slate-50 to-white border-slate-300 text-slate-900 shadow-[0_20px_60px_-15px_rgba(79,70,229,0.15)]',
    header: 'bg-white/95 border-slate-200 text-slate-900',
    accentText: 'text-indigo-600',
    accentBg: 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-black',
    highlightBox: 'bg-indigo-50 border-indigo-200 text-indigo-900',
    badge: 'bg-indigo-50 border-indigo-300 text-indigo-700',
    glow: 'rgba(79, 70, 229, 0.08)',
  },
  navy: {
    bg: 'bg-[#030d22] text-blue-50',
    card: 'bg-gradient-to-b from-[#081836]/95 via-[#06142e] to-[#040f24] border-sky-400/30 text-white shadow-[0_20px_60px_-15px_rgba(56,189,248,0.15)]',
    header: 'bg-[#030d22]/95 border-blue-500/20 text-white',
    accentText: 'text-sky-400',
    accentBg: 'bg-gradient-to-r from-sky-400 to-blue-500 text-slate-950 font-black',
    highlightBox: 'bg-blue-950/60 border-sky-400/40 text-sky-200',
    badge: 'bg-sky-500/15 border-sky-400/40 text-sky-300',
    glow: 'rgba(56, 189, 248, 0.12)',
  },
  emerald: {
    bg: 'bg-[#021d15] text-emerald-50',
    card: 'bg-gradient-to-b from-[#052e22]/95 via-[#04241b] to-[#021912] border-emerald-400/30 text-white shadow-[0_20px_60px_-15px_rgba(16,185,129,0.15)]',
    header: 'bg-[#021d15]/95 border-emerald-500/20 text-white',
    accentText: 'text-emerald-400',
    accentBg: 'bg-gradient-to-r from-emerald-400 to-teal-500 text-slate-950 font-black',
    highlightBox: 'bg-emerald-950/60 border-emerald-400/40 text-emerald-200',
    badge: 'bg-emerald-500/15 border-emerald-400/40 text-emerald-300',
    glow: 'rgba(16, 185, 129, 0.12)',
  },
};

const currentThemeClass = computed(() => {
  return THEME_STYLES[activeTheme.value] || THEME_STYLES.dark;
});

const currentSlides = computed(() => {
  return activeMateri.value?.kegiatan || [];
});

const currentSlide = computed(() => {
  return currentSlides.value[currentSlideIndex.value] || null;
});

const slideProgressPercent = computed(() => {
  if (!currentSlides.value.length) return 100;
  return Math.round(((currentSlideIndex.value + 1) / currentSlides.value.length) * 100);
});

// YouTube embed parser
const youtubeEmbedUrl = computed(() => {
  if (!activeMateri.value || activeMateri.value.tipe !== 'Video YouTube' || !activeMateri.value.fileUrl) return null;
  try {
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = activeMateri.value.fileUrl.match(regExp);
    if (match && match[2].length === 11) {
      return `https://www.youtube.com/embed/${match[2]}?autoplay=1&rel=0`;
    }
  } catch (e) {
    return null;
  }
  return null;
});

// Open Reader
async function openMateri(materi) {
  if (!activeSiswa.value) {
    showIdentityModal.value = true;
    return;
  }

  activeMateri.value = materi;
  userSelectedTheme.value = materi.theme || 'dark';
  currentSlideIndex.value = 0;
  showReader.value = true;

  const readStatus = getStudentReadStatus(materi);
  isCompletedForActiveSiswa.value = readStatus.status === 'selesai';

  // Record track open in backend
  try {
    await axios.post(route('siswa.materi.track-open', materi.id), {
      siswa_id: activeSiswa.value.id,
      progress_persen: currentSlides.value.length > 0 ? Math.round((1 / currentSlides.value.length) * 100) : 50,
    });
    if (!materi.reads) materi.reads = [];
    const rec = materi.reads.find(r => String(r.siswaId) === String(activeSiswa.value.id));
    if (!rec) {
      materi.reads.push({ siswaId: activeSiswa.value.id, status: 'sedang_membaca', progressPersen: 25 });
    }
  } catch (err) {
    console.error('Track open failed:', err);
  }
}

// Toggle browser fullscreen
async function toggleBrowserFullscreen() {
  try {
    if (!document.fullscreenElement) {
      const docEl = document.documentElement;
      if (docEl.requestFullscreen) await docEl.requestFullscreen();
      isFullscreenMode.value = true;
    } else {
      if (document.exitFullscreen) await document.exitFullscreen();
      isFullscreenMode.value = false;
    }
  } catch (e) {}
}

// Close Reader & Exit Fullscreen
function closeReader() {
  try {
    if (document.fullscreenElement) {
      document.exitFullscreen();
    }
  } catch (e) {}
  showReader.value = false;
  activeMateri.value = null;
  isFullscreenMode.value = false;
}

// Next / Prev Slide
function nextSlide() {
  if (currentSlideIndex.value < currentSlides.value.length - 1) {
    currentSlideIndex.value++;
    updateReadingProgress();
  }
}

function prevSlide() {
  if (currentSlideIndex.value > 0) {
    currentSlideIndex.value--;
  }
}

function goToSlide(idx) {
  if (idx >= 0 && idx < currentSlides.value.length) {
    currentSlideIndex.value = idx;
    updateReadingProgress();
  }
}

async function updateReadingProgress() {
  if (!activeMateri.value || !activeSiswa.value || isCompletedForActiveSiswa.value) return;
  const progress = slideProgressPercent.value;
  try {
    await axios.post(route('siswa.materi.track-open', activeMateri.value.id), {
      siswa_id: activeSiswa.value.id,
      progress_persen: progress,
    });
  } catch (e) {}
}

async function markCompleted() {
  if (!activeMateri.value || !activeSiswa.value || isMarkingComplete.value) return;
  isMarkingComplete.value = true;

  try {
    await axios.post(route('siswa.materi.track-complete', activeMateri.value.id), {
      siswa_id: activeSiswa.value.id,
    });

    isCompletedForActiveSiswa.value = true;
    showToast('Selamat! Anda telah menyelesaikan materi ini 🎉', 'success');

    const rec = activeMateri.value.reads?.find(r => String(r.siswaId) === String(activeSiswa.value.id));
    if (rec) {
      rec.status = 'selesai';
      rec.progressPersen = 100;
    }
  } catch (err) {
    showToast('Gagal mencatat penyelesaian materi.', 'error');
  } finally {
    isMarkingComplete.value = false;
  }
}

function jumpToCbt() {
  closeReader();
  emit('change-view', 'cbt');
}

// Keyboard navigation (ArrowLeft, ArrowRight, Space, Escape)
function handleKeyDown(e) {
  if (!showReader.value) return;
  if (e.key === 'ArrowRight' || e.key === ' ') {
    e.preventDefault();
    nextSlide();
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault();
    prevSlide();
  } else if (e.key === 'Escape') {
    closeReader();
  } else if (e.key === 'f' || e.key === 'F') {
    toggleBrowserFullscreen();
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
  <div class="space-y-6 pb-12 animate-in fade-in duration-300">

    <!-- Active Student Identity Card (For Shared Class Account) -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 border border-white/10 rounded-3xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xl">
      <div class="flex items-center gap-3.5 min-w-0">
        <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 flex items-center justify-center font-black text-xl shrink-0 shadow-md">
          🎓
        </div>
        <div class="min-w-0">
          <div class="text-[10px] text-cyan-400 font-mono font-bold uppercase tracking-wider">
            Siswa Pembaca Aktif:
          </div>
          <div v-if="activeSiswa" class="text-base sm:text-lg font-black text-white truncate flex items-center gap-2">
            <span>{{ activeSiswa.nama }}</span>
            <span class="text-xs text-gray-400 font-mono font-normal">NIS: {{ activeSiswa.nis || '-' }}</span>
          </div>
          <div v-else class="text-sm font-bold text-amber-300">
            ⚠️ Belum memilih nama siswa
          </div>
        </div>
      </div>

      <button
        @click="switchStudent"
        type="button"
        class="px-4 py-2 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/10 text-white font-bold text-xs transition active:scale-95 flex items-center gap-2 shrink-0 shadow-sm"
      >
        <span>🔄</span>
        <span>Ganti Siswa</span>
      </button>
    </div>

    <!-- Filter & Search Controls -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-[11px] font-bold text-cyan-300 uppercase mb-1.5">📅 Semester</label>
          <select
            v-model="selectedSemester"
            class="w-full bg-slate-800 border border-cyan-500/40 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-cyan-300 font-extrabold focus:border-cyan-400 outline-none min-h-[44px]"
          >
            <option value="Ganjil">Semester Ganjil (1)</option>
            <option value="Genap">Semester Genap (2)</option>
            <option value="Semua">Semua Semester</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">📦 Format Materi</label>
          <select
            v-model="selectedTipe"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
          >
            <option value="Semua">Semua Format</option>
            <option value="Slider PPT">🖥️ Slider PPT Interaktif</option>
            <option value="Dokumen PDF/PPT">📄 Dokumen / PDF</option>
            <option value="Video YouTube">🎥 Video Pembelajaran</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">🔍 Cari Materi</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari judul materi, mapel..."
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-cyan-400 outline-none min-h-[44px]"
          />
        </div>
      </div>
    </div>

    <!-- Materi Cards Grid -->
    <div v-if="filteredMateri.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="m in filteredMateri"
        :key="m.id"
        class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 p-5 flex flex-col justify-between hover:border-cyan-500/50 hover:shadow-2xl hover:shadow-cyan-500/10 transition-all duration-300 group"
      >
        <div>
          <!-- Card Header & Badges -->
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="text-[11px] font-extrabold uppercase px-3 py-1 rounded-full border tracking-wider"
              :class="m.tipe === 'Slider PPT' ? 'bg-cyan-500/15 border-cyan-500/30 text-cyan-300' : (m.tipe === 'Video YouTube' ? 'bg-rose-500/15 border-rose-500/30 text-rose-300' : 'bg-amber-500/15 border-amber-500/30 text-amber-300')"
            >
              {{ m.tipe === 'Slider PPT' ? '🖥️ Slider PPT' : (m.tipe === 'Video YouTube' ? '🎥 Video' : '📄 Dokumen') }}
            </span>

            <span class="text-xs font-mono font-bold text-gray-400">
              {{ m.mapel || 'Informatika' }}
            </span>
          </div>

          <!-- Title -->
          <h3 class="text-base font-black text-white group-hover:text-cyan-300 transition-colors line-clamp-2 leading-snug mb-2">
            {{ m.judul }}
          </h3>

          <!-- Deskripsi -->
          <p class="text-xs text-gray-400 line-clamp-2 leading-relaxed mb-4">
            {{ m.deskripsi || 'Tidak ada deskripsi singkat.' }}
          </p>

          <!-- Status Belajar Siswa Aktif -->
          <div class="bg-slate-950/60 rounded-2xl p-3 border border-white/5 space-y-2 mb-4">
            <div class="flex items-center justify-between text-[11px]">
              <span class="text-gray-400 font-medium">Status Anda:</span>
              <span
                class="font-black"
                :class="getStudentReadStatus(m).status === 'selesai' ? 'text-emerald-400' : (getStudentReadStatus(m).status === 'sedang_membaca' ? 'text-amber-400' : 'text-gray-400')"
              >
                {{ getStudentReadStatus(m).status === 'selesai' ? '✓ Tuntas' : (getStudentReadStatus(m).status === 'sedang_membaca' ? '⏳ Sedang Belajar' : '⚪ Belum Dibaca') }}
              </span>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-300"
                :class="getStudentReadStatus(m).status === 'selesai' ? 'bg-emerald-400' : 'bg-cyan-400'"
                :style="{ width: getStudentReadStatus(m).progress + '%' }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Action Open Button -->
        <button
          @click="openMateri(m)"
          type="button"
          class="w-full py-3 px-4 rounded-2xl bg-gradient-to-r from-cyan-500 via-teal-600 to-indigo-600 text-white font-black text-xs shadow-lg shadow-cyan-500/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 min-h-[44px]"
        >
          <span>Buka Materi Pembelajaran</span>
          <span>➔</span>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-slate-900/60 backdrop-blur-xl rounded-3xl border border-white/10 p-12 text-center space-y-3">
      <div class="text-4xl">📚</div>
      <h3 class="text-base font-black text-white">Belum Ada Materi Pembelajaran</h3>
      <p class="text-xs text-gray-400 max-w-sm mx-auto">
        Materi untuk semester {{ selectedSemester }} belum diunggah atau tidak cocok dengan filter pencarian Anda.
      </p>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: SWITCH STUDENT IDENTITY MODAL (SHARED ACCOUNT) -->
    <!-- ======================================================== -->
    <Teleport to="body">
      <div
        v-if="showIdentityModal"
        class="fixed inset-0 z-[9990] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-200"
      >
      <div class="bg-slate-900 border border-white/10 rounded-3xl max-w-md w-full p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div class="flex items-center gap-2.5">
            <span class="text-2xl">👤</span>
            <div>
              <h3 class="text-base font-black text-white">Pilih Nama Anda</h3>
              <p class="text-[11px] text-gray-400">Akun bersama kelas — pilih nama Anda sebelum membaca</p>
            </div>
          </div>
          <button
            v-if="activeSiswa"
            @click="showIdentityModal = false"
            class="text-gray-400 hover:text-white text-lg font-bold"
          >✕</button>
        </div>

        <!-- Search Input -->
        <div>
          <input
            v-model="studentSearchQuery"
            type="text"
            placeholder="Ketik nama atau NIS Anda..."
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-xs text-white placeholder-gray-400 focus:border-cyan-400 outline-none"
            autofocus
          />
        </div>

        <!-- Student List -->
        <div class="max-h-60 overflow-y-auto space-y-2 pr-1">
          <button
            v-for="s in filteredSiswaList"
            :key="s.id"
            @click="selectStudent(s)"
            type="button"
            class="w-full p-3 rounded-2xl border text-left transition flex items-center justify-between group"
            :class="String(s.id) === String(activeSiswaId) ? 'bg-cyan-500/20 border-cyan-500/50 text-cyan-200' : 'bg-slate-800/60 border-white/5 text-gray-300 hover:bg-slate-800 hover:border-white/20'"
          >
            <div class="min-w-0 pr-2">
              <div class="text-xs font-black truncate group-hover:text-white">{{ s.nama }}</div>
              <div class="text-[10px] text-gray-400 font-mono">NIS: {{ s.nis || '-' }} · Absen: {{ s.absen || '-' }}</div>
            </div>
            <span v-if="String(s.id) === String(activeSiswaId)" class="text-xs text-cyan-400 font-bold shrink-0">✓ Aktif</span>
          </button>
        </div>

        <div class="text-center pt-2">
          <p class="text-[11px] text-gray-400">
            Nama ini akan dicatat guru sebagai bukti kehadiran membaca materi & tugas.
          </p>
        </div>
      </div>
    </div>
    </Teleport>

    <!-- ======================================================== -->
    <!-- FULLSCREEN IMMERSIVE PRESENTATION STAGE (16:9 EXECUTIVE CANVAS) -->
    <!-- ======================================================== -->
    <Teleport to="body">
      <div
        v-if="showReader && activeMateri"
        class="fixed inset-0 w-full h-full z-[9999] flex flex-col overflow-hidden transition-colors duration-300"
        :class="currentThemeClass.bg"
        style="overscroll-behavior: contain; touch-action: pan-y;"
        @touchstart="handleTouchStart"
        @touchend="handleTouchEnd"
      >
      <!-- TOP HUD PRESENTATION BAR -->
      <header
        class="border-b px-4 sm:px-8 py-3 flex items-center justify-between gap-4 backdrop-blur-xl shrink-0 z-30"
        :class="currentThemeClass.header"
      >
        <!-- Left: Exit, Title, Identity -->
        <div class="flex items-center gap-3 sm:gap-4 min-w-0">
          <button
            @click="closeReader"
            type="button"
            class="px-3.5 py-2 rounded-2xl bg-white/10 hover:bg-white/20 text-xs font-black transition flex items-center gap-2 active:scale-95 text-white shadow-sm"
            title="Tutup Reader & Kembali ke Dashboard"
          >
            <span>✕</span>
            <span class="hidden sm:inline">Keluar</span>
          </button>

          <div class="min-w-0">
            <h2 class="text-xs sm:text-base font-black truncate leading-tight flex items-center gap-2">
              <span class="hidden md:inline-block px-2 py-0.5 rounded-lg text-[10px] uppercase font-mono font-bold tracking-wider" :class="currentThemeClass.badge">
                {{ activeMateri.tipe }}
              </span>
              <span>{{ activeMateri.judul }}</span>
            </h2>
            <div class="text-[11px] text-slate-400 truncate flex items-center gap-2 mt-0.5">
              <span v-if="activeSiswa" class="font-bold text-cyan-400">
                👤 {{ activeSiswa.nama }}
              </span>
              <span v-if="activeMateri.tipe === 'Slider PPT'" class="font-mono text-gray-400">
                · Slide {{ currentSlideIndex + 1 }} dari {{ currentSlides.length }}
              </span>
            </div>
          </div>
        </div>

        <!-- Right: Theme Picker, Fullscreen Toggle, Complete / CBT -->
        <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
          <!-- Mobile Quick Slide Pager -->
          <div v-if="activeMateri.tipe === 'Slider PPT' && currentSlides.length > 1" class="flex sm:hidden items-center gap-0.5 bg-white/10 p-0.5 rounded-xl border border-white/15 shrink-0">
            <button
              @click="prevSlide"
              :disabled="currentSlideIndex === 0"
              type="button"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px] font-black text-white hover:bg-white/20 disabled:opacity-20 active:scale-90 transition"
              title="Slide Sebelumnya"
            >
              ◀
            </button>
            <span class="text-[10px] font-mono font-black text-cyan-300 px-1 whitespace-nowrap">
              {{ currentSlideIndex + 1 }}/{{ currentSlides.length }}
            </span>
            <button
              @click="nextSlide"
              :disabled="currentSlideIndex === currentSlides.length - 1"
              type="button"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px] font-black text-slate-950 bg-cyan-400 hover:bg-cyan-300 disabled:opacity-20 active:scale-90 transition shadow"
              title="Slide Berikutnya"
            >
              ▶
            </button>
          </div>
          <!-- Live Theme Switcher for Slider PPT -->
          <div v-if="activeMateri.tipe === 'Slider PPT'" class="hidden md:flex items-center gap-1 bg-white/5 p-1 rounded-2xl border border-white/10">
            <button
              v-for="(tObj, tKey) in THEME_STYLES"
              :key="tKey"
              @click="userSelectedTheme = tKey"
              type="button"
              class="w-7 h-7 rounded-xl flex items-center justify-center text-xs font-bold transition-all"
              :class="activeTheme === tKey ? 'scale-110 shadow ring-2 ring-cyan-400 ' + tObj.accentBg : 'opacity-60 hover:opacity-100'"
              :title="'Ganti Tema: ' + tKey"
            >
              {{ tKey === 'dark' ? '🌙' : (tKey === 'light' ? '☀️' : (tKey === 'navy' ? '🌊' : '🍃')) }}
            </button>
          </div>

          <!-- Native Fullscreen Toggle -->
          <button
            @click="toggleBrowserFullscreen"
            type="button"
            class="w-9 h-9 rounded-2xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-sm transition active:scale-95"
            title="Layar Penuh (F)"
          >
            {{ isFullscreenMode ? '🗗' : '⛶' }}
          </button>

          <!-- Complete Button -->
          <button
            v-if="!isCompletedForActiveSiswa"
            @click="markCompleted"
            :disabled="isMarkingComplete"
            type="button"
            class="px-3.5 py-2 rounded-2xl text-xs font-black bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition active:scale-95 shadow-md flex items-center gap-1.5 disabled:opacity-50"
          >
            <span>✓</span>
            <span class="hidden sm:inline">Tandai Selesai</span>
          </button>
          <span
            v-else
            class="px-3.5 py-2 rounded-2xl text-[11px] font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5"
          >
            <span>✓</span>
            <span>Tuntas Dibaca</span>
          </span>

          <!-- Linked CBT Exam Button -->
          <button
            v-if="activeMateri.cbtJadwal"
            @click="jumpToCbt"
            type="button"
            class="px-4 py-2 rounded-2xl text-xs font-black bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30 transition hover:scale-105 active:scale-95 flex items-center gap-1.5"
            title="Lanjut ke Ujian CBT"
          >
            <span>🎯</span>
            <span class="hidden md:inline">Lanjut Ujian CBT</span>
          </button>
        </div>
      </header>

      <!-- Reading Progress Bar -->
      <div v-if="activeMateri.tipe === 'Slider PPT'" class="w-full h-1.5 bg-white/10 shrink-0">
        <div
          class="h-full bg-gradient-to-r from-cyan-400 to-teal-400 transition-all duration-300 shadow-sm"
          :style="{ width: slideProgressPercent + '%' }"
        ></div>
      </div>

      <!-- MAIN READER STAGE -->
      <main class="flex-1 min-h-0 overflow-y-auto p-2.5 sm:p-6 lg:p-8 pb-32 sm:pb-32 flex flex-col items-center justify-start md:justify-center relative">

        <!-- ============================================== -->
        <!-- 1. SLIDER PPT: EXECUTIVE WIDESCREEN PRESENTATION -->
        <!-- ============================================== -->
        <div
          v-if="activeMateri.tipe === 'Slider PPT' && currentSlide"
          class="w-full max-w-6xl mx-auto flex items-center justify-center relative py-1 sm:py-2"
        >
          <!-- Floating Left Arrow Button -->
          <button
            v-if="currentSlides.length > 1 && currentSlideIndex > 0"
            @click="prevSlide"
            type="button"
            class="flex absolute left-1 sm:left-2 lg:-left-8 top-1/2 -translate-y-1/2 z-20 w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-slate-900/85 hover:bg-slate-800 text-white border border-white/25 items-center justify-center text-xs sm:text-lg font-black shadow-2xl transition-all duration-200 hover:scale-110 active:scale-90 backdrop-blur-md"
            title="Slide Sebelumnya (◄)"
          >
            ❮
          </button>

          <!-- Floating Right Arrow Button -->
          <button
            v-if="currentSlides.length > 1 && currentSlideIndex < currentSlides.length - 1"
            @click="nextSlide"
            type="button"
            class="flex absolute right-1 sm:right-2 lg:-right-8 top-1/2 -translate-y-1/2 z-20 w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-cyan-500 hover:bg-cyan-400 text-slate-950 items-center justify-center text-xs sm:text-lg font-black shadow-2xl shadow-cyan-500/40 transition-all duration-200 hover:scale-110 active:scale-90"
            title="Slide Berikutnya (►)"
          >
            ❯
          </button>

          <!-- THE 16:9 SLIDE CANVAS CONTAINER -->
          <div
            class="w-full rounded-2xl sm:rounded-3xl border p-4 sm:p-10 lg:p-12 transition-all duration-300 relative overflow-hidden flex flex-col justify-between min-h-0 sm:min-h-[500px] lg:min-h-[560px]"
            :class="currentThemeClass.card"
          >
            <!-- Ambient Glow Decoration -->
            <div
              class="pointer-events-none absolute -top-32 -left-32 w-80 h-80 rounded-full blur-3xl opacity-30"
              :style="{ backgroundColor: currentThemeClass.glow }"
            ></div>
            <div
              class="pointer-events-none absolute -bottom-32 -right-32 w-80 h-80 rounded-full blur-3xl opacity-20"
              :style="{ backgroundColor: currentThemeClass.glow }"
            ></div>

            <!-- SLIDE TOP META HEADER -->
            <div class="relative z-10 flex items-center justify-between gap-4 border-b border-white/10 pb-4 mb-6">
              <!-- Label Pill -->
              <div class="flex items-center gap-2.5">
                <span
                  class="text-xs font-black uppercase tracking-wider px-3.5 py-1.5 rounded-full border shadow-sm flex items-center gap-1.5"
                  :class="currentThemeClass.badge"
                >
                  <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                  <span>{{ currentSlide.label || 'Materi Pembelajaran' }}</span>
                </span>
                <span class="text-[11px] font-mono text-gray-400 hidden sm:inline">
                  {{ activeMateri.mapel || 'Informatika' }}
                </span>
              </div>

              <!-- Slide Number Counter -->
              <div class="flex items-center gap-3">
                <span class="text-xs font-mono font-extrabold px-3 py-1 rounded-xl bg-white/10 text-white border border-white/10">
                  SLIDE {{ String(currentSlideIndex + 1).padStart(2, '0') }} / {{ String(currentSlides.length).padStart(2, '0') }}
                </span>
              </div>
            </div>

            <!-- SLIDE MAIN CONTENT AREA -->
            <div class="relative z-10 flex-1">
              <!-- Slide Title -->
              <h3 class="text-xl sm:text-3xl lg:text-4xl font-black mb-4 sm:mb-6 leading-tight tracking-tight text-white drop-shadow-sm">
                {{ currentSlide.judul }}
              </h3>

              <!-- LAYOUT A: Standard (Text, Markdown Tables, Formulas) -->
              <div v-if="currentSlide.layout === 'standard'" class="space-y-6">
                <div v-if="currentSlide.fileUrl" class="max-w-2xl mx-auto rounded-3xl overflow-hidden border border-white/15 shadow-2xl bg-black/40">
                  <img :src="currentSlide.fileUrl" class="w-full object-contain max-h-80" />
                </div>

                <!-- Rich Markdown & Math Content -->
                <div
                  class="slide-markdown-content text-sm sm:text-base leading-relaxed"
                  v-html="renderFormulaContent(currentSlide.konten)"
                ></div>
              </div>

              <!-- LAYOUT B: Image Left + Content Right -->
              <div v-else-if="currentSlide.layout === 'image_left'" class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
                <div v-if="currentSlide.fileUrl" class="md:col-span-5 rounded-3xl overflow-hidden border border-white/15 shadow-2xl bg-black/40">
                  <img :src="currentSlide.fileUrl" class="w-full object-contain max-h-96" />
                </div>
                <div
                  :class="currentSlide.fileUrl ? 'md:col-span-7' : 'md:col-span-12'"
                  class="slide-markdown-content text-sm sm:text-base leading-relaxed"
                  v-html="renderFormulaContent(currentSlide.konten)"
                ></div>
              </div>

              <!-- LAYOUT C: Content Left + Image Right -->
              <div v-else-if="currentSlide.layout === 'image_right'" class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
                <div
                  :class="currentSlide.fileUrl ? 'md:col-span-7' : 'md:col-span-12'"
                  class="slide-markdown-content text-sm sm:text-base leading-relaxed order-2 md:order-1"
                  v-html="renderFormulaContent(currentSlide.konten)"
                ></div>
                <div v-if="currentSlide.fileUrl" class="md:col-span-5 rounded-3xl overflow-hidden border border-white/15 shadow-2xl bg-black/40 order-1 md:order-2">
                  <img :src="currentSlide.fileUrl" class="w-full object-contain max-h-96" />
                </div>
              </div>

              <!-- LAYOUT D: Highlight Box (Formula / Takeaways) -->
              <div v-else class="space-y-6">
                <div
                  class="p-6 sm:p-8 rounded-3xl border shadow-xl relative overflow-hidden backdrop-blur-sm"
                  :class="currentThemeClass.highlightBox"
                >
                  <div
                    class="slide-markdown-content text-base sm:text-lg font-medium leading-relaxed"
                    v-html="renderFormulaContent(currentSlide.konten)"
                  ></div>
                </div>

                <div v-if="currentSlide.fileUrl" class="max-w-xl mx-auto rounded-3xl overflow-hidden border border-white/15 shadow-2xl bg-black/40">
                  <img :src="currentSlide.fileUrl" class="w-full object-contain max-h-72" />
                </div>
              </div>
            </div>

            <!-- SLIDE FOOTER BANNER ON LAST SLIDE -->
            <div
              v-if="currentSlideIndex === currentSlides.length - 1"
              class="relative z-10 mt-8 p-5 sm:p-6 rounded-2xl bg-gradient-to-r from-emerald-950/70 via-teal-950/60 to-indigo-950/70 border border-emerald-500/40 text-center space-y-3 shadow-2xl"
            >
              <div class="text-3xl">🎉</div>
              <h4 class="text-base sm:text-lg font-black text-white">Luar Biasa! Anda Telah Membaca Semua Slide</h4>
              <p class="text-xs text-slate-300 max-w-lg mx-auto leading-relaxed">
                Tuntaskan sesi belajar Anda dengan konfirmasi penyelesaian materi atau langsung kerjakan ujian CBT.
              </p>

              <div class="flex items-center justify-center gap-3 pt-1 flex-wrap">
                <button
                  v-if="!isCompletedForActiveSiswa"
                  @click="markCompleted"
                  :disabled="isMarkingComplete"
                  class="px-6 py-2.5 rounded-2xl bg-emerald-500 text-slate-950 font-black text-xs hover:bg-emerald-400 active:scale-95 transition shadow-lg shadow-emerald-500/25"
                >
                  ✓ Konfirmasi Selesai Membaca
                </button>

                <button
                  v-if="activeMateri.cbtJadwal"
                  @click="jumpToCbt"
                  class="px-6 py-2.5 rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-black text-xs hover:scale-105 active:scale-95 transition shadow-lg shadow-indigo-500/30 flex items-center gap-2"
                >
                  <span>🎯 Lanjut Kerjakan Ujian CBT Sekarang</span>
                  <span>➔</span>
                </button>
              </div>
            </div>

            <!-- SLIDE CANVAS BOTTOM META -->
            <div class="relative z-10 flex items-center justify-between border-t border-white/10 pt-4 mt-6 text-[11px] text-gray-400">
              <span class="font-mono">SMKS Muhammadiyah 2 Genteng</span>
              <span class="hidden sm:inline font-mono">Gunakan tombol ◄ ► atau Spasi untuk berpindah slide</span>
              <span class="font-mono">TP: {{ activeMateri.tp?.judul ? activeMateri.tp.judul.substring(0, 35) + '...' : 'Kurikulum Merdeka' }}</span>
            </div>
          </div>
        </div>

        <!-- ============================================== -->
        <!-- 2. VIDEO YOUTUBE EMBED -->
        <!-- ============================================== -->
        <div
          v-else-if="activeMateri.tipe === 'Video YouTube'"
          class="w-full max-w-4xl mx-auto space-y-6"
        >
          <div class="aspect-video w-full rounded-3xl overflow-hidden border border-white/15 shadow-2xl bg-black">
            <iframe
              v-if="youtubeEmbedUrl"
              :src="youtubeEmbedUrl"
              class="w-full h-full"
              frameborder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            ></iframe>
            <div v-else class="w-full h-full flex flex-col items-center justify-center text-center p-6 text-gray-400 space-y-3">
              <div class="text-4xl">⚠️</div>
              <p class="text-sm font-bold text-white">Tautan video YouTube tidak valid.</p>
              <a :href="activeMateri.fileUrl" target="_blank" class="text-xs text-cyan-400 underline">Buka tautan asli di tab baru</a>
            </div>
          </div>

          <div class="bg-slate-900/90 rounded-3xl p-6 border border-white/10 space-y-3">
            <h3 class="text-lg font-black text-white">Deskripsi Video Pembelajaran</h3>
            <p class="text-sm text-gray-300 leading-relaxed">{{ activeMateri.deskripsi || 'Simak video hingga selesai untuk memahami materi.' }}</p>
          </div>
        </div>

        <!-- ============================================== -->
        <!-- 3. DOKUMEN / PDF VIEWER -->
        <!-- ============================================== -->
        <div
          v-else
          class="w-full max-w-5xl mx-auto space-y-6"
        >
          <div class="w-full h-[70vh] rounded-3xl overflow-hidden border border-white/15 shadow-2xl bg-slate-900">
            <iframe
              v-if="activeMateri.fileUrl"
              :src="activeMateri.fileUrl"
              class="w-full h-full"
              frameborder="0"
            ></iframe>
            <div v-else class="w-full h-full flex flex-col items-center justify-center text-center p-6 space-y-3">
              <div class="text-4xl">📄</div>
              <p class="text-sm font-bold text-white">Dokumen digital tidak memiliki tautan penampil langsung.</p>
              <a
                v-if="activeMateri.filePath"
                :href="activeMateri.filePath"
                download
                class="px-5 py-2.5 rounded-2xl bg-cyan-500 text-slate-950 font-black text-xs"
              >
                Unduh Berkas PDF
              </a>
            </div>
          </div>
        </div>

      </main>

      <!-- BOTTOM PRESENTATION CONTROLS (FOR SLIDER PPT) -->
      <footer
        v-if="activeMateri.tipe === 'Slider PPT' && currentSlides.length > 1"
        class="fixed bottom-0 inset-x-0 z-50 border-t border-cyan-500/30 px-3 sm:px-8 py-2.5 sm:py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] flex items-center justify-between gap-2 sm:gap-4 backdrop-blur-2xl shadow-[0_-10px_35px_rgba(0,0,0,0.95)]"
        :class="currentThemeClass.header"
      >
        <!-- Prev Button -->
        <button
          @click="prevSlide"
          :disabled="currentSlideIndex === 0"
          type="button"
          class="px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-white/10 hover:bg-white/20 text-xs font-black transition disabled:opacity-25 active:scale-95 flex items-center gap-1.5 text-white shrink-0 min-h-[42px]"
          title="Slide Sebelumnya (◄)"
        >
          <span>◀</span>
          <span class="text-[11px] sm:text-xs">Sebelumnya</span>
        </button>

        <!-- Slide Indicators / Thumbnails -->
        <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto max-w-[130px] sm:max-w-md py-1 no-scrollbar">
          <button
            v-for="(s, idx) in currentSlides"
            :key="idx"
            @click="goToSlide(idx)"
            type="button"
            class="h-2 sm:h-2.5 rounded-full transition-all duration-200 shrink-0"
            :class="currentSlideIndex === idx ? 'w-6 sm:w-10 bg-cyan-400 shadow-md shadow-cyan-400/50' : 'w-2 sm:w-2.5 bg-white/20 hover:bg-white/50'"
            :title="`Pindah ke slide ${idx + 1}: ${s.judul}`"
          ></button>
        </div>

        <!-- Next Button -->
        <button
          @click="nextSlide"
          :disabled="currentSlideIndex === currentSlides.length - 1"
          type="button"
          class="px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl text-xs font-black transition disabled:opacity-25 active:scale-95 flex items-center gap-1.5 shadow-lg shrink-0 min-h-[42px]"
          :class="currentThemeClass.accentBg"
          title="Slide Berikutnya (►)"
        >
          <span class="text-[11px] sm:text-xs">Berikutnya</span>
          <span>▶</span>
        </button>
      </footer>
    </div>
    </Teleport>

  </div>
</template>

<style>
/* ========================================================
   PRESENTATION MARKDOWN & TABLES STYLING (EXECUTIVE DECK)
   ======================================================== */
.slide-markdown-content {
  color: inherit;
}

.slide-markdown-content p {
  margin-bottom: 1.25rem;
  line-height: 1.75;
}

.slide-markdown-content strong {
  color: #ffffff;
  font-weight: 800;
}

.slide-markdown-content em {
  font-style: italic;
  opacity: 0.9;
}

/* Presentation Table (Styled like financial & tech keynotes) */
.slide-markdown-content table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin: 1.5rem 0;
  border-radius: 1.25rem;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(12px);
  box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.4);
}

.slide-markdown-content thead {
  background: rgba(2, 6, 23, 0.85);
  border-bottom: 2px solid rgba(6, 182, 212, 0.4);
}

.slide-markdown-content th {
  padding: 1rem 1.25rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.075em;
  color: #38bdf8;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.slide-markdown-content td {
  padding: 1rem 1.25rem;
  font-size: 0.875rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  vertical-align: middle;
}

.slide-markdown-content tbody tr:last-child td {
  border-bottom: none;
}

.slide-markdown-content tbody tr:nth-child(even) {
  background: rgba(255, 255, 255, 0.02);
}

.slide-markdown-content tbody tr:hover {
  background: rgba(6, 182, 212, 0.08);
}

/* Keyboard Shortcut & Code Badges */
.slide-markdown-content code {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.25rem 0.6rem;
  border-radius: 0.5rem;
  background: rgba(30, 41, 59, 0.9);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.35);
  box-shadow: inset 0 -2px 0 0 rgba(0, 0, 0, 0.4), 0 2px 4px rgba(0, 0, 0, 0.2);
  display: inline-block;
  white-space: nowrap;
}

/* Headings inside slide */
.slide-markdown-content h1,
.slide-markdown-content h2,
.slide-markdown-content h3,
.slide-markdown-content h4 {
  font-weight: 900;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  color: #ffffff;
}

/* Lists styling */
.slide-markdown-content ul {
  list-style-type: none;
  padding-left: 0;
  margin: 1.25rem 0;
  space-y: 0.75rem;
}

.slide-markdown-content ul > li {
  position: relative;
  padding-left: 1.75rem;
  margin-bottom: 0.6rem;
  line-height: 1.6;
}

.slide-markdown-content ul > li::before {
  content: '▸';
  position: absolute;
  left: 0.25rem;
  top: 0;
  color: #38bdf8;
  font-weight: 900;
  font-size: 1.1rem;
}

.slide-markdown-content ol {
  padding-left: 1.5rem;
  margin: 1.25rem 0;
  list-style-type: decimal;
}

.slide-markdown-content ol > li {
  margin-bottom: 0.6rem;
  line-height: 1.6;
}

/* Blockquotes */
.slide-markdown-content blockquote {
  border-left: 4px solid #38bdf8;
  padding: 0.75rem 1.25rem;
  margin: 1.25rem 0;
  background: rgba(56, 189, 248, 0.08);
  border-radius: 0 1rem 1rem 0;
  font-style: italic;
}
</style>
