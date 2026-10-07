<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const teacherName = computed(() => page.props.auth?.user?.name || 'Guru Pengajar');

const props = defineProps({
  jurnalList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  jadwalList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] },
  teacherSubject: { type: String, default: 'Informatika' }
});

const emit = defineEmits(['save-jurnal', 'delete-jurnal']);

// Indonesian Month Names
const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Master School Teaching Period Hours Definition (Jam ke-1 s/d Jam ke-10)
const schoolPeriodSlots = [
  { id: 1, label: 'Jam ke-1', time: '07.00 - 07.45' },
  { id: 2, label: 'Jam ke-2', time: '07.45 - 08.30' },
  { id: 3, label: 'Jam ke-3', time: '08.30 - 09.15' },
  { id: 4, label: 'Jam ke-4', time: '09.30 - 10.15' },
  { id: 5, label: 'Jam ke-5', time: '10.15 - 11.00' },
  { id: 6, label: 'Jam ke-6', time: '11.00 - 11.45' },
  { id: 7, label: 'Jam ke-7', time: '12.30 - 13.15' },
  { id: 8, label: 'Jam ke-8', time: '13.15 - 14.00' },
  { id: 9, label: 'Jam ke-9', time: '14.15 - 15.00' },
  { id: 10, label: 'Jam ke-10', time: '15.00 - 15.45' }
];

// Helper: Get Today's Date String YYYY-MM-DD
function getTodayString() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// Current Date Defaults & Filters
const now = new Date();
const selectedYear = ref(now.getFullYear());
const selectedMonth = ref(now.getMonth() + 1); // 1-12
const selectedKelasFilter = ref('Semua');
const selectedMapelFilter = ref('Semua');
const searchQuery = ref('');

// 1. MATA PELAJARAN RESMI GURU (MURNI DARI DATA GURU ADMIN: tab=guru / mapel_utama)
const teacherAdminSubjects = computed(() => {
  const str = props.teacherSubject || '';
  const list = str.split(',').map(s => s.trim()).filter(s => s.length > 0);
  if (list.length > 0) return list;
  return ['Informatika'];
});

// 2. KELAS YANG SUDAH DIJADWALKAN OLEH GURU (DARI tab=jadwal)
const scheduledClasses = computed(() => {
  const set = new Set();
  (props.jadwalList || []).forEach(j => {
    const k = j.kelas;
    if (k && typeof k === 'string' && k.trim()) set.add(k.trim());
  });

  if (set.size > 0) {
    return [...set].sort();
  }

  // Fallback ke daftar kelas ampuan jika jadwal belum dibuat oleh guru
  return (props.kelasList || []).map(k => k.namaKelas || k);
});

// Helper: Format Date String to Indonesian
function formatIndoDate(dateStr) {
  if (!dateStr) return '-';
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  const d = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
  return d.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

// Helper: Format Jam Mengajar into short label (e.g. "Jam 1 - 2" or "Jam 3 - 4")
function formatJamShort(jamStr) {
  if (!jamStr) return 'Jam 1 - 2';
  const matches = String(jamStr).match(/\d+/g);
  if (!matches || matches.length === 0) return jamStr;
  
  if (matches.length >= 2) {
    const min = Math.min(parseInt(matches[0], 10), parseInt(matches[1], 10));
    const max = Math.max(parseInt(matches[0], 10), parseInt(matches[1], 10));
    return min === max ? `Jam ${min}` : `Jam ${min} - ${max}`;
  }
  return `Jam ${matches[0]}`;
}

// Filtered & Sorted Jurnal List
const filteredJurnal = computed(() => {
  let list = [...props.jurnalList];

  // Filter Bulan & Tahun
  list = list.filter(j => {
    if (!j.tanggal) return true;
    const parts = j.tanggal.split('-');
    const jYear = parseInt(parts[0], 10);
    const jMonth = parseInt(parts[1], 10);
    return jYear === Number(selectedYear.value) && jMonth === Number(selectedMonth.value);
  });

  // Filter Kelas
  if (selectedKelasFilter.value !== 'Semua') {
    list = list.filter(j => j.kelas === selectedKelasFilter.value);
  }

  // Filter Mata Pelajaran
  if (selectedMapelFilter.value !== 'Semua') {
    list = list.filter(j => {
      const m = j.mataPelajaran || j.mata_pelajaran || '';
      return m.toLowerCase() === selectedMapelFilter.value.toLowerCase();
    });
  }

  // Filter Search Query
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(j => 
      j.materi.toLowerCase().includes(q) || 
      j.kelas.toLowerCase().includes(q) || 
      ((j.mataPelajaran || j.mata_pelajaran) && (j.mataPelajaran || j.mata_pelajaran).toLowerCase().includes(q)) ||
      (j.uraian && j.uraian.toLowerCase().includes(q)) ||
      (j.catatan && j.catatan.toLowerCase().includes(q))
    );
  }

  // Sort Chronologically Ascending
  return list.sort((a, b) => {
    const dateA = new Date(a.tanggal || 0);
    const dateB = new Date(b.tanggal || 0);
    if (dateA - dateB !== 0) return dateA - dateB;
    return (a.id || 0) - (b.id || 0);
  });
});

// Stat Summary Calculations
const totalJurnal = computed(() => filteredJurnal.value.length);

const totalJamMengajar = computed(() => {
  return filteredJurnal.value.reduce((acc, curr) => {
    const jamVal = parseInt(curr.jamKe || '2', 10);
    return acc + (isNaN(jamVal) ? 2 : jamVal);
  }, 0);
});

const kelasTerbanyak = computed(() => {
  if (filteredJurnal.value.length === 0) return '-';
  const counts = {};
  filteredJurnal.value.forEach(j => {
    counts[j.kelas] = (counts[j.kelas] || 0) + 1;
  });
  let top = '-';
  let max = 0;
  for (const k in counts) {
    if (counts[k] > max) {
      max = counts[k];
      top = k;
    }
  }
  return top;
});

// Target Class & Semester helpers for PDF/Excel Export
const targetKelas = computed(() => {
  if (selectedKelasFilter.value && selectedKelasFilter.value !== 'Semua') {
    return selectedKelasFilter.value;
  }
  return filteredJurnal.value[0]?.kelas || scheduledClasses.value[0] || 'Semua Kelas Terjadwal';
});

const semesterName = computed(() => {
  return Number(selectedMonth.value) >= 7 ? 'Ganjil' : 'Genap';
});

const targetTahunPelajaran = computed(() => {
  const tp = filteredJurnal.value[0]?.tahunPelajaran || jurnalForm.value.tahunPelajaran || '2025/2026';
  return String(tp).startsWith('Tahun') ? tp : `Tahun Pelajaran ${tp}`;
});

// Modal Form State & Simplified Start/End Period Selection
const showJurnalModal = ref(false);
const editingJurnal = ref(null);

const startPeriod = ref(1);
const endPeriod = ref(3);

const jurnalForm = ref({
  tanggal: getTodayString(),
  jamKe: '',
  kelas: scheduledClasses.value[0] || 'X RPL 1',
  mataPelajaran: teacherAdminSubjects.value[0] || 'Informatika',
  materi: '',
  uraian: '',
  catatan: '',
  tahunPelajaran: 'Tahun Pelajaran 2025/2026',
  semester: 'Ganjil'
});

// Pilihan Mata Pelajaran yang Terikat dengan Kelas Terpilih Berdasarkan Jadwal Guru
const subjectsForSelectedKelas = computed(() => {
  if (!jurnalForm.value.kelas) return teacherAdminSubjects.value;

  const scheduled = (props.jadwalList || [])
    .filter(j => j.kelas === jurnalForm.value.kelas)
    .map(j => j.mataPelajaran || j.mata_pelajaran)
    .filter(Boolean);

  const set = new Set(scheduled);
  if (set.size > 0) {
    return [...set];
  }

  // Jika kelas belum terjadwal secara spesifik, sediakan mapel resmi guru dari Admin
  return teacherAdminSubjects.value;
});

// Event saat guru mengganti Kelas di Modal: Otomatis kunci mapel & jam sesuai jadwal kelas tersebut!
function onKelasChange() {
  const mapels = subjectsForSelectedKelas.value;
  if (mapels.length > 0) {
    jurnalForm.value.mataPelajaran = mapels[0];
  }

  // Auto-fill jam pelajaran jika ada jadwal pada kelas ini
  const schedulesInClass = (props.jadwalList || []).filter(j => j.kelas === jurnalForm.value.kelas);
  if (schedulesInClass.length > 0) {
    const firstJadwal = schedulesInClass[0];
    const jamStr = firstJadwal.jamKe || firstJadwal.jam_ke || '';
    const matches = String(jamStr).match(/\d+/g);
    if (matches && matches.length >= 2) {
      startPeriod.value = parseInt(matches[0], 10);
      endPeriod.value = parseInt(matches[1], 10);
    } else if (matches && matches.length === 1) {
      startPeriod.value = parseInt(matches[0], 10);
      endPeriod.value = parseInt(matches[0], 10);
    }
  }
}

// Computed Dynamic Jam Mengajar Label
const computedJamLabel = computed(() => {
  const minId = Math.min(startPeriod.value || 1, endPeriod.value || 1);
  const maxId = Math.max(startPeriod.value || 1, endPeriod.value || 1);

  return minId === maxId ? `Jam ${minId}` : `Jam ${minId} - ${maxId}`;
});

// Shortcut Action: Pilih Langsung dari Kartu Jadwal Mengajar Guru
function applyJadwalShortcut(jadwal) {
  if (!jadwal) return;
  if (jadwal.kelas) {
    jurnalForm.value.kelas = jadwal.kelas;
  }
  const subj = jadwal.mataPelajaran || jadwal.mata_pelajaran;
  if (subj) {
    jurnalForm.value.mataPelajaran = subj;
  } else {
    jurnalForm.value.mataPelajaran = subjectsForSelectedKelas.value[0] || teacherAdminSubjects.value[0];
  }

  const jamStr = jadwal.jamKe || jadwal.jam_ke || '';
  const matches = String(jamStr).match(/\d+/g);
  if (matches && matches.length >= 2) {
    startPeriod.value = parseInt(matches[0], 10);
    endPeriod.value = parseInt(matches[1], 10);
  } else if (matches && matches.length === 1) {
    startPeriod.value = parseInt(matches[0], 10);
    endPeriod.value = parseInt(matches[0], 10);
  }
}

function openAddJurnalModal() {
  editingJurnal.value = null;
  startPeriod.value = 1;
  endPeriod.value = 3;
  
  const defaultKelas = selectedKelasFilter.value !== 'Semua' 
    ? selectedKelasFilter.value 
    : (scheduledClasses.value[0] || 'X RPL 1');

  jurnalForm.value = {
    tanggal: getTodayString(),
    jamKe: '',
    kelas: defaultKelas,
    mataPelajaran: teacherAdminSubjects.value[0] || 'Informatika',
    materi: '',
    uraian: '',
    catatan: '',
    tahunPelajaran: 'Tahun Pelajaran 2026 - 2027',
    semester: 'Ganjil'
  };

  // Sesuaikan mapel dan jam dengan kelas default
  onKelasChange();

  showJurnalModal.value = true;
}

function openEditJurnal(item) {
  editingJurnal.value = item;
  const matches = (item.jamKe || '').match(/\d+/g);
  if (matches && matches.length >= 2) {
    startPeriod.value = parseInt(matches[0], 10);
    endPeriod.value = parseInt(matches[1], 10);
  } else if (matches && matches.length === 1) {
    startPeriod.value = parseInt(matches[0], 10);
    endPeriod.value = parseInt(matches[0], 10);
  } else {
    startPeriod.value = 1;
    endPeriod.value = 3;
  }

  jurnalForm.value = { 
    ...item,
    kelas: item.kelas,
    mataPelajaran: item.mataPelajaran || item.mata_pelajaran || teacherAdminSubjects.value[0] || 'Informatika',
    tahunPelajaran: item.tahunPelajaran || 'Tahun Pelajaran 2026 - 2027',
    semester: item.semester || 'Ganjil'
  };
  showJurnalModal.value = true;
}

function handleSaveJurnal() {
  if (!jurnalForm.value.tanggal || !jurnalForm.value.kelas || !jurnalForm.value.materi) return;
  
  jurnalForm.value.jamKe = computedJamLabel.value;
  if (!jurnalForm.value.mataPelajaran) {
    jurnalForm.value.mataPelajaran = subjectsForSelectedKelas.value[0] || teacherAdminSubjects.value[0] || 'Informatika';
  }

  emit('save-jurnal', { editing: editingJurnal.value, data: { ...jurnalForm.value } });
  showJurnalModal.value = false;
}

// Export Excel File (.xls Spreadsheet matching official template)
function exportCsv() {
  const monthName = monthNames[selectedMonth.value - 1];
  const mapelTitle = selectedMapelFilter.value !== 'Semua' 
    ? selectedMapelFilter.value.toUpperCase() 
    : teacherAdminSubjects.value.join(', ').toUpperCase();
  
  let rowsHtml = '';
  filteredJurnal.value.forEach((item, index) => {
    rowsHtml += `
      <tr height="20" style="height: 15pt;">
        <td height="20" valign="middle" style="text-align: center; border: 1px solid #000; height: 15pt; vertical-align: middle;">${index + 1}</td>
        <td height="20" valign="middle" style="font-weight: bold; border: 1px solid #000; height: 15pt; vertical-align: middle;">${formatIndoDate(item.tanggal)}</td>
        <td height="20" valign="middle" style="text-align: center; border: 1px solid #000; height: 15pt; vertical-align: middle;">${formatJamShort(item.jamKe)}</td>
        <td height="20" valign="middle" style="text-align: center; font-weight: bold; border: 1px solid #000; height: 15pt; vertical-align: middle;">${item.kelas}</td>
        <td height="20" valign="middle" style="border: 1px solid #000; height: 15pt; vertical-align: middle;">${item.mataPelajaran || '-'}</td>
        <td height="20" valign="middle" style="font-weight: bold; border: 1px solid #000; height: 15pt; vertical-align: middle;">${item.materi}</td>
        <td height="20" valign="middle" style="border: 1px solid #000; height: 15pt; vertical-align: middle;">${item.uraian || '-'}</td>
        <td height="20" valign="middle" style="border: 1px solid #000; height: 15pt; vertical-align: middle;">${item.catatan || '-'}</td>
      </tr>
    `;
  });

  const excelHtml = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
      <head>
        <meta charset="utf-8" />
        <!--[if gte mso 9]>
        <xml>
          <x:ExcelWorkbook>
            <x:ExcelWorksheets>
              <x:ExcelWorksheet>
                <x:Name>Jurnal Mengajar</x:Name>
                <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
              </x:ExcelWorksheet>
            </x:ExcelWorksheets>
          </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
          table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
          tr { height: 16pt; }
          th, td { border: 1px solid #000000; padding: 4px 6px; height: 16pt; vertical-align: middle; }
          .header-title { text-align: center; font-weight: bold; font-size: 16px; border: none; height: 25pt; vertical-align: middle; }
          .header-subtitle { text-align: center; font-weight: bold; font-size: 13px; border: none; height: 18pt; vertical-align: middle; }
          .meta-label { font-weight: bold; border: none; text-align: left; height: 16pt; vertical-align: middle; }
          .meta-val { font-weight: bold; border: none; text-align: left; height: 16pt; vertical-align: middle; }
        </style>
      </head>
      <body>
        <table>
          <tr height="30"><td colspan="8" class="header-title" valign="middle">JURNAL MENGAJAR GURU</td></tr>
          <tr height="22"><td colspan="8" class="header-subtitle" valign="middle">${targetTahunPelajaran.value}</td></tr>
          <tr height="10"><td colspan="8" style="border:none;"></td></tr>
          <tr height="20">
            <td class="meta-label" valign="middle">Nama Sekolah</td>
            <td colspan="7" class="meta-val" valign="middle">: SMKS MUHAMMADIYAH 2 GENTENG  BANYUWANGI</td>
          </tr>
          <tr height="20">
            <td class="meta-label" valign="middle">Nama Pendidik</td>
            <td colspan="7" class="meta-val" valign="middle">: ${teacherName.value}</td>
          </tr>
          <tr height="20">
            <td class="meta-label" valign="middle">Mata Pelajaran</td>
            <td colspan="7" class="meta-val" valign="middle">: ${mapelTitle}</td>
          </tr>
          <tr height="20">
            <td class="meta-label" valign="middle">Kelas / Semester</td>
            <td colspan="7" class="meta-val" valign="middle">: ${targetKelas.value} / ${semesterName.value}</td>
          </tr>
          <tr height="10"><td colspan="8" style="border:none;"></td></tr>
          <thead>
            <tr height="24" style="height: 18pt; background-color: #f1f5f9;">
              <th valign="middle" style="width: 32px; text-align: center; border: 1px solid #000; font-weight: bold;">No.</th>
              <th valign="middle" style="width: 95px; text-align: center; border: 1px solid #000; font-weight: bold;">Hari & Tanggal</th>
              <th valign="middle" style="width: 80px; text-align: center; border: 1px solid #000; font-weight: bold;">Jam</th>
              <th valign="middle" style="width: 70px; text-align: center; border: 1px solid #000; font-weight: bold;">Kelas</th>
              <th valign="middle" style="width: 140px; text-align: left; border: 1px solid #000; font-weight: bold;">Mata Pelajaran</th>
              <th valign="middle" style="width: 220px; text-align: left; border: 1px solid #000; font-weight: bold;">Pokok Bahasan / Materi</th>
              <th valign="middle" style="width: 320px; text-align: left; border: 1px solid #000; font-weight: bold;">Uraian Kegiatan Pembelajaran</th>
              <th valign="middle" style="width: 220px; text-align: left; border: 1px solid #000; font-weight: bold;">Catatan & Kendala</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml || '<tr><td colspan="8" style="text-align: center; border: 1px solid #000;">Belum ada catatan jurnal.</td></tr>'}
          </tbody>
        </table>
      </body>
    </html>
  `;

  const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel;charset=utf-8' });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  const cleanMapel = (selectedMapelFilter.value !== 'Semua' ? selectedMapelFilter.value : 'semua_mapel').replace(/\s+/g, '_');
  link.setAttribute("download", `jurnal_${targetKelas.value}_${cleanMapel}_${monthName}_${selectedYear.value}.xls`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Export PDF Document matching official template (F4 Portrait)
function exportPdf() {
  const url = route('guru.jurnal.export-pdf', {
    kelas: selectedKelasFilter.value,
    mapel: selectedMapelFilter.value,
    month: selectedMonth.value,
    year: selectedYear.value
  });
  window.open(url, '_blank');
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Title & Top Actions Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Jurnal KBM Mengajar</span>
          <span class="text-cyan-400 text-base">📝</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">
          Pencatatan harian KBM disesuaikan dengan Jadwal Pelajaran Guru &amp; Mata Pelajaran Admin
        </p>
      </div>

      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        <!-- Export Excel (.xls) -->
        <button
          type="button"
          @click.prevent="exportCsv"
          class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-emerald-600/25 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span>📊</span>
          <span>Export Excel (.xls)</span>
        </button>

        <!-- Export / Print PDF -->
        <button
          type="button"
          @click.prevent="exportPdf"
          class="inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-500 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-purple-600/25 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span>🖨️</span>
          <span>Cetak / Export PDF</span>
        </button>

        <!-- Add Jurnal Button -->
        <button
          @click="openAddJurnalModal"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-teal-600 to-indigo-600 text-white px-5 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-105 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span class="text-base font-black">+</span>
          <span>Catat Jurnal Mengajar Baru</span>
        </button>
      </div>
    </div>

    <!-- Stat Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-cyan-500/20 text-cyan-300 font-black text-sm flex items-center justify-center border border-cyan-500/30 shadow-md">📚</span>
          <span class="text-[11px] font-extrabold text-cyan-300 bg-cyan-500/15 border border-cyan-500/30 px-3 py-1 rounded-full font-mono">TOTAL JURNAL</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ totalJurnal }}</div>
        <div class="text-xs text-gray-400 font-semibold">Sesi Pembelajaran Tercatat</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-teal-500/20 text-teal-300 font-black text-sm flex items-center justify-center border border-teal-500/30 shadow-md">⏱️</span>
          <span class="text-[11px] font-extrabold text-teal-300 bg-teal-500/15 border border-teal-500/30 px-3 py-1 rounded-full font-mono">JAM MENGAJAR</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">~{{ totalJamMengajar }} Jam</div>
        <div class="text-xs text-gray-400 font-semibold">Akumulasi Bulan Ini</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-indigo-500/20 text-indigo-300 font-black text-sm flex items-center justify-center border border-indigo-500/30 shadow-md">🏫</span>
          <span class="text-[11px] font-extrabold text-indigo-300 bg-indigo-500/15 border border-indigo-500/30 px-3 py-1 rounded-full font-mono">KELAS INTENSIF</span>
        </div>
        <div class="text-2xl font-black text-white tracking-tight mb-1 truncate">{{ kelasTerbanyak }}</div>
        <div class="text-xs text-gray-400 font-semibold">Terbanyak Diajar</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-purple-500/20 text-purple-300 font-black text-sm flex items-center justify-center border border-purple-500/30 shadow-md">📖</span>
          <span class="text-[11px] font-extrabold text-purple-300 bg-purple-500/15 border border-purple-500/30 px-3 py-1 rounded-full font-mono">MAPEL ADMIN</span>
        </div>
        <div class="text-2xl font-black text-white tracking-tight mb-1 truncate">
          {{ teacherAdminSubjects.length }} Mapel
        </div>
        <div class="text-xs text-gray-400 font-semibold truncate" :title="teacherAdminSubjects.join(', ')">
          {{ teacherAdminSubjects.join(', ') }}
        </div>
      </div>
    </div>

    <!-- Filter Control Bar (5 Columns: Bulan, Tahun, Kelas Terjadwal, Mapel Guru, Cari) -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        
        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Pilih Bulan</label>
          <select v-model="selectedMonth" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]">
            <option v-for="(mName, idx) in monthNames" :key="idx" :value="idx + 1">
              {{ mName }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Pilih Tahun</label>
          <select v-model="selectedYear" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]">
            <option :value="2025">Tahun 2025</option>
            <option :value="2026">Tahun 2026</option>
            <option :value="2027">Tahun 2027</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Filter Kelas Terjadwal</label>
          <select v-model="selectedKelasFilter" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]">
            <option value="Semua">Semua Kelas Terjadwal</option>
            <option v-for="k in scheduledClasses" :key="k" :value="k">{{ k }}</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Filter Mata Pelajaran</label>
          <select v-model="selectedMapelFilter" class="w-full bg-slate-800 border border-cyan-500/30 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-cyan-300 font-bold focus:border-cyan-400 outline-none min-h-[44px]">
            <option value="Semua">Semua Mapel Guru</option>
            <option v-for="m in teacherAdminSubjects" :key="m" :value="m">📖 {{ m }}</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Cari Materi / Catatan</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari materi KBM..."
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-cyan-400 outline-none min-h-[44px]"
          />
        </div>

      </div>
    </div>

    <!-- JURNAL TABLE & MOBILE CARDS -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      
      <!-- Desktop & Tablet Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="px-4 py-4 text-center w-12 border-r border-white/5">No</th>
              <th class="px-5 py-4 font-black text-white w-44 border-r border-white/5">Hari &amp; Tanggal</th>
              <th class="px-4 py-4 text-center w-36 border-r border-white/5">Jam</th>
              <th class="px-4 py-4 text-center w-28 border-r border-white/5">Kelas</th>
              <th class="px-5 py-4 w-48 border-r border-white/5">Mata Pelajaran</th>
              <th class="px-6 py-4 w-60 border-r border-white/5">Pokok Bahasan / Sub-Materi</th>
              <th class="px-6 py-4 min-w-[200px] border-r border-white/5">Uraian Kegiatan KBM</th>
              <th class="px-5 py-4 w-48 border-r border-white/5">Catatan & Kendala</th>
              <th class="px-4 py-4 text-right w-32">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr
              v-for="(item, index) in filteredJurnal"
              :key="item.id"
              class="hover:bg-white/5 transition-colors"
            >
              <td class="px-4 py-4 text-center font-mono text-gray-400 text-xs border-r border-white/5">
                {{ index + 1 }}
              </td>

              <td class="px-5 py-4 border-r border-white/5">
                <div class="flex items-center gap-2">
                  <span class="w-7 h-7 rounded-xl bg-cyan-500/20 text-cyan-300 flex items-center justify-center text-xs shrink-0 border border-cyan-500/30">📅</span>
                  <span class="font-extrabold text-white text-xs leading-snug">{{ formatIndoDate(item.tanggal) }}</span>
                </div>
              </td>

              <td class="px-4 py-4 text-center border-r border-white/5">
                <span class="inline-flex items-center gap-1 bg-cyan-500/20 text-cyan-300 text-xs font-extrabold px-2.5 py-1 rounded-full border border-cyan-500/30">
                  <span>⏱️</span>
                  <span>{{ formatJamShort(item.jamKe) }}</span>
                </span>
              </td>

              <td class="px-4 py-4 text-center border-r border-white/5">
                <span class="inline-flex items-center gap-1 bg-teal-500/20 text-teal-300 text-xs font-black px-2.5 py-1 rounded-full border border-teal-500/30">
                  <span>🏫</span>
                  <span>{{ item.kelas }}</span>
                </span>
              </td>

              <td class="px-5 py-4 border-r border-white/5">
                <span class="inline-flex items-center gap-1.5 bg-indigo-500/20 text-indigo-300 text-xs font-bold px-3 py-1 rounded-full border border-indigo-500/30">
                  <span>📖</span>
                  <span class="truncate max-w-[140px]">{{ item.mataPelajaran || item.mata_pelajaran || teacherAdminSubjects[0] }}</span>
                </span>
              </td>

              <td class="px-6 py-4 border-r border-white/5">
                <div class="font-extrabold text-cyan-300 text-xs leading-snug">
                  💡 {{ item.materi }}
                </div>
              </td>

              <td class="px-6 py-4 border-r border-white/5">
                <div v-if="item.uraian" class="bg-white/5 rounded-2xl p-2.5 border border-white/10 text-xs text-gray-300 leading-relaxed font-medium">
                  {{ item.uraian }}
                </div>
                <span v-else class="text-gray-500 italic text-xs">-</span>
              </td>

              <td class="px-5 py-4 border-r border-white/5">
                <div v-if="item.catatan" class="bg-amber-500/10 rounded-2xl p-2.5 border border-amber-500/30 text-xs text-amber-200 italic leading-relaxed font-medium">
                  📝 {{ item.catatan }}
                </div>
                <span v-else class="text-gray-500 italic text-xs">-</span>
              </td>

              <td class="px-4 py-4 text-right shrink-0">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    @click="openEditJurnal(item)"
                    class="px-2.5 py-1.5 text-xs font-bold text-cyan-300 bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 transition-all active:scale-95"
                    title="Edit Jurnal"
                  >
                    ✏️
                  </button>
                  <button
                    @click="$emit('delete-jurnal', item.id)"
                    class="px-2.5 py-1.5 text-xs font-bold text-red-400 bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 transition-all active:scale-95"
                    title="Hapus Jurnal"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="filteredJurnal.length === 0">
              <td colspan="9" class="px-6 py-12 text-center text-gray-400 text-xs font-medium">
                Belum ada catatan jurnal mengajar yang cocok dengan filter yang dipilih. Klik "+ Catat Jurnal Mengajar Baru".
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Smartphone Card List View -->
      <div class="block md:hidden p-4 space-y-4">
        <div v-for="item in filteredJurnal" :key="item.id" class="bg-slate-950/40 rounded-2xl p-4 border border-white/10 space-y-3 shadow-md">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <span class="font-extrabold text-xs text-white">📅 {{ formatIndoDate(item.tanggal) }}</span>
            <div class="flex items-center gap-1.5">
              <span class="bg-indigo-500/20 text-indigo-300 text-[11px] font-bold px-2.5 py-0.5 rounded-full border border-indigo-500/30">
                📖 {{ item.mataPelajaran || item.mata_pelajaran || teacherAdminSubjects[0] }}
              </span>
              <span class="bg-cyan-500/20 text-cyan-300 text-xs font-extrabold px-3 py-0.5 rounded-full border border-cyan-500/30">
                {{ item.kelas }}
              </span>
            </div>
          </div>

          <div>
            <div class="text-xs text-teal-400 font-extrabold mb-1">⏱️ {{ formatJamShort(item.jamKe) }}</div>
            <h3 class="text-base font-black text-cyan-300">💡 {{ item.materi }}</h3>
            <p v-if="item.uraian" class="text-xs text-gray-300 mt-2 leading-relaxed bg-white/5 p-3 rounded-2xl border border-white/10 font-medium">
              {{ item.uraian }}
            </p>
            <p v-if="item.catatan" class="text-xs text-amber-200 italic mt-2 bg-amber-500/10 p-2.5 rounded-2xl border border-amber-500/30 font-medium">
              📝 {{ item.catatan }}
            </p>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/10">
            <button @click="openEditJurnal(item)" class="flex-1 py-2 text-center text-cyan-300 font-bold text-xs bg-cyan-500/15 rounded-xl border border-cyan-500/30 active:scale-95 transition-all min-h-[40px]">
              ✏️ Edit Jurnal
            </button>
            <button @click="$emit('delete-jurnal', item.id)" class="flex-1 py-2 text-center text-red-400 font-bold text-xs bg-red-500/15 rounded-xl border border-red-500/30 active:scale-95 transition-all min-h-[40px]">
              🗑️ Hapus
            </button>
          </div>
        </div>

        <div v-if="filteredJurnal.length === 0" class="p-6 text-center text-gray-400 text-xs font-medium">
          Belum ada catatan jurnal mengajar yang sesuai.
        </div>
      </div>

    </div>

    <!-- MODAL FORM CRUD JURNAL MENGAJAR (TERIKAT JADWAL GURU & MAPEL ADMIN) -->
    <div v-if="showJurnalModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl p-6 sm:p-8 max-w-xl w-full shadow-2xl max-h-[92vh] overflow-y-auto space-y-5 transform transition-all animate-in fade-in zoom-in-95">
        <div class="w-12 h-1 bg-white/20 rounded-full mx-auto sm:hidden"></div>
        
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div>
            <h3 class="text-lg font-black text-white flex items-center gap-2">
              <span>{{ editingJurnal ? 'Edit Jurnal Mengajar' : 'Catat Jurnal Mengajar Baru' }}</span>
              <span class="text-cyan-400 text-sm">✍️</span>
            </h3>
            <p class="text-xs text-gray-400 mt-0.5">Diselaraskan dengan Jadwal Mengajar Guru &amp; Mapel Admin</p>
          </div>
          <button @click="showJurnalModal = false" class="text-gray-400 hover:text-white text-lg font-bold p-1">✕</button>
        </div>

        <!-- QUICK SHORTCUT: AUTO-FILL DARI JADWAL PELAJARAN BUATAN GURU -->
        <div v-if="!editingJurnal && jadwalList && jadwalList.length > 0" class="p-3.5 bg-gradient-to-r from-cyan-950/40 via-slate-900 to-indigo-950/40 border border-cyan-500/30 rounded-2xl space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-black uppercase text-cyan-300 flex items-center gap-1.5">
              <span>⚡</span>
              <span>Pilih Cepat dari Jadwal Mengajar Anda:</span>
            </span>
            <span class="text-[10px] text-cyan-400/80 font-mono">1-Klik Auto-Fill</span>
          </div>
          <div class="flex flex-wrap gap-2 max-h-28 overflow-y-auto pr-1">
            <button
              v-for="j in jadwalList"
              :key="j.id"
              type="button"
              @click="applyJadwalShortcut(j)"
              class="px-3 py-1.5 rounded-xl bg-slate-800/90 hover:bg-cyan-500/25 border border-white/10 hover:border-cyan-500/40 text-[11px] text-gray-200 hover:text-cyan-200 transition-all font-semibold flex items-center gap-1.5 shrink-0 active:scale-95"
            >
              <span class="text-cyan-400 font-bold">{{ j.hari }}</span>
              <span>•</span>
              <span class="font-bold text-white">{{ j.kelas }}</span>
              <span>•</span>
              <span class="text-teal-300">{{ j.mataPelajaran || j.mata_pelajaran }}</span>
              <span class="text-gray-400 font-mono text-[10px]">({{ j.jamKe || j.jam_ke }})</span>
            </button>
          </div>
        </div>
        
        <form @submit.prevent="handleSaveJurnal" class="space-y-4 text-xs sm:text-sm">
          
          <!-- Tanggal & Semester -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Tanggal Mengajar *</label>
              <input
                v-model="jurnalForm.tanggal"
                type="date"
                class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none min-h-[44px]"
                required
              />
            </div>

            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Semester *</label>
              <select
                v-model="jurnalForm.semester"
                class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm font-semibold min-h-[44px]"
                required
              >
                <option value="Ganjil">Semester Ganjil</option>
                <option value="Genap">Semester Genap</option>
              </select>
            </div>
          </div>

          <!-- Kelas Target (Dari Jadwal Guru) -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-gray-300 font-bold uppercase text-[11px]">
                Pilih Kelas Terjadwal *
              </label>
              <span class="text-[10px] text-cyan-400 font-mono">
                Terhubung ke Jadwal Guru
              </span>
            </div>
            <select
              v-model="jurnalForm.kelas"
              @change="onKelasChange"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm font-semibold min-h-[44px]"
              required
            >
              <option value="" disabled>-- Pilih Kelas Terjadwal --</option>
              <option v-for="k in scheduledClasses" :key="k" :value="k">
                🏫 Kelas {{ k }}
              </option>
            </select>
          </div>

          <!-- MATA PELAJARAN (TERIKAT JADWAL KELAS & MAPEL ADMIN GURU) -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <label class="block text-cyan-300 font-bold uppercase text-[11px]">
                Mata Pelajaran KBM *
              </label>
              <span class="text-[10px] bg-cyan-500/15 text-cyan-300 px-2.5 py-0.5 rounded-full border border-cyan-500/30 font-mono font-bold">
                ✓ Baku (Setting Admin)
              </span>
            </div>

            <!-- Dropdown Pilihan Mapel Resmi (Baku, Tanpa Ketik Bebas) -->
            <div>
              <select
                v-model="jurnalForm.mataPelajaran"
                class="w-full bg-slate-800 border border-cyan-500/40 rounded-2xl px-4 py-3 text-cyan-300 font-bold outline-none text-sm min-h-[44px] focus:border-cyan-400"
                required
              >
                <option v-for="subj in subjectsForSelectedKelas" :key="subj" :value="subj">
                  📖 {{ subj }}
                </option>
              </select>
            </div>
            <p class="text-[10px] text-gray-400">
              Mata pelajaran terikat langsung dengan jadwal mengajar kelas dan data penugasan resmi dari Admin.
            </p>
          </div>

          <!-- PILIHAN JAM MENGAJAR RINGKAS -->
          <div class="bg-slate-950/60 rounded-3xl p-5 border border-white/10 space-y-4">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-extrabold text-cyan-300 uppercase">
                Pilih Jam Mengajar
              </label>
              <span class="text-xs font-black text-emerald-300 bg-emerald-500/20 px-3 py-0.5 rounded-full border border-emerald-500/30 font-mono">
                {{ (Math.max(startPeriod || 1, endPeriod || 1) - Math.min(startPeriod || 1, endPeriod || 1)) + 1 }} Jam Pelajaran
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-400 font-semibold text-[11px] mb-1.5">Dari Jam ke- *</label>
                <select
                  v-model.number="startPeriod"
                  class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-xs sm:text-sm font-semibold min-h-[44px]"
                >
                  <option v-for="slot in schoolPeriodSlots" :key="slot.id" :value="slot.id">
                    {{ slot.label }} ({{ slot.time.split(' - ')[0] }})
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-gray-400 font-semibold text-[11px] mb-1.5">Sampai Jam ke- *</label>
                <select
                  v-model.number="endPeriod"
                  class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-xs sm:text-sm font-semibold min-h-[44px]"
                >
                  <option v-for="slot in schoolPeriodSlots" :key="slot.id" :value="slot.id">
                    {{ slot.label }} ({{ slot.time.split(' - ')[1] }})
                  </option>
                </select>
              </div>
            </div>

            <div class="p-3 bg-slate-900 rounded-2xl border border-white/10 text-xs font-mono text-cyan-300 font-bold flex items-center gap-2">
              <span>⏱️</span>
              <span>Hasil Jam Mengajar: <span class="text-white font-sans font-semibold">{{ computedJamLabel }}</span></span>
            </div>
          </div>

          <!-- Pokok Bahasan / Materi -->
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Pokok Bahasan / Sub-Materi *</label>
            <input
              v-model="jurnalForm.materi"
              type="text"
              placeholder="Contoh: Pemrograman Web Lanjut — Vue.js Component"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none min-h-[44px]"
              required
            />
          </div>

          <!-- Uraian Kegiatan Pembelajaran -->
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Uraian Kegiatan Pembelajaran</label>
            <textarea
              v-model="jurnalForm.uraian"
              rows="3"
              placeholder="Jelaskan ringkas aktivitas KBM, diskusi materi, praktek di lab, atau penugasan..."
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm font-medium leading-relaxed"
            ></textarea>
          </div>

          <!-- Catatan Kendala / Tindak Lanjut -->
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Catatan Kendala / Tindak Lanjut (Opsional)</label>
            <textarea
              v-model="jurnalForm.catatan"
              rows="2"
              placeholder="Contoh: 2 siswa izin dispensasi lomba / Perlu latihan pengayaan minggu depan..."
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm font-medium leading-relaxed"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button type="button" @click="showJurnalModal = false" class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl text-xs font-bold min-h-[44px]">
              Batal
            </button>
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 rounded-2xl text-xs font-black shadow-lg hover:shadow-cyan-500/25 active:scale-95 transition-all min-h-[44px]">
              Simpan Jurnal KBM
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
