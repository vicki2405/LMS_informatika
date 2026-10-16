<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { confirmDelete } from '@/Utils/sweetalert';

const props = defineProps({
  absensiList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  jadwalList: { type: Array, default: () => [] },
  initialKelas: String,
  initialTanggal: String
});

const indoDays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const todayDayName = computed(() => indoDays[new Date().getDay()] || 'Senin');

const todayScheduledClasses = computed(() => {
  const set = new Set();
  (props.jadwalList || []).filter(j => j.hari === todayDayName.value).forEach(j => {
    const kName = j.kelas || j.namaKelas;
    if (kName) set.add(kName);
  });
  return Array.from(set);
});

const emit = defineEmits(['save-batch-absensi', 'back-to-matrix']);

// Indonesian Month Names
const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Helper: Today's Date String YYYY-MM-DD
function getTodayString() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
function getTingkatFromKelas(className) {
  if (!className) return 'X';
  if (className.toUpperCase().startsWith('XII')) return 'XII';
  if (className.toUpperCase().startsWith('XI')) return 'XI';
  return 'X';
}

function getInitialKelas() {
  if (props.initialKelas) return props.initialKelas;
  // Smart default to today's scheduled class
  if (todayScheduledClasses.value && todayScheduledClasses.value.length > 0) {
    return todayScheduledClasses.value[0];
  }
  try {
    const saved = localStorage.getItem('guru_active_absensi_kelas');
    if (saved) return saved;
  } catch (e) {}
  return props.kelasList[0]?.namaKelas || props.kelasList[0] || 'X RPL 1';
}

// Form & Controls State
const batchTanggal = ref(props.initialTanggal || getTodayString());
const batchKelas = ref(getInitialKelas());
const showConfigHeader = ref(false); // Collapsed by default for maximum viewport space!
const searchQuery = ref('');
const statusFilter = ref('Semua'); // 'Semua', 'BelumDipanggil', 'Hadir', 'Sakit', 'Izin', 'Alpa'
const batchRows = ref([]);

const selectedTingkat = ref(getTingkatFromKelas(batchKelas.value));

const filteredKelasByTingkat = computed(() => {
  return (props.kelasList || []).filter(k => {
    const t = k?.tingkat || getTingkatFromKelas(k?.namaKelas || k?.nama || k);
    return t === selectedTingkat.value;
  }).sort((a, b) => (a?.namaKelas || a?.nama || a || '').toString().localeCompare((b?.namaKelas || b?.nama || b || '').toString(), undefined, { numeric: true, sensitivity: 'base' }));
});

function onTingkatChange() {
  const available = filteredKelasByTingkat.value;
  if (available.length > 0) {
    batchKelas.value = available[0].namaKelas || available[0];
  } else {
    batchKelas.value = '';
  }
}

// Formatted Date Label for Summary Bar (e.g. "25 Juli 2026")
const formattedBatchDate = computed(() => {
  if (!batchTanggal.value) return '';
  const parts = batchTanggal.value.split('-');
  if (parts.length !== 3) return batchTanggal.value;
  const year = parts[0];
  const monthIdx = parseInt(parts[1], 10) - 1;
  const day = parseInt(parts[2], 10);
  return `${day} ${monthNames[monthIdx] || ''} ${year}`;
});

// Initialize student rows for the selected class
function initBatchRows() {
  const classStudents = props.siswaList.filter(s => s.kelas === batchKelas.value);
  const normBatchDate = String(batchTanggal.value).substring(0, 10);
  
  batchRows.value = classStudents.map(s => {
    // Check if attendance already exists for this student on batchTanggal
    const existing = props.absensiList.find(a => 
      a && 
      (a.siswaId === s.id || a.siswa_id === s.id) && 
      (a.tanggal === normBatchDate || (a.tanggal && String(a.tanggal).substring(0, 10) === normBatchDate))
    );
    const isInactive = s.status === 'Keluar' || s.status === 'Pindah';
    return {
      siswaId: s.id,
      namaSiswa: s.nama,
      nis: s.nis || '',
      kelas: s.kelas,
      statusSiswa: s.status || 'Aktif',
      tanggalStatus: s.tanggal_status || '',
      isInactive: isInactive,
      // Inactive students cannot have attendance recorded
      status: isInactive ? '' : (existing ? existing.status : ''),
      keterangan: existing ? existing.keterangan || '' : ''
    };
  });
}

onMounted(() => {
  selectedTingkat.value = getTingkatFromKelas(batchKelas.value);
  initBatchRows();
});

// Watchers for Class or Date changes
watch([batchKelas, batchTanggal], () => {
  initBatchRows();
});

watch(batchKelas, (newVal) => {
  if (newVal) {
    try {
      localStorage.setItem('guru_active_absensi_kelas', newVal);
    } catch (e) {}
  }
});

watch(() => props.initialKelas, (newVal) => {
  if (newVal && newVal !== batchKelas.value) {
    batchKelas.value = newVal;
    selectedTingkat.value = getTingkatFromKelas(newVal);
  }
});

watch(() => props.kelasList, (newVal) => {
  if (newVal && newVal.length > 0) {
    const exists = newVal.some(k => (k.namaKelas || k) === batchKelas.value);
    if (!exists) {
      const preferred = props.initialKelas || getInitialKelas();
      const preferredExists = newVal.some(k => (k.namaKelas || k) === preferred);
      batchKelas.value = preferredExists ? preferred : (newVal[0]?.namaKelas || newVal[0]);
      selectedTingkat.value = getTingkatFromKelas(batchKelas.value);
      initBatchRows();
    }
  }
}, { immediate: true });

// Filtered Batch Rows
const filteredBatchRows = computed(() => {
  let list = batchRows.value;

  if (statusFilter.value === 'Inactive') {
    list = list.filter(r => r.isInactive);
  } else if (statusFilter.value === 'BelumDipanggil') {
    list = list.filter(r => !r.isInactive && !r.status);
  } else if (statusFilter.value !== 'Semua') {
    list = list.filter(r => !r.isInactive && r.status === statusFilter.value);
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(r => r.namaSiswa.toLowerCase().includes(q) || r.nis.includes(q));
  }

  return list;
});

// Live Counters
const countHadir = computed(() => batchRows.value.filter(r => !r.isInactive && r.status === 'Hadir').length);
const countSakit = computed(() => batchRows.value.filter(r => !r.isInactive && r.status === 'Sakit').length);
const countIzin = computed(() => batchRows.value.filter(r => !r.isInactive && r.status === 'Izin').length);
const countAlpa = computed(() => batchRows.value.filter(r => !r.isInactive && r.status === 'Alpa').length);
const countUnset = computed(() => batchRows.value.filter(r => !r.isInactive && !r.status).length);
const countInactive = computed(() => batchRows.value.filter(r => r.isInactive).length);

// Shortcut: Set All Remaining Unset Students to Hadir (H)
function markRemainingAsHadir() {
  batchRows.value.forEach(row => {
    if (!row.isInactive && !row.status) {
      row.status = 'Hadir';
    }
  });
}

// Submit Full-Page Batch Attendance
function handleSaveBatchAttendance() {
  const activeRowsToSave = batchRows.value.filter(r => !r.isInactive && r.status !== '');
  if (countUnset.value > 0) {
    confirmDelete({
      title: 'Siswa Belum Dipanggil',
      text: `Masih terdapat ${countUnset.value} siswa aktif yang belum dipanggil. Yakin ingin menyimpan?`,
      confirmButtonText: 'Ya, Simpan Presensi!'
    }).then((res) => {
      if (res.isConfirmed) {
        emit('save-batch-absensi', {
          tanggal: batchTanggal.value,
          kelas: batchKelas.value,
          rows: activeRowsToSave
        });
      }
    });
    return;
  }

  emit('save-batch-absensi', {
    tanggal: batchTanggal.value,
    kelas: batchKelas.value,
    rows: activeRowsToSave
  });
}
</script>

<template>
  <div class="space-y-6 pb-24 animate-in fade-in duration-300">
    
    <!-- Navigation & Title Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <button
          @click="$emit('back-to-matrix', batchKelas)"
          class="inline-flex items-center gap-2 text-xs font-black text-cyan-300 hover:text-white bg-cyan-500/15 hover:bg-cyan-500/30 px-4 py-2 rounded-2xl border border-cyan-500/30 transition-all duration-200 active:scale-95 mb-3"
        >
          ← Kembali ke Matriks Presensi
        </button>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Input Presensi Kelas</span>
          <span class="text-cyan-400 text-base">📋</span>
        </h1>
        <p class="text-xs sm:text-sm text-gray-400 mt-1 font-medium">Layar pencatatan presensi massal kelas (H, S, I, A)</p>
      </div>

      <!-- Quick Action Buttons -->
      <div class="flex items-center gap-3 shrink-0">
        <button
          @click="markRemainingAsHadir"
          class="text-xs text-slate-950 font-black bg-gradient-to-r from-cyan-400 to-teal-400 hover:from-cyan-300 hover:to-teal-300 px-4 py-2.5 rounded-2xl shadow-lg shadow-cyan-500/25 transition-all duration-200 active:scale-95 min-h-[44px]"
        >
          ⚡ Set Belum Diabsen -> Hadir (H)
        </button>
      </div>
    </div>

    <!-- Summary Banner Bar -->
    <div v-if="!showConfigHeader" class="p-4 bg-gradient-to-r from-cyan-950/60 via-slate-900 to-teal-950/60 rounded-3xl border border-cyan-500/30 flex flex-col gap-3 shadow-xl">
      <div class="flex flex-wrap items-center gap-2 text-xs font-extrabold">
        <span class="bg-cyan-500/20 text-cyan-300 px-3 py-1.5 rounded-2xl border border-cyan-500/30 flex items-center gap-1.5">
          📅 {{ formattedBatchDate }}
        </span>
        <span class="bg-teal-500/20 text-teal-300 px-3 py-1.5 rounded-2xl border border-teal-500/30 flex items-center gap-1.5">
          🏫 Kelas: {{ batchKelas }}
        </span>
        <span class="text-xs text-gray-300 font-medium">Total Siswa: <strong class="text-white font-black">{{ batchRows.length }} Siswa</strong></span>
      </div>

      <button
        @click="showConfigHeader = true"
        class="w-full text-xs text-cyan-300 hover:text-white font-bold bg-white/5 hover:bg-white/10 px-4 py-2.5 rounded-2xl border border-white/10 transition-all flex items-center justify-center gap-1.5 active:scale-95 min-h-[40px]"
      >
        ✏️ Ubah Tanggal / Kelas Target
      </button>
    </div>

    <!-- EXPANDED CONFIGURATION FORM HEADER -->
    <div v-else class="p-6 bg-slate-900/90 backdrop-blur-xl rounded-3xl border border-white/10 space-y-4 shadow-2xl">
      <div class="flex items-center justify-between border-b border-white/10 pb-3">
        <span class="text-xs font-black text-white uppercase tracking-wider">Pengaturan Tanggal &amp; Kelas Presensi</span>
        <button @click="showConfigHeader = false" class="text-xs text-cyan-400 hover:text-cyan-300 font-bold flex items-center gap-1">
          🔽 Sembunyikan Form
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Tanggal Absensi</label>
          <input
            v-model="batchTanggal"
            type="date"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm focus:border-cyan-400 outline-none min-h-[44px]"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Tingkat Kelas</label>
          <select
            v-model="selectedTingkat"
            @change="onTingkatChange"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
          >
            <option value="X">Tingkat X</option>
            <option value="XI">Tingkat XI</option>
            <option value="XII">Tingkat XII</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Pilih Kelas Target</label>
          <select
            v-model="batchKelas"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white text-sm font-semibold focus:border-cyan-400 outline-none min-h-[44px]"
            required
          >
            <option value="" disabled>-- Pilih Kelas --</option>
            <option v-for="k in filteredKelasByTingkat" :key="k.id" :value="k.namaKelas">
              {{ k.namaKelas }} ({{ k.jurusan }})
            </option>
          </select>
        </div>
      </div>

      <div v-if="filteredKelasByTingkat.length === 0" class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-2xl text-xs text-amber-300 space-y-1">
        <span class="font-bold block">⚠️ Belum ada kelas Tingkat {{ selectedTingkat }} yang Anda ampu</span>
        <span>Silakan daftarkan kelas Tingkat {{ selectedTingkat }} yang diajar di Menu Kelas terlebih dahulu.</span>
      </div>
    </div>

    <!-- LIVE STAT COUNTERS & STATUS TABS FILTER -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-4 border border-white/10 space-y-3 shadow-xl">
      <!-- Status Filter Tabs: scrollable horizontal on mobile -->
      <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar snap-x snap-mandatory" style="-webkit-overflow-scrolling:touch">
        <button
          @click="statusFilter = 'Semua'"
          :class="['px-3 py-2 rounded-2xl text-xs font-extrabold border transition-all shrink-0 min-h-[38px] snap-start', statusFilter === 'Semua' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/50' : 'bg-white/5 text-gray-400 border-white/10']"
        >
          Semua ({{ batchRows.length }})
        </button>

        <button
          @click="statusFilter = 'BelumDipanggil'"
          :class="['px-3 py-2 rounded-2xl text-xs font-extrabold border transition-all shrink-0 min-h-[38px] snap-start', statusFilter === 'BelumDipanggil' ? 'bg-amber-500/30 text-amber-200 border-amber-500/60' : 'bg-amber-500/10 text-amber-400 border-amber-500/30']"
        >
          ⚠️ Belum Dipanggil ({{ countUnset }})
        </button>

        <button
          @click="statusFilter = 'Hadir'"
          :class="['px-3 py-2 rounded-2xl text-xs font-extrabold border transition-all shrink-0 min-h-[38px] snap-start', statusFilter === 'Hadir' ? 'bg-emerald-500/30 text-emerald-200 border-emerald-500/60' : 'bg-white/5 text-emerald-400 border-emerald-500/20']"
        >
          🟢 Hadir ({{ countHadir }})
        </button>

        <button
          @click="statusFilter = 'Sakit'"
          :class="['px-3 py-2 rounded-2xl text-xs font-extrabold border transition-all shrink-0 min-h-[38px] snap-start', statusFilter === 'Sakit' ? 'bg-amber-500/30 text-amber-200 border-amber-500/60' : 'bg-white/5 text-amber-400 border-amber-500/20']"
        >
          🟡 Sakit ({{ countSakit }})
        </button>

        <button
          @click="statusFilter = 'Izin'"
          :class="['px-3 py-2 rounded-2xl text-xs font-extrabold border transition-all shrink-0 min-h-[38px] snap-start', statusFilter === 'Izin' ? 'bg-blue-500/30 text-blue-200 border-blue-500/60' : 'bg-white/5 text-blue-400 border-blue-500/20']"
        >
          🔵 Izin ({{ countIzin }})
        </button>

        <button
          @click="statusFilter = 'Alpa'"
          :class="['px-3 py-2 rounded-2xl text-xs font-extrabold border transition-all shrink-0 min-h-[38px] snap-start', statusFilter === 'Alpa' ? 'bg-red-500/30 text-red-200 border-red-500/60' : 'bg-white/5 text-red-400 border-red-500/20']"
        >
          🔴 Alpa ({{ countAlpa }})
        </button>

        <button
          v-if="countInactive > 0"
          @click="statusFilter = 'Inactive'"
          :class="['px-3 py-2 rounded-2xl text-xs font-extrabold border transition-all shrink-0 min-h-[38px] snap-start', statusFilter === 'Inactive' ? 'bg-red-500/30 text-red-200 border-red-500/60' : 'bg-red-500/10 text-red-400 border-red-500/30']"
        >
          🚫 Keluar / Pindah ({{ countInactive }})
        </button>
      </div>

      <!-- Quick Search -->
      <input
        v-model="searchQuery"
        type="text"
        placeholder="🔍 Cari nama siswa..."
        class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-white text-xs placeholder-gray-400 focus:border-cyan-400 outline-none min-h-[40px]"
      />
    </div>

    <!-- STUDENT ATTENDANCE ROWS LIST -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl p-3 sm:p-5 space-y-3">
      <div
        v-for="(row, idx) in filteredBatchRows"
        :key="row.siswaId"
        :class="[
          'p-3 sm:p-4 rounded-2xl border transition-all',
          row.isInactive
            ? (row.statusSiswa === 'Keluar' ? 'bg-red-950/25 border-red-500/50 shadow-md' : 'bg-amber-950/25 border-amber-500/50 shadow-md')
            : (!row.status
                ? 'bg-amber-500/10 border-amber-500/40 shadow-md'
                : 'bg-white/5 border-white/10 hover:bg-white/10')
        ]"
      >
        <!-- Inactive Alert Banner -->
        <div
          v-if="row.isInactive"
          :class="[
            'mb-3 px-3 py-2 rounded-xl text-xs font-black flex items-center justify-between border',
            row.statusSiswa === 'Keluar'
              ? 'bg-red-500/20 text-red-200 border-red-500/40'
              : 'bg-amber-500/20 text-amber-200 border-amber-500/40'
          ]"
        >
          <span class="flex items-center gap-1.5">
            <span>{{ row.statusSiswa === 'Keluar' ? '🚫' : '🚚' }}</span>
            <span>STATUS: SISWA TELAH {{ row.statusSiswa.toUpperCase() }}</span>
            <span v-if="row.tanggalStatus" class="text-[10px] font-normal font-mono opacity-80">(sejak {{ row.tanggalStatus }})</span>
          </span>
          <span class="text-[10px] uppercase font-bold tracking-wider">Presensi Dinonaktifkan</span>
        </div>

        <!-- Student Information -->
        <div class="flex items-center gap-3 mb-3">
          <span class="w-8 h-8 rounded-xl bg-white/10 text-cyan-300 font-mono text-xs flex items-center justify-center font-extrabold shrink-0 border border-white/10">
            {{ idx + 1 }}
          </span>
          <div class="min-w-0 flex-1">
            <div class="font-extrabold text-white text-sm flex items-center gap-2 flex-wrap">
              <span :class="row.isInactive ? 'line-through text-gray-400' : 'text-white'" class="truncate">{{ row.namaSiswa }}</span>
              <span
                v-if="row.isInactive"
                :class="row.statusSiswa === 'Keluar' ? 'bg-red-500/25 text-red-300 border border-red-500/40' : 'bg-amber-500/25 text-amber-300 border border-amber-500/40'"
                class="text-[10px] px-2 py-0.5 rounded-full border font-black uppercase shrink-0"
              >
                {{ row.statusSiswa === 'Keluar' ? '🚫 KELUAR' : '🚚 PINDAH' }}
              </span>
              <span v-else-if="!row.status" class="text-[10px] bg-amber-500/20 text-amber-300 px-2 py-0.5 rounded-full border border-amber-500/40 font-black animate-pulse shrink-0">
                ⚠️ Belum Dipanggil
              </span>
            </div>
            <div class="text-xs text-gray-400 mt-0.5 font-medium flex items-center gap-2">
              <span>Kelas: <strong class="text-cyan-400">{{ row.kelas }}</strong></span>
              <span v-if="row.nis" class="text-gray-500 font-mono">NIS: {{ row.nis }}</span>
            </div>
          </div>
        </div>

        <!-- Blocked Message for Inactive Student -->
        <div v-if="row.isInactive" class="p-3 bg-red-950/40 border border-red-500/30 rounded-2xl text-center text-xs text-red-300 font-bold flex items-center justify-center gap-2">
          <span>🚫 Siswa berstatus {{ row.statusSiswa.toUpperCase() }} — Presensi diblokir dan tidak dicatat dalam sistem kehadiran.</span>
        </div>

        <!-- H, S, I, A Buttons: 2x2 grid on mobile, 1 row on sm+ for Active Students -->
        <div v-else class="grid grid-cols-2 sm:flex sm:items-center gap-2">
          <!-- HADIR (H) -->
          <button
            type="button"
            @click="row.status = 'Hadir'"
            :class="[
              'py-2.5 px-3 rounded-2xl font-extrabold text-xs border transition-all duration-200 active:scale-95 min-h-[44px] flex items-center justify-center gap-1.5 w-full',
              row.status === 'Hadir'
                ? 'bg-emerald-500 text-slate-950 border-emerald-400 shadow-lg shadow-emerald-500/30 scale-105 font-black'
                : 'bg-white/5 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20'
            ]"
          >
            🟢 Hadir (H)
          </button>

          <!-- SAKIT (S) -->
          <button
            type="button"
            @click="row.status = 'Sakit'"
            :class="[
              'py-2.5 px-3 rounded-2xl font-extrabold text-xs border transition-all duration-200 active:scale-95 min-h-[44px] flex items-center justify-center gap-1.5 w-full',
              row.status === 'Sakit'
                ? 'bg-amber-500 text-slate-950 border-amber-400 shadow-lg shadow-amber-500/30 scale-105 font-black'
                : 'bg-white/5 text-amber-400 border-amber-500/20 hover:bg-amber-500/20'
            ]"
          >
            🟡 Sakit (S)
          </button>

          <!-- IZIN (I) -->
          <button
            type="button"
            @click="row.status = 'Izin'"
            :class="[
              'py-2.5 px-3 rounded-2xl font-extrabold text-xs border transition-all duration-200 active:scale-95 min-h-[44px] flex items-center justify-center gap-1.5 w-full',
              row.status === 'Izin'
                ? 'bg-blue-500 text-slate-950 border-blue-400 shadow-lg shadow-blue-500/30 scale-105 font-black'
                : 'bg-white/5 text-blue-400 border-blue-500/20 hover:bg-blue-500/20'
            ]"
          >
            🔵 Izin (I)
          </button>

          <!-- ALPA (A) -->
          <button
            type="button"
            @click="row.status = 'Alpa'"
            :class="[
              'py-2.5 px-3 rounded-2xl font-extrabold text-xs border transition-all duration-200 active:scale-95 min-h-[44px] flex items-center justify-center gap-1.5 w-full',
              row.status === 'Alpa'
                ? 'bg-red-500 text-slate-950 border-red-400 shadow-lg shadow-red-500/30 scale-105 font-black'
                : 'bg-white/5 text-red-400 border-red-500/20 hover:bg-red-500/20'
            ]"
          >
            🔴 Alpa (A)
          </button>
        </div>
      </div>

      <div v-if="filteredBatchRows.length === 0" class="p-12 text-center text-gray-400 text-xs font-medium">
        Tidak ada siswa yang cocok dengan filter atau pencarian.
      </div>
    </div>

    <!-- FIXED BOTTOM ACTION BAR -->
    <div class="fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-2xl border-t border-white/10 px-3 sm:px-6 py-3 shadow-2xl safe-area-bottom">
      <div class="max-w-7xl mx-auto space-y-2">
        <!-- Status info row: only on mobile -->
        <div class="flex sm:hidden items-center justify-between text-[11px] font-bold px-1">
          <span class="text-gray-400">Total: <strong class="text-white">{{ batchRows.length }} Siswa</strong></span>
          <span class="text-amber-400 font-extrabold" v-if="countUnset > 0">⚠️ Belum: {{ countUnset }}</span>
          <span class="text-emerald-400 font-extrabold" v-else>✓ Semua Dipanggil</span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Quick Pills Jadwal Hari Ini -->
            <div v-if="todayScheduledClasses.length > 0" class="hidden sm:flex items-center gap-1.5 mr-2">
              <span class="text-[11px] font-bold text-cyan-300">Hari ini:</span>
              <button
                v-for="k in todayScheduledClasses"
                :key="k"
                type="button"
                @click="batchKelas = k; selectedTingkat = getTingkatFromKelas(k);"
                :class="[
                  'px-2.5 py-1 rounded-xl text-xs font-bold transition-all border',
                  batchKelas === k
                    ? 'bg-cyan-500 text-slate-950 border-cyan-400'
                    : 'bg-white/5 text-gray-300 border-white/10 hover:bg-white/10'
                ]"
              >
                {{ k }}
              </button>
            </div>
          <!-- Status on desktop -->
          <div class="hidden sm:flex flex-1 items-center gap-3 text-xs font-bold">
            <span class="text-gray-300">Total: <strong class="text-white font-black">{{ batchRows.length }} Siswa</strong></span>
            <span class="text-amber-400 font-extrabold" v-if="countUnset > 0">⚠️ Belum Dipanggil: {{ countUnset }}</span>
            <span class="text-emerald-400 font-extrabold" v-else>✓ Semua Siswa Sudah Dipanggil</span>
          </div>

          <button
            type="button"
            @click="$emit('back-to-matrix', batchKelas)"
            class="flex-1 sm:flex-none px-4 py-3 rounded-2xl border border-white/10 text-gray-300 text-xs font-bold hover:bg-white/5 active:scale-95 transition-all min-h-[44px]"
          >
            Kembali
          </button>

          <button
            type="button"
            @click="handleSaveBatchAttendance"
            class="flex-[2] sm:flex-none px-5 py-3 rounded-2xl bg-gradient-to-r from-cyan-400 via-teal-400 to-indigo-500 text-slate-950 text-xs font-black shadow-lg shadow-cyan-500/30 hover:opacity-95 active:scale-95 transition-all min-h-[44px]"
          >
            💾 Simpan Presensi
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
