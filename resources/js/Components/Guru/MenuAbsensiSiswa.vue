<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  absensiList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  jadwalList: { type: Array, default: () => [] },
  initialKelas: { type: String, default: '' },
});

const indoDaysList = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const todayDayName = computed(() => indoDaysList[new Date().getDay()] || 'Senin');

const todayScheduledClasses = computed(() => {
  const set = new Set();
  (props.jadwalList || []).filter(j => j.hari === todayDayName.value).forEach(j => {
    const kName = j.kelas || j.namaKelas;
    if (kName) set.add(kName);
  });
  return Array.from(set);
});

const emit = defineEmits(['save-absensi', 'save-batch-absensi', 'delete-absensi', 'open-full-input-absensi', 'update:selected-kelas']);

// Inline Cell Edit State
const inlineEdit = ref(null); // { recordId, siswaId, day, currentStatus }
const inlineEditStatus = ref('');
const statusOptions = ['Hadir', 'Sakit', 'Izin', 'Alpa'];

// Month Names in Indonesian
const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Helper: Today's date string YYYY-MM-DD
function getTodayString() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// Resolve initial class from prop, persistent localStorage, or first class
function resolveInitialKelas() {
  if (props.initialKelas) {
    return props.initialKelas;
  }
  // Smart Default 1: Jadwal Mengajar Hari Ini
  if (todayScheduledClasses.value && todayScheduledClasses.value.length > 0) {
    return todayScheduledClasses.value[0];
  }
  if (typeof window !== 'undefined') {
    const cached = localStorage.getItem('guru_active_absensi_kelas');
    if (cached && props.kelasList && props.kelasList.some(k => (k.namaKelas || k) === cached)) {
      return cached;
    }
  }
  if (props.kelasList && props.kelasList.length > 0) {
    return props.kelasList[0].namaKelas || (typeof props.kelasList[0] === 'string' ? props.kelasList[0] : 'Semua');
  }
  return 'Semua';
}

// Current Date & View Defaults
const now = new Date();
const selectedYear = ref(now.getFullYear());
const selectedMonth = ref(now.getMonth() + 1); // 1-12
const selectedKelasFilter = ref(resolveInitialKelas());
const searchQuery = ref('');
const debouncedSearch = ref('');

// Debounce search input to avoid re-rendering the whole matrix on every keystroke
let searchTimer = null;
watch(searchQuery, (val) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { debouncedSearch.value = val; }, 250);
});

// Sync selected class with parent and localStorage
watch(selectedKelasFilter, (newVal) => {
  if (newVal && newVal !== 'Semua') {
    emit('update:selected-kelas', newVal);
    if (typeof window !== 'undefined') {
      localStorage.setItem('guru_active_absensi_kelas', newVal);
    }
  }
});

// React to prop updates from parent
watch(() => props.initialKelas, (newVal) => {
  if (newVal && newVal !== selectedKelasFilter.value) {
    selectedKelasFilter.value = newVal;
  }
});

watch(() => props.jadwalList, () => {
  if (!props.initialKelas && todayScheduledClasses.value.length > 0 && (selectedKelasFilter.value === 'Semua' || !selectedKelasFilter.value)) {
    selectedKelasFilter.value = todayScheduledClasses.value[0];
  }
});

watch(() => props.kelasList, (newVal) => {
  if (newVal && newVal.length > 0 && (selectedKelasFilter.value === 'Semua' || !selectedKelasFilter.value)) {
    selectedKelasFilter.value = resolveInitialKelas();
  }
});

// Only render the viewport-appropriate matrix (desktop table OR mobile cards)
const isMobile = ref(false);
let mq = null;
function syncViewport(e) { isMobile.value = e.matches; }
onMounted(() => {
  mq = window.matchMedia('(max-width: 767px)');
  syncViewport(mq);
  mq.addEventListener('change', syncViewport);

  const initial = resolveInitialKelas();
  if (initial && (selectedKelasFilter.value === 'Semua' || selectedKelasFilter.value !== initial)) {
    selectedKelasFilter.value = initial;
  }
});
onUnmounted(() => {
  if (mq) mq.removeEventListener('change', syncViewport);
});

// Safe Array Fallbacks
const safeSiswaList = computed(() => Array.isArray(props.siswaList) ? props.siswaList : []);
const safeAbsensiList = computed(() => Array.isArray(props.absensiList) ? props.absensiList : []);
const safeKelasList = computed(() => Array.isArray(props.kelasList) ? props.kelasList : []);

// Filtered Students List for Main Table Matrix
const filteredSiswa = computed(() => {
  let list = [...safeSiswaList.value];

  if (selectedKelasFilter.value && selectedKelasFilter.value !== 'Semua') {
    list = list.filter(s => s && (s.kelas === selectedKelasFilter.value || s.namaKelas === selectedKelasFilter.value));
  }

  if (debouncedSearch.value && debouncedSearch.value.trim()) {
    const q = debouncedSearch.value.toLowerCase();
    list = list.filter(s => s && (
      (s.nama && s.nama.toLowerCase().includes(q)) ||
      (s.nis && String(s.nis).includes(q))
    ));
  }

  return list.sort((a, b) => (a.nama || '').localeCompare(b.nama || ''));
});

// Configurable Pagination to keep rendered DOM bounded on large rosters
const perPage = ref(50); // 25 | 50 | 100 | 'Semua'
const currentPage = ref(1);
const effectivePageSize = computed(() => perPage.value === 'Semua' ? (filteredSiswa.value.length || 1) : Number(perPage.value));
const startIndex = computed(() => (currentPage.value - 1) * effectivePageSize.value);
const totalPages = computed(() => {
  if (perPage.value === 'Semua') return 1;
  return Math.max(1, Math.ceil(filteredSiswa.value.length / effectivePageSize.value));
});
const paginatedSiswa = computed(() => {
  if (perPage.value === 'Semua') return filteredSiswa.value;
  return filteredSiswa.value.slice(startIndex.value, startIndex.value + effectivePageSize.value);
});
watch([selectedKelasFilter, debouncedSearch, perPage], () => { currentPage.value = 1; });
watch(totalPages, (tp) => { if (currentPage.value > tp) currentPage.value = tp; });
function changePage(p) {
  if (p >= 1 && p <= totalPages.value) currentPage.value = p;
}

// Dynamically Calculate Number of Days in Selected Month & Year
const daysInMonth = computed(() => {
  return new Date(selectedYear.value, selectedMonth.value, 0).getDate();
});

// Array of Day Numbers [1, 2, ..., daysInMonth]
const daysList = computed(() => {
  const days = [];
  for (let i = 1; i <= daysInMonth.value; i++) {
    days.push(i);
  }
  return days;
});

// Helper: Get Day Abbr (e.g. 'Sn', 'Sl', 'Rb')
function getDayAbbr(dayNumber) {
  const dateObj = new Date(selectedYear.value, selectedMonth.value - 1, dayNumber);
  const dayIndex = dateObj.getDay();
  const abbrs = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];
  return { abbr: abbrs[dayIndex], isWeekend: dayIndex === 0 || dayIndex === 6 };
}

// Fast O(1) Map lookup index for attendance records
const absensiMap = computed(() => {
  const map = new Map();
  const list = safeAbsensiList.value;
  for (let i = 0; i < list.length; i++) {
    const a = list[i];
    if (!a) continue;
    const sId = a.siswaId || a.siswa_id;
    const tgl = a.tanggal ? String(a.tanggal).substring(0, 10) : '';
    if (sId && tgl) {
      map.set(`${sId}_${tgl}`, a);
    }
  }
  return map;
});

// Helper: Find Attendance Record for a Student on a Specific Day (O(1))
function getAbsensiRecord(siswaId, dayNumber) {
  if (!siswaId) return null;
  const monthPadded = String(selectedMonth.value).padStart(2, '0');
  const dayPadded = String(dayNumber).padStart(2, '0');
  const dateStr = `${selectedYear.value}-${monthPadded}-${dayPadded}`;

  return absensiMap.value.get(`${siswaId}_${dateStr}`) || null;
}

// Memoized per-cell lookup: { [siswaId]: { [dayNumber]: statusChar|'' } }
// Built once per (filtered list, absensi, month) change so the template only
// does ONE plain object lookup per cell instead of calling getAbsensiRecord 5x.
const cellStatusMap = computed(() => {
  const map = {};
  for (let i = 0; i < filteredSiswa.value.length; i++) {
    const s = filteredSiswa.value[i];
    if (!s || !s.id) continue;
    const cells = {};
    for (let d = 1; d <= daysInMonth.value; d++) {
      const rec = getAbsensiRecord(s.id, d);
      cells[d] = rec ? rec.status : '';
    }
    map[s.id] = cells;
  }
  return map;
});

// Single memoized lookup used by the matrix template
function getCellStatus(siswaId, dayNumber) {
  const cells = cellStatusMap.value[siswaId];
  return cells ? (cells[dayNumber] || '') : '';
}

// Memoized per-student recap derived from the same cell map
const studentRecaps = computed(() => {
  const map = {};
  for (let i = 0; i < filteredSiswa.value.length; i++) {
    const siswa = filteredSiswa.value[i];
    if (!siswa || !siswa.id) continue;
    const cells = cellStatusMap.value[siswa.id] || {};
    let H = 0, S = 0, I = 0, A = 0;
    for (const st of Object.values(cells)) {
      if (st === 'Hadir') H++;
      else if (st === 'Sakit') S++;
      else if (st === 'Izin') I++;
      else if (st === 'Alpa') A++;
    }
    map[siswa.id] = { H, S, I, A };
  }
  return map;
});

// Grand Total Recap for All Filtered Students
const grandTotalRecap = computed(() => {
  let H = 0, S = 0, I = 0, A = 0;
  Object.values(studentRecaps.value || {}).forEach(recap => {
    if (recap) {
      H += recap.H || 0;
      S += recap.S || 0;
      I += recap.I || 0;
      A += recap.A || 0;
    }
  });
  return { H, S, I, A };
});

// Open Batch Attendance Full-Page View
function openBatchAttendanceModal(targetKelas = null, targetDate = null) {
  const dateStr = targetDate || getTodayString();
  let selectedClass = targetKelas;
  if (!selectedClass) {
    selectedClass = selectedKelasFilter.value !== 'Semua' ? selectedKelasFilter.value : (props.kelasList[0]?.namaKelas || 'X RPL 1');
  }

  emit('open-full-input-absensi', { kelas: selectedClass, tanggal: dateStr });
}

// Quick Cell Click Handler: if record exists → inline edit; if empty → open batch modal
function openCellModal(siswa, dayNumber) {
  if (siswa.status === 'Keluar' || siswa.status === 'Pindah') {
    Swal.fire({
      icon: 'warning',
      title: 'Presensi Dinonaktifkan',
      html: `Presensi untuk siswa <strong>${siswa.nama}</strong> diblokir karena berstatus <span class="text-rose-400 font-black">${siswa.status.toUpperCase()}</span>${siswa.tanggal_status ? ' (sejak <strong>' + siswa.tanggal_status + '</strong>)' : ''}.<br><span class="text-xs text-gray-400 mt-2 block">Siswa ini tidak lagi aktif di rombongan belajar.</span>`,
      confirmButtonColor: '#06b6d4',
      confirmButtonText: 'Mengerti'
    });
    return;
  }
  const monthPadded = String(selectedMonth.value).padStart(2, '0');
  const dayPadded = String(dayNumber).padStart(2, '0');
  const dateStr = `${selectedYear.value}-${monthPadded}-${dayPadded}`;
  const record = getAbsensiRecord(siswa.id, dayNumber);
  if (record) {
    openInlineEdit(record, siswa.id, dayNumber);
  } else {
    openBatchAttendanceModal(siswa.kelas, dateStr);
  }
}

// Open inline edit dropdown for an existing record
function openInlineEdit(record, siswaId, day) {
  inlineEdit.value = { recordId: record.id, siswaId, day };
  inlineEditStatus.value = record.status;
}

// Close inline edit without saving
function closeInlineEdit() {
  inlineEdit.value = null;
  inlineEditStatus.value = '';
}

// Save inline edit — PATCH the record status
function saveInlineEdit() {
  if (!inlineEdit.value || !inlineEditStatus.value) return;
  const { recordId } = inlineEdit.value;
  router.patch(
    route('guru.absensi.update', recordId),
    { status: inlineEditStatus.value },
    {
      preserveScroll: true,
      only: ['absensiListProp'],
      onSuccess: () => closeInlineEdit(),
    }
  );
}

// Confirm and bulk delete all absensi records for a date + kelas
function confirmBulkDeleteDate(dayNumber) {
  const monthPadded = String(selectedMonth.value).padStart(2, '0');
  const dayPadded = String(dayNumber).padStart(2, '0');
  const dateStr = `${selectedYear.value}-${monthPadded}-${dayPadded}`;

  // Get kelas_id from filtered kelas
  const kelasObj = props.kelasList.find(k => k.namaKelas === selectedKelasFilter.value);
  const kelasId = kelasObj?.id ?? null;

  const kelasLabel = selectedKelasFilter.value !== 'Semua' ? selectedKelasFilter.value : 'Semua Kelas';

  Swal.fire({
    title: 'Batalkan Absensi Tanggal Ini?',
    html: `Semua catatan absensi tanggal <strong>${dayNumber} ${monthNames[selectedMonth.value - 1]} ${selectedYear.value}</strong><br>untuk kelas <strong>${kelasLabel}</strong> akan dihapus permanen.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#475569',
    confirmButtonText: 'Ya, Batalkan Absensi!',
    cancelButtonText: 'Batal',
  }).then(result => {
    if (result.isConfirmed) {
      router.delete(route('guru.absensi.bulk-delete'), {
        data: { tanggal: dateStr, kelas_id: kelasId },
        preserveScroll: true,
        only: ['absensiListProp'],
      });
    }
  });
}

// Export Monthly Matrix to Excel (.xls Spreadsheet) matching Official School Template
function exportCsv() {
  const monthName = monthNames[selectedMonth.value - 1];
  
  let daysHeaderHtml = '';
  daysList.value.forEach(d => {
    daysHeaderHtml += `<th height="20" valign="middle" style="width: 25px; text-align: center; border: 1px solid #000; font-weight: bold; background-color: #f8fafc; height: 15pt; vertical-align: middle;">${d}</th>`;
  });

  let rowsHtml = '';
  filteredSiswa.value.forEach((siswa, index) => {
    let daysCellsHtml = '';
    daysList.value.forEach(d => {
      const rec = getAbsensiRecord(siswa.id, d);
      let cellText = rec ? rec.status.charAt(0) : '';
      daysCellsHtml += `<td height="20" valign="middle" style="text-align: center; border: 1px solid #000; height: 15pt; vertical-align: middle;">${cellText}</td>`;
    });

    const recapH = studentRecaps.value[siswa.id]?.H ?? 0;
    const recapS = studentRecaps.value[siswa.id]?.S ?? 0;
    const recapI = studentRecaps.value[siswa.id]?.I ?? 0;
    const recapA = studentRecaps.value[siswa.id]?.A ?? 0;

    const statusSuffix = (siswa.status === 'Keluar') ? ' [KELUAR]' : (siswa.status === 'Pindah') ? ' [PINDAH]' : '';

    rowsHtml += `
      <tr height="20" style="height: 15pt;">
        <td height="20" valign="middle" style="text-align: center; border: 1px solid #000; height: 15pt; vertical-align: middle;">${index + 1}</td>
        <td height="20" valign="middle" style="font-weight: bold; border: 1px solid #000; text-transform: uppercase; height: 15pt; vertical-align: middle;">${siswa.nama.toUpperCase()}${statusSuffix}</td>
        ${daysCellsHtml}
        <td height="20" valign="middle" style="text-align: center; border: 1px solid #000; height: 15pt; vertical-align: middle;">${recapS || ''}</td>
        <td height="20" valign="middle" style="text-align: center; border: 1px solid #000; height: 15pt; vertical-align: middle;">${recapI || ''}</td>
        <td height="20" valign="middle" style="text-align: center; border: 1px solid #000; height: 15pt; vertical-align: middle;">${recapA || ''}</td>
        <td height="20" valign="middle" style="text-align: center; border: 1px solid #000; font-weight: bold; height: 15pt; vertical-align: middle;">${recapH || ''}</td>
      </tr>
    `;
  });

  const totalCols = daysList.value.length + 6;
  const excelHtml = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
      <head>
        <meta charset="utf-8" />
        <!--[if gte mso 9]>
        <xml>
          <x:ExcelWorkbook>
            <x:ExcelWorksheets>
              <x:ExcelWorksheet>
                <x:Name>Daftar Hadir</x:Name>
                <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
              </x:ExcelWorksheet>
            </x:ExcelWorksheets>
          </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
          table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
          tr { height: 15pt; }
          th, td { border: 1px solid #000000; padding: 2px 4px; height: 15pt; vertical-align: middle; }
          .title { text-align: center; font-weight: bold; font-size: 16px; border: none; height: 25pt; vertical-align: middle; }
          .sub-info-left { font-weight: bold; text-align: left; border: none; height: 18pt; vertical-align: middle; }
          .sub-info-right { font-weight: bold; text-align: right; border: none; height: 18pt; vertical-align: middle; }
        </style>
      </head>
      <body>
        <table>
          <tr height="30">
            <td colspan="${totalCols}" class="title" valign="middle">DAFTAR HADIR SISWA</td>
          </tr>
          <tr height="24">
            <td colspan="3" class="sub-info-left" valign="middle">Bulan : ${monthName} ${selectedYear.value}</td>
            <td colspan="${totalCols - 3}" class="sub-info-right" valign="middle">${selectedKelasFilter.value.toUpperCase()}</td>
          </tr>
          <tr></tr>
          <thead>
            <tr height="20" style="height: 15pt;">
              <th rowspan="2" valign="middle" style="border: 1px solid #000; font-weight: bold; background-color: #f1f5f9; width: 35px; height: 30pt; vertical-align: middle;">No.</th>
              <th rowspan="2" valign="middle" style="border: 1px solid #000; font-weight: bold; background-color: #f1f5f9; min-width: 200px; text-align: left; height: 30pt; vertical-align: middle;">NAMA</th>
              <th colspan="${daysList.value.length}" valign="middle" style="border: 1px solid #000; font-weight: bold; background-color: #f1f5f9; text-align: center; height: 15pt; vertical-align: middle;">KEHADIRAN SISWA</th>
              <th colspan="4" valign="middle" style="border: 1px solid #000; font-weight: bold; background-color: #f1f5f9; text-align: center; height: 15pt; vertical-align: middle;">KET.</th>
            </tr>
            <tr height="20" style="height: 15pt;">
              ${daysHeaderHtml}
              <th height="20" valign="middle" style="border: 1px solid #000; font-weight: bold; background-color: #f8fafc; width: 25px; height: 15pt; vertical-align: middle;">S</th>
              <th height="20" valign="middle" style="border: 1px solid #000; font-weight: bold; background-color: #f8fafc; width: 25px; height: 15pt; vertical-align: middle;">I</th>
              <th height="20" valign="middle" style="border: 1px solid #000; font-weight: bold; background-color: #f8fafc; width: 25px; height: 15pt; vertical-align: middle;">A</th>
              <th height="20" valign="middle" style="border: 1px solid #000; font-weight: bold; background-color: #f8fafc; width: 25px; height: 15pt; vertical-align: middle;">T</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml}
          </tbody>
        </table>
      </body>
    </html>
  `;

  const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel;charset=utf-8' });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.setAttribute("download", `daftar_hadir_siswa_${selectedKelasFilter.value}_${monthName}_${selectedYear.value}.xls`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Export / Print Monthly PDF Ledger matching Official F4 Landscape Template
function exportPdf() {
  const monthName = monthNames[selectedMonth.value - 1];
  
  let daysHeaderHtml = '';
  daysList.value.forEach(d => {
    daysHeaderHtml += `<th valign="middle" style="width: 18px; text-align: center; font-size: 9px; padding: 2px; border: 1px solid #000; font-weight: bold; height: 15pt; vertical-align: middle;">${d}</th>`;
  });

  let rowsHtml = '';
  filteredSiswa.value.forEach((siswa, index) => {
    let daysCellsHtml = '';
    daysList.value.forEach(d => {
      const rec = getAbsensiRecord(siswa.id, d);
      let cellText = '';
      let bgStyle = '';
      if (rec) {
        cellText = rec.status.charAt(0);
        if (rec.status === 'Hadir') bgStyle = 'color: #000; font-weight: bold;';
        else if (rec.status === 'Sakit') bgStyle = 'background-color: #fef9c3; color: #854d0e; font-weight: bold;';
        else if (rec.status === 'Izin') bgStyle = 'background-color: #dbeafe; color: #1e40af; font-weight: bold;';
        else if (rec.status === 'Alpa') bgStyle = 'background-color: #fee2e2; color: #991b1b; font-weight: bold;';
      }
      daysCellsHtml += `<td valign="middle" style="text-align: center; font-size: 9px; padding: 2px; border: 1px solid #000; height: 15pt; vertical-align: middle; ${bgStyle}">${cellText}</td>`;
    });

    const recapH = studentRecaps.value[siswa.id]?.H ?? 0;
    const recapS = studentRecaps.value[siswa.id]?.S ?? 0;
    const recapI = studentRecaps.value[siswa.id]?.I ?? 0;
    const recapA = studentRecaps.value[siswa.id]?.A ?? 0;

    const statusSuffix = (siswa.status === 'Keluar') ? ' [KELUAR]' : (siswa.status === 'Pindah') ? ' [PINDAH]' : '';

    rowsHtml += `
      <tr style="height: 15pt;">
        <td valign="middle" style="padding: 2px; border: 1px solid #000; text-align: center; font-size: 10px; height: 15pt; vertical-align: middle;">${index + 1}</td>
        <td valign="middle" style="padding: 2px 6px; border: 1px solid #000; font-weight: bold; font-size: 10px; text-transform: uppercase; white-space: nowrap; height: 15pt; vertical-align: middle;">${siswa.nama.toUpperCase()}${statusSuffix}</td>
        ${daysCellsHtml}
        <td valign="middle" style="padding: 2px; border: 1px solid #000; text-align: center; font-size: 10px; font-weight: bold; height: 15pt; vertical-align: middle;">${recapS || ''}</td>
        <td valign="middle" style="padding: 2px; border: 1px solid #000; text-align: center; font-size: 10px; font-weight: bold; height: 15pt; vertical-align: middle;">${recapI || ''}</td>
        <td valign="middle" style="padding: 2px; border: 1px solid #000; text-align: center; font-size: 10px; font-weight: bold; height: 15pt; vertical-align: middle;">${recapA || ''}</td>
        <td valign="middle" style="padding: 2px; border: 1px solid #000; text-align: center; font-size: 10px; font-weight: bold; background: #f8fafc; height: 15pt; vertical-align: middle;">${recapH || ''}</td>
      </tr>
    `;
  });

  const htmlContent = `
    <!DOCTYPE html>
    <html>
      <head>
        <title></title>
        <style>
          @page { size: 330mm 215mm landscape; margin: 6mm; }
          body { font-family: Arial, sans-serif; padding: 4px; color: #000000; background: #ffffff; margin: 0; }
          .title-box { text-align: center; font-weight: bold; font-size: 16px; letter-spacing: 1px; padding: 2px; margin-bottom: 6px; text-transform: uppercase; }
          .sub-info { display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 11px; margin-bottom: 6px; padding: 0 2px; }
          table { width: 100%; border-collapse: collapse; border: 2px solid #000000; }
          tr { height: 15pt; }
          th, td { border: 1px solid #000000; color: #000000; vertical-align: middle; height: 15pt; }
          th { background-color: #ffffff; text-transform: uppercase; font-size: 10px; vertical-align: middle; }
          td { vertical-align: middle; }
        </style>
      </head>
      <body>
        <div class="title-box">DAFTAR HADIR SISWA</div>
        <div class="sub-info">
          <div>Bulan : ${monthName} ${selectedYear.value}</div>
          <div style="font-size: 13px; font-weight: 900;">${selectedKelasFilter.value.toUpperCase()}</div>
        </div>
        <table>
          <thead>
            <tr style="height: 15pt;">
              <th rowspan="2" valign="middle" style="width: 28px; text-align: center; font-weight: bold; vertical-align: middle;">No.</th>
              <th rowspan="2" valign="middle" style="text-align: center; font-weight: bold; min-width: 170px; vertical-align: middle;">NAMA</th>
              <th colspan="${daysList.value.length}" valign="middle" style="text-align: center; font-weight: bold; padding: 3px; vertical-align: middle;">KEHADIRAN SISWA</th>
              <th colspan="4" valign="middle" style="text-align: center; font-weight: bold; padding: 3px; width: 80px; vertical-align: middle;">KET.</th>
            </tr>
            <tr style="height: 15pt;">
              ${daysHeaderHtml}
              <th valign="middle" style="width: 20px; text-align: center; font-weight: bold; vertical-align: middle;">S</th>
              <th valign="middle" style="width: 20px; text-align: center; font-weight: bold; vertical-align: middle;">I</th>
              <th valign="middle" style="width: 20px; text-align: center; font-weight: bold; vertical-align: middle;">A</th>
              <th valign="middle" style="width: 20px; text-align: center; font-weight: bold; background: #f8fafc; vertical-align: middle;">T</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml}
          </tbody>
        </table>
      </body>
    </html>
  `;

  let iframe = document.getElementById('print-pdf-iframe');
  if (iframe) {
    document.body.removeChild(iframe);
  }

  iframe = document.createElement('iframe');
  iframe.id = 'print-pdf-iframe';
  iframe.style.position = 'fixed';
  iframe.style.right = '0';
  iframe.style.bottom = '0';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
  document.body.appendChild(iframe);

  const doc = iframe.contentWindow.document;
  doc.open();
  doc.write(htmlContent);
  doc.close();

  setTimeout(() => {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
  }, 300);
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Title & Top Actions Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Presensi & Absensi Siswa</span>
          <span class="text-cyan-400 text-base">📋</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">Tabel Matriks Presensi Bulanan Siswa (Tanggal 1 s/d {{ daysInMonth }} & Rekapan H, S, I, A)</p>
      </div>

      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        <!-- Export Excel (.csv) -->
        <button
          @click="exportCsv"
          class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-emerald-600/25 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span>📊</span>
          <span>Export Excel (.csv)</span>
        </button>

        <!-- Export / Print PDF -->
        <button
          @click="exportPdf"
          class="inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-500 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-purple-600/25 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span>🖨️</span>
          <span>Cetak / Export PDF</span>
        </button>

        <!-- Add Attendance Button -->
        <button
          @click="openBatchAttendanceModal()"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-teal-600 to-indigo-600 text-white px-5 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-105 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span class="text-base">+</span>
          <span>Catat Presensi Baru</span>
        </button>
      </div>
    </div>

    <!-- Stat Summary Cards (H, S, I, A Grand Total) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-emerald-500/20 text-emerald-300 font-black text-sm flex items-center justify-center border border-emerald-500/30 shadow-md">H</span>
          <span class="text-[11px] font-extrabold text-emerald-300 bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 rounded-full font-mono">HADIR</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ grandTotalRecap.H }}</div>
        <div class="text-xs text-gray-400 font-semibold">Seluruh Kehadiran Bulan Ini</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-amber-500/20 text-amber-300 font-black text-sm flex items-center justify-center border border-amber-500/30 shadow-md">S</span>
          <span class="text-[11px] font-extrabold text-amber-300 bg-amber-500/15 border border-amber-500/30 px-3 py-1 rounded-full font-mono">SAKIT</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ grandTotalRecap.S }}</div>
        <div class="text-xs text-gray-400 font-semibold">Siswa Sakit Bulan Ini</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-blue-500/20 text-blue-300 font-black text-sm flex items-center justify-center border border-blue-500/30 shadow-md">I</span>
          <span class="text-[11px] font-extrabold text-blue-300 bg-blue-500/15 border border-blue-500/30 px-3 py-1 rounded-full font-mono">IZIN</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ grandTotalRecap.I }}</div>
        <div class="text-xs text-gray-400 font-semibold">Siswa Izin Bulan Ini</div>
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <div class="flex items-center justify-between mb-3">
          <span class="w-9 h-9 rounded-2xl bg-red-500/20 text-red-300 font-black text-sm flex items-center justify-center border border-red-500/30 shadow-md">A</span>
          <span class="text-[11px] font-extrabold text-red-300 bg-red-500/15 border border-red-500/30 px-3 py-1 rounded-full font-mono">ALPA</span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ grandTotalRecap.A }}</div>
        <div class="text-xs text-gray-400 font-semibold">Tanpa Keterangan (Alpa)</div>
      </div>
    </div>

    <!-- Filter Control Bar -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
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
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Filter Kelas Ampuan</label>
          <select v-model="selectedKelasFilter" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-cyan-400 outline-none min-h-[44px]">
            <option value="Semua">Semua Kelas Ampuan</option>
            <option v-for="k in kelasList" :key="k.id" :value="k.namaKelas">{{ k.namaKelas }}</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Cari Nama Siswa</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama atau NIS..."
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-cyan-400 outline-none min-h-[44px]"
          />
        </div>

      </div>
    </div>

    <!-- MONTHLY ATTENDANCE MATRIX TABLE -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      
      <!-- Table Matrix (rendered only on desktop) -->
      <div v-if="!isMobile" class="overflow-x-auto max-h-[70vh] overflow-y-auto custom-scrollbar">
        <table class="w-full text-left text-xs text-gray-300 border-collapse">
          <thead class="bg-slate-950/80 text-gray-400 uppercase text-[11px] font-extrabold border-b border-white/10 sticky top-0 z-20 shadow-md">
            <tr>
              <th class="px-3 py-4 text-center w-10 sticky left-0 bg-slate-950 z-30 shadow-r">No</th>
              <th class="px-4 py-4 sticky left-10 bg-slate-950 z-30 min-w-[200px] shadow-r">Nama Siswa</th>

              <!-- Dynamic Days Headers (1 s/d 28/29/30/31) -->
              <th
                v-for="d in daysList"
                :key="d"
                :class="['px-1 py-2 text-center min-w-[30px] max-w-[34px] border-r border-white/5 group/dayhdr', getDayAbbr(d).isWeekend ? 'bg-red-500/10 text-red-300 font-bold' : '']"
              >
                <div class="text-[9px] font-mono text-gray-400">{{ getDayAbbr(d).abbr }}</div>
                <div class="font-black text-white text-xs">{{ d }}</div>
                <!-- Batalkan Absensi Tanggal Button (visible on hover) -->
                <button
                  @click.stop="confirmBulkDeleteDate(d)"
                  title="Batalkan semua absensi tanggal ini"
                  class="opacity-0 group-hover/dayhdr:opacity-100 mt-0.5 w-5 h-5 rounded-md bg-red-500/20 hover:bg-red-500/50 text-red-400 hover:text-white transition-all flex items-center justify-center mx-auto"
                >
                  <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </th>

              <!-- Summary Columns (H, S, I, A) -->
              <th class="px-3 py-4 text-center font-black text-emerald-400 bg-emerald-500/15 border-l border-white/10 w-10">H</th>
              <th class="px-3 py-4 text-center font-black text-amber-400 bg-amber-500/15 w-10">S</th>
              <th class="px-3 py-4 text-center font-black text-blue-400 bg-blue-500/15 w-10">I</th>
              <th class="px-3 py-4 text-center font-black text-red-400 bg-red-500/15 w-10">A</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr
              v-for="(siswa, index) in paginatedSiswa"
              :key="siswa.id"
              :class="[
                'transition-colors',
                siswa.status === 'Keluar' ? 'bg-red-950/25 hover:bg-red-950/35 border-l-4 border-l-red-500' :
                siswa.status === 'Pindah' ? 'bg-amber-950/25 hover:bg-amber-950/35 border-l-4 border-l-amber-500' :
                'hover:bg-white/5'
              ]"
            >
              <!-- Sticky No -->
              <td class="px-3 py-3 text-center font-mono text-gray-400 sticky left-0 bg-slate-900 z-10 shadow-r">
                {{ startIndex + index + 1 }}
              </td>

              <!-- Sticky Student Name -->
              <td class="px-4 py-3 font-extrabold sticky left-10 bg-slate-900 z-10 shadow-r truncate max-w-[210px] border-r border-white/10">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span :class="siswa.status === 'Keluar' || siswa.status === 'Pindah' ? 'line-through text-gray-300' : 'text-white'">
                    {{ siswa.nama }}
                  </span>
                  <span
                    v-if="siswa.status === 'Keluar'"
                    class="inline-flex items-center gap-0.5 text-[9px] font-black px-1.5 py-0.5 rounded bg-red-500/25 text-red-300 border border-red-500/40 uppercase shrink-0"
                    title="Siswa telah Keluar"
                  >
                    🚫 KELUAR
                  </span>
                  <span
                    v-else-if="siswa.status === 'Pindah'"
                    class="inline-flex items-center gap-0.5 text-[9px] font-black px-1.5 py-0.5 rounded bg-amber-500/25 text-amber-300 border border-amber-500/40 uppercase shrink-0"
                    title="Siswa telah Pindah"
                  >
                    🚚 PINDAH
                  </span>
                </div>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="text-[10px] font-semibold text-cyan-400">{{ siswa.kelas }}</span>
                  <span v-if="siswa.status && siswa.status !== 'Aktif' && siswa.tanggal_status" class="text-[9px] text-gray-400 font-mono">
                    (per {{ siswa.tanggal_status }})
                  </span>
                </div>
              </td>

              <!-- Dynamic Days Attendance Cells -->
              <td
                v-for="d in daysList"
                :key="d"
                :class="[
                  'px-1 py-2 text-center border-r border-white/5 transition-all select-none relative',
                  siswa.status === 'Keluar'
                    ? 'bg-red-950/40 hover:bg-red-900/40 cursor-not-allowed'
                    : siswa.status === 'Pindah'
                      ? 'bg-amber-950/40 hover:bg-amber-900/40 cursor-not-allowed'
                      : (getDayAbbr(d).isWeekend ? 'bg-red-500/5 cursor-pointer hover:ring-1 hover:ring-cyan-400' : 'cursor-pointer hover:ring-1 hover:ring-cyan-400')
                ]"
                :title="siswa.status === 'Keluar' || siswa.status === 'Pindah'
                  ? `Presensi diblokir - Siswa telah ${siswa.status}`
                  : (getCellStatus(siswa.id, d) ? `Klik untuk edit status ${siswa.nama} Tgl ${d}` : `Klik untuk input absensi ${siswa.nama} Tgl ${d}`)"
                @click="openCellModal(siswa, d)"
              >
                <!-- Inline Edit Dropdown -->
                <div
                  v-if="inlineEdit && inlineEdit.siswaId === siswa.id && inlineEdit.day === d"
                  class="absolute z-50 top-full left-1/2 -translate-x-1/2 mt-1 bg-slate-800 border border-cyan-500/40 rounded-2xl shadow-2xl shadow-cyan-950/40 p-2 min-w-[120px]"
                  @click.stop
                >
                  <div class="text-[10px] font-extrabold text-cyan-400 mb-1.5 px-1">Ubah Status:</div>
                  <button
                    v-for="opt in statusOptions"
                    :key="opt"
                    @click="inlineEditStatus = opt; saveInlineEdit()"
                    :class="[
                      'w-full text-left px-3 py-1.5 rounded-xl text-xs font-bold transition-all mb-0.5',
                      opt === 'Hadir' ? 'hover:bg-emerald-500/20 hover:text-emerald-300 text-gray-300' :
                      opt === 'Sakit' ? 'hover:bg-amber-500/20 hover:text-amber-300 text-gray-300' :
                      opt === 'Izin' ? 'hover:bg-blue-500/20 hover:text-blue-300 text-gray-300' :
                      'hover:bg-red-500/20 hover:text-red-300 text-gray-300'
                    ]"
                  >
                    {{ opt === 'Hadir' ? '✅' : opt === 'Sakit' ? '🤒' : opt === 'Izin' ? '📄' : '❌' }} {{ opt }}
                  </button>
                  <button @click.stop="closeInlineEdit()" class="w-full text-center text-[10px] text-gray-500 hover:text-gray-300 mt-1 py-1">Batal</button>
                </div>

                <!-- Cell display content -->
                <template v-if="siswa.status === 'Keluar' || siswa.status === 'Pindah'">
                  <span
                    v-if="getCellStatus(siswa.id, d)"
                    class="w-6 h-6 rounded-lg bg-red-500/20 text-red-300 font-extrabold text-[11px] inline-flex items-center justify-center border border-red-500/30 opacity-70 line-through"
                    title="Data sebelum keluar/pindah"
                  >
                    {{ getCellStatus(siswa.id, d).charAt(0) }}
                  </span>
                  <span
                    v-else
                    :class="siswa.status === 'Keluar' ? 'text-red-500/60' : 'text-amber-500/60'"
                    class="text-[11px] font-black"
                    title="Presensi diblokir"
                  >
                    ✕
                  </span>
                </template>
                <template v-else-if="getCellStatus(siswa.id, d)">
                  <span
                    v-if="getCellStatus(siswa.id, d) === 'Hadir'"
                    class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 font-extrabold text-[11px] inline-flex items-center justify-center border border-emerald-500/30"
                  >
                    H
                  </span>
                  <span
                    v-else-if="getCellStatus(siswa.id, d) === 'Sakit'"
                    class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-300 font-extrabold text-[11px] inline-flex items-center justify-center border border-amber-500/30"
                  >
                    S
                  </span>
                  <span
                    v-else-if="getCellStatus(siswa.id, d) === 'Izin'"
                    class="w-6 h-6 rounded-lg bg-blue-500/20 text-blue-300 font-extrabold text-[11px] inline-flex items-center justify-center border border-blue-500/30"
                  >
                    I
                  </span>
                  <span
                    v-else-if="getCellStatus(siswa.id, d) === 'Alpa'"
                    class="w-6 h-6 rounded-lg bg-red-500/20 text-red-300 font-extrabold text-[11px] inline-flex items-center justify-center border border-red-500/30"
                  >
                    A
                  </span>
                </template>
                <template v-else>
                  <span class="text-gray-600 text-[10px] font-mono hover:text-cyan-400">-</span>
                </template>
              </td>

              <!-- Student Summary Totals -->
              <td class="px-3 py-3 text-center font-black text-emerald-400 bg-emerald-500/5 border-l border-white/10 font-mono">
                {{ studentRecaps[siswa.id]?.H ?? 0 }}
              </td>
              <td class="px-3 py-3 text-center font-black text-amber-400 bg-amber-500/5 font-mono">
                {{ studentRecaps[siswa.id]?.S ?? 0 }}
              </td>
              <td class="px-3 py-3 text-center font-black text-blue-400 bg-blue-500/5 font-mono">
                {{ studentRecaps[siswa.id]?.I ?? 0 }}
              </td>
              <td class="px-3 py-3 text-center font-black text-red-400 bg-red-500/5 font-mono">
                {{ studentRecaps[siswa.id]?.A ?? 0 }}
              </td>
            </tr>

            <tr v-if="filteredSiswa.length === 0">
              <td :colspan="daysInMonth + 6" class="px-6 py-12 text-center text-gray-400 text-xs font-medium">
                Tidak ada data siswa yang cocok dengan filter.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Smartphone Card View (rendered only on mobile) -->
      <div v-else class="p-4 space-y-4">
        <div
          v-for="(siswa, index) in paginatedSiswa"
          :key="siswa.id"
          :class="[
            'rounded-2xl p-4 border space-y-3 shadow-md transition-all',
            siswa.status === 'Keluar'
              ? 'bg-red-950/25 border-red-500/50'
              : siswa.status === 'Pindah'
                ? 'bg-amber-950/25 border-amber-500/50'
                : 'bg-slate-950/40 border-white/10'
          ]"
        >
          <!-- Status Banner for Inactive Students -->
          <div
            v-if="siswa.status === 'Keluar' || siswa.status === 'Pindah'"
            :class="[
              'px-3 py-2 rounded-xl text-xs font-black flex items-center justify-between gap-2 border',
              siswa.status === 'Keluar'
                ? 'bg-red-500/20 text-red-200 border-red-500/40'
                : 'bg-amber-500/20 text-amber-200 border-amber-500/40'
            ]"
          >
            <div class="flex items-center gap-1.5">
              <span>{{ siswa.status === 'Keluar' ? '🚫' : '🚚' }}</span>
              <span>STATUS: SISWA TELAH {{ siswa.status.toUpperCase() }}</span>
            </div>
            <span class="text-[10px] uppercase font-bold tracking-wider">Presensi Diblokir</span>
          </div>

          <div class="flex items-center justify-between border-b border-white/10 pb-2">
            <div>
              <div class="flex items-center gap-2">
                <span
                  class="text-xs font-black"
                  :class="siswa.status === 'Keluar' || siswa.status === 'Pindah' ? 'line-through text-gray-400' : 'text-white'"
                >
                  #{{ startIndex + index + 1 }} {{ siswa.nama }}
                </span>
                <span
                  v-if="siswa.status === 'Keluar'"
                  class="text-[9px] font-black px-1.5 py-0.5 rounded bg-red-500/20 text-red-300 border border-red-500/40"
                >
                  KELUAR
                </span>
                <span
                  v-else-if="siswa.status === 'Pindah'"
                  class="text-[9px] font-black px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40"
                >
                  PINDAH
                </span>
              </div>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="text-[11px] font-semibold text-cyan-400">Kelas: {{ siswa.kelas }}</span>
                <span v-if="siswa.status && siswa.status !== 'Aktif' && siswa.tanggal_status" class="text-[10px] text-gray-400 font-mono">
                  (sejak {{ siswa.tanggal_status }})
                </span>
              </div>
            </div>
          </div>

          <!-- Total H, S, I, A Badges -->
          <div class="grid grid-cols-4 gap-2 text-center bg-slate-900/80 p-2.5 rounded-2xl border border-white/5">
            <div class="bg-emerald-500/10 p-1.5 rounded-xl border border-emerald-500/20">
              <div class="text-[10px] text-emerald-400 font-extrabold">Hadir (H)</div>
              <div class="text-lg font-black text-emerald-300">{{ studentRecaps[siswa.id]?.H ?? 0 }}</div>
            </div>
            <div class="bg-amber-500/10 p-1.5 rounded-xl border border-amber-500/20">
              <div class="text-[10px] text-amber-400 font-extrabold">Sakit (S)</div>
              <div class="text-lg font-black text-amber-300">{{ studentRecaps[siswa.id]?.S ?? 0 }}</div>
            </div>
            <div class="bg-blue-500/10 p-1.5 rounded-xl border border-blue-500/20">
              <div class="text-[10px] text-blue-400 font-extrabold">Izin (I)</div>
              <div class="text-lg font-black text-blue-300">{{ studentRecaps[siswa.id]?.I ?? 0 }}</div>
            </div>
            <div class="bg-red-500/10 p-1.5 rounded-xl border border-red-500/20">
              <div class="text-[10px] text-red-400 font-extrabold">Alpa (A)</div>
              <div class="text-lg font-black text-red-300">{{ studentRecaps[siswa.id]?.A ?? 0 }}</div>
            </div>
          </div>

          <!-- Quick Days Attendance Grid -->
          <div class="pt-1">
            <div class="text-[10px] font-bold text-gray-400 uppercase mb-2">Presensi Harian (Tgl 1 s/d {{ daysInMonth }}):</div>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="d in daysList"
                :key="d"
                @click="openCellModal(siswa, d)"
                :class="[
                  'w-8 h-8 rounded-xl text-[10px] font-extrabold flex flex-col items-center justify-center border transition-all active:scale-95',
                  siswa.status === 'Keluar' || siswa.status === 'Pindah'
                    ? 'bg-red-950/40 border-red-500/30 text-red-400/80 cursor-not-allowed'
                    : (getCellStatus(siswa.id, d) === 'Hadir' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' :
                       getCellStatus(siswa.id, d) === 'Sakit' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' :
                       getCellStatus(siswa.id, d) === 'Izin' ? 'bg-blue-500/20 text-blue-300 border-blue-500/40' :
                       getCellStatus(siswa.id, d) === 'Alpa' ? 'bg-red-500/20 text-red-300 border-red-500/40' : 'bg-white/5 text-gray-400 border-white/10')
                ]"
              >
                <span>{{ d }}</span>
                <span class="text-[8px] leading-none font-black">
                  {{ (siswa.status === 'Keluar' || siswa.status === 'Pindah') ? '✕' : (getCellStatus(siswa.id, d) ? getCellStatus(siswa.id, d).charAt(0) : '-') }}
                </span>
              </button>
            </div>
          </div>
        </div>

        <div v-if="filteredSiswa.length === 0" class="p-6 text-center text-gray-400 text-xs font-medium">
          Tidak ada data siswa yang cocok.
        </div>
      </div>

      <!-- Pagination Bar Footer -->
      <div v-if="filteredSiswa.length > 0" class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-t border-white/10 bg-slate-950/40">
        <div class="flex items-center gap-3 text-xs text-gray-400">
          <span>Tampilkan:</span>
          <select v-model="perPage" class="bg-slate-800 border border-white/10 text-white font-bold rounded-xl px-2.5 py-1.5 outline-none focus:border-cyan-400">
            <option :value="25">25 per halaman</option>
            <option :value="50">50 per halaman</option>
            <option :value="100">100 per halaman</option>
            <option value="Semua">Semua ({{ filteredSiswa.length }})</option>
          </select>
          <span class="hidden sm:inline">
            Menampilkan <strong class="text-white">{{ filteredSiswa.length === 0 ? 0 : startIndex + 1 }}</strong> - <strong class="text-white">{{ Math.min(startIndex + (perPage === 'Semua' ? filteredSiswa.length : Number(perPage)), filteredSiswa.length) }}</strong> dari <strong class="text-cyan-400">{{ filteredSiswa.length }}</strong> Siswa
          </span>
        </div>

        <div v-if="totalPages > 1" class="flex items-center gap-1.5">
          <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1.5 rounded-xl bg-slate-800 border border-white/10 text-xs font-bold text-gray-300 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed">
            ◀ Sebelum
          </button>
          <button
            v-for="p in totalPages"
            :key="p"
            @click="currentPage = p"
            :class="[
              'px-3 py-1.5 rounded-xl text-xs font-bold transition-all',
              currentPage === p ? 'bg-cyan-500 text-slate-950 font-black shadow-md shadow-cyan-500/20' : 'bg-slate-800 border border-white/10 text-gray-300 hover:text-white'
            ]"
          >
            {{ p }}
          </button>
          <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="px-3 py-1.5 rounded-xl bg-slate-800 border border-white/10 text-xs font-bold text-gray-300 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed">
            Lanjut ▶
          </button>
        </div>
      </div>

    </div>
  </div>
</template>
