<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { confirmDelete, showSuccessAlert } from '@/Utils/sweetalert';

const props = defineProps({
  isAdmin: {
    type: Boolean,
    default: false
  },
  kelasListWithCount: Array,
  jurusanList: {
    type: Array,
    default: () => []
  },
  waliKelasList: {
    type: Array,
    default: () => []
  },
  masterKelasAdmin: {
    type: Array,
    default: () => []
  },
  masterWaliAdmin: {
    type: Array,
    default: () => []
  },
  guruList: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits([
  'save-kelas', 'delete-kelas',
  'save-jurusan', 'delete-jurusan',
  'save-wali', 'delete-wali'
]);

const activeKelasTab = ref('daftar_kelas');

// Filter & Pagination State for Master Kelas Datatable
const selectedJurusanFilter = ref('Semua');
const selectedTingkatFilter = ref('Semua');
const searchKelasQuery = ref('');
const perPage = ref(10);
const currentPage = ref(1);

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id || 'default');
const storageKey = computed(() => 'guru_selected_kelas_' + userId.value);

const selectedKelasIds = ref([]);
const tempSelectedKelasIds = ref([]);

onMounted(() => {
  // Seed selectedKelasIds from server-provided kelas ampuan (kelasListWithCount)
  // This is now the source of truth (from DB pivot), localStorage is no longer primary
  if (props.kelasListWithCount && props.kelasListWithCount.length > 0) {
    selectedKelasIds.value = props.kelasListWithCount.map(k => k.id);
  } else if (typeof window !== 'undefined') {
    // Fallback: legacy localStorage for backward compatibility during migration
    const saved = localStorage.getItem(storageKey.value);
    if (saved) {
      try {
        selectedKelasIds.value = JSON.parse(saved);
      } catch (e) {
        selectedKelasIds.value = [];
      }
    }
  }
});

watch(() => props.kelasListWithCount, (newVal) => {
  if (newVal && newVal.length > 0) {
    selectedKelasIds.value = newVal.map(k => k.id);
  }
}, { deep: true });

const filteredKelasList = computed(() => {
  let list = [...(props.kelasListWithCount || [])];

  if (!props.isAdmin) {
    list = list.filter(k => selectedKelasIds.value.includes(k.id));
  }

  if (selectedJurusanFilter.value !== 'Semua') {
    list = list.filter(k => k.jurusan === selectedJurusanFilter.value);
  }

  if (selectedTingkatFilter.value !== 'Semua') {
    list = list.filter(k => k.tingkat === selectedTingkatFilter.value);
  }

  if (searchKelasQuery.value.trim()) {
    const q = searchKelasQuery.value.toLowerCase();
    list = list.filter(k =>
      k.namaKelas.toLowerCase().includes(q) ||
      (k.tingkat && k.tingkat.toLowerCase().includes(q)) ||
      (k.jurusan && k.jurusan.toLowerCase().includes(q)) ||
      (k.waliKelas && k.waliKelas.toLowerCase().includes(q))
    );
  }

  return list.sort((a, b) => a.namaKelas.localeCompare(b.namaKelas, undefined, { numeric: true, sensitivity: 'base' }));
});

// Computed Total Pages
const totalPages = computed(() => {
  if (perPage.value === 'Semua') return 1;
  return Math.ceil(filteredKelasList.value.length / (Number(perPage.value) || 10)) || 1;
});

// Computed Paginated Items
const paginatedKelasList = computed(() => {
  if (perPage.value === 'Semua') return filteredKelasList.value;
  const limit = Number(perPage.value) || 10;
  const start = (currentPage.value - 1) * limit;
  return filteredKelasList.value.slice(start, start + limit);
});

// Computed Range Summary
const pageRangeText = computed(() => {
  const total = filteredKelasList.value.length;
  if (total === 0) return '0 - 0 dari 0 data';
  if (perPage.value === 'Semua') return `1 - ${total} dari ${total} data`;
  const limit = Number(perPage.value) || 10;
  const start = (currentPage.value - 1) * limit + 1;
  const end = Math.min(currentPage.value * limit, total);
  return `${start} - ${end} dari ${total} data`;
});

// Reset pagination on filter changes
watch([searchKelasQuery, selectedJurusanFilter, selectedTingkatFilter, perPage], () => {
  currentPage.value = 1;
});

// Modal States
const showKelasModal = ref(false);
const editingKelas = ref(null);
const kelasForm = ref({ tingkat: 'X', namaKelas: '', jurusan: '', waliKelas: '' });

const showJurusanModal = ref(false);
const editingJurusan = ref(null);
const jurusanForm = ref({ kode: '', nama: '', deskripsi: '' });

// Teacher Selection Modal Search & Grouping State
const searchModalQuery = ref('');
const selectedModalTingkat = ref('Semua');

const groupedMasterKelas = computed(() => {
  let list = [...(props.masterKelasAdmin || [])];
  
  // Filter by search query
  if (searchModalQuery.value.trim()) {
    const q = searchModalQuery.value.toLowerCase();
    list = list.filter(m => m.namaKelas.toLowerCase().includes(q));
  }
  
  // Filter by tingkat
  if (selectedModalTingkat.value !== 'Semua') {
    list = list.filter(m => m.tingkat === selectedModalTingkat.value);
  }
  
  // Group by tingkat
  const groups = {};
  list.forEach(m => {
    const t = m.tingkat || 'X';
    if (!groups[t]) {
      groups[t] = [];
    }
    groups[t].push(m);
  });
  
  // Sort classes in each group
  const result = [];
  ['X', 'XI', 'XII'].forEach(t => {
    if (groups[t] && groups[t].length > 0) {
      result.push({
        tingkat: t,
        classes: groups[t].sort((a, b) => a.namaKelas.localeCompare(b.namaKelas, undefined, { numeric: true, sensitivity: 'base' }))
      });
    }
  });
  return result;
});

// Teacher Mode Auto-Fill Handlers
function onSelectMasterKelas() {
  const found = (props.masterKelasAdmin || []).find(m => m.namaKelas === kelasForm.value.namaKelas);
  if (found) {
    kelasForm.value.jurusan = found.jurusan || '';
    kelasForm.value.tingkat = found.tingkat || 'X';
  }
}

// Master Kelas Handlers
function openAddKelasModal() {
  editingKelas.value = null;
  if (props.isAdmin) {
    kelasForm.value = {
      tingkat: 'X',
      namaKelas: '',
      jurusan: '',
      waliKelas: '',
    };
  } else {
    tempSelectedKelasIds.value = [...selectedKelasIds.value];
  }
  showKelasModal.value = true;
}

function openEditKelas(k) {
  editingKelas.value = k;
  kelasForm.value = {
    id: k.id,
    tingkat: k.tingkat || 'X',
    namaKelas: k.namaKelas || '',
    jurusan: k.jurusan || '',
    waliKelas: k.guruId ? String(k.guruId) : '',
  };
  showKelasModal.value = true;
}

function handleSaveKelas() {
  if (props.isAdmin) {
    if (!kelasForm.value.namaKelas) return;
    emit('save-kelas', {
      editing: editingKelas.value,
      data: { ...kelasForm.value }
    });
  } else {
    // Guru mode: emit the selected kelas IDs for server persistence
    if (tempSelectedKelasIds.value.length === 0) return;
    emit('save-kelas', { kelasIds: [...tempSelectedKelasIds.value] });
    // Optimistically update local selection
    selectedKelasIds.value = [...tempSelectedKelasIds.value];
  }
  showKelasModal.value = false;
}

function handleRemoveKelas(id) {
  if (props.isAdmin) {
    emit('delete-kelas', id);
  } else {
    // Guru mode: emit to Dashboard.vue which will DELETE from server pivot
    emit('delete-kelas', id);
    // Optimistically remove from local selection
    selectedKelasIds.value = selectedKelasIds.value.filter(x => x !== id);
  }
}

// Master Jurusan Handlers
function openAddJurusanModal() {
  editingJurusan.value = null;
  jurusanForm.value = { kode: '', nama: '', deskripsi: '' };
  showJurusanModal.value = true;
}

function openEditJurusan(j) {
  editingJurusan.value = j;
  jurusanForm.value = { ...j };
  showJurusanModal.value = true;
}

function handleSaveJurusan() {
  if (!jurusanForm.value.kode || !jurusanForm.value.nama) return;
  emit('save-jurusan', { editing: editingJurusan.value, data: { ...jurusanForm.value } });
  showJurusanModal.value = false;
}

</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>{{ isAdmin ? 'Pengaturan Master Kelas & Jurusan Sekolah' : 'Menu Kelas Ampuan Guru' }}</span>
          <span class="text-purple-400 text-base">⚙️</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">
          {{ isAdmin ? 'Pusat Pengelolaan Master Data Kelas dan Master Jurusan Sekolah. Data wali kelas dapat diatur melalui menu Wali Kelas.' : 'Daftarkan kelas yang Anda ampu/ajar di sini. Kelas ini otomatis menjadi opsi pilihan di menu Jadwal Guru.' }}
        </p>
      </div>

      <!-- Action Button based on Active Tab -->
      <template v-if="isAdmin">
        <button
          v-if="activeKelasTab === 'daftar_kelas'"
          @click="openAddKelasModal"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-purple-500 via-indigo-600 to-purple-600 text-white px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 hover:scale-105 active:scale-95 transition-all duration-200 shrink-0 min-h-[44px]"
        >
          <span class="text-base">+</span>
          <span>Tambah Master Kelas</span>
        </button>
        
        <button
          v-else-if="activeKelasTab === 'jurusan'"
          @click="openAddJurusanModal"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-purple-500 via-indigo-600 to-purple-600 text-white px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 hover:scale-105 active:scale-95 transition-all duration-200 shrink-0 min-h-[44px]"
        >
          <span class="text-base">+</span>
          <span>Tambah Jurusan Baru</span>
        </button>
      </template>

      <template v-else>
        <button
          @click="openAddKelasModal"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-105 active:scale-95 transition-all duration-200 shrink-0 min-h-[44px]"
        >
          <span class="text-base">+</span>
          <span>Tambah Kelas</span>
        </button>
      </template>
    </div>

    <!-- Info Sync Banner -->
    <div :class="['p-4 rounded-3xl flex items-start gap-3 shadow-xl border', isAdmin ? 'bg-purple-500/10 border-purple-500/30' : 'bg-indigo-500/10 border-indigo-500/30']">
      <span class="text-xl shrink-0">ℹ️</span>
      <div class="text-xs leading-relaxed font-medium">
        <span class="font-extrabold text-white block mb-0.5">
          {{ isAdmin ? 'Admin Super Control Center' : 'Integrasi Data Master Admin' }}
        </span>
        <span class="text-gray-300">
          {{ isAdmin ? 'Halaman ini digunakan oleh Administrator untuk mengelola seluruh struktur master kelas dan jurusan sekolah.' : 'Daftarkan kelas yang Anda ajar di sini. Setiap kelas yang Anda pilih akan langsung aktif sebagai filter di menu penilaian dan kelola materi.' }}
        </span>
      </div>
    </div>

    <!-- Sub Menu Tabs Bar (Full Active for Admin, Hidden for Teacher) -->
    <div v-if="isAdmin" class="flex items-center gap-2 border-b border-white/10 pb-3 overflow-x-auto custom-scrollbar">
      <button
        @click="activeKelasTab = 'daftar_kelas'"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 shrink-0',
          activeKelasTab === 'daftar_kelas'
            ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/25 border border-purple-500/40'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        <span>🏫</span>
        <span>Master Data Kelas</span>
      </button>

      <button
        @click="activeKelasTab = 'jurusan'"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 shrink-0',
          activeKelasTab === 'jurusan'
            ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/25 border border-purple-500/40'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        <span>📁</span>
        <span>Master Data Jurusan</span>
      </button>

    </div>

    <!-- TAB 1: DAFTAR KELAS (ADMIN & TEACHER) -->
    <div v-if="activeKelasTab === 'daftar_kelas'" class="space-y-4">
      
      <!-- Datatable Control Toolbar (Limit, Jurusan Filter, Search) -->
      <div class="bg-slate-900/80 backdrop-blur-xl p-4 rounded-3xl border border-white/10 shadow-xl flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        
        <div class="flex flex-wrap items-center gap-3">
          <!-- Limit Per Page Dropdown -->
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 font-bold uppercase text-[10px]">Tampilkan:</span>
            <select
              v-model="perPage"
              @change="currentPage = 1"
              class="bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs font-bold text-white focus:border-purple-400 outline-none"
            >
              <option :value="10">10 Baris</option>
              <option :value="20">20 Baris</option>
              <option :value="30">30 Baris</option>
              <option :value="50">50 Baris</option>
              <option value="Semua">Semua Data</option>
            </select>
          </div>

          <!-- Tingkat Filter Dropdown -->
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 font-bold uppercase text-[10px]">Tingkat:</span>
            <select
              v-model="selectedTingkatFilter"
              @change="currentPage = 1"
              class="bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs font-bold text-purple-300 focus:border-purple-400 outline-none w-24"
            >
              <option value="Semua">Semua</option>
              <option value="X">Kelas X</option>
              <option value="XI">Kelas XI</option>
              <option value="XII">Kelas XII</option>
            </select>
          </div>

          <!-- Jurusan Filter Dropdown -->
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 font-bold uppercase text-[10px]">Jurusan:</span>
            <select
              v-model="selectedJurusanFilter"
              @change="currentPage = 1"
              class="bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs font-bold text-purple-300 focus:border-purple-400 outline-none max-w-[200px]"
            >
              <option value="Semua">Semua Jurusan</option>
              <option v-for="j in jurusanList" :key="j.id" :value="j.nama">
                {{ j.nama }} ({{ j.kode }})
              </option>
            </select>
          </div>
        </div>

        <!-- Search Input Bar -->
        <div class="relative min-w-[240px]">
          <input
            v-model="searchKelasQuery"
            @input="currentPage = 1"
            type="text"
            placeholder="Cari kelas..."
            class="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2 pl-9 text-xs text-white placeholder-gray-500 focus:border-purple-400 outline-none"
          />
          <svg class="w-4 h-4 text-gray-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>

      </div>

      <!-- Main Datatable Container -->
      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left text-xs sm:text-sm text-gray-300">
            <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
              <tr>
                <th class="px-6 py-4 w-16 text-center">No.</th>
                <th class="px-6 py-4">Tingkat</th>
                <th class="px-6 py-4">Nama Kelas</th>
                <th class="px-6 py-4">Jurusan</th>
                <th class="px-6 py-4">Wali Kelas</th>
                <th class="px-6 py-4 text-center">Siswa</th>
                <th class="px-6 py-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 font-medium">
              <tr v-for="(k, idx) in paginatedKelasList" :key="k.id" class="hover:bg-white/5 transition-colors duration-150">
                <td class="px-6 py-4 text-center font-mono text-xs text-indigo-300 font-bold">
                  {{ perPage === 'Semua' ? idx + 1 : (currentPage - 1) * Number(perPage) + idx + 1 }}
                </td>

                <td class="px-6 py-4">
                  <span class="bg-purple-500/20 text-purple-300 text-xs font-black px-2.5 py-1 rounded-lg border border-purple-500/30 font-mono">
                    {{ k.tingkat || 'X' }}
                  </span>
                </td>

                <td class="px-6 py-4 font-black text-white text-base">
                  {{ k.namaKelas }}
                </td>

                <td class="px-6 py-4">
                  <span class="bg-cyan-500/20 text-cyan-300 text-xs font-bold px-2.5 py-1 rounded-lg border border-cyan-500/30" :title="k.jurusan">
                    {{ k.jurusanKode || k.jurusan || 'Belum Diatur' }}
                  </span>
                </td>

                <td class="px-6 py-4">
                  <span v-if="k.waliKelas && k.waliKelas !== 'Belum Ditugaskan'" class="text-sm font-semibold text-white">
                    {{ k.waliKelas }}
                  </span>
                  <span v-else class="text-xs text-gray-500 italic">
                    Belum Ditugaskan
                  </span>
                </td>

                <td class="px-6 py-4 text-center">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 rounded-xl text-xs font-bold font-mono">
                    👥 {{ k.jumlahSiswa ?? 0 }}
                  </span>
                </td>

                <td class="px-6 py-4 text-right space-x-2">
                  <button v-if="isAdmin" @click="openEditKelas(k)" class="text-cyan-300 hover:text-white font-bold text-xs bg-cyan-500/15 hover:bg-cyan-500/30 px-3.5 py-1.5 rounded-xl border border-cyan-500/30 active:scale-95 transition-all">✏️ Edit</button>
                  <button @click="handleRemoveKelas(k.id)" class="text-red-400 hover:text-red-300 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 px-3.5 py-1.5 rounded-xl border border-red-500/30 active:scale-95 transition-all">🗑️ Hapus</button>
                </td>
              </tr>
              <tr v-if="!paginatedKelasList || paginatedKelasList.length === 0">
                <td colspan="7" class="px-6 py-12 text-center text-gray-400 font-medium">
                  Belum ada data kelas yang sesuai dengan kriteria filter/pencarian.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      <!-- Mobile Card List View -->
      <div class="block md:hidden p-4 space-y-4">
        <div v-for="(k, idx) in paginatedKelasList" :key="k.id" class="bg-slate-950/40 rounded-2xl p-4 border border-white/10 space-y-3 shadow-md">
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono font-bold text-indigo-300">#{{ perPage === 'Semua' ? idx + 1 : (currentPage - 1) * Number(perPage) + idx + 1 }}</span>
            <div class="flex gap-1.5">
              <span class="bg-purple-500/20 text-purple-300 text-xs font-bold px-2.5 py-0.5 rounded-full border border-purple-500/30 font-mono">
                {{ k.tingkat || 'X' }}
              </span>
              <span class="bg-cyan-500/20 text-cyan-300 text-xs font-extrabold px-2.5 py-0.5 rounded-full border border-cyan-500/30" :title="k.jurusan">
                {{ k.jurusanKode || k.jurusan || 'Belum Diatur' }}
              </span>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <h3 class="text-lg font-black text-white">{{ k.namaKelas }}</h3>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 rounded-lg text-xs font-bold font-mono">
              👥 {{ k.jumlahSiswa ?? 0 }} Siswa
            </span>
          </div>

          <div class="text-xs text-gray-400 flex items-center gap-1.5 bg-white/5 px-3 py-2 rounded-xl">
            <span class="text-gray-500">Wali:</span>
            <span :class="k.waliKelas && k.waliKelas !== 'Belum Ditugaskan' ? 'font-bold text-white' : 'text-gray-500 italic'">
              {{ k.waliKelas && k.waliKelas !== 'Belum Ditugaskan' ? k.waliKelas : 'Belum Ditugaskan' }}
            </span>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/10">
            <button v-if="isAdmin" @click="openEditKelas(k)" class="flex-1 py-2.5 text-center text-cyan-300 font-bold text-xs bg-cyan-500/15 rounded-xl border border-cyan-500/30 active:scale-95 transition-all min-h-[44px]">
              ✏️ Edit
            </button>
            <button @click="handleRemoveKelas(k.id)" :class="[isAdmin ? 'flex-1' : 'w-full', 'py-2.5 text-center text-red-400 font-bold text-xs bg-red-500/15 rounded-xl border border-red-500/30 active:scale-95 transition-all min-h-[44px]']">
              🗑️ Hapus
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination Footer Controls Bar -->
      <div class="p-4 bg-slate-950/60 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-semibold text-gray-400">
        <div>
          Menampilkan <span class="text-white font-extrabold">{{ pageRangeText }}</span>
        </div>

        <div v-if="perPage !== 'Semua' && totalPages > 1" class="flex items-center gap-1.5">
          <button
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-gray-300 hover:text-white disabled:opacity-30 disabled:pointer-events-none transition-all"
          >
            ◄ Sebelum
          </button>

          <button
            v-for="p in totalPages"
            :key="p"
            @click="currentPage = p"
            :class="[
              'px-3 py-1.5 rounded-xl font-black transition-all border text-xs',
              currentPage === p
                ? 'bg-purple-600 text-white border-purple-400 shadow-md'
                : 'bg-white/5 text-gray-400 hover:text-white border-white/10'
            ]"
          >
            {{ p }}
          </button>

          <button
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-gray-300 hover:text-white disabled:opacity-30 disabled:pointer-events-none transition-all"
          >
            Berikutnya ►
          </button>
        </div>
      </div>
    </div>
    </div>

    <!-- TAB 2: DATA JURUSAN (ADMIN ONLY) -->
    <div v-if="isAdmin && activeKelasTab === 'jurusan'" class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="px-6 py-4">Kode Jurusan</th>
              <th class="px-6 py-4">Nama Lengkap Jurusan</th>
              <th class="px-6 py-4">Deskripsi / Spesialisasi</th>
              <th class="px-6 py-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr v-for="j in jurusanList" :key="j.id" class="hover:bg-white/5 transition-colors duration-150">
              <td class="px-6 py-4 font-mono font-black text-purple-300 text-sm">{{ j.kode }}</td>
              <td class="px-6 py-4 font-extrabold text-white">{{ j.nama }}</td>
              <td class="px-6 py-4 text-xs text-gray-400 max-w-xs leading-relaxed">{{ j.deskripsi }}</td>
              <td class="px-6 py-4 text-right space-x-2">
                <button @click="openEditJurusan(j)" class="text-cyan-300 hover:text-white font-bold text-xs bg-cyan-500/15 hover:bg-cyan-500/30 px-3.5 py-1.5 rounded-xl border border-cyan-500/30 active:scale-95 transition-all">✏️ Edit</button>
                <button @click="$emit('delete-jurusan', j.id)" class="text-red-400 hover:text-red-300 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 px-3.5 py-1.5 rounded-xl border border-red-500/30 active:scale-95 transition-all">🗑️ Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL FORM CRUD KELAS -->
    <div v-if="showKelasModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-6 transform transition-all animate-in fade-in zoom-in-95">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">
            {{ editingKelas ? 'Edit Data Kelas' : (isAdmin ? 'Tambah Master Kelas Baru (Admin)' : 'Pilih Kelas Yang Diajar') }}
          </h3>
          <button @click="showKelasModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <div class="space-y-4 text-xs sm:text-sm">
          <!-- Dropdown Tingkat Kelas (Admin Only) -->
          <div v-if="isAdmin">
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Tingkat Kelas *</label>
            <select
              v-model="kelasForm.tingkat"
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-purple-400 text-sm font-semibold min-h-[44px]"
              required
            >
              <option value="X">Tingkat X (Sepuluh)</option>
              <option value="XI">Tingkat XI (Sebelas)</option>
              <option value="XII">Tingkat XII (Duabelas)</option>
            </select>
          </div>

          <div v-if="isAdmin">
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nama Master Kelas *</label>
            <input
              v-model="kelasForm.namaKelas"
              type="text"
              placeholder="Contoh: X RPL 1, XI TKJ 2"
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-purple-400 min-h-[44px]"
              required
            />
          </div>

          <div v-else class="space-y-3">
            <div class="flex items-center justify-between">
              <label class="block text-gray-300 font-bold uppercase text-[11px]">Pilih Kelas Yang Diajar *</label>
              <span class="text-xs text-cyan-400 font-extrabold font-mono">{{ tempSelectedKelasIds.length }} Terpilih</span>
            </div>

            <!-- Search & Quick Filter inside Modal -->
            <div class="flex gap-2">
              <input
                v-model="searchModalQuery"
                type="text"
                placeholder="Cari kelas (misal: XI RPL)..."
                class="flex-1 bg-slate-950 border border-white/10 rounded-2xl px-3 py-2 text-xs text-white placeholder-gray-500 focus:border-cyan-400 outline-none"
              />
              <select
                v-model="selectedModalTingkat"
                class="bg-slate-950 border border-white/10 rounded-2xl px-2.5 py-2 text-xs text-cyan-300 font-bold focus:border-cyan-400 outline-none w-24"
              >
                <option value="Semua">Semua</option>
                <option value="X">Kelas X</option>
                <option value="XI">Kelas XI</option>
                <option value="XII">Kelas XII</option>
              </select>
            </div>

            <!-- Grouped Class Checklist -->
            <div class="max-h-64 overflow-y-auto space-y-4 p-3.5 bg-slate-950 border border-white/10 rounded-2xl custom-scrollbar">
              <div v-for="group in groupedMasterKelas" :key="group.tingkat" class="space-y-2">
                <div class="text-[10px] font-black text-cyan-300 bg-cyan-500/10 border border-cyan-500/25 px-3 py-1.5 rounded-xl uppercase tracking-wider flex items-center justify-between">
                  <span>Tingkat {{ group.tingkat }}</span>
                  <span>{{ group.classes.length }} Kelas</span>
                </div>
                <div class="grid grid-cols-2 gap-2 pl-1">
                  <label v-for="m in group.classes" :key="m.id" class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-white/5 cursor-pointer transition-all border border-transparent hover:border-white/5">
                    <input
                      type="checkbox"
                      :value="m.id"
                      v-model="tempSelectedKelasIds"
                      class="w-4 h-4 rounded text-cyan-500 bg-slate-900 border-white/10 focus:ring-cyan-500 focus:ring-offset-slate-950 cursor-pointer"
                    />
                    <span class="text-xs font-bold text-white truncate" :title="m.namaKelas">
                      Kelas {{ m.namaKelas }}
                    </span>
                  </label>
                </div>
              </div>
              <div v-if="groupedMasterKelas.length === 0" class="text-xs text-gray-500 text-center py-8 font-medium">
                Tidak ada kelas yang sesuai.
              </div>
            </div>
          </div>

          <div v-if="isAdmin">
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Pilih Jurusan *</label>
            <select v-model="kelasForm.jurusan" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-purple-400 text-sm font-semibold min-h-[44px]" required>
              <option value="">-- Pilih Jurusan --</option>
              <option v-for="j in jurusanList" :key="j.id" :value="j.nama">{{ j.nama }} ({{ j.kode }})</option>
            </select>
          </div>

          <!-- Input / Dropdown Wali Kelas (Admin Only) -->
          <div v-if="isAdmin">
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Pilih Wali Kelas (Opsional)</label>
            <select
              v-model="kelasForm.waliKelas"
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-purple-400 text-sm font-semibold min-h-[44px]"
            >
              <option value="">-- Belum Ditugaskan / Tidak Ada Wali Kelas --</option>
              <option v-for="g in guruList" :key="g.id" :value="g.id">
                {{ g.nama }} {{ g.nip ? `(NIP: ${g.nip})` : '' }}
              </option>
            </select>
            <p class="text-[11px] text-gray-400 mt-1">Pilih guru yang akan ditugaskan sebagai wali kelas ini.</p>
          </div>


        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
          <button @click="showKelasModal = false" class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl font-bold text-xs min-h-[44px]">Batal</button>
          <button @click="handleSaveKelas" :class="[isAdmin ? 'from-purple-500 to-indigo-600' : 'from-cyan-500 to-blue-600', 'px-5 py-3 bg-gradient-to-r text-white rounded-2xl font-extrabold text-xs shadow-lg active:scale-95 transition-all min-h-[44px]']">Simpan Kelas</button>
        </div>
      </div>
    </div>

    <!-- MODAL FORM CRUD JURUSAN -->
    <div v-if="isAdmin && showJurusanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-6 transform transition-all animate-in fade-in zoom-in-95">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">{{ editingJurusan ? 'Edit Data Jurusan' : 'Tambah Jurusan Baru' }}</h3>
          <button @click="showJurusanModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <div class="space-y-4 text-xs sm:text-sm">
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Kode Jurusan *</label>
            <input v-model="jurusanForm.kode" type="text" placeholder="Contoh: RPL, TKJ, DKV" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-purple-400 uppercase font-mono min-h-[44px]" required />
          </div>
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nama Lengkap Jurusan *</label>
            <input v-model="jurusanForm.nama" type="text" placeholder="Contoh: Rekayasa Perangkat Lunak" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-purple-400 min-h-[44px]" required />
          </div>
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Deskripsi Singkat</label>
            <textarea v-model="jurusanForm.deskripsi" rows="3" placeholder="Fokus keahlian jurusan..." class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-purple-400"></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
          <button @click="showJurusanModal = false" class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl font-bold text-xs min-h-[44px]">Batal</button>
          <button @click="handleSaveJurusan" class="px-5 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-2xl font-extrabold text-xs shadow-lg min-h-[44px]">Simpan Jurusan</button>
        </div>
      </div>
    </div>

  </div>
</template>
