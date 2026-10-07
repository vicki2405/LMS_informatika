<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  siswaList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  canManage: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits([
  'save-siswa', 'delete-siswa',
  'bulk-delete-siswa', 'import-siswa'
]);

const searchSiswaName = ref('');
const selectedKelasSiswaFilter = ref('Semua');
const selectedTingkatSiswaFilter = ref('Semua');
const selectedStatusFilter = ref('Semua');
const selectedSiswaIds = ref([]);
const perPage = ref(20);
const currentPage = ref(1);

// Modals State
const showSiswaModal = ref(false);
const editingSiswa = ref(null);
const siswaForm = ref({ nis: '', nama: '', jenisKelamin: 'Laki-laki', kelas: '', telepon: '', status: 'Aktif', tanggal_status: '' });

const showImportModal = ref(false);
const importFile = ref(null);

const filteredSiswaList = computed(() => {
  let list = [...props.siswaList];

  if (searchSiswaName.value.trim()) {
    const q = searchSiswaName.value.toLowerCase();
    list = list.filter(s => s.nama.toLowerCase().includes(q) || s.nis?.includes(q));
  }

  if (selectedTingkatSiswaFilter.value !== 'Semua') {
    list = list.filter(s => s.tingkat === selectedTingkatSiswaFilter.value);
  }

  if (selectedKelasSiswaFilter.value !== 'Semua') {
    list = list.filter(s => s.kelas === selectedKelasSiswaFilter.value);
  }

  if (selectedStatusFilter.value !== 'Semua') {
    list = list.filter(s => (s.status || 'Aktif') === selectedStatusFilter.value);
  }

  return list.sort((a, b) => a.nama.localeCompare(b.nama));
});

const totalItems = computed(() => filteredSiswaList.value.length);

const totalPages = computed(() => {
  if (perPage.value === 'Semua') return 1;
  return Math.ceil(totalItems.value / perPage.value);
});

const sortedFilteredSiswa = computed(() => {
  if (perPage.value === 'Semua') {
    return filteredSiswaList.value;
  }
  const start = (currentPage.value - 1) * perPage.value;
  return filteredSiswaList.value.slice(start, start + perPage.value);
});

const startItem = computed(() => {
  if (perPage.value === 'Semua') return 1;
  return (currentPage.value - 1) * perPage.value + 1;
});

const endItem = computed(() => {
  if (perPage.value === 'Semua') return totalItems.value;
  return currentPage.value * perPage.value;
});

const visiblePages = computed(() => {
  const pages = [];
  const start = Math.max(1, currentPage.value - 2);
  const end = Math.min(totalPages.value, start + 4);
  for (let i = start; i <= end; i++) {
    pages.push(i);
  }
  return pages;
});

watch([searchSiswaName, selectedKelasSiswaFilter, selectedTingkatSiswaFilter, selectedStatusFilter, perPage], () => {
  currentPage.value = 1;
});

const isAllSiswaSelected = computed(() => {
  if (sortedFilteredSiswa.value.length === 0) return false;
  return sortedFilteredSiswa.value.every(s => selectedSiswaIds.value.includes(s.id));
});

function toggleSelectAllSiswa(event) {
  if (event.target.checked) {
    selectedSiswaIds.value = sortedFilteredSiswa.value.map(s => s.id);
  } else {
    selectedSiswaIds.value = [];
  }
}

function toggleSelectSiswa(id) {
  if (selectedSiswaIds.value.includes(id)) {
    selectedSiswaIds.value = selectedSiswaIds.value.filter(sid => sid !== id);
  } else {
    selectedSiswaIds.value.push(id);
  }
}

function handleBulkDelete() {
  if (selectedSiswaIds.value.length === 0) return;
  emit('bulk-delete-siswa', selectedSiswaIds.value);
  selectedSiswaIds.value = [];
}

// Student CRUD Handlers
function openAddSiswaModal() {
  editingSiswa.value = null;
  siswaForm.value = {
    nis: String(1000 + props.siswaList.length + 1),
    nama: '',
    jenisKelamin: 'Laki-laki',
    kelas: props.kelasList[0]?.namaKelas || 'X RPL 1',
    telepon: '',
    status: 'Aktif',
    tanggal_status: ''
  };
  showSiswaModal.value = true;
}

function openEditSiswa(s) {
  editingSiswa.value = s;
  siswaForm.value = { 
    ...s,
    status: s.status || 'Aktif',
    tanggal_status: s.tanggal_status || ''
  };
  showSiswaModal.value = true;
}

function handleSaveSiswa() {
  if (!siswaForm.value.nama || !siswaForm.value.kelas) return;
  emit('save-siswa', { editing: editingSiswa.value, data: { ...siswaForm.value } });
  showSiswaModal.value = false;
}

// Import Excel / CSV & Download Template Handlers
function downloadCsvTemplate() {
  const bom = "\uFEFF";
  const csvContent = bom 
    + "NIS,Nama Lengkap,Jenis Kelamin,Kelas,No Telepon\r\n"
    + "1001,Budi Santoso,Laki-laki,X RPL 1,081234567890\r\n"
    + "1002,Siti Aminah,Perempuan,XI TKJ 2,082345678901\r\n"
    + "1003,Dewi Lestari,Perempuan,XII DKV 1,083456789012\r\n";
  const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", "template_import_siswa.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function downloadExcelTemplate() {
  // Download from server - a static pre-made template
  window.open('/admin/siswa/template', '_blank');
}

function handleFileUpload(event) {
  importFile.value = event.target.files[0];
}

function parseCsvLine(textLine, delimiter = ',') {
  const result = [];
  let current = '';
  let inQuotes = false;
  for (let i = 0; i < textLine.length; i++) {
    const char = textLine[i];
    if (char === '"') {
      inQuotes = !inQuotes;
    } else if (char === delimiter && !inQuotes) {
      result.push(current.trim());
      current = '';
    } else {
      current += char;
    }
  }
  result.push(current.trim());
  return result;
}

const isImporting = ref(false);

function processImport() {
  if (!importFile.value) {
    alert('Pilih berkas file CSV / Excel terlebih dahulu!');
    return;
  }

  const file = importFile.value;
  const ext = file.name.split('.').pop()?.toLowerCase();

  // For .xlsx/.xls files: send to server directly via FormData
  if (ext === 'xlsx' || ext === 'xls') {
    isImporting.value = true;
    const formData = new FormData();
    formData.append('file', file);
    router.post(route('admin.siswa.import-file'), formData, {
      forceFormData: true,
      preserveScroll: true,
      only: ['siswaListProp', 'kelasListProp'],
      onSuccess: () => {
        showImportModal.value = false;
        importFile.value = null;
        isImporting.value = false;
      },
      onError: (errors) => {
        alert('Import gagal: ' + Object.values(errors).join(', '));
        isImporting.value = false;
      }
    });
    return;
  }

  // For .csv files: parse client-side
  const reader = new FileReader();

  reader.onload = (e) => {
    const content = e.target.result;
    if (typeof content !== 'string') {
      alert('Format berkas tidak valid. Harap gunakan berkas CSV atau Excel.');
      return;
    }

    const lines = content.split(/\r\n|\n|\r/).filter(line => line.trim().length > 0);
    if (lines.length <= 1) {
      alert('Berkas kosong atau hanya berisi header kolom!');
      return;
    }

    // Auto-detect delimiter (, or ;)
    const firstLine = lines[0];
    const delimiter = (firstLine.includes(';') && !firstLine.includes(',')) ? ';' : ',';

    const header = parseCsvLine(firstLine, delimiter).map(h => h.toLowerCase().replace(/[^a-z0-9]/g, ''));

    let idxNis = header.findIndex(h => h.includes('nis'));
    let idxNama = header.findIndex(h => h.includes('nama'));
    let idxGender = header.findIndex(h => h.includes('jenis') || h.includes('kelamin') || h.includes('gender'));
    let idxKelas = header.findIndex(h => h.includes('kelas'));
    let idxTelepon = header.findIndex(h => h.includes('telepon') || h.includes('hp') || h.includes('wa') || h.includes('kontak'));

    if (idxNis === -1) idxNis = -1; // Optional
    if (idxNama === -1) idxNama = 0;
    if (idxGender === -1) idxGender = 1;
    if (idxKelas === -1) idxKelas = 2;
    if (idxTelepon === -1) idxTelepon = 3;

    const importedSiswa = [];
    for (let i = 1; i < lines.length; i++) {
      const cols = parseCsvLine(lines[i], delimiter);
      const nama = cols[idxNama] ? cols[idxNama].replace(/^"|"$/g, '').trim() : '';
      if (!nama) continue;

      const nis = idxNis >= 0 && cols[idxNis] ? cols[idxNis].replace(/^"|"$/g, '').trim() : '';
      const jenisKelamin = cols[idxGender] ? cols[idxGender].replace(/^"|"$/g, '').trim() : 'Laki-laki';
      const kelas = cols[idxKelas] ? cols[idxKelas].replace(/^"|"$/g, '').trim() : '';
      const telepon = cols[idxTelepon] ? cols[idxTelepon].replace(/^"|"$/g, '').trim() : '';

      importedSiswa.push({ nis, nama, jenisKelamin, kelas, telepon });
    }

    if (importedSiswa.length === 0) {
      alert('Tidak ada data siswa valid yang ditemukan di dalam file!');
      return;
    }

    emit('import-siswa', importedSiswa);
    showImportModal.value = false;
    importFile.value = null;
  };

  reader.readAsText(file, 'UTF-8');
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Master Data Siswa</span>
          <span class="text-cyan-400 text-base">🎓</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">Daftar siswa diurutkan otomatis abjad (A-Z) dengan fitur Hapus Masal & Import Excel/CSV</p>
      </div>
      
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        <!-- Read-Only Badge for Subject Teachers -->
        <div v-if="!canManage" class="px-4 py-2.5 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl text-emerald-400 text-xs font-bold flex items-center gap-2">
          <span>🛡️ Data Siswa Terverifikasi Wali Kelas (Read-Only)</span>
        </div>

        <template v-else>
          <!-- Bulk Delete Button -->
          <button
            v-if="selectedSiswaIds.length > 0"
            @click="handleBulkDelete"
            class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-red-600 to-pink-600 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-red-500/25 hover:shadow-red-500/40 active:scale-95 transition-all duration-200 animate-pulse min-h-[44px]"
          >
            <span>🗑️</span>
            <span>Hapus Masal ({{ selectedSiswaIds.length }} Siswa)</span>
          </button>

          <!-- Import Excel Button -->
          <button
            @click="showImportModal = true"
            class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-emerald-600/25 active:scale-95 transition-all duration-200 min-h-[44px]"
          >
            <span>📁</span>
            <span>Import Excel / CSV</span>
          </button>

          <!-- Add Single Student Button -->
          <button
            @click="openAddSiswaModal"
            class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-blue-600 to-indigo-600 text-white px-5 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-105 active:scale-95 transition-all duration-200 min-h-[44px]"
          >
            <span class="text-base">+</span>
            <span>Tambah Siswa Baru</span>
          </button>
        </template>
      </div>
    </div>

    <!-- Search Bar & Class Filter Bar & Status Filter Bar & Limit Per Page -->
    <div class="grid sm:grid-cols-2 md:grid-cols-5 gap-4">
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>
        <input
          v-model="searchSiswaName"
          type="text"
          placeholder="Cari nama atau NIS siswa..."
          class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-white/10 rounded-2xl text-xs sm:text-sm text-white placeholder-gray-400 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 outline-none transition-all duration-200 min-h-[44px]"
        />
        <button v-if="searchSiswaName" @click="searchSiswaName = ''" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs text-gray-400 hover:text-white">✕</button>
      </div>

      <div class="flex items-center gap-3">
        <label class="text-xs text-gray-400 font-bold shrink-0">Tingkat:</label>
        <select
          v-model="selectedTingkatSiswaFilter"
          class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
        >
          <option value="Semua">Semua</option>
          <option value="X">Tingkat X</option>
          <option value="XI">Tingkat XI</option>
          <option value="XII">Tingkat XII</option>
        </select>
      </div>

      <div class="flex items-center gap-3">
        <label class="text-xs text-gray-400 font-bold shrink-0">Kelas:</label>
        <select
          v-model="selectedKelasSiswaFilter"
          class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
        >
          <option value="Semua">Semua Kelas</option>
          <option v-for="k in kelasList" :key="k.id" :value="k.namaKelas">{{ k.namaKelas }}</option>
        </select>
      </div>

      <div class="flex items-center gap-3">
        <label class="text-xs text-gray-400 font-bold shrink-0">Status:</label>
        <select
          v-model="selectedStatusFilter"
          class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
        >
          <option value="Semua">Semua Status</option>
          <option value="Aktif">Aktif</option>
          <option value="Pindah">Pindah</option>
          <option value="Keluar">Keluar</option>
        </select>
      </div>

      <div class="flex items-center gap-3">
        <label class="text-xs text-gray-400 font-bold shrink-0">Tampilkan:</label>
        <select
          v-model="perPage"
          class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
        >
          <option :value="10">10 Data</option>
          <option :value="20">20 Data</option>
          <option :value="30">30 Data</option>
          <option :value="50">50 Data</option>
          <option :value="100">100 Data</option>
          <option :value="200">200 Data</option>
          <option value="Semua">Semua</option>
        </select>
      </div>
    </div>

    <!-- Data Table Container -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      
      <!-- Desktop & Tablet Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th v-if="canManage" class="px-4 py-4 text-center w-12">
                <input
                  type="checkbox"
                  :checked="isAllSiswaSelected"
                  @change="toggleSelectAllSiswa"
                  class="w-4 h-4 rounded border-white/20 bg-slate-800 text-cyan-500 focus:ring-cyan-400"
                  title="Pilih Semua Siswa"
                />
              </th>
              <th class="px-4 py-4 text-center w-12">No.</th>
              <th class="px-4 py-4">NIS</th>
              <th class="px-6 py-4">Nama Lengkap (A-Z)</th>
              <th class="px-6 py-4">Jenis Kelamin</th>
              <th class="px-6 py-4">Tingkat</th>
              <th class="px-6 py-4">Kelas</th>
              <th class="px-6 py-4">Wali Kelas</th>
              <th class="px-6 py-4">No. WhatsApp</th>
              <th class="px-6 py-4">Status</th>
              <th v-if="canManage" class="px-6 py-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr
              v-for="(siswa, index) in sortedFilteredSiswa"
              :key="siswa.id"
              :class="[
                'transition-colors duration-150',
                selectedSiswaIds.includes(siswa.id) ? 'bg-cyan-500/10' : '',
                siswa.status === 'Keluar' ? 'bg-red-950/25 hover:bg-red-950/35 border-l-4 border-l-red-500' :
                siswa.status === 'Pindah' ? 'bg-amber-950/25 hover:bg-amber-950/35 border-l-4 border-l-amber-500' :
                'hover:bg-white/5'
              ]"
            >
              <td v-if="canManage" class="px-4 py-4 text-center">
                <input
                  type="checkbox"
                  :checked="selectedSiswaIds.includes(siswa.id)"
                  @change="toggleSelectSiswa(siswa.id)"
                  class="w-4 h-4 rounded border-white/20 bg-slate-800 text-cyan-500 focus:ring-cyan-400"
                />
              </td>
              <td class="px-4 py-4 text-center font-mono text-gray-400 text-xs">{{ index + 1 }}</td>
              <td class="px-4 py-4 font-mono text-cyan-300 text-xs font-bold">
                {{ siswa.nis || '-' }}
              </td>
              <td class="px-6 py-4 font-bold text-white">
                <div class="flex items-center gap-3">
                  <div
                    :class="[
                      'w-8 h-8 rounded-xl font-black text-xs flex items-center justify-center shadow-md shrink-0 text-white',
                      siswa.status === 'Keluar'
                        ? 'bg-gradient-to-br from-red-600 to-rose-700 ring-2 ring-red-400/50'
                        : siswa.status === 'Pindah'
                          ? 'bg-gradient-to-br from-amber-500 to-orange-600 ring-2 ring-amber-400/50'
                          : 'bg-gradient-to-br from-cyan-500 to-blue-600'
                    ]"
                  >
                    {{ siswa.nama.substring(0, 2).toUpperCase() }}
                  </div>
                  <div>
                    <div class="flex items-center gap-2 flex-wrap">
                      <span
                        class="text-sm font-extrabold"
                        :class="siswa.status === 'Keluar' || siswa.status === 'Pindah' ? 'line-through text-gray-300' : 'text-white'"
                      >
                        {{ siswa.nama }}
                      </span>
                      <span
                        v-if="siswa.status === 'Keluar'"
                        class="inline-flex items-center gap-1 text-[10px] font-black px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/40 uppercase"
                      >
                        🚫 KELUAR
                      </span>
                      <span
                        v-else-if="siswa.status === 'Pindah'"
                        class="inline-flex items-center gap-1 text-[10px] font-black px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 uppercase"
                      >
                        🚚 PINDAH
                      </span>
                    </div>
                    <span v-if="siswa.status && siswa.status !== 'Aktif' && siswa.tanggal_status" class="text-[10px] text-gray-400 font-mono block mt-0.5">
                      Per tgl: {{ siswa.tanggal_status }}
                    </span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 text-xs">
                <span :class="['px-3 py-1 rounded-full font-bold', siswa.jenisKelamin === 'Laki-laki' ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30' : 'bg-pink-500/20 text-pink-300 border border-pink-500/30']">
                  {{ siswa.jenisKelamin }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="bg-purple-500/20 text-purple-300 text-xs font-black px-3.5 py-1 rounded-full border border-purple-500/30">
                  Tingkat {{ siswa.tingkat || 'X' }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="bg-cyan-500/20 text-cyan-300 text-xs font-bold px-3 py-1 rounded-full border border-cyan-500/30">
                  Kelas {{ siswa.kelas }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="text-xs font-bold text-gray-300">
                  👤 {{ siswa.waliKelas || '-' }}
                </span>
              </td>
              <td class="px-6 py-4 font-mono text-xs text-gray-300">
                <a v-if="siswa.telepon" :href="'https://wa.me/' + siswa.telepon.replace(/[^0-9]/g, '')" target="_blank" class="text-emerald-400 hover:underline font-bold bg-emerald-500/10 px-3 py-1 rounded-xl border border-emerald-500/20 inline-flex items-center gap-1">
                  💬 {{ siswa.telepon }}
                </a>
                <span v-else class="text-gray-500">-</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-col gap-1">
                  <span :class="[
                    'px-2.5 py-0.5 rounded-full font-bold text-[11px] uppercase border inline-block w-fit',
                    siswa.status === 'Pindah' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 font-black' :
                    siswa.status === 'Keluar' ? 'bg-red-500/20 text-red-300 border-red-500/40 font-black' :
                    'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'
                  ]">
                    {{ siswa.status || 'Aktif' }}
                  </span>
                  <span v-if="siswa.status && siswa.status !== 'Aktif' && siswa.tanggal_status" class="text-[10px] text-gray-400 font-mono font-medium">
                    Tgl: {{ siswa.tanggal_status }}
                  </span>
                </div>
              </td>
              <td v-if="canManage" class="px-6 py-4 text-right space-x-2">
                <button @click="openEditSiswa(siswa)" class="text-cyan-300 hover:text-white font-bold text-xs bg-cyan-500/15 hover:bg-cyan-500/30 px-3 py-1.5 rounded-xl border border-cyan-500/30 active:scale-95 transition-all min-h-[36px]">✏️ Edit</button>
                <button @click="$emit('delete-siswa', siswa.id)" class="text-red-400 hover:text-red-300 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 px-3 py-1.5 rounded-xl border border-red-500/30 active:scale-95 transition-all min-h-[36px]">🗑️ Hapus</button>
              </td>
            </tr>
            <tr v-if="sortedFilteredSiswa.length === 0">
              <td :colspan="canManage ? 11 : 9" class="px-6 py-12 text-center text-gray-400 font-medium">Tidak ada data siswa yang cocok.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card List View -->
      <div class="block md:hidden p-4 space-y-4">
        <div
          v-for="(siswa, index) in sortedFilteredSiswa"
          :key="siswa.id"
          :class="[
            'rounded-2xl p-4 border transition-all space-y-3 shadow-md',
            selectedSiswaIds.includes(siswa.id) ? 'border-cyan-500/50 bg-cyan-500/10' : 'border-white/10',
            siswa.status === 'Keluar' ? 'bg-red-950/25 border-red-500/40' :
            siswa.status === 'Pindah' ? 'bg-amber-950/25 border-amber-500/40' : 'bg-slate-950/40'
          ]"
        >
          <!-- Status Banner for Inactive Students -->
          <div
            v-if="siswa.status === 'Keluar'"
            class="px-3 py-1.5 rounded-xl bg-red-500/20 border border-red-500/40 text-red-300 text-xs font-black flex items-center justify-between"
          >
            <span class="flex items-center gap-1.5">
              <span>🚫</span>
              <span>SISWA KELUAR</span>
            </span>
            <span v-if="siswa.tanggal_status" class="font-mono text-[10px] text-gray-300">
              Tgl: {{ siswa.tanggal_status }}
            </span>
          </div>
          <div
            v-else-if="siswa.status === 'Pindah'"
            class="px-3 py-1.5 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-black flex items-center justify-between"
          >
            <span class="flex items-center gap-1.5">
              <span>🚚</span>
              <span>SISWA PINDAH</span>
            </span>
            <span v-if="siswa.tanggal_status" class="font-mono text-[10px] text-gray-300">
              Tgl: {{ siswa.tanggal_status }}
            </span>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <input
                v-if="canManage"
                type="checkbox"
                :checked="selectedSiswaIds.includes(siswa.id)"
                @change="toggleSelectSiswa(siswa.id)"
                class="w-5 h-5 rounded border-white/20 bg-slate-800 text-cyan-500 focus:ring-cyan-400"
              />
              <span class="font-mono text-xs font-bold text-gray-400">#{{ index + 1 }}</span>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-1.5">
              <span :class="['text-[11px] px-2.5 py-0.5 rounded-full font-bold', siswa.jenisKelamin === 'Laki-laki' ? 'bg-blue-500/20 text-blue-300' : 'bg-pink-500/20 text-pink-300']">
                {{ siswa.jenisKelamin }}
              </span>
              <span class="bg-purple-500/20 text-purple-300 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-purple-500/30">
                Tingkat {{ siswa.tingkat || 'X' }}
              </span>
              <span class="bg-cyan-500/20 text-cyan-300 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full border border-cyan-500/30">
                {{ siswa.kelas }}
              </span>
              <span :class="[
                'text-[11px] px-2.5 py-0.5 rounded-full font-black border',
                siswa.status === 'Pindah' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' :
                siswa.status === 'Keluar' ? 'bg-red-500/20 text-red-300 border-red-500/40' :
                'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'
              ]">
                {{ siswa.status || 'Aktif' }}
              </span>
            </div>
          </div>

          <div>
            <h3 class="text-base font-black text-white" :class="siswa.status === 'Keluar' || siswa.status === 'Pindah' ? 'line-through text-gray-300' : 'text-white'">
              {{ siswa.nama }}
            </h3>
            <div v-if="siswa.status && siswa.status !== 'Aktif' && siswa.tanggal_status" class="text-[11px] text-amber-300/80 font-bold font-mono mt-0.5">
              Tanggal {{ siswa.status }}: {{ siswa.tanggal_status }}
            </div>
            <div class="text-xs text-gray-400 mt-1.5 flex items-center justify-between font-medium">
              <span>NIS: <span class="font-mono text-cyan-300 font-bold bg-cyan-500/10 px-2 py-0.5 rounded-lg border border-cyan-500/20">{{ siswa.nis || '-' }}</span></span>
              <a v-if="siswa.telepon" :href="'tel:' + siswa.telepon" class="text-cyan-400 font-mono flex items-center gap-1 font-bold">
                📞 {{ siswa.telepon }}
              </a>
            </div>
          </div>

          <div v-if="canManage" class="flex items-center justify-end gap-2 pt-2 border-t border-white/10">
            <button @click="openEditSiswa(siswa)" class="flex-1 py-2.5 text-center text-cyan-300 font-bold text-xs bg-cyan-500/15 rounded-xl border border-cyan-500/30 active:scale-95 transition-all min-h-[44px]">
              ✏️ Edit
            </button>
            <button @click="$emit('delete-siswa', siswa.id)" class="flex-1 py-2.5 text-center text-red-400 font-bold text-xs bg-red-500/15 rounded-xl border border-red-500/30 active:scale-95 transition-all min-h-[44px]">
              🗑️ Hapus
            </button>
          </div>
        </div>

        <div v-if="sortedFilteredSiswa.length === 0" class="p-6 text-center text-gray-400 text-xs font-medium">
          Tidak ada data siswa yang cocok.
        </div>
      </div>

      <!-- Pagination Controls (Sleek Desktop & Mobile Friendly UI) -->
      <div v-if="perPage !== 'Semua' && totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 border-t border-white/10 bg-slate-950/20">
        <div class="text-xs text-gray-400 font-medium">
          Menampilkan <span class="text-white font-bold">{{ Math.min(startItem, totalItems) }}</span> - <span class="text-white font-bold">{{ Math.min(endItem, totalItems) }}</span> dari <span class="text-cyan-400 font-bold">{{ totalItems }}</span> data siswa
        </div>
        <div class="flex items-center justify-center gap-1.5 flex-wrap">
          <button
            @click="currentPage > 1 ? currentPage-- : null"
            :disabled="currentPage === 1"
            class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white/5 border border-white/10 text-gray-300 hover:bg-white/10 active:scale-95 disabled:opacity-30 disabled:pointer-events-none transition-all min-h-[38px] flex items-center gap-1"
          >
            ◀ Prev
          </button>
          <button
            v-for="p in visiblePages"
            :key="p"
            @click="currentPage = p"
            :class="[
              'px-3.5 py-2 rounded-xl text-xs font-black min-h-[38px] transition-all',
              currentPage === p ? 'bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-md' : 'bg-white/5 border border-white/10 text-gray-300 hover:bg-white/10 active:scale-95'
            ]"
          >
            {{ p }}
          </button>
          <button
            @click="currentPage < totalPages ? currentPage++ : null"
            :disabled="currentPage === totalPages"
            class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white/5 border border-white/10 text-gray-300 hover:bg-white/10 active:scale-95 disabled:opacity-30 disabled:pointer-events-none transition-all min-h-[38px] flex items-center gap-1"
          >
            Next ▶
          </button>
        </div>
      </div>

    </div>

    <!-- MODAL FORM CRUD SISWA -->
    <div v-if="showSiswaModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto space-y-6 transform transition-all animate-in fade-in zoom-in-95">
        <div class="w-12 h-1 bg-white/20 rounded-full mx-auto sm:hidden"></div>
        
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">{{ editingSiswa ? 'Edit Data Siswa' : 'Tambah Siswa Baru' }}</h3>
          <button @click="showSiswaModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSaveSiswa" class="space-y-4 text-xs sm:text-sm">
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nama Lengkap Siswa *</label>
            <input v-model="siswaForm.nama" type="text" placeholder="Masukkan nama lengkap siswa" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm min-h-[44px]" required />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Jenis Kelamin *</label>
            <select v-model="siswaForm.jenisKelamin" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm min-h-[44px]" required>
              <option value="Laki-laki">Laki-laki</option>
              <option value="Perempuan">Perempuan</option>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Pilih Rombel Kelas *</label>
            <select v-model="siswaForm.kelas" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm min-h-[44px]" required>
              <option value="" disabled>-- Pilih Kelas --</option>
              <template v-for="tingkat in ['X','XI','XII']" :key="tingkat">
                <optgroup
                  v-if="kelasList.filter(k => k.tingkat === tingkat).length"
                  :label="'── Tingkat ' + tingkat + ' ──'"
                >
                  <option
                    v-for="k in kelasList.filter(kl => kl.tingkat === tingkat)"
                    :key="k.id"
                    :value="k.namaKelas"
                  >{{ k.namaKelas }} ({{ k.jurusan }})</option>
                </optgroup>
              </template>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">No. Telepon / Contacts *</label>
            <input v-model="siswaForm.telepon" type="text" placeholder="Contoh: 081234567890" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm min-h-[44px]" required />
          </div>

          <div v-if="canManage">
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Status Siswa</label>
            <select v-model="siswaForm.status" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm min-h-[44px]">
              <option value="Aktif">Aktif</option>
              <option value="Pindah">Pindah</option>
              <option value="Keluar">Keluar</option>
            </select>
          </div>

          <div v-if="canManage && (siswaForm.status === 'Pindah' || siswaForm.status === 'Keluar')">
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Tanggal Pindah / Keluar *</label>
            <input v-model="siswaForm.tanggal_status" type="date" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm min-h-[44px] font-mono" required />
            <p class="text-[11px] text-gray-400 mt-1.5 font-medium">Isi tanggal dilakukannya kepindahan atau keluarnya siswa.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button type="button" @click="showSiswaModal = false" class="flex-1 sm:flex-none px-5 py-3 rounded-2xl border border-white/10 text-gray-400 text-xs font-bold hover:bg-white/5 active:scale-95 transition-all min-h-[44px]">Batal</button>
            <button type="submit" class="flex-1 sm:flex-none px-5 py-3 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-xs font-extrabold shadow-lg hover:shadow-cyan-500/25 active:scale-95 transition-all min-h-[44px]">Simpan Siswa</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL FORM IMPORT EXCEL / CSV WITH DOWNLOAD TEMPLATE BUTTON -->
    <div v-if="showImportModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto space-y-6 transform transition-all animate-in fade-in zoom-in-95">
        <div class="w-12 h-1 bg-white/20 rounded-full mx-auto sm:hidden"></div>
        
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">Import Data Siswa (Excel / CSV)</h3>
          <button @click="showImportModal = false" class="text-gray-400 hover:text-white p-1 font-bold">✕</button>
        </div>

        <div class="p-4 bg-cyan-500/10 border border-cyan-500/20 rounded-2xl space-y-3">
          <span class="text-xs font-extrabold text-cyan-300 uppercase block">⬇️ Unduh Template Berkas:</span>
          <div class="flex flex-col sm:flex-row gap-2">
            <button
              type="button"
              @click="downloadCsvTemplate"
              class="flex-1 inline-flex items-center justify-center gap-1.5 bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl shadow hover:opacity-90 active:scale-95 transition-all min-h-[38px]"
            >
              <span>📄</span>
              <span>Template CSV</span>
            </button>
            <button
              type="button"
              @click="downloadExcelTemplate"
              class="flex-1 inline-flex items-center justify-center gap-1.5 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-black text-xs px-4 py-2.5 rounded-xl shadow hover:opacity-90 active:scale-95 transition-all min-h-[38px]"
            >
              <span>📊</span>
              <span>Template Excel (.xlsx)</span>
            </button>
          </div>
          <p class="text-xs text-gray-300 leading-relaxed font-medium">
            Format kolom yang diperlukan: <br>
            <code class="font-mono bg-slate-950 px-2 py-1 rounded text-cyan-300 text-[11px] border border-white/10 inline-block mt-1">NIS, Nama Lengkap, Jenis Kelamin, Kelas, No Telepon</code>
          </p>
        </div>

        <div class="space-y-4 text-xs sm:text-sm">
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-2">Pilih File CSV / Excel *</label>
            <input
              type="file"
              accept=".csv, .xlsx, .xls"
              @change="handleFileUpload"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white text-xs sm:text-sm file:mr-4 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-cyan-500/20 file:text-cyan-300 hover:file:bg-cyan-500/30 min-h-[44px]"
            />
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button type="button" @click="showImportModal = false" class="flex-1 sm:flex-none px-5 py-3 rounded-2xl border border-white/10 text-gray-400 text-xs font-bold hover:bg-white/5 active:scale-95 transition-all min-h-[44px]">Batal</button>
            <button
              type="button"
              @click="processImport"
              :disabled="isImporting"
              class="flex-1 sm:flex-none px-5 py-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-extrabold shadow-lg hover:shadow-emerald-500/25 active:scale-95 transition-all min-h-[44px] disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span v-if="isImporting">⏳ Mengimpor...</span>
              <span v-else>🚀 Proses Import Data</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
