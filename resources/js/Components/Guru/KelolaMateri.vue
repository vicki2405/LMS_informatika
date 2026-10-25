<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { renderFormulaContent } from '@/Utils/formulaRenderer';
import { showToast } from '@/Utils/sweetalert';

const props = defineProps({
  materiList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  tpList: { type: Array, default: () => [] },
  teacherSubject: { type: String, default: 'Informatika' }
});

const emit = defineEmits(['delete-materi']);

function getInitialSemester() {
  try {
    const saved = typeof window !== 'undefined' ? localStorage.getItem('guru_active_semester') : null;
    if (saved && ['Ganjil', 'Genap'].includes(saved)) {
      return saved;
    }
  } catch (e) {}
  const currentMonth = new Date().getMonth() + 1;
  return (currentMonth >= 1 && currentMonth <= 6) ? 'Genap' : 'Ganjil';
}

// Filter Controls State
const selectedSemester = ref(getInitialSemester());
const selectedTpFilter = ref('Semua');
const selectedTipeFilter = ref('Semua');
const selectedStatusFilter = ref('Semua'); // 'Semua', 'ON', 'OFF'
const searchQuery = ref('');

// Filter TP berdasarkan Semester yang dipilih agar tidak membludak ratusan TP
const availableTps = computed(() => {
  let list = props.tpList || [];
  if (selectedSemester.value !== 'Semua') {
    list = list.filter(t => (t.semester || 'Ganjil') === selectedSemester.value);
  }
  return list;
});

// Format teks opsi TP agar tidak melebar merusak render popover browser
function formatTpOption(tp) {
  const judul = tp.judul || '';
  const truncated = judul.length > 45 ? judul.substring(0, 42) + '...' : judul;
  return `${tp.kode}: ${truncated}`;
}

watch(selectedSemester, () => {
  if (selectedTpFilter.value !== 'Semua') {
    const exists = availableTps.value.some(t => t.id === Number(selectedTpFilter.value));
    if (!exists) {
      selectedTpFilter.value = 'Semua';
    }
  }
});

if (typeof window !== 'undefined') {
  try {
    localStorage.setItem('guru_active_semester', selectedSemester.value);
  } catch (e) {}
}

// Toggle Status Loading State
const togglingId = ref(null);

async function togglePublishStatus(materi) {
  if (togglingId.value) return;
  togglingId.value = materi.id;

  const oldStatus = materi.isPublished !== false;
  const newStatus = !oldStatus;

  // Optimistic update
  materi.isPublished = newStatus;

  try {
    const res = await axios.post(route('guru.materi.toggle-status', materi.id), {
      is_published: newStatus
    });
    if (res.data?.success) {
      showToast(
        newStatus
          ? `Materi "${materi.judul}" sekarang AKTIF (ON) dan rilis ke siswa.`
          : `Materi "${materi.judul}" dinonaktifkan (OFF / Draft).`,
        'success'
      );
    }
  } catch (err) {
    // Rollback on error
    materi.isPublished = oldStatus;
    showToast(err.response?.data?.message || 'Gagal mengubah status materi. Silakan coba lagi.', 'error');
  } finally {
    togglingId.value = null;
  }
}

// Filtered Materi List
const filteredMateriList = computed(() => {
  let list = props.materiList || [];

  if (selectedSemester.value !== 'Semua') {
    list = list.filter(m => (m.semester || 'Ganjil') === selectedSemester.value);
  }

  if (selectedTpFilter.value !== 'Semua') {
    list = list.filter(m => m.tpId === Number(selectedTpFilter.value));
  }

  if (selectedTipeFilter.value !== 'Semua') {
    list = list.filter(m => m.tipe === selectedTipeFilter.value);
  }

  if (selectedStatusFilter.value === 'ON') {
    list = list.filter(m => m.isPublished !== false);
  } else if (selectedStatusFilter.value === 'OFF') {
    list = list.filter(m => m.isPublished === false);
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(m => (m.judul || '').toLowerCase().includes(q) || (m.deskripsiSingkat && m.deskripsiSingkat.toLowerCase().includes(q)));
  }

  return list;
});

// Stat Summary Calculations
const totalModul = computed(() => filteredMateriList.value.length);
const coveredTpCount = computed(() => {
  const coveredIds = new Set(filteredMateriList.value.map(m => m.tpId));
  return coveredIds.size;
});
const totalConnectedCbt = computed(() => {
  return filteredMateriList.value.filter(m => !!m.cbtJadwal).length;
});
const totalPublished = computed(() => {
  return filteredMateriList.value.filter(m => m.isPublished !== false).length;
});

// Preview Modal State
const showPreviewModal = ref(false);
const previewMateriObj = ref(null);
const previewActiveSlide = ref(0);

function openPreviewModal(materi) {
  previewMateriObj.value = materi;
  previewActiveSlide.value = 0;
  showPreviewModal.value = true;
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

// Monitoring Modal State
const showMonitoringModal = ref(false);
const monitoringLoading = ref(false);
const monitoringData = ref(null);
const monitoringStatusFilter = ref('semua');
const monitoringSearch = ref('');

async function openMonitoringModal(materi) {
  showMonitoringModal.value = true;
  monitoringLoading.value = true;
  monitoringData.value = null;
  monitoringStatusFilter.value = 'semua';
  monitoringSearch.value = '';

  try {
    const res = await axios.get(route('guru.materi.monitoring', materi.id));
    monitoringData.value = res.data;
  } catch (err) {
    console.error('Gagal memuat data monitoring:', err);
  } finally {
    monitoringLoading.value = false;
  }
}

const filteredStudents = computed(() => {
  if (!monitoringData.value?.students) return [];
  let rows = monitoringData.value.students;

  if (monitoringStatusFilter.value !== 'semua') {
    rows = rows.filter(s => s.status === monitoringStatusFilter.value);
  }

  if (monitoringSearch.value.trim()) {
    const q = monitoringSearch.value.toLowerCase();
    rows = rows.filter(s => s.nama.toLowerCase().includes(q) || s.nis.toLowerCase().includes(q));
  }

  return rows;
});

// Navigation to dedicated form pages
function goCreate() {
  router.get(route('guru.materi.create'));
}

function goEdit(materi) {
  router.get(route('guru.materi.edit', materi.id));
}

// Helper: Get Icon based on Content Type
function getTypeBadge(tipe) {
  if (tipe === 'Slider PPT') return { label: '🖥️ Slider PPT', color: 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30' };
  if (tipe === 'Dokumen PDF/PPT') return { label: '📄 Dokumen PDF/PPT', color: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' };
  return { label: '🎥 Video Pembelajaran', color: 'bg-red-500/20 text-red-300 border-red-500/30' };
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">

    <!-- Header Title & Action Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
            <span>Kelola Materi Pembelajaran</span>
            <span class="text-cyan-400 text-base">📚</span>
          </h1>
          <span class="bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 text-xs font-extrabold px-3 py-1 rounded-full">
            Semester {{ selectedSemester }}
          </span>
        </div>
        <p class="text-gray-400 text-xs sm:text-sm font-medium">
          Materi interaktif slider PPT berumus sains, PDF/PPT, dan video pembelajaran langsung terhubung ke ujian CBT & monitoring baca siswa.
        </p>
      </div>

      <div class="flex items-center gap-2.5 shrink-0">
        <button
          @click="goCreate"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-teal-600 to-indigo-600 text-white px-5 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-105 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span class="text-base">+</span>
          <span>Buat Materi Baru</span>
        </button>
      </div>
    </div>

    <!-- Stat Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-cyan-500/20 text-cyan-300 font-black text-sm flex items-center justify-center border border-cyan-500/30 shadow-md">📚</span>
          <span class="text-[11px] font-extrabold text-cyan-300 bg-cyan-500/15 border border-cyan-500/30 px-3 py-1 rounded-full font-mono">TOTAL MATERI</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ totalModul }} Materi</div>
        <div class="text-xs text-gray-400 font-semibold">Semester {{ selectedSemester }}</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-purple-500/20 text-purple-300 font-black text-sm flex items-center justify-center border border-purple-500/30 shadow-md">🎯</span>
          <a href="/guru/dashboard?tab=tp" class="text-[11px] font-extrabold text-purple-300 bg-purple-500/15 border border-purple-500/30 px-3 py-1 rounded-full font-mono hover:bg-purple-500/25 transition">COVERED TP ↗</a>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ coveredTpCount }} / {{ tpList.length }} TP</div>
        <div class="text-xs text-gray-400 font-semibold">Memiliki Materi Aktif</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-indigo-500/20 text-indigo-300 font-black text-sm flex items-center justify-center border border-indigo-500/30 shadow-md">🎯</span>
          <span class="text-[11px] font-extrabold text-indigo-300 bg-indigo-500/15 border border-indigo-500/30 px-3 py-1 rounded-full font-mono">TERHUBUNG CBT</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ totalConnectedCbt }} Materi</div>
        <div class="text-xs text-gray-400 font-semibold">Terintegrasi Evaluasi CBT</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-emerald-500/20 text-emerald-300 font-black text-sm flex items-center justify-center border border-emerald-500/30 shadow-md">🟢</span>
          <span class="text-[11px] font-extrabold text-emerald-300 bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 rounded-full font-mono">RILIS SISWA</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ totalPublished }} Rilis</div>
        <div class="text-xs text-gray-400 font-semibold">Status Dilihat Siswa</div>
      </div>
    </div>

    <!-- Filter Controls Bar -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-4 sm:p-5 border border-white/10 shadow-xl">
      <div class="grid grid-cols-1 md:grid-cols-6 lg:grid-cols-12 gap-3.5 items-end">
        <!-- Semester -->
        <div class="md:col-span-3 lg:col-span-2">
          <label class="block text-[11px] font-bold text-cyan-300 uppercase mb-1">📅 Semester</label>
          <select
            v-model="selectedSemester"
            class="w-full bg-slate-800 border border-cyan-500/40 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-cyan-300 font-extrabold focus:border-cyan-400 outline-none min-h-[44px]"
          >
            <option value="Ganjil">Semester Ganjil</option>
            <option value="Genap">Semester Genap</option>
            <option value="Semua">Semua Semester</option>
          </select>
        </div>

        <!-- Sasaran TP -->
        <div class="md:col-span-3 lg:col-span-3">
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1">🎯 Sasaran TP</label>
          <select
            v-model="selectedTpFilter"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
          >
            <option value="Semua">Semua TP ({{ availableTps.length }} TP)</option>
            <option v-for="tp in availableTps" :key="tp.id" :value="tp.id">{{ formatTpOption(tp) }}</option>
          </select>
        </div>

        <!-- Format Konten -->
        <div class="md:col-span-3 lg:col-span-2">
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1">📦 Format</label>
          <select
            v-model="selectedTipeFilter"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
          >
            <option value="Semua">Semua Format</option>
            <option value="Slider PPT">🖥️ Slider PPT</option>
            <option value="Dokumen PDF/PPT">📄 Dokumen PDF/PPT</option>
            <option value="Video YouTube">🎥 Video</option>
          </select>
        </div>

        <!-- Status Rilis -->
        <div class="md:col-span-3 lg:col-span-2">
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1">⚡ Status</label>
          <select
            v-model="selectedStatusFilter"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
          >
            <option value="Semua">Semua Status</option>
            <option value="ON">🟢 ON (Rilis)</option>
            <option value="OFF">⚪ OFF (Draft)</option>
          </select>
        </div>

        <!-- Search Query -->
        <div class="md:col-span-6 lg:col-span-3">
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1">🔍 Cari Judul Materi</label>
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari materi..."
              class="w-full bg-slate-800 border border-white/10 rounded-2xl pl-4 pr-9 py-2.5 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-cyan-400 outline-none min-h-[44px]"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-xs font-bold"
            >✕</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MAIN MATERI TABLE & CARD VIEW -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      <!-- Table Header Bar -->
      <div class="p-4 sm:p-5 border-b border-white/10 bg-slate-950/60 flex items-center justify-between">
        <h3 class="text-xs font-black text-white uppercase tracking-wider">📚 Daftar Materi Pembelajaran:</h3>
        <span class="text-xs text-gray-400 font-mono font-bold">Total {{ filteredMateriList.length }} Materi</span>
      </div>

      <!-- Desktop Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="px-4 py-4 text-center w-12 border-r border-white/5">No</th>
              <th class="px-5 py-4 w-32 border-r border-white/5">Target TP</th>
              <th class="px-6 py-4 border-r border-white/5">Materi & Integrasi</th>
              <th class="px-4 py-4 w-36 border-r border-white/5">Format</th>
              <th class="px-4 py-4 text-center w-36 border-r border-white/5">Monitoring Baca</th>
              <th class="px-4 py-4 text-center w-28 border-r border-white/5">Status</th>
              <th class="px-5 py-4 text-right w-44">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr v-for="(materi, index) in filteredMateriList" :key="materi.id" class="hover:bg-white/5 transition-colors">
              <td class="px-4 py-4 text-center font-mono text-gray-400 text-xs border-r border-white/5">
                {{ index + 1 }}
              </td>

              <!-- Target TP Badge -->
              <td class="px-5 py-4 border-r border-white/5">
                <div class="flex flex-col items-start gap-1">
                  <span class="bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 text-xs font-black px-3 py-1 rounded-full">
                    {{ materi.tpKode || 'TP-01' }}
                  </span>
                  <span v-if="materi.mapelNama" class="bg-indigo-500/15 text-indigo-300 border border-indigo-500/25 text-[10px] font-bold px-2 py-0.5 rounded-full">
                    {{ materi.mapelNama }}
                  </span>
                </div>
              </td>

              <!-- Judul Materi & Integrasi CBT -->
              <td class="px-6 py-4 border-r border-white/5">
                <div class="space-y-1.5">
                  <div
                    class="font-black text-white text-sm hover:text-cyan-300 transition-colors cursor-pointer"
                    @click="openPreviewModal(materi)"
                  >
                    {{ materi.judul }}
                  </div>
                  <div class="text-gray-400 text-xs line-clamp-1 font-normal">{{ materi.deskripsiSingkat }}</div>

                  <!-- Integrasi CBT Badge -->
                  <div class="flex items-center gap-2 flex-wrap pt-0.5">
                    <span v-if="materi.cbtJadwal" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                      🎯 Terhubung CBT: {{ materi.cbtJadwal.judul }}
                    </span>
                    <span v-if="materi.kegiatan?.length" class="text-[10px] text-slate-400">
                      🧩 {{ materi.kegiatan.length }} slide/tahap
                    </span>
                    <span class="text-[10px] text-cyan-400 font-semibold">
                      🏛️ {{ materi.targetKelas || 'Semua Kelas' }}
                    </span>
                  </div>
                </div>
              </td>

              <!-- Format Badge -->
              <td class="px-4 py-4 border-r border-white/5">
                <span :class="['text-xs font-extrabold px-3 py-1 rounded-full border inline-flex items-center gap-1.5', getTypeBadge(materi.tipe).color]">
                  {{ getTypeBadge(materi.tipe).label }}
                </span>
              </td>

              <!-- Monitoring Siswa Button / Badge -->
              <td class="px-4 py-4 text-center border-r border-white/5">
                <button
                  @click="openMonitoringModal(materi)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 text-xs font-bold transition active:scale-95 shadow-sm"
                  title="Klik untuk melihat siapa saja yang telah membuka/membaca materi ini"
                >
                  <span>👥</span>
                  <span>Pantau Siswa</span>
                  <span v-if="materi.readsCount" class="ml-0.5 px-1.5 py-0.2 rounded-full bg-cyan-500 text-slate-950 font-black text-[10px]">
                    {{ materi.readsCount }}
                  </span>
                </button>
              </td>

              <!-- Status Publikasi (Setting ON / OFF) -->
              <td class="px-4 py-4 text-center border-r border-white/5">
                <button
                  type="button"
                  @click="togglePublishStatus(materi)"
                  :disabled="togglingId === materi.id"
                  class="group inline-flex items-center gap-2 px-3 py-1.5 rounded-full border transition-all duration-200 active:scale-95 focus:outline-none min-h-[36px]"
                  :class="[
                    materi.isPublished !== false
                      ? 'bg-emerald-500/15 border-emerald-500/40 hover:bg-emerald-500/25 text-emerald-300'
                      : 'bg-slate-800/80 border-slate-700 hover:bg-slate-800 text-slate-400'
                  ]"
                  :title="materi.isPublished !== false ? 'Materi sedang ON (Rilis). Klik untuk mematikan ke OFF (Draft)' : 'Materi sedang OFF (Draft). Klik untuk mengaktifkan ke ON (Rilis)'"
                >
                  <!-- Switch Track & Thumb -->
                  <div
                    class="w-8 h-4 rounded-full p-0.5 transition-colors duration-200 flex items-center relative"
                    :class="materi.isPublished !== false ? 'bg-emerald-500 justify-end' : 'bg-slate-600 justify-start'"
                  >
                    <div
                      class="w-3 h-3 rounded-full bg-white shadow-sm transform transition-transform duration-200"
                      :class="{'animate-pulse': togglingId === materi.id}"
                    ></div>
                  </div>
                  <!-- Label Text -->
                  <span class="text-[11px] font-black tracking-wide">
                    {{ materi.isPublished !== false ? 'ON (Rilis)' : 'OFF (Draft)' }}
                  </span>
                </button>
              </td>

              <!-- Action Buttons -->
              <td class="px-5 py-4 text-right shrink-0">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    @click="openPreviewModal(materi)"
                    class="p-2 text-xs font-bold text-cyan-300 bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 transition active:scale-95"
                    title="Pratinjau Tampilan Siswa"
                  >👁️</button>
                  <button
                    @click="goEdit(materi)"
                    class="p-2 text-xs font-bold text-purple-300 bg-purple-500/15 hover:bg-purple-500/30 rounded-xl border border-purple-500/30 transition active:scale-95"
                    title="Edit Materi"
                  >✏️</button>
                  <button
                    @click="$emit('delete-materi', materi.id)"
                    class="p-2 text-xs font-bold text-red-400 bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 transition active:scale-95"
                    title="Hapus Materi"
                  >🗑️</button>
                </div>
              </td>
            </tr>

            <tr v-if="filteredMateriList.length === 0">
              <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-xs font-medium">
                Belum ada materi pembelajaran untuk semester {{ selectedSemester }}. Klik <strong>+ Buat Materi Baru</strong> untuk memulai.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Smartphone Card List View -->
      <div class="block md:hidden p-4 space-y-4">
        <div v-for="materi in filteredMateriList" :key="materi.id" class="bg-slate-950/40 rounded-2xl p-4 border border-white/10 space-y-3 shadow-md">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 text-xs font-black px-2.5 py-0.5 rounded-full">
                {{ materi.tpKode || 'TP-01' }}
              </span>
              <span v-if="materi.mapelNama" class="bg-indigo-500/15 text-indigo-300 border border-indigo-500/25 text-[10px] font-bold px-2 py-0.5 rounded-full">
                {{ materi.mapelNama }}
              </span>
              <span v-if="materi.targetKelas" class="bg-emerald-500/15 text-emerald-300 border border-emerald-500/25 text-[10px] font-bold px-2 py-0.5 rounded-full">
                🏛️ {{ materi.targetKelas }}
              </span>
            </div>
            <!-- Switch Toggle ON / OFF Mobile -->
            <button
              type="button"
              @click="togglePublishStatus(materi)"
              :disabled="togglingId === materi.id"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border transition-all duration-200 active:scale-95 min-h-[38px]"
              :class="[
                materi.isPublished !== false
                  ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300'
                  : 'bg-slate-800/90 border-slate-700 text-slate-400'
              ]"
              :title="materi.isPublished !== false ? 'Status ON: Klik untuk Nonaktifkan (OFF)' : 'Status OFF: Klik untuk Rilis (ON)'"
            >
              <div
                class="w-7 h-3.5 rounded-full p-0.5 transition-colors duration-200 flex items-center relative"
                :class="materi.isPublished !== false ? 'bg-emerald-500 justify-end' : 'bg-slate-600 justify-start'"
              >
                <div
                  class="w-2.5 h-2.5 rounded-full bg-white shadow-sm transition-transform duration-200"
                  :class="{'animate-pulse': togglingId === materi.id}"
                ></div>
              </div>
              <span class="text-[10px] font-extrabold tracking-tight">
                {{ materi.isPublished !== false ? 'ON' : 'OFF' }}
              </span>
            </button>
          </div>

          <div>
            <h3 class="font-extrabold text-white text-sm cursor-pointer" @click="openPreviewModal(materi)">
              {{ materi.judul }}
            </h3>
            <p class="text-xs text-gray-300 leading-relaxed mt-1 line-clamp-2">{{ materi.deskripsiSingkat }}</p>
            <div v-if="materi.cbtJadwal" class="mt-2 text-[11px] text-indigo-300 font-bold flex items-center gap-1">
              <span>🎯 Terhubung CBT:</span> {{ materi.cbtJadwal.judul }}
            </div>
          </div>

          <div class="pt-2 border-t border-white/10 flex items-center justify-between gap-2">
            <span :class="['text-[10px] font-extrabold px-2.5 py-1 rounded-full border', getTypeBadge(materi.tipe).color]">
              {{ getTypeBadge(materi.tipe).label }}
            </span>

            <div class="flex items-center gap-1.5">
              <button
                @click="openMonitoringModal(materi)"
                class="px-2.5 py-1 text-xs font-bold text-cyan-300 bg-cyan-500/15 rounded-lg border border-cyan-500/30"
              >
                👥 Pantau
              </button>
              <button
                @click="goEdit(materi)"
                class="px-2.5 py-1 text-xs font-bold text-purple-300 bg-purple-500/15 rounded-lg border border-purple-500/30"
              >
                ✏️
              </button>
              <button
                @click="$emit('delete-materi', materi.id)"
                class="px-2.5 py-1 text-xs font-bold text-red-400 bg-red-500/15 rounded-lg border border-red-500/30"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL MONITORING PEMBACA SISWA -->
    <div
      v-if="showMonitoringModal"
      class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6"
    >
      <div class="bg-slate-900 border border-white/15 rounded-3xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="p-5 bg-slate-950 border-b border-white/10 flex items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xl">📊</span>
              <h2 class="text-base sm:text-lg font-black text-white">Monitoring Pembaca Materi</h2>
            </div>
            <p v-if="monitoringData?.materi" class="text-xs text-cyan-300 font-semibold truncate mt-0.5">
              {{ monitoringData.materi.judul }} ({{ monitoringData.materi.targetKelas }})
            </p>
          </div>
          <button
            @click="showMonitoringModal = false"
            class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold flex items-center justify-center transition"
          >✕</button>
        </div>

        <!-- Loading State -->
        <div v-if="monitoringLoading" class="p-16 text-center text-slate-400 space-y-3">
          <div class="animate-spin text-3xl">⏳</div>
          <p class="text-sm font-bold">Memuat data aktivitas siswa...</p>
        </div>

        <!-- Monitoring Content Body -->
        <div v-else-if="monitoringData" class="flex-1 overflow-y-auto p-5 space-y-5">
          <!-- Summary Banner -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-slate-950/60 p-4 rounded-2xl border border-white/10">
              <span class="text-[10px] font-black uppercase text-slate-400">Total Siswa Kelas</span>
              <div class="text-2xl font-black text-white mt-1">{{ monitoringData.summary.totalSiswa }} Siswa</div>
            </div>
            <div class="bg-emerald-950/30 p-4 rounded-2xl border border-emerald-500/30">
              <span class="text-[10px] font-black uppercase text-emerald-400">🟢 Selesai Membaca</span>
              <div class="text-2xl font-black text-emerald-300 mt-1">{{ monitoringData.summary.selesaiCount }} Siswa</div>
              <span class="text-[10px] text-emerald-400 font-bold">({{ monitoringData.summary.persentaseSelesai }}%)</span>
            </div>
            <div class="bg-amber-950/30 p-4 rounded-2xl border border-amber-500/30">
              <span class="text-[10px] font-black uppercase text-amber-400">🟡 Sedang Baca</span>
              <div class="text-2xl font-black text-amber-300 mt-1">{{ monitoringData.summary.membacaCount }} Siswa</div>
            </div>
            <div class="bg-slate-950/60 p-4 rounded-2xl border border-white/10">
              <span class="text-[10px] font-black uppercase text-slate-400">⚪ Belum Buka</span>
              <div class="text-2xl font-black text-slate-300 mt-1">{{ monitoringData.summary.belumBukaCount }} Siswa</div>
            </div>
          </div>

          <!-- Progress Bar Global -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs font-bold">
              <span class="text-slate-300">Tingkat Ketuntasan Baca Kelas</span>
              <span class="text-emerald-400 font-mono">{{ monitoringData.summary.persentaseSelesai }}% Tuntas</span>
            </div>
            <div class="w-full h-3 bg-slate-950 rounded-full overflow-hidden border border-white/10 flex">
              <div
                class="bg-emerald-500 transition-all duration-500"
                :style="{ width: monitoringData.summary.persentaseSelesai + '%' }"
              ></div>
            </div>
          </div>

          <!-- Filters & Search -->
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
            <div class="flex items-center gap-1.5 flex-wrap">
              <button
                v-for="st in [
                  { id: 'semua', label: 'Semua Siswa' },
                  { id: 'selesai', label: '🟢 Selesai' },
                  { id: 'sedang_membaca', label: '🟡 Sedang Baca' },
                  { id: 'belum_baca', label: '⚪ Belum Buka' }
                ]"
                :key="st.id"
                @click="monitoringStatusFilter = st.id"
                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition"
                :class="monitoringStatusFilter === st.id
                  ? 'bg-cyan-500 text-white border-cyan-400 shadow'
                  : 'bg-white/5 border-white/10 text-slate-400 hover:bg-white/10'"
              >
                {{ st.label }}
              </button>
            </div>

            <input
              v-model="monitoringSearch"
              type="text"
              placeholder="Cari nama / NIS siswa..."
              class="w-full sm:w-64 bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl px-3.5 py-1.5 text-xs text-white placeholder-slate-500"
            />
          </div>

          <!-- Student List Table -->
          <div class="rounded-2xl border border-white/10 overflow-hidden bg-slate-950/60">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-950 text-slate-400 uppercase text-[10px] font-bold border-b border-white/10">
                <tr>
                  <th class="px-4 py-3 w-10">No</th>
                  <th class="px-4 py-3">Nama Siswa</th>
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3 w-40">Progres Baca</th>
                  <th class="px-4 py-3 text-right">Terakhir Dibaca</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5 font-medium">
                <tr v-for="(s, idx) in filteredStudents" :key="s.id" class="hover:bg-white/5">
                  <td class="px-4 py-3 font-mono text-slate-500">{{ idx + 1 }}</td>
                  <td class="px-4 py-3">
                    <div class="font-bold text-white">{{ s.nama }}</div>
                    <div class="text-[10px] text-slate-400 font-mono">{{ s.nis }}</div>
                  </td>
                  <td class="px-4 py-3">
                    <span v-if="s.status === 'selesai'" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                      🟢 Selesai
                    </span>
                    <span v-else-if="s.status === 'sedang_membaca'" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                      🟡 Sedang Baca
                    </span>
                    <span v-else class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-white/10">
                      ⚪ Belum Buka
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <div class="flex-1 h-2 bg-slate-800 rounded-full overflow-hidden">
                        <div
                          class="h-full transition-all duration-300"
                          :class="s.status === 'selesai' ? 'bg-emerald-400' : (s.progressPersen > 0 ? 'bg-amber-400' : 'bg-transparent')"
                          :style="{ width: (s.progressPersen || 0) + '%' }"
                        ></div>
                      </div>
                      <span class="font-mono text-[10px] font-bold text-slate-300 w-8 text-right">
                        {{ s.progressPersen || 0 }}%
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-right text-[11px] text-slate-400 font-mono">
                    {{ s.terakhirDibaca || '-' }}
                  </td>
                </tr>

                <tr v-if="filteredStudents.length === 0">
                  <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                    Tidak ditemukan data siswa untuk filter yang dipilih.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL PREVIEW DETAIL MATERI GURU -->
    <div
      v-if="showPreviewModal && previewMateriObj"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4 sm:p-6"
    >
      <div class="bg-slate-900 border border-white/15 rounded-3xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        <!-- Header -->
        <div class="p-5 bg-slate-950 border-b border-white/10 flex items-center justify-between gap-4">
          <div class="min-w-0">
            <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border mb-1 inline-block', getTypeBadge(previewMateriObj.tipe).color]">
              {{ getTypeBadge(previewMateriObj.tipe).label }}
            </span>
            <h2 class="text-base sm:text-lg font-black text-white truncate">{{ previewMateriObj.judul }}</h2>
          </div>
          <button
            @click="showPreviewModal = false"
            class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold flex items-center justify-center transition"
          >✕</button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <!-- Slider Mode Preview -->
          <div v-if="previewMateriObj.tipe === 'Slider PPT' && previewMateriObj.kegiatan?.length" class="space-y-4">
            <!-- Slide Selector -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-white/10">
              <button
                v-for="(sl, sIdx) in previewMateriObj.kegiatan"
                :key="sl.id || sIdx"
                @click="previewActiveSlide = sIdx"
                class="px-3 py-1.5 rounded-xl text-xs font-bold border transition shrink-0"
                :class="previewActiveSlide === sIdx ? 'bg-cyan-500 text-white border-cyan-400' : 'bg-white/5 border-white/10 text-slate-400'"
              >
                Slide {{ sIdx + 1 }}: {{ sl.judul || sl.label }}
              </button>
            </div>

            <!-- Slide Content Card -->
            <div
              class="p-6 rounded-2xl border space-y-4 min-h-[300px] transition-all flex flex-col justify-between"
              :class="getPreviewThemeConfig(previewMateriObj.theme).wrapper"
            >
              <div>
                <div class="flex items-center justify-between pb-2 border-b mb-3" :class="getPreviewThemeConfig(previewMateriObj.theme).footer">
                  <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded" :class="getPreviewThemeConfig(previewMateriObj.theme).badge">
                    {{ previewMateriObj.kegiatan[previewActiveSlide]?.label || 'Konsep' }}
                  </span>
                  <span class="text-xs font-mono opacity-60">
                    Slide {{ previewActiveSlide + 1 }} / {{ previewMateriObj.kegiatan.length }}
                  </span>
                </div>

                <h3 class="text-base sm:text-lg font-black mb-4 leading-snug" :class="getPreviewThemeConfig(previewMateriObj.theme).title">
                  {{ previewMateriObj.kegiatan[previewActiveSlide]?.judul }}
                </h3>

                <!-- Layout Check -->
                <div v-if="previewMateriObj.kegiatan[previewActiveSlide]?.layout === 'image_left'" class="grid grid-cols-1 md:grid-cols-12 gap-5 items-center">
                  <div class="md:col-span-5 rounded-2xl overflow-hidden bg-black/40 border border-white/15 max-h-72 flex items-center justify-center">
                    <img v-if="previewMateriObj.kegiatan[previewActiveSlide]?.fileUrl" :src="previewMateriObj.kegiatan[previewActiveSlide].fileUrl" class="w-full object-contain" />
                  </div>
                  <div class="md:col-span-7">
                    <div
                      class="slide-markdown-content text-xs sm:text-sm leading-relaxed"
                      :class="getPreviewThemeConfig(previewMateriObj.theme).prose"
                      v-html="renderFormulaContent(previewMateriObj.kegiatan[previewActiveSlide]?.konten)"
                    ></div>
                  </div>
                </div>

                <div v-else-if="previewMateriObj.kegiatan[previewActiveSlide]?.layout === 'image_right'" class="grid grid-cols-1 md:grid-cols-12 gap-5 items-center">
                  <div class="md:col-span-7 order-2 md:order-1">
                    <div
                      class="slide-markdown-content text-xs sm:text-sm leading-relaxed"
                      :class="getPreviewThemeConfig(previewMateriObj.theme).prose"
                      v-html="renderFormulaContent(previewMateriObj.kegiatan[previewActiveSlide]?.konten)"
                    ></div>
                  </div>
                  <div class="md:col-span-5 order-1 md:order-2 rounded-2xl overflow-hidden bg-black/40 border border-white/15 max-h-72 flex items-center justify-center">
                    <img v-if="previewMateriObj.kegiatan[previewActiveSlide]?.fileUrl" :src="previewMateriObj.kegiatan[previewActiveSlide].fileUrl" class="w-full object-contain" />
                  </div>
                </div>

                <div v-else-if="previewMateriObj.kegiatan[previewActiveSlide]?.layout === 'highlight'" class="p-5 rounded-2xl border border-amber-400/40 bg-amber-500/10 space-y-4">
                  <div
                    class="slide-markdown-content text-xs sm:text-sm leading-relaxed"
                    :class="getPreviewThemeConfig(previewMateriObj.theme).prose"
                    v-html="renderFormulaContent(previewMateriObj.kegiatan[previewActiveSlide]?.konten)"
                  ></div>
                  <div v-if="previewMateriObj.kegiatan[previewActiveSlide]?.fileUrl" class="max-w-md mx-auto rounded-xl overflow-hidden border border-white/20">
                    <img :src="previewMateriObj.kegiatan[previewActiveSlide].fileUrl" class="w-full object-contain max-h-64" />
                  </div>
                </div>

                <div v-else class="space-y-4">
                  <div v-if="previewMateriObj.kegiatan[previewActiveSlide]?.fileUrl" class="max-w-xl mx-auto rounded-2xl overflow-hidden border border-white/20">
                    <img :src="previewMateriObj.kegiatan[previewActiveSlide].fileUrl" class="w-full object-contain max-h-72" />
                  </div>
                  <div
                    class="slide-markdown-content text-xs sm:text-sm leading-relaxed"
                    :class="getPreviewThemeConfig(previewMateriObj.theme).prose"
                    v-html="renderFormulaContent(previewMateriObj.kegiatan[previewActiveSlide]?.konten)"
                  ></div>
                </div>
              </div>

              <!-- Footer Meta -->
              <div class="pt-3 mt-4 border-t flex items-center justify-between text-[10px] opacity-70" :class="getPreviewThemeConfig(previewMateriObj.theme).footer">
                <span>SMKS Muhammadiyah 2 Genteng</span>
                <span>Modul Interaktif Siswa</span>
              </div>
            </div>
          </div>

          <!-- Document / Video Preview -->
          <div v-else class="space-y-4">
            <div v-if="previewMateriObj.fileUrl" class="p-4 rounded-2xl bg-slate-950 border border-white/10 flex items-center justify-between">
              <span class="text-xs text-slate-300 font-bold truncate">Berkas / Tautan Pembelajaran</span>
              <a
                :href="previewMateriObj.fileUrl"
                target="_blank"
                class="px-4 py-2 rounded-xl bg-cyan-500 text-slate-950 font-black text-xs hover:bg-cyan-400 transition"
              >
                Buka Dokumen
              </a>
            </div>

            <div v-if="previewMateriObj.deskripsiSingkat" class="p-5 rounded-2xl bg-slate-950/60 border border-white/10">
              <h4 class="text-xs font-bold text-slate-400 mb-2 uppercase">Petunjuk & Deskripsi</h4>
              <p class="text-sm text-slate-200 leading-relaxed whitespace-pre-line">{{ previewMateriObj.deskripsiSingkat }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* Scoped adjustments for Slide Preview Markdown */
:deep(.slide-markdown-content u) {
  text-decoration: underline;
  text-underline-offset: 3px;
}

:deep(.slide-markdown-content s),
:deep(.slide-markdown-content del) {
  text-decoration: line-through;
  opacity: 0.75;
}

:deep(.slide-markdown-content ol) {
  padding-left: 1.5rem;
  margin: 0.75rem 0;
  list-style-type: decimal;
}

:deep(.slide-markdown-content ol[type="a"]) {
  list-style-type: lower-alpha;
}

:deep(.slide-markdown-content ol[type="A"]) {
  list-style-type: upper-alpha;
}

:deep(.slide-markdown-content ol[type="i"]) {
  list-style-type: lower-roman;
}

:deep(.slide-markdown-content ul) {
  padding-left: 1.25rem;
  margin: 0.75rem 0;
  list-style-type: disc;
}

:deep(.slide-markdown-content li) {
  margin-bottom: 0.35rem;
  line-height: 1.5;
}

:deep(.slide-markdown-content table) {
  width: 100%;
  border-collapse: collapse;
  margin: 0.75rem 0;
  font-size: 0.85em;
}

:deep(.slide-markdown-content th),
:deep(.slide-markdown-content td) {
  border: 1px solid rgba(255, 255, 255, 0.15);
  padding: 0.4rem 0.6rem;
  text-align: left;
}

:deep(.slide-markdown-content th) {
  background: rgba(255, 255, 255, 0.08);
  font-weight: 800;
}

:deep(.slide-markdown-content blockquote) {
  border-left: 3px solid #38bdf8;
  padding: 0.4rem 0.8rem;
  margin: 0.75rem 0;
  background: rgba(56, 189, 248, 0.08);
  border-radius: 0 0.5rem 0.5rem 0;
  font-style: italic;
}

select option {
  background-color: #0f172a;
  color: #f8fafc;
}
</style>
