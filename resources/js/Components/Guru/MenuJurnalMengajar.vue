<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const teacherName = computed(() => page.props.auth?.user?.name || 'Guru Pengajar');

const props = defineProps({
  jurnalList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  jadwalList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] },
  teacherSubject: { type: String, default: 'Informatika' },
  shortcutJadwal: { type: Object, default: null }
});

const emit = defineEmits(['save-jurnal', 'delete-jurnal', 'clear-shortcut']);

const dayNamesIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const todayDayName = computed(() => dayNamesIndo[new Date().getDay()] || 'Senin');

const todaySchedules = computed(() => {
  return (props.jadwalList || []).filter(j => j.hari === todayDayName.value);
});

const todayScheduledClasses = computed(() => {
  const set = new Set();
  todaySchedules.value.forEach(j => {
    const kName = j.kelas || j.namaKelas;
    if (kName) set.add(kName);
  });
  return Array.from(set);
});

// Indonesian Month Names
const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Master School Teaching Period Hours Definition (Jam ke-1 s/d Jam ke-10)
const schoolPeriodSlots = [
  { id: 1, label: 'Jam ke-1' },
  { id: 2, label: 'Jam ke-2' },
  { id: 3, label: 'Jam ke-3' },
  { id: 4, label: 'Jam ke-4' },
  { id: 5, label: 'Jam ke-5' },
  { id: 6, label: 'Jam ke-6' },
  { id: 7, label: 'Jam ke-7' },
  { id: 8, label: 'Jam ke-8' },
  { id: 9, label: 'Jam ke-9' },
  { id: 10, label: 'Jam ke-10' }
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
const selectedTingkatFilter = ref('Semua');
const selectedKelasFilter = ref('Semua');
const selectedMapelFilter = ref('Semua');
const searchQuery = ref('');

// Helper: Tingkat Resolver from Class Name
function getTingkatFromKelas(namaKelas) {
  if (!namaKelas || typeof namaKelas !== 'string') return 'X';
  const upper = namaKelas.trim().toUpperCase();
  if (upper.startsWith('XII') || upper.includes('XII')) return 'XII';
  if (upper.startsWith('XI') || upper.includes('XI')) return 'XI';
  if (upper.startsWith('X') || upper.includes('X')) return 'X';
  return 'X';
}

// Comprehensive aggregation of all teacher classes (kelasList, jadwalList, jurnalList)
const allAvailableKelasList = computed(() => {
  const map = new Map();

  // 1. Classes from props.kelasList
  (props.kelasList || []).forEach(k => {
    const name = typeof k === 'string' ? k : (k?.namaKelas || k?.nama);
    if (name && !map.has(name)) {
      const tingkat = (typeof k === 'object' && k?.tingkat) ? k.tingkat : getTingkatFromKelas(name);
      map.set(name, { id: k?.id || name, namaKelas: name, tingkat });
    }
  });

  // 2. Classes from props.jadwalList
  (props.jadwalList || []).forEach(j => {
    const name = j.kelas || j.namaKelas;
    if (name && !map.has(name)) {
      map.set(name, { id: name, namaKelas: name, tingkat: j.tingkat || getTingkatFromKelas(name) });
    }
  });

  // 3. Classes from props.jurnalList
  (props.jurnalList || []).forEach(j => {
    const name = j.kelas;
    if (name && !map.has(name)) {
      map.set(name, { id: name, namaKelas: name, tingkat: getTingkatFromKelas(name) });
    }
  });

  return Array.from(map.values());
});

// Available Classes for Dropdown (Filtered by Tingkat + Natural Numeric Sorted)
const availableKelasOptions = computed(() => {
  let list = allAvailableKelasList.value;
  if (selectedTingkatFilter.value !== 'Semua') {
    list = list.filter(k => (k?.tingkat || getTingkatFromKelas(k?.namaKelas || k?.nama)) === selectedTingkatFilter.value);
  }
  return [...list].sort((a, b) => (a?.namaKelas || a?.nama || a || '').toString().localeCompare((b?.namaKelas || b?.nama || b || '').toString(), undefined, { numeric: true, sensitivity: 'base' }));
});

// Filtered options for the search box inside the custom dropdown
const filteredDropdownKelasOptions = computed(() => {
  let list = availableKelasOptions.value;
  if (searchKelasDropdownQuery.value && searchKelasDropdownQuery.value.trim()) {
    const q = searchKelasDropdownQuery.value.toLowerCase().trim();
    list = list.filter(k => (k?.namaKelas || k?.nama || '').toLowerCase().includes(q));
  }
  return list;
});

// All classes for modal form (Always natural numeric sorted)
const scheduledClasses = computed(() => {
  return [...allAvailableKelasList.value]
    .sort((a, b) => (a?.namaKelas || a?.nama || a || '').toString().localeCompare((b?.namaKelas || b?.nama || b || '').toString(), undefined, { numeric: true, sensitivity: 'base' }))
    .map(k => k?.namaKelas || k?.nama || k);
});

// 1. MATA PELAJARAN RESMI GURU (MURNI DARI DATA GURU ADMIN: tab=guru / mapel_utama)
const teacherAdminSubjects = computed(() => {
  const str = props.teacherSubject || '';
  const list = str.split(',').map(s => s.trim()).filter(s => s.length > 0);
  if (list.length > 0) return list;
  return ['Informatika'];
});

// Custom Dropdowns State
const isMonthDropdownOpen = ref(false);
const monthDropdownRef = ref(null);

const isYearDropdownOpen = ref(false);
const yearDropdownRef = ref(null);

const isTingkatDropdownOpen = ref(false);
const tingkatDropdownRef = ref(null);

const isKelasDropdownOpen = ref(false);
const searchKelasDropdownQuery = ref('');
const kelasDropdownRef = ref(null);

const isMapelDropdownOpen = ref(false);
const mapelDropdownRef = ref(null);

const tingkatOptions = [
  { value: 'Semua', label: 'Semua Tingkat' },
  { value: 'X', label: 'Tingkat X' },
  { value: 'XI', label: 'Tingkat XI' },
  { value: 'XII', label: 'Tingkat XII' }
];

const yearOptions = [
  { value: 2025, label: 'Tahun 2025' },
  { value: 2026, label: 'Tahun 2026' },
  { value: 2027, label: 'Tahun 2027' }
];

function closeAllDropdowns() {
  isMonthDropdownOpen.value = false;
  isYearDropdownOpen.value = false;
  isTingkatDropdownOpen.value = false;
  isKelasDropdownOpen.value = false;
  isMapelDropdownOpen.value = false;
}

function toggleMonthDropdown() {
  const willOpen = !isMonthDropdownOpen.value;
  closeAllDropdowns();
  isMonthDropdownOpen.value = willOpen;
}

function selectMonth(mIdx) {
  selectedMonth.value = mIdx;
  isMonthDropdownOpen.value = false;
}

function toggleYearDropdown() {
  const willOpen = !isYearDropdownOpen.value;
  closeAllDropdowns();
  isYearDropdownOpen.value = willOpen;
}

function selectYear(y) {
  selectedYear.value = y;
  isYearDropdownOpen.value = false;
}

function toggleTingkatDropdown() {
  const willOpen = !isTingkatDropdownOpen.value;
  closeAllDropdowns();
  isTingkatDropdownOpen.value = willOpen;
}

function selectTingkat(val) {
  selectedTingkatFilter.value = val;
  isTingkatDropdownOpen.value = false;
}

function toggleKelasDropdown() {
  const willOpen = !isKelasDropdownOpen.value;
  closeAllDropdowns();
  isKelasDropdownOpen.value = willOpen;
  if (willOpen) {
    searchKelasDropdownQuery.value = '';
  }
}

function selectKelas(namaKelas) {
  selectedKelasFilter.value = namaKelas;
  isKelasDropdownOpen.value = false;
  searchKelasDropdownQuery.value = '';
}

function toggleMapelDropdown() {
  const willOpen = !isMapelDropdownOpen.value;
  closeAllDropdowns();
  isMapelDropdownOpen.value = willOpen;
}

function selectMapel(mapel) {
  selectedMapelFilter.value = mapel;
  isMapelDropdownOpen.value = false;
}

function handleClickOutside(event) {
  const inMonth = monthDropdownRef.value && monthDropdownRef.value.contains(event.target);
  const inYear = yearDropdownRef.value && yearDropdownRef.value.contains(event.target);
  const inTingkat = tingkatDropdownRef.value && tingkatDropdownRef.value.contains(event.target);
  const inKelas = kelasDropdownRef.value && kelasDropdownRef.value.contains(event.target);
  const inMapel = mapelDropdownRef.value && mapelDropdownRef.value.contains(event.target);

  if (!inMonth && !inYear && !inTingkat && !inKelas && !inMapel) {
    closeAllDropdowns();
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    document.addEventListener('click', handleClickOutside);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    document.removeEventListener('click', handleClickOutside);
  }
});

// Reactive sync between Tingkat and Kelas Filter
watch(selectedTingkatFilter, (newTingkat) => {
  if (newTingkat !== 'Semua' && selectedKelasFilter.value !== 'Semua') {
    const matchingKelas = allAvailableKelasList.value.find(k => k.namaKelas === selectedKelasFilter.value);
    const kelasTingkat = matchingKelas?.tingkat || getTingkatFromKelas(matchingKelas?.namaKelas);
    if (kelasTingkat !== newTingkat) {
      selectedKelasFilter.value = 'Semua';
    }
  }
});

watch(selectedKelasFilter, (newKelas) => {
  if (newKelas && newKelas !== 'Semua') {
    const matchingKelas = allAvailableKelasList.value.find(k => k.namaKelas === newKelas);
    const kelasTingkat = matchingKelas?.tingkat || getTingkatFromKelas(matchingKelas?.namaKelas);
    if (kelasTingkat && selectedTingkatFilter.value !== 'Semua' && selectedTingkatFilter.value !== kelasTingkat) {
      selectedTingkatFilter.value = kelasTingkat;
    }
  }
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

  // Filter Tingkat
  if (selectedTingkatFilter.value && selectedTingkatFilter.value !== 'Semua') {
    list = list.filter(j => {
      const t = getTingkatFromKelas(j.kelas);
      return t === selectedTingkatFilter.value;
    });
  }

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

const defaultSchoolYearString = computed(() => {
  const taKode = page.props.activeTahunAjaran?.kode;
  return taKode ? `Tahun Pelajaran ${taKode}` : 'Tahun Pelajaran 2025/2026';
});

const targetTahunPelajaran = computed(() => {
  const tp = filteredJurnal.value[0]?.tahunPelajaran || jurnalForm.value.tahunPelajaran || defaultSchoolYearString.value;
  return String(tp).startsWith('Tahun') ? tp : `Tahun Pelajaran ${tp}`;
});

// Dedicated Form View State (Bukan Modal Popup)
const isFormViewActive = ref(false);
const editingJurnal = ref(null);

function closeFormView() {
  isFormViewActive.value = false;
  editingJurnal.value = null;
}

const startPeriod = ref(1);
const endPeriod = ref(3);

function getActiveSemester() {
  try {
    const saved = typeof window !== 'undefined' ? localStorage.getItem('guru_active_semester') : null;
    if (saved && ['Ganjil', 'Genap'].includes(saved)) return saved;
  } catch (e) {}
  const currentMonth = new Date().getMonth() + 1;
  return (currentMonth >= 1 && currentMonth <= 6) ? 'Genap' : 'Ganjil';
}

const jurnalForm = ref({
  tanggal: getTodayString(),
  jamKe: '',
  kelas: scheduledClasses.value[0] || 'X RPL 1',
  mataPelajaran: teacherAdminSubjects.value[0] || 'Informatika',
  materi: '',
  uraian: '',
  catatan: '',
  tahunPelajaran: defaultSchoolYearString.value,
  semester: getActiveSemester()
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

// Event saat guru mengganti Kelas di Form: Otomatis kunci mapel & jam sesuai jadwal kelas tersebut!
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

// Shortcut Action: Pilih Langsung dari Jadwal (Bila dipanggil dari luar tab)
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
  
  // Smart Default: Cek jadwal hari ini terlebih dahulu
  const schedToday = todaySchedules.value && todaySchedules.value.length > 0 ? todaySchedules.value[0] : null;

  const defaultKelas = schedToday?.kelas 
    || (selectedKelasFilter.value !== 'Semua' ? selectedKelasFilter.value : (todayScheduledClasses.value[0] || scheduledClasses.value[0] || 'X RPL 1'));

  jurnalForm.value = {
    tanggal: getTodayString(),
    jamKe: '',
    kelas: defaultKelas,
    mataPelajaran: schedToday?.mataPelajaran || schedToday?.mata_pelajaran || teacherAdminSubjects.value[0] || 'Informatika',
    materi: '',
    uraian: '',
    catatan: '',
    tahunPelajaran: defaultSchoolYearString.value,
    semester: getActiveSemester()
  };

  if (schedToday) {
    applyJadwalShortcut(schedToday);
  } else {
    onKelasChange();
  }

  isFormViewActive.value = true;
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
    tahunPelajaran: item.tahunPelajaran || defaultSchoolYearString.value,
    semester: item.semester || 'Ganjil'
  };
  isFormViewActive.value = true;
}

function handleSaveJurnal() {
  if (!jurnalForm.value.tanggal || !jurnalForm.value.kelas || !jurnalForm.value.materi) return;
  
  jurnalForm.value.jamKe = computedJamLabel.value;
  if (!jurnalForm.value.mataPelajaran) {
    jurnalForm.value.mataPelajaran = subjectsForSelectedKelas.value[0] || teacherAdminSubjects.value[0] || 'Informatika';
  }

  emit('save-jurnal', { editing: editingJurnal.value, data: { ...jurnalForm.value } });
  isFormViewActive.value = false;
  editingJurnal.value = null;
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
watch(() => props.shortcutJadwal, (shortcut) => {
  if (shortcut) {
    openAddJurnalModal();
    applyJadwalShortcut(shortcut);
    emit('clear-shortcut');
  }
}, { immediate: true });

</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- TAMPILAN DAFTAR JURNAL (LIST VIEW) -->
    <div v-if="!isFormViewActive" class="space-y-6">
      
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

    <!-- Quick Switch Pills untuk Jadwal Hari Ini -->
    <div v-if="todayScheduledClasses.length > 0" class="flex flex-wrap items-center gap-2 p-3 rounded-2xl bg-cyan-950/30 border border-cyan-500/20">
      <span class="text-xs font-bold text-cyan-300 flex items-center gap-1 shrink-0">
        📅 Jadwal Hari Ini ({{ todayDayName }}):
      </span>
      <button
        v-for="kName in todayScheduledClasses"
        :key="kName"
        type="button"
        @click="selectedKelasFilter = kName"
        :class="[
          'px-3 py-1 rounded-xl text-xs font-bold transition-all border min-h-[32px]',
          selectedKelasFilter === kName
            ? 'bg-cyan-500 text-slate-950 border-cyan-400 shadow-sm shadow-cyan-500/25'
            : 'bg-slate-800 text-gray-300 border-white/10 hover:bg-slate-700'
        ]"
      >
        🏫 {{ kName }}
      </button>
      <button
        type="button"
        @click="selectedKelasFilter = 'Semua'"
        class="text-xs text-gray-400 hover:text-white underline ml-auto"
      >
        Lihat Semua
      </button>
    </div>

    <!-- Filter Control Bar (6 Columns: Bulan, Tahun, Tingkat, Kelas Terjadwal, Mapel Guru, Cari) -->
    <div class="relative z-40 bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4">
        
        <!-- 1. Pilih Bulan (Custom Dropdown) -->
        <div ref="monthDropdownRef" class="space-y-1.5 relative">
          <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Pilih Bulan</label>
          <button
            type="button"
            @click="toggleMonthDropdown"
            class="w-full bg-slate-900 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-white font-semibold flex items-center justify-between min-h-[44px] cursor-pointer hover:border-cyan-400/50 focus:border-cyan-400 transition-all text-left"
            :class="isMonthDropdownOpen ? 'border-cyan-400 ring-2 ring-cyan-400/20' : ''"
          >
            <span class="truncate">
              {{ monthNames[selectedMonth - 1] }}
            </span>
            <span class="text-gray-400 text-xs ml-2 transition-transform duration-200" :class="isMonthDropdownOpen ? 'rotate-180 text-cyan-400' : ''">
              ▼
            </span>
          </button>

          <!-- Floating Dropdown Menu -->
          <div
            v-if="isMonthDropdownOpen"
            class="absolute left-0 top-full mt-1.5 w-full min-w-[170px] z-50 bg-slate-900/95 backdrop-blur-2xl border border-white/15 rounded-2xl shadow-2xl p-2.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
          >
            <div class="max-h-56 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
              <button
                v-for="(mName, idx) in monthNames"
                :key="idx"
                type="button"
                @click="selectMonth(idx + 1)"
                :class="[
                  'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all min-h-[36px]',
                  selectedMonth === (idx + 1) ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-gray-300 hover:bg-white/5 hover:text-white'
                ]"
              >
                <span>{{ mName }}</span>
                <span v-if="selectedMonth === (idx + 1)" class="text-cyan-400 font-bold ml-2">✓</span>
              </button>
            </div>
          </div>
        </div>

        <!-- 2. Pilih Tahun (Custom Dropdown) -->
        <div ref="yearDropdownRef" class="space-y-1.5 relative">
          <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Pilih Tahun</label>
          <button
            type="button"
            @click="toggleYearDropdown"
            class="w-full bg-slate-900 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-white font-semibold flex items-center justify-between min-h-[44px] cursor-pointer hover:border-cyan-400/50 focus:border-cyan-400 transition-all text-left"
            :class="isYearDropdownOpen ? 'border-cyan-400 ring-2 ring-cyan-400/20' : ''"
          >
            <span class="truncate">
              Tahun {{ selectedYear }}
            </span>
            <span class="text-gray-400 text-xs ml-2 transition-transform duration-200" :class="isYearDropdownOpen ? 'rotate-180 text-cyan-400' : ''">
              ▼
            </span>
          </button>

          <!-- Floating Dropdown Menu -->
          <div
            v-if="isYearDropdownOpen"
            class="absolute left-0 top-full mt-1.5 w-full min-w-[150px] z-50 bg-slate-900/95 backdrop-blur-2xl border border-white/15 rounded-2xl shadow-2xl p-2.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
          >
            <div class="space-y-1">
              <button
                v-for="yOpt in yearOptions"
                :key="yOpt.value"
                type="button"
                @click="selectYear(yOpt.value)"
                :class="[
                  'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all min-h-[36px]',
                  selectedYear === yOpt.value ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-gray-300 hover:bg-white/5 hover:text-white'
                ]"
              >
                <span>{{ yOpt.label }}</span>
                <span v-if="selectedYear === yOpt.value" class="text-cyan-400 font-bold ml-2">✓</span>
              </button>
            </div>
          </div>
        </div>

        <!-- 3. Filter Tingkat (Custom Dropdown) -->
        <div ref="tingkatDropdownRef" class="space-y-1.5 relative">
          <label class="block text-[11px] font-bold text-cyan-400 uppercase tracking-wider flex items-center gap-1">
            <span>🏷️</span>
            <span>Tingkat</span>
          </label>
          <button
            type="button"
            @click="toggleTingkatDropdown"
            class="w-full bg-slate-900 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-white font-semibold flex items-center justify-between min-h-[44px] cursor-pointer hover:border-cyan-400/50 focus:border-cyan-400 transition-all text-left"
            :class="isTingkatDropdownOpen ? 'border-cyan-400 ring-2 ring-cyan-400/20' : ''"
          >
            <span class="truncate">
              {{ selectedTingkatFilter === 'Semua' ? 'Semua Tingkat' : 'Tingkat ' + selectedTingkatFilter }}
            </span>
            <span class="text-gray-400 text-xs ml-2 transition-transform duration-200" :class="isTingkatDropdownOpen ? 'rotate-180 text-cyan-400' : ''">
              ▼
            </span>
          </button>

          <!-- Floating Dropdown Menu -->
          <div
            v-if="isTingkatDropdownOpen"
            class="absolute left-0 top-full mt-1.5 w-full min-w-[170px] z-50 bg-slate-900/95 backdrop-blur-2xl border border-white/15 rounded-2xl shadow-2xl p-2.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
          >
            <div class="space-y-1">
              <button
                v-for="opt in tingkatOptions"
                :key="opt.value"
                type="button"
                @click="selectTingkat(opt.value)"
                :class="[
                  'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all min-h-[36px]',
                  selectedTingkatFilter === opt.value ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-gray-300 hover:bg-white/5 hover:text-white'
                ]"
              >
                <span>{{ opt.label }}</span>
                <span v-if="selectedTingkatFilter === opt.value" class="text-cyan-400 font-bold ml-2">✓</span>
              </button>
            </div>
          </div>
        </div>

        <!-- 4. Filter Kelas Terjadwal (Custom Searchable & Scrollable Dropdown with Natural Numeric Sort) -->
        <div ref="kelasDropdownRef" class="space-y-1.5 relative">
          <div class="flex items-center justify-between">
            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Kelas</label>
            <span v-if="selectedTingkatFilter !== 'Semua'" class="text-[10px] text-cyan-400 font-mono font-bold">
              {{ availableKelasOptions.length }} Kelas
            </span>
          </div>

          <!-- Trigger Button -->
          <button
            type="button"
            @click="toggleKelasDropdown"
            class="w-full bg-slate-900 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-white font-semibold flex items-center justify-between min-h-[44px] cursor-pointer hover:border-cyan-400/50 focus:border-cyan-400 transition-all text-left"
            :class="isKelasDropdownOpen ? 'border-cyan-400 ring-2 ring-cyan-400/20' : ''"
          >
            <span class="truncate">
              {{ selectedKelasFilter === 'Semua' ? (selectedTingkatFilter !== 'Semua' ? 'Semua Kelas ' + selectedTingkatFilter : 'Semua Kelas Terjadwal') : selectedKelasFilter }}
            </span>
            <span class="text-gray-400 text-xs ml-2 transition-transform duration-200" :class="isKelasDropdownOpen ? 'rotate-180 text-cyan-400' : ''">
              ▼
            </span>
          </button>

          <!-- Floating Dropdown Menu -->
          <div
            v-if="isKelasDropdownOpen"
            class="absolute left-0 top-full mt-1.5 w-full min-w-[220px] z-50 bg-slate-900/95 backdrop-blur-2xl border border-white/15 rounded-2xl shadow-2xl p-2.5 space-y-2 animate-in fade-in zoom-in-95 duration-150"
          >
            <!-- Search Inside Dropdown -->
            <div class="relative">
              <input
                v-model="searchKelasDropdownQuery"
                type="text"
                placeholder="Cari kelas (misal: TKR 1)..."
                class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-gray-500 focus:border-cyan-400 outline-none"
                @click.stop
              />
              <button
                v-if="searchKelasDropdownQuery"
                type="button"
                @click="searchKelasDropdownQuery = ''"
                class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-xs text-gray-400 hover:text-white"
              >
                ✕
              </button>
            </div>

            <!-- Scrollable List Options (Natural Numeric Ordered) -->
            <div class="max-h-56 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
              <button
                type="button"
                @click="selectKelas('Semua')"
                :class="[
                  'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all min-h-[36px]',
                  selectedKelasFilter === 'Semua' ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-gray-300 hover:bg-white/5 hover:text-white'
                ]"
              >
                <span>{{ selectedTingkatFilter !== 'Semua' ? 'Semua Kelas ' + selectedTingkatFilter : 'Semua Kelas Terjadwal' }}</span>
                <span v-if="selectedKelasFilter === 'Semua'" class="text-cyan-400 font-bold">✓</span>
              </button>

              <button
                v-for="k in filteredDropdownKelasOptions"
                :key="k.id || k.namaKelas"
                type="button"
                @click="selectKelas(k.namaKelas)"
                :class="[
                  'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all min-h-[36px]',
                  selectedKelasFilter === k.namaKelas ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-gray-300 hover:bg-white/5 hover:text-white'
                ]"
              >
                <span class="truncate">{{ k.namaKelas }}</span>
                <span v-if="selectedKelasFilter === k.namaKelas" class="text-cyan-400 font-bold ml-2">✓</span>
              </button>

              <div
                v-if="filteredDropdownKelasOptions.length === 0"
                class="text-center py-4 text-xs text-gray-500"
              >
                Tidak ada kelas yang cocok.
              </div>
            </div>
          </div>
        </div>

        <!-- 5. Filter Mata Pelajaran (Custom Dropdown) -->
        <div ref="mapelDropdownRef" class="space-y-1.5 relative">
          <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Mata Pelajaran</label>
          <button
            type="button"
            @click="toggleMapelDropdown"
            class="w-full bg-slate-900 border border-cyan-500/30 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-cyan-300 font-bold flex items-center justify-between min-h-[44px] cursor-pointer hover:border-cyan-400/50 focus:border-cyan-400 transition-all text-left"
            :class="isMapelDropdownOpen ? 'border-cyan-400 ring-2 ring-cyan-400/20' : ''"
          >
            <span class="truncate">
              {{ selectedMapelFilter === 'Semua' ? 'Semua Mapel Guru' : selectedMapelFilter }}
            </span>
            <span class="text-gray-400 text-xs ml-2 transition-transform duration-200" :class="isMapelDropdownOpen ? 'rotate-180 text-cyan-400' : ''">
              ▼
            </span>
          </button>

          <!-- Floating Dropdown Menu -->
          <div
            v-if="isMapelDropdownOpen"
            class="absolute left-0 top-full mt-1.5 w-full min-w-[190px] z-50 bg-slate-900/95 backdrop-blur-2xl border border-white/15 rounded-2xl shadow-2xl p-2.5 space-y-1 animate-in fade-in zoom-in-95 duration-150"
          >
            <div class="max-h-56 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
              <button
                type="button"
                @click="selectMapel('Semua')"
                :class="[
                  'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all min-h-[36px]',
                  selectedMapelFilter === 'Semua' ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-gray-300 hover:bg-white/5 hover:text-white'
                ]"
              >
                <span>Semua Mapel Guru</span>
                <span v-if="selectedMapelFilter === 'Semua'" class="text-cyan-400 font-bold ml-2">✓</span>
              </button>
              <button
                v-for="m in teacherAdminSubjects"
                :key="m"
                type="button"
                @click="selectMapel(m)"
                :class="[
                  'w-full text-left px-3 py-2 rounded-xl text-xs font-semibold flex items-center justify-between transition-all min-h-[36px]',
                  selectedMapelFilter === m ? 'bg-cyan-500/20 text-cyan-300 font-bold' : 'text-gray-300 hover:bg-white/5 hover:text-white'
                ]"
              >
                <span class="truncate">📖 {{ m }}</span>
                <span v-if="selectedMapelFilter === m" class="text-cyan-400 font-bold ml-2">✓</span>
              </button>
            </div>
          </div>
        </div>

        <!-- 6. Cari Materi / Catatan -->
        <div class="space-y-1.5">
          <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider">Cari Materi / Catatan</label>
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari materi KBM..."
              class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-cyan-400 outline-none min-h-[44px]"
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs text-gray-400 hover:text-white"
            >
              ✕
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- JURNAL TABLE & MOBILE CARDS -->
    <div class="relative z-10 bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      
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

    </div>
    <!-- END TAMPILAN DAFTAR JURNAL (LIST VIEW) -->

    <!-- TAMPILAN FORMULIR CATAT / EDIT JURNAL MENGAJAR (DEDICATED FULL VIEW) -->
    <div v-else class="space-y-6 animate-in fade-in zoom-in-95 duration-200">
      
      <!-- Top Navigation & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/10">
        <div>
          <button
            type="button"
            @click="closeFormView"
            class="inline-flex items-center gap-2 text-xs sm:text-sm font-bold text-cyan-400 hover:text-cyan-300 transition-colors mb-2 group cursor-pointer"
          >
            <span class="text-base group-hover:-translate-x-1 transition-transform">←</span>
            <span>Kembali ke Daftar Jurnal</span>
          </button>
          <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
            <span>{{ editingJurnal ? 'Edit Catatan Jurnal Mengajar' : 'Catat Jurnal Mengajar Baru' }}</span>
            <span class="text-cyan-400 text-lg">✍️</span>
          </h2>
          <p class="text-gray-400 text-xs sm:text-sm mt-1">
            Formulir pencatatan kegiatan belajar mengajar (KBM) harian guru diselaraskan dengan kurikulum &amp; data resmi.
          </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
          <button
            type="button"
            @click="closeFormView"
            class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-gray-300 hover:text-white rounded-2xl text-xs sm:text-sm font-bold transition-all border border-white/10 min-h-[44px]"
          >
            Batal
          </button>
          <button
            type="button"
            @click="handleSaveJurnal"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-cyan-500 via-teal-600 to-indigo-600 hover:opacity-95 text-white rounded-2xl text-xs sm:text-sm font-extrabold shadow-lg shadow-cyan-500/25 active:scale-95 transition-all min-h-[44px]"
          >
            <span>💾</span>
            <span>{{ editingJurnal ? 'Perbarui Jurnal' : 'Simpan Jurnal KBM' }}</span>
          </button>
        </div>
      </div>

      <!-- Main Form Card -->
      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 sm:p-8 border border-white/10 shadow-2xl space-y-6">
        <form @submit.prevent="handleSaveJurnal" class="space-y-6">
          
          <!-- Section 1: Pelaksanaan KBM -->
          <div class="space-y-4">
            <h3 class="text-xs sm:text-sm font-black uppercase text-cyan-400 tracking-wider flex items-center gap-2 pb-2 border-b border-white/5">
              <span>📅</span>
              <span>Waktu &amp; Sasaran Kelas</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <!-- Tanggal Mengajar -->
              <div class="space-y-1.5">
                <label class="block text-gray-300 font-bold uppercase text-[11px]">Tanggal Mengajar *</label>
                <input
                  v-model="jurnalForm.tanggal"
                  type="date"
                  class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-xs sm:text-sm min-h-[44px]"
                  required
                />
              </div>

              <!-- Semester -->
              <div class="space-y-1.5">
                <label class="block text-gray-300 font-bold uppercase text-[11px]">Semester *</label>
                <select
                  v-model="jurnalForm.semester"
                  class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-xs sm:text-sm font-semibold min-h-[44px]"
                  required
                >
                  <option value="Ganjil">Semester Ganjil</option>
                  <option value="Genap">Semester Genap</option>
                </select>
              </div>

              <!-- Kelas Target -->
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <label class="block text-gray-300 font-bold uppercase text-[11px]">Kelas Ampuan *</label>
                  <span class="text-[10px] text-cyan-400 font-mono font-bold">{{ scheduledClasses.length }} Kelas</span>
                </div>
                <select
                  v-model="jurnalForm.kelas"
                  @change="onKelasChange"
                  class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-xs sm:text-sm font-semibold min-h-[44px]"
                  required
                >
                  <option value="" disabled>-- Pilih Kelas Terjadwal --</option>
                  <option v-for="k in scheduledClasses" :key="k" :value="k">
                    🏫 Kelas {{ k }}
                  </option>
                </select>
              </div>

              <!-- Mata Pelajaran -->
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <label class="block text-cyan-300 font-bold uppercase text-[11px]">Mata Pelajaran *</label>
                  <span class="text-[10px] bg-cyan-500/15 text-cyan-300 px-2 py-0.5 rounded-full border border-cyan-500/30 font-mono font-bold">
                    ✓ Baku
                  </span>
                </div>
                <select
                  v-model="jurnalForm.mataPelajaran"
                  class="w-full bg-slate-950 border border-cyan-500/40 rounded-2xl px-4 py-3 text-cyan-300 font-bold outline-none text-xs sm:text-sm min-h-[44px] focus:border-cyan-400"
                  required
                >
                  <option v-for="subj in subjectsForSelectedKelas" :key="subj" :value="subj">
                    📖 {{ subj }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Section 2: Pilihan Jam Mengajar -->
          <div class="bg-slate-950/60 rounded-3xl p-5 border border-white/10 space-y-4">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-extrabold text-cyan-300 uppercase tracking-wider flex items-center gap-1.5">
                <span>⏱️</span>
                <span>Pilih Jam Pelajaran Mengajar</span>
              </label>
              <span class="text-xs font-black text-emerald-300 bg-emerald-500/20 px-3 py-1 rounded-full border border-emerald-500/30 font-mono">
                {{ (Math.max(startPeriod || 1, endPeriod || 1) - Math.min(startPeriod || 1, endPeriod || 1)) + 1 }} JP (Jam Pelajaran)
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-400 font-semibold text-[11px] mb-1.5">Dari Jam ke- *</label>
                <select
                  v-model.number="startPeriod"
                  class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-xs sm:text-sm font-semibold min-h-[44px]"
                >
                  <option v-for="slot in schoolPeriodSlots" :key="slot.id" :value="slot.id">
                    {{ slot.label }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-gray-400 font-semibold text-[11px] mb-1.5">Sampai Jam ke- *</label>
                <select
                  v-model.number="endPeriod"
                  class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-xs sm:text-sm font-semibold min-h-[44px]"
                >
                  <option v-for="slot in schoolPeriodSlots" :key="slot.id" :value="slot.id">
                    {{ slot.label }}
                  </option>
                </select>
              </div>
            </div>

            <div class="p-3 bg-slate-900 rounded-2xl border border-white/10 text-xs font-mono text-cyan-300 font-bold flex items-center justify-between flex-wrap gap-2">
              <span class="flex items-center gap-2">
                <span>⏱️</span>
                <span>Format Jurnal: <span class="text-white font-sans font-semibold">{{ computedJamLabel }}</span> ({{ (Math.max(startPeriod || 1, endPeriod || 1) - Math.min(startPeriod || 1, endPeriod || 1)) + 1 }} JP)</span>
              </span>
              <span class="text-[11px] text-gray-400">Otomatis dihitung untuk rekapitulasi KBM</span>
            </div>
          </div>

          <!-- Section 3: Materi & Pelaksanaan -->
          <div class="space-y-4">
            <h3 class="text-xs sm:text-sm font-black uppercase text-cyan-400 tracking-wider flex items-center gap-2 pb-2 border-b border-white/5">
              <span>💡</span>
              <span>Materi &amp; Uraian KBM</span>
            </h3>

            <!-- Pokok Bahasan / Materi -->
            <div class="space-y-1.5">
              <label class="block text-gray-300 font-bold uppercase text-[11px]">Pokok Bahasan / Sub-Materi *</label>
              <input
                v-model="jurnalForm.materi"
                type="text"
                placeholder="Contoh: Pemrograman Web Lanjut — Vue.js Component & State"
                class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-3 text-white placeholder-gray-500 focus:border-cyan-400 outline-none text-xs sm:text-sm min-h-[44px]"
                required
              />
            </div>

            <!-- Uraian Kegiatan Pembelajaran -->
            <div class="space-y-1.5">
              <label class="block text-gray-300 font-bold uppercase text-[11px]">Uraian Kegiatan Pembelajaran</label>
              <textarea
                v-model="jurnalForm.uraian"
                rows="4"
                placeholder="Jelaskan ringkas aktivitas KBM, diskusi materi, praktek di lab komputer/bengkel, atau hasil evaluasi siswa..."
                class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-3 text-white placeholder-gray-500 focus:border-cyan-400 outline-none text-xs sm:text-sm font-medium leading-relaxed"
              ></textarea>
            </div>

            <!-- Catatan Kendala / Tindak Lanjut -->
            <div class="space-y-1.5">
              <label class="block text-gray-300 font-bold uppercase text-[11px]">Catatan Kendala / Tindak Lanjut (Opsional)</label>
              <textarea
                v-model="jurnalForm.catatan"
                rows="3"
                placeholder="Contoh: 2 siswa izin lomba kejuruan / 1 siswa butuh pengayaan mandiri / Peralatan lab memadai..."
                class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-3 text-white placeholder-gray-500 focus:border-cyan-400 outline-none text-xs sm:text-sm font-medium leading-relaxed"
              ></textarea>
            </div>
          </div>

          <!-- Bottom Action Buttons -->
          <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button
              type="button"
              @click="closeFormView"
              class="w-full sm:w-auto px-6 py-3 bg-slate-800 hover:bg-slate-700 text-gray-300 hover:text-white rounded-2xl text-xs sm:text-sm font-bold transition-all border border-white/10 min-h-[44px]"
            >
              Batal &amp; Kembali
            </button>
            <button
              type="submit"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3 bg-gradient-to-r from-cyan-500 via-teal-500 to-indigo-600 hover:opacity-95 text-white rounded-2xl text-xs sm:text-sm font-black shadow-lg shadow-cyan-500/25 active:scale-95 transition-all min-h-[44px]"
            >
              <span>💾</span>
              <span>{{ editingJurnal ? 'Perbarui Jurnal Mengajar' : 'Simpan Jurnal KBM' }}</span>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</template>
