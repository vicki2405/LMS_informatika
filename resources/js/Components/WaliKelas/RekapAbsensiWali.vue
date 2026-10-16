<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  kelasBinaan: { type: String, default: 'X RPL 1' },
  kelasId: { type: Number, default: null },
  siswaList: { type: Array, default: () => [] },
  absensiList: { type: Array, default: () => [] },
  jadwalList: { type: Array, default: () => [] },
  user: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['save-batch-absensi']);

// Viewport listener to render only active viewport DOM nodes
const isMobile = ref(false);
let mq = null;
function syncViewport(e) { isMobile.value = e.matches; }
onMounted(() => {
  mq = window.matchMedia('(max-width: 767px)');
  syncViewport(mq);
  mq.addEventListener('change', syncViewport);
});
onUnmounted(() => {
  if (mq) mq.removeEventListener('change', syncViewport);
});

// Inline Cell Edit State
const inlineEdit = ref(null); // { recordId, siswaId, day }
const inlineEditStatus = ref('');
const statusOptions = ['Hadir', 'Sakit', 'Izin', 'Alpa'];

const activeSubView = ref('harian'); // 'harian' or 'rekap'
const todayStr = new Date().toISOString().split('T')[0];
const selectedDate = ref(todayStr);
const searchName = ref('');
const currentMonthStr = String(new Date().getMonth() + 1).padStart(2, '0');
const selectedRecapMonth = ref(currentMonthStr);

// Modals / State for Wali Kelas Batch Presensi Mandiri
const showBatchModal = ref(false);
const batchRows = ref([]);

const filteredAbsensiList = computed(() => {
  let list = props.absensiList.filter(a => a.tanggal === selectedDate.value);

  // If no records for selected date yet, generate draft view from student list
  if (list.length === 0 && props.siswaList.length > 0) {
    list = props.siswaList.map(s => ({
      id: `draft-${s.id}`,
      tanggal: selectedDate.value,
      kelas: props.kelasBinaan,
      siswaId: s.id,
      namaSiswa: s.nama,
      nis: s.nis || `100${s.id}`,
      status: 'Belum Diabsen',
      keterangan: 'Belum ada catatan presensi',
      jamKe: 'Jam 1 - 2 (07.00 - 08.30)'
    }));
  }

  if (searchName.value.trim()) {
    const q = searchName.value.toLowerCase();
    list = list.filter(a => a.namaSiswa.toLowerCase().includes(q) || a.nis?.includes(q));
  }

  return list;
});

// Summary Stats for Jam 1-2
const totalSiswa = computed(() => props.siswaList.length || filteredAbsensiList.value.length);
const countHadir = computed(() => filteredAbsensiList.value.filter(a => a.status === 'Hadir').length);
const countSakit = computed(() => filteredAbsensiList.value.filter(a => a.status === 'Sakit').length);
const countIzin = computed(() => filteredAbsensiList.value.filter(a => a.status === 'Izin').length);
const countAlpa = computed(() => filteredAbsensiList.value.filter(a => a.status === 'Alpa').length);
const countBelum = computed(() => filteredAbsensiList.value.filter(a => a.status === 'Belum Diabsen').length);

const isGuruMapelAbasened = computed(() => countBelum.value === 0 && totalSiswa.value > 0);

function openBatchPresensiModal() {
  const normDate = String(selectedDate.value).substring(0, 10);
  batchRows.value = props.siswaList.map(s => {
    const existing = props.absensiList.find(a => 
      a && 
      (a.siswaId === s.id || a.siswa_id === s.id) && 
      (a.tanggal === normDate || (a.tanggal && String(a.tanggal).substring(0, 10) === normDate))
    );
    return {
      siswaId: s.id,
      namaSiswa: s.nama,
      nis: s.nis || `100${s.id}`,
      status: existing?.status || 'Hadir',
      keterangan: existing?.keterangan || 'Hadir'
    };
  });
  showBatchModal.value = true;
}

function setAllBatchStatus(status) {
  batchRows.value.forEach(r => {
    r.status = status;
    if (status === 'Hadir') r.keterangan = 'Hadir';
  });
}

function handleSaveBatch() {
  emit('save-batch-absensi', {
    tanggal: selectedDate.value,
    kelasId: props.kelasId,
    rows: batchRows.value
  });
  showBatchModal.value = false;
}

// Open inline edit dropdown for an existing recap record
function openInlineEditRekap(record, siswaId, day) {
  inlineEdit.value = { recordId: record.id, siswaId, day };
  inlineEditStatus.value = record.status;
}

// Close inline edit without saving
function closeInlineEdit() {
  inlineEdit.value = null;
  inlineEditStatus.value = '';
}

// Save inline edit — PATCH the record
function saveInlineEdit() {
  if (!inlineEdit.value || !inlineEditStatus.value) return;
  const { recordId } = inlineEdit.value;
  router.patch(
    route('wali-kelas.absensi.update', recordId),
    { status: inlineEditStatus.value },
    {
      preserveScroll: true,
      only: ['absensiListProp'],
      onSuccess: () => closeInlineEdit(),
    }
  );
}

// Confirm and bulk delete all absensi for the currently selected date
function confirmBulkDeleteDate() {
  const dateStr = String(selectedDate.value).substring(0, 10);
  const parts = dateStr.split('-');
  const day = parseInt(parts[2]);
  const monthCode = parts[1]; // '01' to '12'
  const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  const monthLabel = monthNames[parseInt(monthCode) - 1] ?? '';

  Swal.fire({
    title: 'Batalkan Absensi Tanggal Ini?',
    html: `Semua catatan absensi tanggal <strong>${day} ${monthLabel}</strong><br>untuk kelas <strong>${props.kelasBinaan}</strong> akan dihapus permanen.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#475569',
    confirmButtonText: 'Ya, Batalkan Absensi!',
    cancelButtonText: 'Batal',
  }).then(result => {
    if (result.isConfirmed) {
      router.delete(route('wali-kelas.absensi.bulk-delete'), {
        data: { tanggal: dateStr, kelas_id: props.kelasId },
        preserveScroll: true,
        only: ['absensiListProp'],
      });
    }
  });
}

// Monthly/Semester Recap months list
const monthsList = [
  { code: '01', name: 'Januari' },
  { code: '02', name: 'Februari' },
  { code: '03', name: 'Maret' },
  { code: '04', name: 'April' },
  { code: '05', name: 'Mei' },
  { code: '06', name: 'Juni' },
  { code: '07', name: 'Juli' },
  { code: '08', name: 'Agustus' },
  { code: '09', name: 'September' },
  { code: '10', name: 'Oktober' },
  { code: '11', name: 'November' },
  { code: '12', name: 'Desember' }
];

const recapList = computed(() => {
  const year = new Date().getFullYear();
  const monthStr = selectedRecapMonth.value;

  const absMap = new Map();
  (props.absensiList || []).forEach(a => {
    if (!a || !a.tanggal) return;
    const sId = a.siswaId || a.siswa_id;
    const cleanDate = String(a.tanggal).substring(0, 10);
    if (sId && cleanDate) {
      absMap.set(`${sId}_${cleanDate}`, a);
    }
  });

  let list = props.siswaList.map(s => {
    let hadir = 0, sakit = 0, izin = 0, alpa = 0;
    const dailyStatus = {};

    for (let day = 1; day <= 31; day++) {
      const dayPadded = String(day).padStart(2, '0');
      const dateStr = `${year}-${monthStr}-${dayPadded}`;
      const record = absMap.get(`${s.id}_${dateStr}`);

      if (record) {
        if (record.status === 'Hadir') {
          dailyStatus[day] = '.';
          hadir++;
        } else if (record.status === 'Sakit') {
          dailyStatus[day] = 'S';
          sakit++;
        } else if (record.status === 'Izin') {
          dailyStatus[day] = 'I';
          izin++;
        } else if (record.status === 'Alpa') {
          dailyStatus[day] = 'A';
          alpa++;
        } else {
          dailyStatus[day] = '';
        }
      } else {
        dailyStatus[day] = '';
      }
    }

    const total = hadir + sakit + izin + alpa;
    const persentase = total > 0 ? Math.round(((hadir + sakit + izin) / total) * 100) : 100;

    return {
      id: s.id,
      nama: s.nama,
      nis: s.nis || `100${s.id}`,
      jenisKelamin: s.jenisKelamin,
      dailyStatus,
      hadir,
      sakit,
      izin,
      alpa,
      total,
      persentase
    };
  });

  if (searchName.value.trim()) {
    const q = searchName.value.toLowerCase();
    list = list.filter(r => r.nama.toLowerCase().includes(q) || r.nis.includes(q));
  }

  return list;
});

const averageKehadiranClass = computed(() => {
  const list = recapList.value;
  if (list.length === 0) return '100%';
  const sum = list.reduce((acc, r) => acc + r.persentase, 0);
  return Math.round(sum / list.length) + '%';
});

const countLakiLaki = computed(() => {
  return props.siswaList.filter(s => s.jenisKelamin === 'Laki-laki').length;
});
const countPerempuan = computed(() => {
  return props.siswaList.filter(s => s.jenisKelamin === 'Perempuan').length;
});
const totalSiswaCount = computed(() => {
  return props.siswaList.length;
});
const selectedMonthName = computed(() => {
  const monthObj = monthsList.find(m => m.code === selectedRecapMonth.value);
  return monthObj ? monthObj.name : '';
});

function printRecap() {
  window.print();
}

function downloadCSV() {
  const year = new Date().getFullYear();
  const monthName = selectedMonthName.value;
  const kelasName = props.kelasBinaan;
  const waliName = props.user?.name || 'Nasir Abdullah, S.Pd';

  let html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Daftar Hadir</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  table { border-collapse: collapse; }
  td, th { border: 1px solid #000000; font-family: Arial, sans-serif; font-size: 9pt; height: 22px; vertical-align: middle; }
  .title { font-size: 12pt; font-weight: bold; text-align: center; border: none; height: 30px; }
  .meta-left { text-align: left; font-weight: bold; border: none; }
  .meta-right { text-align: right; font-weight: bold; border: none; }
  .header-col { background-color: #f2f2f2; font-weight: bold; text-align: center; }
  .center { text-align: center; }
  .left { text-align: left; }
</style>
</head>
<body>
<table>
  <!-- Title -->
  <tr>
    <td colspan="37" class="title">DAFTAR HADIR SISWA</td>
  </tr>
  <tr><td colspan="37" style="border:none; height:10px;"></td></tr>
  <!-- Metadata -->
  <tr>
    <td colspan="15" class="meta-left">Bulan : ${monthName} ${year}</td>
    <td colspan="22" class="meta-right">NAMA KELAS : ${kelasName}</td>
  </tr>
  <tr><td colspan="37" style="border:none; height:10px;"></td></tr>
  
  <!-- Table Header Row 1 -->
  <tr>
    <th rowspan="2" class="header-col" style="width:30px; border: 1px solid #000000;">No.</th>
    <th rowspan="2" class="header-col" style="width:220px; text-align:left; border: 1px solid #000000;">NAMA</th>
    <th colspan="31" class="header-col" style="border: 1px solid #000000;">KEHADIRAN SISWA</th>
    <th colspan="4" class="header-col" style="border: 1px solid #000000;">KET.</th>
  </tr>
  
  <!-- Table Header Row 2 -->
  <tr>`;
  
  for (let i = 1; i <= 31; i++) {
    html += `<th class="header-col" style="width:24px; border: 1px solid #000000;">${i}</th>`;
  }
  html += `<th class="header-col" style="width:25px; color:#d97706; border: 1px solid #000000;">S</th>
    <th class="header-col" style="width:25px; color:#2563eb; border: 1px solid #000000;">I</th>
    <th class="header-col" style="width:25px; color:#dc2626; border: 1px solid #000000;">A</th>
    <th class="header-col" style="width:25px; font-weight:bold; border: 1px solid #000000;">T</th>
  </tr>`;

  // Student rows
  recapList.value.forEach((r, idx) => {
    html += `<tr>
      <td class="center" style="border: 1px solid #000000;">${idx + 1}</td>
      <td class="left" style="font-weight:bold; border: 1px solid #000000;">${r.nama.toUpperCase()}</td>`;
    
    for (let day = 1; day <= 31; day++) {
      const status = r.dailyStatus[day] || '';
      html += `<td class="center" style="font-family:monospace; font-weight:bold; border: 1px solid #000000;">${status}</td>`;
    }
    
    html += `<td class="center" style="color:#d97706; font-weight:bold; border: 1px solid #000000;">${r.sakit || ''}</td>
      <td class="center" style="color:#2563eb; font-weight:bold; border: 1px solid #000000;">${r.izin || ''}</td>
      <td class="center" style="color:#dc2626; font-weight:bold; border: 1px solid #000000;">${r.alpa || ''}</td>
      <td class="center" style="font-weight:bold; background-color:#f8fafc; border: 1px solid #000000;">${(r.sakit + r.izin + r.alpa) || ''}</td>
    </tr>`;
  });

  // Empty spacer rows
  html += `<tr><td colspan="37" style="border:none; height:15px;"></td></tr>`;

  // Summary Gender & Signatures
  html += `<tr>
    <td colspan="2" style="border:none;"></td>
    <td colspan="4" style="border:1px solid #000000; font-style:italic;">Laki - laki :</td>
    <td colspan="2" style="border:1px solid #000000; text-align:center; font-weight:bold;">${countLakiLaki.value}</td>
    <td colspan="20" style="border:none;"></td>
    <td colspan="9" style="border:none; text-align:right; font-family:sans-serif;">Banyuwangi, ...................................................</td>
  </tr>
  <tr>
    <td colspan="2" style="border:none;"></td>
    <td colspan="4" style="border:1px solid #000000; font-style:italic;">Perempuan :</td>
    <td colspan="2" style="border:1px solid #000000; text-align:center; font-weight:bold;">${countPerempuan.value}</td>
    <td colspan="20" style="border:none;"></td>
    <td colspan="9" style="border:none; text-align:right; font-family:sans-serif;">Wali Kelas,</td>
  </tr>
  <tr>
    <td colspan="2" style="border:none;"></td>
    <td colspan="4" style="border:1px solid #000000; font-style:italic; font-weight:bold;">Jumlah :</td>
    <td colspan="2" style="border:1px solid #000000; text-align:center; font-weight:bold;">${totalSiswaCount.value}</td>
    <td colspan="29" style="border:none;"></td>
  </tr>
  <tr><td colspan="37" style="border:none; height:15px;"></td></tr>
  <tr><td colspan="37" style="border:none; height:15px;"></td></tr>
  <tr>
    <td colspan="28" style="border:none;"></td>
    <td colspan="9" style="border:none; text-align:right; font-weight:bold; text-decoration:underline; font-family:sans-serif; text-transform:uppercase;">${waliName}</td>
  </tr>`;

  html += `</table>
</body>
</html>`;

  // Download file as a real styled Excel sheet (.xls)
  const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `rekap_absensi_${kelasName}_bulan_${monthName}_${year}.xls`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Rekap &amp; Presensi Kelas Binaan</span>
          <span class="text-indigo-400 text-base">📌</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">
          Monitoring presensi harian, absen mandiri, dan kompilasi rekap kehadiran bulanan kelas {{ kelasBinaan }}
        </p>
      </div>
    </div>

    <!-- Sub-tab Navigation (Harian vs Rekap) -->
    <div class="flex items-center gap-3 border-b border-white/10 pb-1">
      <button
        @click="activeSubView = 'harian'"
        :class="[
          'px-5 py-3 text-xs sm:text-sm font-black border-b-2 transition-all min-h-[44px]',
          activeSubView === 'harian' ? 'border-cyan-500 text-cyan-400 font-extrabold' : 'border-transparent text-gray-400 hover:text-white'
        ]"
      >
        📅 Presensi Harian
      </button>
      <button
        @click="activeSubView = 'rekap'"
        :class="[
          'px-5 py-3 text-xs sm:text-sm font-black border-b-2 transition-all min-h-[44px]',
          activeSubView === 'rekap' ? 'border-cyan-500 text-cyan-400 font-extrabold' : 'border-transparent text-gray-400 hover:text-white'
        ]"
      >
        📊 Rekap Bulanan &amp; Semester
      </button>
    </div>

    <!-- VIEW 1: PRESENSI HARIAN -->
    <div v-if="activeSubView === 'harian'" class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-4 bg-slate-900/60 p-4 rounded-3xl border border-white/10">
        <!-- Date Picker Filter -->
        <div class="flex items-center gap-2 bg-slate-900 border border-white/10 px-3.5 py-2 rounded-2xl">
          <span class="text-xs text-gray-400 font-bold">📅 Tanggal:</span>
          <input
            v-model="selectedDate"
            type="date"
            class="bg-transparent text-xs sm:text-sm text-white font-extrabold outline-none cursor-pointer"
          />
        </div>

        <!-- Take Attendance Button for Wali Kelas -->
        <button
          @click="openBatchPresensiModal"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 via-teal-600 to-emerald-600 text-white px-5 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-105 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span>✍️</span>
          <span>Absen Mandiri Wali Kelas</span>
        </button>

        <!-- Batalkan Absensi Tanggal Ini -->
        <button
          v-if="countBelum < totalSiswa && totalSiswa > 0"
          @click="confirmBulkDeleteDate"
          class="inline-flex items-center justify-center gap-2 bg-red-600/20 hover:bg-red-600/40 border border-red-500/40 text-red-300 hover:text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm active:scale-95 transition-all duration-200 min-h-[44px]"
          title="Hapus semua catatan absensi tanggal ini"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
          <span>Batalkan Absensi</span>
        </button>
      </div>

      <!-- Alert / Status Banner -->
      <div
        :class="[
          'p-4 rounded-3xl border backdrop-blur-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-xl',
          isGuruMapelAbasened
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200'
            : 'bg-amber-500/10 border-amber-500/30 text-amber-200'
        ]"
      >
        <div class="flex items-center gap-3">
          <span class="text-2xl shrink-0">{{ isGuruMapelAbasened ? '✅' : '⚠️' }}</span>
          <div>
            <h4 class="text-xs font-black uppercase tracking-wider text-white">
              Status Absensi Jam 1 - 2 (07.00 - 08.30 WIB)
            </h4>
            <p class="text-xs font-medium mt-0.5 leading-relaxed">
              <template v-if="isGuruMapelAbasened">
                Presensi kelas binaan <strong>{{ kelasBinaan }}</strong> tanggal <strong>{{ selectedDate }}</strong> telah lengkap tercatat oleh Guru Mapel / Wali Kelas.
              </template>
              <template v-else>
                Sebanyak <strong>{{ countBelum }} siswa</strong> belum tercatat presensinya. Jika Guru Mapel berhalangan hadir, Anda sebagai Wali Kelas dapat langsung melakukan <strong>Absen Mandiri</strong>.
              </template>
            </p>
          </div>
        </div>

        <button
          v-if="!isGuruMapelAbasened"
          @click="openBatchPresensiModal"
          class="shrink-0 px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black rounded-xl shadow-md active:scale-95 transition-all"
        >
          Ambil Alih Absen Sekarang →
        </button>
      </div>

      <!-- Summary Stats Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-4">
        <div class="bg-slate-900/80 border border-white/10 p-4 rounded-3xl shadow-xl">
          <span class="text-[11px] font-black text-gray-400 uppercase tracking-wider">Total Siswa</span>
          <div class="text-2xl font-black text-white mt-1">{{ totalSiswa }}</div>
        </div>
        <div class="bg-emerald-500/10 border border-emerald-500/30 p-4 rounded-3xl shadow-xl">
          <span class="text-[11px] font-black text-emerald-400 uppercase tracking-wider">Hadir (Jam 1-2)</span>
          <div class="text-2xl font-black text-emerald-300 mt-1">{{ countHadir }}</div>
        </div>
        <div class="bg-amber-500/10 border border-amber-500/30 p-4 rounded-3xl shadow-xl">
          <span class="text-[11px] font-black text-amber-400 uppercase tracking-wider">Sakit</span>
          <div class="text-2xl font-black text-amber-300 mt-1">{{ countSakit }}</div>
        </div>
        <div class="bg-blue-500/10 border border-blue-500/30 p-4 rounded-3xl shadow-xl">
          <span class="text-[11px] font-black text-blue-400 uppercase tracking-wider">Izin</span>
          <div class="text-2xl font-black text-blue-300 mt-1">{{ countIzin }}</div>
        </div>
        <div class="bg-rose-500/10 border border-rose-500/30 p-4 rounded-3xl shadow-xl col-span-2 sm:col-span-1">
          <span class="text-[11px] font-black text-rose-400 uppercase tracking-wider">Alpa</span>
          <div class="text-2xl font-black text-rose-300 mt-1">{{ countAlpa }}</div>
        </div>
      </div>

      <!-- Search Input -->
      <div class="relative max-w-md">
        <input
          v-model="searchName"
          type="text"
          placeholder="Cari nama siswa atau NIS..."
          class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 pl-10 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-indigo-400 outline-none"
        />
        <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>

      <!-- Datatable Desktop (hidden md:block) -->
      <div v-if="!isMobile" class="hidden md:block bg-slate-900/80 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs sm:text-sm text-gray-300">
            <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
              <tr>
                <th class="px-5 py-4 w-12 text-center">No.</th>
                <th class="px-6 py-4">Nama Siswa Binaan</th>
                <th class="px-6 py-4">Sesi Jam Mengajar</th>
                <th class="px-6 py-4 text-center">Status Presensi</th>
                <th class="px-6 py-4">Catatan / Keterangan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 font-medium">
              <tr v-for="(row, idx) in filteredAbsensiList" :key="row.id" class="hover:bg-white/5 transition-colors">
                <td class="px-5 py-4 text-center font-mono text-gray-400 text-xs">{{ idx + 1 }}</td>
                <td class="px-6 py-4">
                  <span class="font-extrabold text-white text-sm block">{{ row.namaSiswa }}</span>
                  <span class="text-[11px] text-gray-400 font-mono">NIS: {{ row.nis }}</span>
                </td>
                <td class="px-6 py-4 text-xs font-semibold text-indigo-300">
                  <span class="bg-indigo-500/15 border border-indigo-500/30 px-3 py-1 rounded-full inline-block">
                    ⏰ {{ row.jamKe }}
                  </span>
                </td>
                <td class="px-6 py-4 text-center">
                  <span
                    :class="[
                      'px-3 py-1 rounded-full text-xs font-black inline-block border',
                      row.status === 'Hadir' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                      row.status === 'Sakit' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' :
                      row.status === 'Izin' ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' :
                      row.status === 'Alpa' ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' :
                      'bg-gray-500/20 text-gray-400 border-gray-500/30'
                    ]"
                  >
                    {{ row.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-xs text-gray-300">
                  {{ row.keterangan || '-' }}
                </td>
              </tr>
              <tr v-if="filteredAbsensiList.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-gray-400 font-medium">
                  Belum ada data siswa di dalam kelas binaan ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Mobile Card List (md:hidden) -->
      <div v-if="isMobile" class="md:hidden space-y-3">
        <div 
          v-for="(row, idx) in filteredAbsensiList" 
          :key="row.id" 
          class="bg-slate-900/80 border border-white/10 rounded-2xl p-4 space-y-3 shadow-lg animate-in fade-in"
        >
          <div class="flex items-center justify-between border-b border-white/5 pb-2">
            <span class="font-mono text-gray-500 text-xs font-bold">#{{ idx + 1 }}</span>
            <span
              :class="[
                'px-2.5 py-0.5 rounded-full text-[10px] font-black border',
                row.status === 'Hadir' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                row.status === 'Sakit' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' :
                row.status === 'Izin' ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' :
                row.status === 'Alpa' ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' :
                'bg-gray-500/20 text-gray-400 border-gray-500/30'
              ]"
            >
              {{ row.status }}
            </span>
          </div>

          <div class="space-y-1">
            <span class="font-extrabold text-white text-sm block">{{ row.namaSiswa }}</span>
            <span class="text-[11px] text-gray-400 font-mono block">NIS: {{ row.nis }}</span>
          </div>

          <div class="grid grid-cols-2 gap-2 pt-2 border-t border-white/5 text-xs text-gray-300">
            <div>
              <span class="text-gray-500 font-bold block uppercase text-[9px] tracking-wider mb-0.5">Sesi Jam:</span>
              <span class="font-semibold text-indigo-300">⏰ {{ row.jamKe }}</span>
            </div>
            <div>
              <span class="text-gray-500 font-bold block uppercase text-[9px] tracking-wider mb-0.5">Catatan:</span>
              <span class="font-semibold truncate block">{{ row.keterangan || '-' }}</span>
            </div>
          </div>
        </div>

        <div v-if="filteredAbsensiList.length === 0" class="bg-slate-900/40 border border-white/10 rounded-2xl p-8 text-center text-gray-400 text-xs font-medium">
          Belum ada data siswa di dalam kelas binaan ini.
        </div>
      </div>
    </div>

    <!-- VIEW 2: REKAP BULANAN & SEMESTER -->
    <div v-else-if="activeSubView === 'rekap'" class="space-y-6">
      
      <!-- Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-900/60 p-5 rounded-3xl border border-white/10 shadow-xl">
        <div class="flex flex-wrap items-center gap-4">
          <!-- Month Dropdown Filter -->
          <div class="flex items-center gap-2 bg-slate-900 border border-white/10 px-3.5 py-2 rounded-2xl min-h-[44px]">
            <span class="text-xs text-gray-400 font-bold">📅 Pilih Bulan:</span>
            <select v-model="selectedRecapMonth" class="bg-transparent text-xs sm:text-sm text-white font-extrabold outline-none cursor-pointer border-none p-0 focus:ring-0">
              <option value="Semua" class="bg-slate-900 text-white">Semua Bulan</option>
              <option v-for="m in monthsList" :key="m.code" :value="m.code" class="bg-slate-900 text-white">{{ m.name }}</option>
            </select>
          </div>

          <!-- Rerata Kehadiran Badge -->
          <span class="bg-emerald-500/20 text-emerald-300 text-xs font-black px-4 py-2.5 rounded-2xl border border-emerald-500/30 shadow-md">
            ✓ Kehadiran Rata-Rata Kelas: {{ averageKehadiranClass }}
          </span>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            @click="downloadCSV"
            class="inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm border border-white/10 transition-all active:scale-95 min-h-[44px]"
          >
            📥 Unduh CSV/Excel
          </button>
          <button
            @click="printRecap"
            class="inline-flex items-center justify-center gap-2 bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-300 border border-indigo-500/30 px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all active:scale-95 min-h-[44px]"
          >
            🖨️ Cetak Rekap
          </button>
        </div>
      </div>

      <!-- Search Input for Recap -->
      <div class="relative max-w-md">
        <input
          v-model="searchName"
          type="text"
          placeholder="Cari nama siswa atau NIS..."
          class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 pl-10 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-indigo-400 outline-none"
        />
        <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>

      <!-- Datatable Rekap Kehadiran (hidden md:block) -->
      <div v-if="!isMobile" class="hidden md:block bg-slate-900/80 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl p-6 printable-area">
        
        <!-- Header Dokumen (Tampil di Print/Cetak) -->
        <div class="text-center space-y-2 mb-6">
          <h2 class="text-lg font-black text-white uppercase tracking-wider print:text-slate-950">DAFTAR HADIR SISWA</h2>
          <div class="flex justify-between items-center text-xs font-bold text-gray-300 print:text-slate-950 px-2 pt-2 border-b border-white/10 print:border-slate-300 pb-2">
            <span>Bulan : <span class="underline">{{ selectedMonthName }} {{ new Date().getFullYear() }}</span></span>
            <span>NAMA KELAS : <span class="underline">{{ kelasBinaan }}</span></span>
            <span>TAHUN PELAJARAN : <span class="underline">{{ $page.props.activeTahunAjaran?.kode || '2025/2026' }}</span></span>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-[11px] text-gray-300 border-collapse border border-white/10 print:border-slate-400">
            <thead class="bg-slate-950/60 text-gray-300 uppercase font-extrabold tracking-wider border-b border-white/10 print:bg-slate-100 print:text-slate-900 print:border-slate-400">
              <!-- Baris Header 1 -->
              <tr class="divide-x divide-white/10 print:divide-slate-300">
                <th rowspan="2" class="py-3 px-1.5 w-[3%] text-center border border-white/10 print:border-slate-400">No.</th>
                <th rowspan="2" class="py-3 px-3 w-[22%] text-left border border-white/10 print:border-slate-400">NAMA</th>
                <th colspan="31" class="py-2 px-1 text-center border border-white/10 print:border-slate-400 text-xs">KEHADIRAN SISWA</th>
                <th colspan="4" class="py-2 px-2 text-center border border-white/10 print:border-slate-400 text-xs">KET.</th>
              </tr>
              <!-- Baris Header 2 (Tanggal 1..31 & S, I, A, T) -->
              <tr class="divide-x divide-white/10 print:divide-slate-300 border-t border-white/10 print:border-slate-400">
                <th v-for="day in 31" :key="'h-'+day" class="py-1 w-[1.7%] text-center font-bold font-mono text-[9px] border border-white/10 print:border-slate-400">{{ day }}</th>
                <th class="py-1 w-[2.2%] text-center font-black text-amber-400 border border-white/10 print:border-slate-400" title="Sakit">S</th>
                <th class="py-1 w-[2.2%] text-center font-black text-blue-400 border border-white/10 print:border-slate-400" title="Izin">I</th>
                <th class="py-1 w-[2.2%] text-center font-black text-rose-400 border border-white/10 print:border-slate-400" title="Alpa">A</th>
                <th class="py-1 w-[2.2%] text-center font-black text-white border border-white/10 print:border-slate-400" title="Total Absen">T</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 print:divide-slate-300 font-medium">
              <tr v-for="(r, idx) in recapList" :key="r.id" class="hover:bg-white/5 transition-colors divide-x divide-white/5 print:divide-slate-200">
                <!-- No -->
                <td class="py-2 px-1.5 text-center font-mono text-gray-400 print:text-slate-900 border border-white/10 print:border-slate-300">{{ idx + 1 }}</td>
                <!-- Nama -->
                <td class="py-2 px-3 font-extrabold text-white print:text-slate-900 border border-white/10 print:border-slate-300 truncate" :title="r.nama">
                  {{ r.nama.toUpperCase() }}
                </td>
                <!-- Kolom 1 s/d 31 -->
                <td v-for="day in 31" :key="'d-'+r.id+'-'+day" class="py-2 px-0 text-center font-mono font-black border border-white/10 print:border-slate-300" :class="[
                  r.dailyStatus[day] === 'S' ? 'text-amber-400 bg-amber-500/10 print:bg-transparent print:text-slate-900' :
                  r.dailyStatus[day] === 'I' ? 'text-blue-400 bg-blue-500/10 print:bg-transparent print:text-slate-900' :
                  r.dailyStatus[day] === 'A' ? 'text-rose-400 bg-rose-500/10 print:bg-transparent print:text-slate-900' :
                  'text-gray-400'
                ]">
                  {{ r.dailyStatus[day] }}
                </td>
                <!-- S, I, A, T -->
                <td class="py-2 px-1 text-center font-mono font-bold text-amber-400 bg-amber-500/5 print:bg-transparent print:text-slate-900 border border-white/10 print:border-slate-300">{{ r.sakit || '' }}</td>
                <td class="py-2 px-1 text-center font-mono font-bold text-blue-400 bg-blue-500/5 print:bg-transparent print:text-slate-900 border border-white/10 print:border-slate-300">{{ r.izin || '' }}</td>
                <td class="py-2 px-1 text-center font-mono font-bold text-rose-400 bg-rose-500/5 print:bg-transparent print:text-slate-900 border border-white/10 print:border-slate-300">{{ r.alpa || '' }}</td>
                <td class="py-2 px-1 text-center font-mono font-black text-white bg-slate-950/20 print:bg-transparent print:text-slate-900 border border-white/10 print:border-slate-300">{{ r.sakit + r.izin + r.alpa || '' }}</td>
              </tr>
              <tr v-if="recapList.length === 0">
                <td colspan="37" class="py-12 text-center text-gray-400 text-xs font-medium">Tidak ada data rekap siswa.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer Ringkasan & Tanda Tangan (Sesuai Gambar) -->
        <div class="mt-8 grid grid-cols-2 gap-4 text-xs text-gray-300 print:text-slate-900">
          <!-- Sisi Kiri: Ringkasan Gender -->
          <div class="space-y-1 pl-4">
            <table class="w-48 text-left border-collapse border border-white/10 print:border-slate-400">
              <tbody>
                <tr class="border-b border-white/10 print:border-slate-300">
                  <td class="py-1.5 px-3 italic font-medium">Laki - laki :</td>
                  <td class="py-1.5 px-3 text-center font-bold font-mono">{{ countLakiLaki }}</td>
                </tr>
                <tr class="border-b border-white/10 print:border-slate-300">
                  <td class="py-1.5 px-3 italic font-medium">Perempuan :</td>
                  <td class="py-1.5 px-3 text-center font-bold font-mono">{{ countPerempuan }}</td>
                </tr>
                <tr>
                  <td class="py-1.5 px-3 italic font-black">Jumlah :</td>
                  <td class="py-1.5 px-3 text-center font-black font-mono border-t border-double border-white/20 print:border-slate-400">{{ totalSiswaCount }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Sisi Kanan: Tanda Tangan Wali Kelas -->
          <div class="text-right pr-8 space-y-16">
            <div>
              <p>Banyuwangi, ....................................................................</p>
              <p class="mr-12">Wali Kelas,</p>
            </div>
            <div class="mr-12">
              <p class="font-bold underline uppercase">{{ props.user?.name || 'Nasir Abdullah, S.Pd.' }}</p>
            </div>
          </div>
        </div>

      </div>

      <!-- Mobile Card List for Recap (md:hidden) -->
      <div v-if="isMobile" class="md:hidden space-y-3 printable-area">
        <div 
          v-for="(r, idx) in recapList" 
          :key="r.id" 
          class="bg-slate-900/80 border border-white/10 rounded-2xl p-4 space-y-3 shadow-lg animate-in fade-in"
        >
          <div class="flex items-center justify-between border-b border-white/5 pb-2">
            <span class="font-mono text-gray-500 text-xs font-bold">#{{ idx + 1 }}</span>
            <span 
              :class="[
                'font-mono font-black text-sm',
                r.persentase >= 90 ? 'text-emerald-400' :
                r.persentase >= 75 ? 'text-amber-400' : 'text-rose-400'
              ]"
            >
              {{ r.persentase }}% Hadir
            </span>
          </div>

          <div class="space-y-1">
            <span class="font-extrabold text-white text-sm block">{{ r.nama }}</span>
            <span class="text-[11px] text-gray-400 font-mono block">NIS: {{ r.nis }}</span>
          </div>

          <div class="grid grid-cols-4 gap-2 pt-2 border-t border-white/5 text-center text-xs">
            <div class="bg-emerald-500/5 border border-emerald-500/10 p-2 rounded-xl">
              <span class="text-emerald-400 font-black block text-sm mb-0.5">{{ r.hadir }}</span>
              <span class="text-[9px] text-gray-500 font-bold uppercase">Hadir</span>
            </div>
            <div class="bg-amber-500/5 border border-amber-500/10 p-2 rounded-xl">
              <span class="text-amber-400 font-black block text-sm mb-0.5">{{ r.sakit }}</span>
              <span class="text-[9px] text-gray-500 font-bold uppercase">Sakit</span>
            </div>
            <div class="bg-blue-500/5 border border-blue-500/10 p-2 rounded-xl">
              <span class="text-blue-400 font-black block text-sm mb-0.5">{{ r.izin }}</span>
              <span class="text-[9px] text-gray-500 font-bold uppercase">Izin</span>
            </div>
            <div class="bg-rose-500/5 border border-rose-500/10 p-2 rounded-xl">
              <span class="text-rose-400 font-black block text-sm mb-0.5">{{ r.alpa }}</span>
              <span class="text-[9px] text-gray-500 font-bold uppercase">Alpa</span>
            </div>
          </div>
        </div>

        <div v-if="recapList.length === 0" class="bg-slate-900/40 border border-white/10 rounded-2xl p-8 text-center text-gray-400 text-xs font-medium">
          Tidak ada data rekap siswa.
        </div>
      </div>
    </div>


    <!-- MODAL ABSEN MANDIRI WALI KELAS -->
    <div v-if="showBatchModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-3xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto animate-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div>
            <h3 class="text-lg font-black text-white flex items-center gap-2">
              <span>Absen Mandiri Wali Kelas — {{ kelasBinaan }}</span>
              <span class="text-emerald-400">✍️</span>
            </h3>
            <p class="text-xs text-gray-400 font-medium mt-0.5">
              Input presensi siswa kelas binaan untuk tanggal <strong>{{ selectedDate }}</strong> (Jam 1 - 2)
            </p>
          </div>
          <button @click="showBatchModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <!-- Shortcut Preset Buttons -->
        <div class="flex items-center justify-between bg-slate-950/60 p-3 rounded-2xl border border-white/10">
          <span class="text-xs font-extrabold text-gray-300 uppercase">Pilih Cepat Status:</span>
          <div class="flex items-center gap-2">
            <button @click="setAllBatchStatus('Hadir')" class="px-3 py-1.5 bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-extrabold rounded-xl hover:bg-emerald-500/30 transition-all">
              Semua Hadir
            </button>
            <button @click="setAllBatchStatus('Alpa')" class="px-3 py-1.5 bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-extrabold rounded-xl hover:bg-rose-500/30 transition-all">
              Reset Semua
            </button>
          </div>
        </div>

        <!-- Rows Table -->
        <div class="space-y-3 max-h-[50vh] overflow-y-auto pr-1">
          <div
            v-for="(row, idx) in batchRows"
            :key="row.siswaId"
            class="p-3.5 bg-slate-950/40 rounded-2xl border border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
          >
            <div class="flex items-center gap-3">
              <span class="font-mono text-xs text-gray-500 font-bold w-6">#{{ idx + 1 }}</span>
              <div>
                <span class="text-sm font-extrabold text-white block">{{ row.namaSiswa }}</span>
                <span class="text-[11px] text-gray-400 font-mono">NIS: {{ row.nis }}</span>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <select
                v-model="row.status"
                class="bg-slate-900 border border-white/10 rounded-xl px-3 py-2 text-xs font-extrabold text-white focus:border-emerald-400 outline-none"
              >
                <option value="Hadir">🟢 Hadir</option>
                <option value="Sakit">🟡 Sakit</option>
                <option value="Izin">🔵 Izin</option>
                <option value="Alpa">🔴 Alpa</option>
              </select>

              <input
                v-model="row.keterangan"
                type="text"
                placeholder="Catatan..."
                class="bg-slate-900 border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-gray-500 focus:border-emerald-400 outline-none w-36 sm:w-48"
              />
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
          <button type="button" @click="showBatchModal = false" class="px-5 py-2.5 rounded-2xl border border-white/10 text-gray-400 text-xs font-bold hover:bg-white/5 active:scale-95 transition-all">Batal</button>
          <button type="button" @click="handleSaveBatch" class="px-5 py-2.5 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-xs font-black shadow-lg shadow-emerald-500/25 hover:scale-105 active:scale-95 transition-all">Simpan Presensi Wali Kelas</button>
        </div>
      </div>
    </div>

  </div>
</template>

<style>
@media print {
  @page {
    size: landscape;
    margin: 0.8cm;
  }
  body {
    background-color: white !important;
    color: black !important;
    font-family: 'Courier New', Courier, monospace !important;
  }
  /* Hide dashboard navigation, sidebars, headers, filters, buttons, and anything outside printable area */
  header, nav, aside, footer, button, select, input, .flex, .border-b, .relative {
    display: none !important;
  }
  .printable-area {
    display: block !important;
    position: absolute;
    left: 0;
    top: 0;
    width: 100% !important;
    background: white !important;
    color: black !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .printable-area table {
    width: 100% !important;
    border-collapse: collapse !important;
    color: black !important;
  }
  .printable-area table th {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
    border: 1px solid #475569 !important;
    font-size: 8px !important;
    padding: 4px 1px !important;
    text-align: center !important;
  }
  .printable-area table td {
    color: #0f172a !important;
    border: 1px solid #475569 !important;
    font-size: 8px !important;
    padding: 3px 1px !important;
  }
  .printable-area span {
    color: black !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
  }
}
</style>
