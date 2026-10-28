<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { showToast } from '@/Utils/sweetalert';
import MateriPreviewModal from './MateriPreviewModal.vue';

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

// 1. Filter States
const selectedSemester = ref(getInitialSemester());
const selectedTpFilter = ref('Semua');
const selectedTipeFilter = ref('Semua');
const selectedStatusFilter = ref('Semua'); // 'Semua', 'ON', 'OFF'
const searchQuery = ref('');
const debouncedSearchQuery = ref('');
let searchTimer = null;

// Debounce Search 300ms for optimal UI responsiveness
watch(searchQuery, (newVal) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    debouncedSearchQuery.value = newVal.trim().toLowerCase();
    currentPage.value = 1;
  }, 300);
});

onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer);
});

// Filter TP options based on active semester
const availableTps = computed(() => {
  let list = props.tpList || [];
  if (selectedSemester.value !== 'Semua') {
    list = list.filter(t => (t.semester || 'Ganjil') === selectedSemester.value);
  }
  return list;
});

function formatTpOption(tp) {
  const judul = tp.judul || '';
  const truncated = judul.length > 45 ? judul.substring(0, 42) + '...' : judul;
  return `${tp.kode}: ${truncated}`;
}

watch(selectedSemester, (newSem) => {
  if (typeof window !== 'undefined' && ['Ganjil', 'Genap'].includes(newSem)) {
    try {
      localStorage.setItem('guru_active_semester', newSem);
    } catch (e) {}
  }
  if (selectedTpFilter.value !== 'Semua') {
    const exists = availableTps.value.some(t => t.id === Number(selectedTpFilter.value));
    if (!exists) {
      selectedTpFilter.value = 'Semua';
    }
  }
  currentPage.value = 1;
});

watch([selectedTpFilter, selectedTipeFilter, selectedStatusFilter], () => {
  currentPage.value = 1;
});

// 2. Single-pass O(N) Filtering
const filteredMateriList = computed(() => {
  const q = debouncedSearchQuery.value;
  const sem = selectedSemester.value;
  const tpF = selectedTpFilter.value;
  const tipeF = selectedTipeFilter.value;
  const statF = selectedStatusFilter.value;
  const raw = props.materiList || [];

  return raw.filter((m) => {
    if (sem !== 'Semua') {
      const mSem = m.semester || m.tp?.semester || 'Ganjil';
      if (mSem !== sem) return false;
    }
    if (tpF !== 'Semua') {
      if (String(m.tpId || m.tp?.id) !== String(tpF)) return false;
    }
    if (tipeF !== 'Semua') {
      if (m.tipe !== tipeF) return false;
    }
    if (statF !== 'Semua') {
      const isPub = Boolean(m.isPublished);
      if (statF === 'ON' && !isPub) return false;
      if (statF === 'OFF' && isPub) return false;
    }
    if (q) {
      const j = (m.judul || '').toLowerCase();
      const mapel = (m.mapel || m.mapelNama || '').toLowerCase();
      const k = (m.targetKelas || m.kelas || '').toLowerCase();
      const desk = (m.deskripsiSingkat || '').toLowerCase();
      const tpK = (m.tpKode || '').toLowerCase();
      if (!j.includes(q) && !mapel.includes(q) && !k.includes(q) && !desk.includes(q) && !tpK.includes(q)) {
        return false;
      }
    }
    return true;
  });
});

// 3. Sorting Engine
const sortBy = ref('id'); // 'id', 'judul', 'mapel', 'tipe', 'targetKelas', 'readsCount', 'isPublished'
const sortDir = ref('desc'); // 'asc', 'desc'

function toggleSort(field) {
  if (sortBy.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = field;
    sortDir.value = 'asc';
  }
}

const sortedMateriList = computed(() => {
  const list = [...filteredMateriList.value];
  const field = sortBy.value;
  const dir = sortDir.value === 'asc' ? 1 : -1;

  list.sort((a, b) => {
    let valA = a[field];
    let valB = b[field];

    if (field === 'mapel') {
      valA = a.mapel || a.mapelNama || '';
      valB = b.mapel || b.mapelNama || '';
    } else if (field === 'readsCount') {
      valA = a.readsCount || 0;
      valB = b.readsCount || 0;
    } else if (typeof valA === 'string') {
      return (valA || '').localeCompare(valB || '') * dir;
    } else if (typeof valA === 'boolean') {
      return (valA === valB ? 0 : valA ? 1 : -1) * dir;
    }

    if (valA < valB) return -1 * dir;
    if (valA > valB) return 1 * dir;
    return 0;
  });

  return list;
});

// 4. Pagination Engine
const perPage = ref(10);
const currentPage = ref(1);

const totalItems = computed(() => sortedMateriList.value.length);
const totalPages = computed(() => {
  if (perPage.value === -1) return 1;
  return Math.ceil(totalItems.value / perPage.value) || 1;
});

const paginatedMateriList = computed(() => {
  if (perPage.value === -1) return sortedMateriList.value;
  const start = (currentPage.value - 1) * perPage.value;
  return sortedMateriList.value.slice(start, start + perPage.value);
});

// 5. Bulk Selection Engine
const selectedIds = ref(new Set());

const isAllSelected = computed(() => {
  const currentIds = paginatedMateriList.value.map(m => m.id);
  if (currentIds.length === 0) return false;
  return currentIds.every(id => selectedIds.value.has(id));
});

function toggleSelectAll() {
  const currentIds = paginatedMateriList.value.map(m => m.id);
  if (isAllSelected.value) {
    currentIds.forEach(id => selectedIds.value.delete(id));
  } else {
    currentIds.forEach(id => selectedIds.value.add(id));
  }
}

function toggleSelect(id) {
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id);
  } else {
    selectedIds.value.add(id);
  }
}

const isProcessingBulk = ref(false);

async function executeBulkAction(action) {
  if (selectedIds.value.size === 0 || isProcessingBulk.value) return;
  const count = selectedIds.value.size;

  if (action === 'delete') {
    if (!confirm(`Hapus permanen ${count} materi pembelajaran yang dipilih? Tindakan ini tidak dapat dibatalkan.`)) {
      return;
    }
  }

  isProcessingBulk.value = true;
  try {
    const res = await axios.post(route('guru.materi.bulk-action'), {
      action,
      ids: Array.from(selectedIds.value),
    });
    showToast(res.data?.message || 'Aksi massal berhasil diproses!', 'success');
    selectedIds.value.clear();
    router.reload({ only: ['materiListProp'] });
  } catch (err) {
    showToast(err.response?.data?.message || 'Gagal memproses aksi massal.', 'error');
  } finally {
    isProcessingBulk.value = false;
  }
}

// 6. Preview Modal State
const showPreviewModal = ref(false);
const previewMateriObj = ref(null);

function openPreviewModal(materi) {
  previewMateriObj.value = materi;
  showPreviewModal.value = true;
}

// 7. Toggle Publish Status
const togglingId = ref(null);

async function toggleMateriPublish(materi) {
  togglingId.value = materi.id;
  try {
    const res = await axios.post(route('guru.materi.toggle-status', materi.id));
    if (res.data?.success) {
      materi.isPublished = res.data.isPublished;
      showToast(res.data.message, 'success');
    }
  } catch (err) {
    showToast('Gagal mengubah status publikasi materi.', 'error');
  } finally {
    togglingId.value = null;
  }
}

function openCreateMateri() {
  router.visit(route('guru.materi.create'));
}

function openEditMateri(materiId) {
  router.visit(route('guru.materi.edit', materiId));
}

function confirmDelete(materi) {
  emit('delete-materi', materi.id);
}

function openMonitoring(materiId) {
  router.visit(route('guru.materi.monitoring', materiId));
}

function getTypeBadge(tipe) {
  switch (tipe) {
    case 'Slider PPT':
      return { label: 'Slider Interaktif', color: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30' };
    case 'PDF':
      return { label: 'Dokumen PDF', color: 'bg-rose-500/10 text-rose-400 border-rose-500/30' };
    case 'PPT':
      return { label: 'Slide PPT', color: 'bg-amber-500/10 text-amber-400 border-amber-500/30' };
    case 'Video':
      return { label: 'Video', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' };
    default:
      return { label: tipe || 'Materi', color: 'bg-blue-500/10 text-blue-400 border-blue-500/30' };
  }
}
</script>

<template>
  <div class="space-y-6">

    <!-- Top Bar: Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 rounded-3xl bg-slate-900/60 border border-white/10 backdrop-blur-xl">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse"></span>
          <span class="text-xs font-mono tracking-wider uppercase text-cyan-400 font-bold">Modul Pembelajaran & Media Ajar</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Kelola Materi Belajar</span>
          <span class="text-xs font-mono px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
            {{ totalItems }} Modul
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-1 max-w-xl">
          Materi interaktif slider PPT KaTeX, dokumen PDF/PPT, dan video pembelajaran langsung tersambung ke Ujian CBT & monitoring baca siswa.
        </p>
      </div>

      <div class="flex items-center gap-2.5 shrink-0">
        <button
          @click="openCreateMateri"
          class="flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-extrabold text-xs shadow-lg shadow-cyan-500/25 border border-cyan-400/40 transition active:scale-95"
        >
          <span class="text-base leading-none">＋</span>
          <span>Buat Materi Baru</span>
        </button>
      </div>
    </div>

    <!-- Filter Control Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 p-4 rounded-2xl bg-slate-950/60 border border-white/10">

      <!-- Search Input (300ms Debounced) -->
      <div class="lg:col-span-4 relative">
        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">🔍</span>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari materi, mapel, kelas, TP..."
          class="w-full pl-9 pr-8 py-2.5 bg-slate-900/90 border border-white/15 rounded-xl text-xs text-white placeholder-slate-400 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 outline-none transition"
        />
        <button
          v-if="searchQuery"
          @click="searchQuery = ''"
          class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white text-xs"
        >✕</button>
      </div>

      <!-- Semester Selector -->
      <div class="lg:col-span-2">
        <select
          v-model="selectedSemester"
          class="w-full py-2.5 px-3 bg-slate-900/90 border border-white/15 rounded-xl text-xs text-white focus:border-cyan-400 outline-none transition"
        >
          <option value="Semua">Semua Semester</option>
          <option value="Ganjil">Semester Ganjil</option>
          <option value="Genap">Semester Genap</option>
        </select>
      </div>

      <!-- TP Filter Selector -->
      <div class="lg:col-span-3">
        <select
          v-model="selectedTpFilter"
          class="w-full py-2.5 px-3 bg-slate-900/90 border border-white/15 rounded-xl text-xs text-white focus:border-cyan-400 outline-none transition truncate"
        >
          <option value="Semua">Semua Tujuan Pembelajaran</option>
          <option v-for="tp in availableTps" :key="tp.id" :value="tp.id">
            {{ formatTpOption(tp) }}
          </option>
        </select>
      </div>

      <!-- Tipe Materi Selector -->
      <div class="lg:col-span-2">
        <select
          v-model="selectedTipeFilter"
          class="w-full py-2.5 px-3 bg-slate-900/90 border border-white/15 rounded-xl text-xs text-white focus:border-cyan-400 outline-none transition"
        >
          <option value="Semua">Semua Format</option>
          <option value="Slider PPT">Slider Interaktif</option>
          <option value="PDF">Dokumen PDF</option>
          <option value="PPT">Slide PPT</option>
          <option value="Video">Video Ajar</option>
        </select>
      </div>

      <!-- Status ON/OFF Selector -->
      <div class="lg:col-span-1">
        <select
          v-model="selectedStatusFilter"
          class="w-full py-2.5 px-2 bg-slate-900/90 border border-white/15 rounded-xl text-xs text-white focus:border-cyan-400 outline-none transition text-center"
        >
          <option value="Semua">Semua</option>
          <option value="ON">Rilis (ON)</option>
          <option value="OFF">Draft (OFF)</option>
        </select>
      </div>
    </div>

    <!-- Multi-select Bulk Action Floating Bar -->
    <div
      v-if="selectedIds.size > 0"
      class="sticky top-20 z-30 p-3 sm:p-4 rounded-2xl bg-slate-950/95 border-2 border-cyan-500/50 shadow-2xl shadow-cyan-500/20 backdrop-blur-xl flex flex-wrap items-center justify-between gap-3 animate-in fade-in slide-in-from-top-2 duration-200"
    >
      <div class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-300 font-mono font-black text-sm flex items-center justify-center border border-cyan-500/30">
          {{ selectedIds.size }}
        </span>
        <span class="text-xs sm:text-sm font-extrabold text-white">Materi Terpilih</span>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <button
          @click="executeBulkAction('publish')"
          :disabled="isProcessingBulk"
          class="px-3 py-1.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 text-xs font-bold transition flex items-center gap-1.5"
        >
          <span>👁️</span>
          <span>Rilis (ON)</span>
        </button>

        <button
          @click="executeBulkAction('unpublish')"
          :disabled="isProcessingBulk"
          class="px-3 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 text-xs font-bold transition flex items-center gap-1.5"
        >
          <span>🔒</span>
          <span>Draft (OFF)</span>
        </button>

        <button
          @click="executeBulkAction('delete')"
          :disabled="isProcessingBulk"
          class="px-3 py-1.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/40 text-rose-300 text-xs font-bold transition flex items-center gap-1.5"
        >
          <span>🗑️</span>
          <span>Hapus</span>
        </button>

        <button
          @click="selectedIds.clear()"
          class="px-2.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 text-xs font-medium transition"
        >
          Batal
        </button>
      </div>
    </div>

    <!-- Main Content Area: Desktop Table & Mobile Cards -->
    <div v-if="paginatedMateriList.length > 0">

      <!-- Desktop Table View (hidden on mobile, visible on desktop) -->
      <div class="hidden md:block overflow-hidden rounded-3xl bg-slate-900/60 border border-white/10 shadow-xl backdrop-blur-xl">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs min-w-[850px]">

            <!-- Table Header with Sortable Controls -->
            <thead class="bg-slate-950/80 border-b border-white/10 text-slate-400 font-extrabold uppercase tracking-wider text-[11px]">
              <tr>
                <th class="py-4 px-4 w-10 text-center">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    @change="toggleSelectAll"
                    class="rounded border-white/20 bg-slate-800 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-slate-900 cursor-pointer"
                  />
                </th>
                <th class="py-4 px-4 cursor-pointer hover:text-white transition" @click="toggleSort('judul')">
                  <div class="flex items-center gap-1.5">
                    <span>Materi & Modul</span>
                    <span v-if="sortBy === 'judul'" class="text-cyan-400">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-4 px-3 cursor-pointer hover:text-white transition" @click="toggleSort('mapel')">
                  <div class="flex items-center gap-1.5">
                    <span>Mata Pelajaran</span>
                    <span v-if="sortBy === 'mapel'" class="text-cyan-400">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-4 px-3 cursor-pointer hover:text-white transition" @click="toggleSort('tipe')">
                  <div class="flex items-center gap-1.5">
                    <span>Format</span>
                    <span v-if="sortBy === 'tipe'" class="text-cyan-400">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-4 px-3 cursor-pointer hover:text-white transition" @click="toggleSort('targetKelas')">
                  <div class="flex items-center gap-1.5">
                    <span>Target Kelas</span>
                    <span v-if="sortBy === 'targetKelas'" class="text-cyan-400">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-4 px-3 cursor-pointer hover:text-white transition text-center" @click="toggleSort('readsCount')">
                  <div class="flex items-center justify-center gap-1.5">
                    <span>📊 Monitoring</span>
                    <span v-if="sortBy === 'readsCount'" class="text-cyan-400">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-4 px-3 cursor-pointer hover:text-white transition text-center" @click="toggleSort('isPublished')">
                  <div class="flex items-center justify-center gap-1.5">
                    <span>Status</span>
                    <span v-if="sortBy === 'isPublished'" class="text-cyan-400">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-4 px-4 text-right">Aksi</th>
              </tr>
            </thead>

            <!-- Table Body -->
            <tbody class="divide-y divide-white/5">
              <tr
                v-for="materi in paginatedMateriList"
                :key="materi.id"
                class="hover:bg-white/[0.03] transition-colors"
                :class="selectedIds.has(materi.id) ? 'bg-cyan-500/[0.05]' : ''"
              >
                <!-- Checkbox -->
                <td class="py-3.5 px-4 text-center">
                  <input
                    type="checkbox"
                    :checked="selectedIds.has(materi.id)"
                    @change="toggleSelect(materi.id)"
                    class="rounded border-white/20 bg-slate-800 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-slate-900 cursor-pointer"
                  />
                </td>

                <!-- Materi Title & Info -->
                <td class="py-3.5 px-4">
                  <div class="flex flex-col">
                    <button
                      @click="openPreviewModal(materi)"
                      class="font-black text-white hover:text-cyan-300 text-left transition text-sm leading-snug line-clamp-1"
                      title="Klik untuk pratinjau"
                    >
                      {{ materi.judul }}
                    </button>
                    <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-400">
                      <span v-if="materi.kegiatan?.length" class="text-cyan-400 font-mono">
                        {{ materi.kegiatan.length }} Slide
                      </span>
                      <span v-if="materi.cbtJadwalId" class="inline-flex items-center gap-1 text-emerald-400 font-bold bg-emerald-500/10 px-1.5 py-0.2 rounded border border-emerald-500/20">
                        <span>📝 Terhubung CBT</span>
                      </span>
                      <span class="text-slate-500">Semester {{ materi.semester || 'Ganjil' }}</span>
                    </div>
                  </div>
                </td>

                <!-- Mata Pelajaran Saja (TP Dihapus) -->
                <td class="py-3.5 px-3">
                  <span class="font-extrabold text-slate-200">{{ materi.mapel || materi.mapelNama || '-' }}</span>
                </td>

                <!-- Format Badge -->
                <td class="py-3.5 px-3">
                  <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border inline-block whitespace-nowrap', getTypeBadge(materi.tipe).color]">
                    {{ getTypeBadge(materi.tipe).label }}
                  </span>
                </td>

                <!-- Target Kelas -->
                <td class="py-3.5 px-3">
                  <span class="px-2.5 py-1 rounded-xl bg-white/5 border border-white/10 text-slate-300 font-bold text-[11px] whitespace-nowrap">
                    {{ materi.targetKelas || 'Semua Kelas' }}
                  </span>
                </td>

                <!-- Akses & Monitoring Button -->
                <td class="py-3.5 px-3 text-center">
                  <button
                    @click="openMonitoring(materi.id)"
                    class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 hover:border-cyan-400 text-cyan-300 hover:text-white text-xs font-bold transition active:scale-95 shadow-sm"
                    title="Buka Analitik & Monitoring Baca Siswa"
                  >
                    <span>📊</span>
                    <span>Monitoring</span>
                    <span class="text-[11px] font-mono px-1.5 py-0.5 rounded-md bg-cyan-500/20 text-cyan-200 border border-cyan-400/20">
                      {{ materi.readsCompletedCount || 0 }}/{{ materi.readsCount || 0 }}
                    </span>
                  </button>
                </td>

                <!-- Publish Status Toggle -->
                <td class="py-3.5 px-3 text-center">
                  <button
                    @click="toggleMateriPublish(materi)"
                    :disabled="togglingId === materi.id"
                    class="px-2.5 py-1 rounded-full text-[10px] font-extrabold border transition shadow-sm"
                    :class="materi.isPublished ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/30' : 'bg-slate-800 text-slate-400 border-white/10 hover:bg-slate-700'"
                  >
                    <span v-if="togglingId === materi.id">...</span>
                    <span v-else>{{ materi.isPublished ? 'ON (Rilis)' : 'OFF (Draft)' }}</span>
                  </button>
                </td>

                <!-- Action Buttons -->
                <td class="py-3.5 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      @click="openPreviewModal(materi)"
                      class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-cyan-400 border border-white/10 transition"
                      title="Pratinjau Materi"
                    >
                      👁️
                    </button>
                    <button
                      @click="openEditMateri(materi.id)"
                      class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-amber-400 border border-white/10 transition"
                      title="Edit Materi"
                    >
                      ✏️
                    </button>
                    <button
                      @click="confirmDelete(materi)"
                      class="p-1.5 rounded-lg bg-white/5 hover:bg-rose-500/20 text-rose-400 border border-white/10 transition"
                      title="Hapus Materi"
                    >
                      🗑️
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Mobile Optimized Card List View (md:hidden) -->
      <div class="md:hidden space-y-3">
        <!-- Mobile Select All Bar -->
        <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-900/80 border border-white/10 text-xs font-bold text-gray-300">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              :checked="isAllSelected"
              @change="toggleSelectAll"
              class="rounded border-white/20 bg-slate-800 text-cyan-500 focus:ring-cyan-500 cursor-pointer"
            />
            <span>Pilih Semua di Halaman Ini</span>
          </label>
          <span class="text-[11px] font-mono text-cyan-400">{{ selectedIds.size }} dipilih</span>
        </div>

        <!-- Mobile Cards -->
        <div
          v-for="materi in paginatedMateriList"
          :key="'mob-' + materi.id"
          class="p-4 rounded-2xl bg-slate-900/80 border border-white/10 shadow-lg space-y-3 transition-all"
          :class="selectedIds.has(materi.id) ? 'ring-2 ring-cyan-500/50 bg-cyan-950/20' : ''"
        >
          <!-- Card Header: Checkbox + Judul + Status Badge -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2.5 min-w-0">
              <input
                type="checkbox"
                :checked="selectedIds.has(materi.id)"
                @change="toggleSelect(materi.id)"
                class="mt-1 rounded border-white/20 bg-slate-800 text-cyan-500 focus:ring-cyan-500 cursor-pointer shrink-0"
              />
              <div class="min-w-0">
                <span class="text-[10px] font-black font-mono text-cyan-400 block">{{ materi.tpKode || 'TP-01' }}</span>
                <h4 class="font-bold text-white text-sm leading-snug line-clamp-2">{{ materi.judul }}</h4>
              </div>
            </div>

            <!-- Publish Switch -->
            <button
              type="button"
              @click="toggleMateriPublish(materi)"
              :disabled="togglingId === materi.id"
              class="px-2.5 py-1 rounded-full text-[10px] font-extrabold border transition shadow-sm shrink-0 min-h-[36px] flex items-center justify-center cursor-pointer"
              :class="materi.isPublished ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/30' : 'bg-slate-800 text-slate-400 border-white/10 hover:bg-slate-700'"
            >
              <span v-if="togglingId === materi.id">...</span>
              <span v-else>{{ materi.isPublished ? 'ON (Rilis)' : 'OFF (Draft)' }}</span>
            </button>
          </div>

          <!-- Card Meta Badges -->
          <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
            <span class="px-2 py-0.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 font-medium">
              📖 {{ materi.mapel || materi.mapelNama || '-' }}
            </span>
            <span
              class="px-2 py-0.5 rounded-lg border font-semibold"
              :class="getTypeBadge(materi.tipe).color"
            >
              {{ getTypeBadge(materi.tipe).label }}
            </span>
            <span class="px-2 py-0.5 rounded-lg bg-indigo-500/10 border border-indigo-500/25 text-indigo-300 font-mono">
              🏫 {{ materi.targetKelas || 'Semua Kelas' }}
            </span>
          </div>

          <!-- Card Footer: Stats & Actions -->
          <div class="pt-2 border-t border-white/10 flex items-center justify-between gap-2">
            <button
              type="button"
              @click="openMonitoring(materi.id)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 hover:border-cyan-400 text-cyan-300 text-xs font-bold min-h-[38px] transition active:scale-95 cursor-pointer"
              title="Buka Analitik & Monitoring Baca Siswa"
            >
              <span>📊</span>
              <span>Monitoring Siswa</span>
              <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-md bg-cyan-500/20 text-cyan-200 border border-cyan-400/20">
                {{ materi.readsCompletedCount || 0 }}/{{ materi.readsCount || 0 }}
              </span>
            </button>

            <div class="flex items-center gap-1.5">
              <button
                type="button"
                @click="openPreviewModal(materi)"
                class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-cyan-400 border border-white/10 transition text-xs font-bold min-h-[38px] flex items-center gap-1 cursor-pointer"
                title="Pratinjau Materi"
              >
                <span>👁️</span>
                <span>Lihat</span>
              </button>
              <button
                type="button"
                @click="openEditMateri(materi.id)"
                class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-amber-400 border border-white/10 transition text-xs font-bold min-h-[38px] flex items-center gap-1 cursor-pointer"
                title="Edit Materi"
              >
                <span>✏️</span>
              </button>
              <button
                type="button"
                @click="confirmDelete(materi)"
                class="px-3 py-2 rounded-xl bg-white/5 hover:bg-rose-500/20 text-rose-400 border border-white/10 transition text-xs font-bold min-h-[38px] flex items-center gap-1 cursor-pointer"
                title="Hapus Materi"
              >
                <span>🗑️</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination Toolbar -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-slate-950/60 border border-white/10 mt-4 text-xs">
        <div class="flex items-center gap-3 text-slate-400">
          <span>Menampilkan {{ (currentPage - 1) * perPage + 1 }} - {{ Math.min(currentPage * perPage, totalItems) }} dari {{ totalItems }} materi</span>
          <select
            v-model="perPage"
            @change="currentPage = 1"
            class="py-1 px-2 bg-slate-900 border border-white/15 rounded-lg text-white text-xs outline-none focus:border-cyan-400"
          >
            <option :value="10">10 / hal</option>
            <option :value="25">25 / hal</option>
            <option :value="50">50 / hal</option>
            <option :value="-1">Semua</option>
          </select>
        </div>

        <div v-if="totalPages > 1" class="flex items-center gap-1">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="px-3 py-1.5 rounded-xl border border-white/10 bg-white/5 text-slate-300 font-bold hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition"
          >
            ← Prev
          </button>

          <span class="px-3 py-1.5 text-slate-400 font-mono font-bold">
            {{ currentPage }} / {{ totalPages }}
          </span>

          <button
            @click="currentPage++"
            :disabled="currentPage >= totalPages"
            class="px-3 py-1.5 rounded-xl border border-white/10 bg-white/5 text-slate-300 font-bold hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="text-center py-16 px-4 rounded-3xl bg-slate-900/40 border border-dashed border-white/15 space-y-4"
    >
      <div class="w-16 h-16 rounded-3xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-3xl mx-auto border border-cyan-500/20">
        📚
      </div>
      <div>
        <h3 class="text-base sm:text-lg font-black text-white">Belum Ada Materi Ditemukan</h3>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          Tidak ada materi yang sesuai dengan kriteria filter atau pencarian Anda. Silakan ubah filter atau buat materi baru.
        </p>
      </div>
      <button
        @click="openCreateMateri"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-cyan-500 text-slate-950 font-black text-xs hover:bg-cyan-400 transition"
      >
        <span>＋ Buat Materi Pertama</span>
      </button>
    </div>

    <!-- Modular Preview Modal -->
    <MateriPreviewModal
      :show="showPreviewModal"
      :materi="previewMateriObj"
      @close="showPreviewModal = false"
    />

  </div>
</template>