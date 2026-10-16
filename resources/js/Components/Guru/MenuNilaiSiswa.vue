<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { showToast } from '@/Utils/sweetalert';

const props = defineProps({
  tpList: { type: Array, default: () => [] },
  nilaiList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  tahunAjaranList: {
    type: Array,
    default: () => []
  },
  teacherSubject: { type: String, default: 'Informatika' },
  mapelList: { type: Array, default: () => [] },
  cbtJadwalList: { type: Array, default: () => [] },
  cbtNilaiList: { type: Array, default: () => [] },
});

const emit = defineEmits(['save-tp', 'delete-tp', 'save-nilai-batch', 'save-ta', 'delete-ta', 'set-active-ta']);

const page = usePage();

// View Mode State: 'matrix' (Ledger Matriks Nilai) | 'batch-input' (Input Massal Nilai per TP)
const viewMode = ref('matrix');

// Collapsible Master TP Panel State
const showTpPanel = ref(false);

// Academic Period Filters State
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

const selectedSemester = ref(getInitialSemester());

watch(selectedSemester, (newVal) => {
  if (typeof window !== 'undefined' && newVal) {
    try {
      localStorage.setItem('guru_active_semester', newVal);
    } catch (e) {}
  }
}, { immediate: true });

function setSemester(sem) {
  if (selectedSemester.value === sem) return;
  selectedSemester.value = sem;
  try {
    localStorage.setItem('guru_active_semester', sem);
  } catch (e) {}
  initBatchRows();
  showToast(`Beralih ke Semester ${sem}`, 'info');
}

const activeTaObj = computed(() => props.tahunAjaranList.find(t => t.isAktif || t.is_aktif) || page.props.activeTahunAjaran || props.tahunAjaranList[0]);
const selectedTahunAjaran = ref(activeTaObj.value?.kode || page.props.activeTahunAjaran?.kode || '2026/2027');

watch(activeTaObj, (newVal) => {
  if (newVal?.kode && (!selectedTahunAjaran.value || selectedTahunAjaran.value === '2025/2026')) {
    selectedTahunAjaran.value = newVal.kode;
  }
}, { immediate: true });

function getInitialKelas() {
  try {
    const saved = typeof window !== 'undefined' ? localStorage.getItem('guru_active_absensi_kelas') : null;
    if (saved && (props.kelasList || []).some(k => (k.namaKelas || k) === saved)) {
      return saved;
    }
  } catch (e) {}
  return props.kelasList[0]?.namaKelas || 'X RPL 1';
}

// Class & Student Filters State
const selectedKelasFilter = ref(getInitialKelas());
const selectedMapelFilter = ref('Semua');
const searchQuery = ref('');

const selectedTingkatFilter = ref('X');

function getTingkatFromKelas(className) {
  if (!className) return 'X';
  if (className.toUpperCase().startsWith('XII')) return 'XII';
  if (className.toUpperCase().startsWith('XI')) return 'XI';
  return 'X';
}

const filteredKelasByTingkat = computed(() => {
  return (props.kelasList || []).filter(k => {
    const t = k?.tingkat || getTingkatFromKelas(k?.namaKelas || k?.nama || k);
    return t === selectedTingkatFilter.value;
  }).sort((a, b) => (a?.namaKelas || a?.nama || a || '').toString().localeCompare((b?.namaKelas || b?.nama || b || '').toString(), undefined, { numeric: true, sensitivity: 'base' }));
});

function onTingkatFilterChange() {
  const available = filteredKelasByTingkat.value;
  if (available.length > 0) {
    selectedKelasFilter.value = available[0].namaKelas || available[0];
  } else {
    selectedKelasFilter.value = '';
  }
}

watch(selectedKelasFilter, (newVal) => {
  if (newVal && typeof window !== 'undefined') {
    localStorage.setItem('guru_active_absensi_kelas', newVal);
  }
});

watch(() => props.kelasList, (newVal) => {
  if (newVal && newVal.length > 0) {
    const exists = newVal.some(k => (k.namaKelas || k) === selectedKelasFilter.value);
    if (!exists) {
      const preferred = getInitialKelas();
      const prefExists = newVal.some(k => (k.namaKelas || k) === preferred);
      selectedKelasFilter.value = prefExists ? preferred : (newVal[0].namaKelas || newVal[0]);
      selectedTingkatFilter.value = newVal[0].tingkat || getTingkatFromKelas(selectedKelasFilter.value);
    }
  }
}, { immediate: true });

onMounted(() => {
  if (selectedKelasFilter.value) {
    selectedTingkatFilter.value = getTingkatFromKelas(selectedKelasFilter.value);
  }
});

const teacherAssignedMapels = computed(() => {
  const subjectStr = props.teacherSubject || '';
  const list = subjectStr.split(',').map(s => s.trim()).filter(s => s.length > 0);
  return list.length > 0 ? list : (props.mapelList || []).map(m => m.nama);
});

// Filtered TP List for Current Academic Period (Semester, Tahun Ajaran & Mapel)
const activeTpList = computed(() => {
  return props.tpList.filter(t => {
    const semMatch = (t.semester || 'Ganjil') === selectedSemester.value;
    const taMatch = (t.tahunAjaran || '2025/2026') === selectedTahunAjaran.value;
    const mapelMatch = selectedMapelFilter.value === 'Semua' || t.mapel === selectedMapelFilter.value;
    return semMatch && taMatch && mapelMatch;
  });
});

// Filtered Students for Selected Class
const filteredSiswa = computed(() => {
  let list = props.siswaList.filter(s => s.kelas === selectedKelasFilter.value);
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(s => s.nama.toLowerCase().includes(q) || (s.nis && s.nis.includes(q)));
  }
  return list;
});

// Fast O(1) Map lookup index for student grades
const nilaiMap = computed(() => {
  const map = new Map();
  const list = Array.isArray(props.nilaiList) ? props.nilaiList : [];
  for (let i = 0; i < list.length; i++) {
    const n = list[i];
    if (!n) continue;
    const sId = n.siswaId || n.siswa_id;
    const tId = n.tpId || n.tp_id;
    if (sId && tId) {
      map.set(`${sId}_${tId}`, n);
    }
  }
  return map;
});

// Helper: Get Score for a Student & TP (O(1))
function getScore(siswaId, tpId) {
  const rec = nilaiMap.value.get(`${siswaId}_${tpId}`);
  return rec ? rec.nilai : null;
}

// Helper: Get Catatan for a Student & TP (O(1))
function getCatatan(siswaId, tpId) {
  const rec = nilaiMap.value.get(`${siswaId}_${tpId}`);
  return rec?.catatan || '';
}

// Helper: Status Ketuntasan (Tuntas / Belum Tuntas) berdasarkan nilai & KKTP
function getStatusNilai(nilai, kktp = 75) {
  if (nilai === null || nilai === undefined || nilai === '') return { label: 'Belum Dinilai', color: 'bg-gray-500/20 text-gray-400 border-gray-500/30' };
  const n = Number(nilai);
  return n >= Number(kktp)
    ? { label: 'Tuntas', color: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' }
    : { label: 'Belum Tuntas', color: 'bg-red-500/20 text-red-300 border-red-500/30' };
}

// O(1) Memoized Student Statistics Map (Algoritma Opsi 1: Pembagi Total Target TP Aktif)
const studentStatsMap = computed(() => {
  const map = new Map();
  const activeTps = activeTpList.value || [];
  const totalTps = activeTps.length;
  const siswaArr = props.siswaList || [];

  for (let i = 0; i < siswaArr.length; i++) {
    const s = siswaArr[i];
    let sum = 0;
    let filledCount = 0;

    for (let j = 0; j < totalTps; j++) {
      const tp = activeTps[j];
      const rec = nilaiMap.value.get(`${s.id}_${tp.id}`);
      if (rec && rec.nilai !== null && rec.nilai !== undefined && rec.nilai !== '' && !isNaN(rec.nilai)) {
        sum += Number(rec.nilai);
        filledCount++;
      }
    }

    // Opsi 1: Pembagi adalah TOTAL TARGET TP semester ini.
    // TP yang belum dikerjakan otomatis bernilai 0 sehingga adil bagi siswa yang rajin mengerjakan semua TP.
    const finalScore = totalTps > 0 ? Math.round((sum / totalTps) * 10) / 10 : 0;
    const isComplete = totalTps > 0 && filledCount === totalTps;

    map.set(s.id, {
      siswaId: s.id,
      finalScore,
      filledCount,
      totalTps,
      isComplete,
      sum
    });
  }
  return map;
});

// Helper: Get Student Statistics Object (O(1))
function getStudentStats(siswaId) {
  return studentStatsMap.value.get(siswaId) || {
    finalScore: 0,
    filledCount: 0,
    totalTps: activeTpList.value.length,
    isComplete: false,
    sum: 0
  };
}

// Helper: Calculate Final Score for a Student (Opsi 1)
function getStudentAverage(siswaId) {
  return getStudentStats(siswaId).finalScore;
}

// Helper: Get Predikat Badge from Score & Completeness
function getPredikat(score, isComplete = true) {
  if (score === null || score === undefined || score === 0) {
    return { label: '-', color: 'bg-gray-500/10 text-gray-400 border-gray-500/20' };
  }
  if (!isComplete && score < 75) {
    return { label: 'Remedial', color: 'bg-rose-500/15 text-rose-300 border-rose-500/30' };
  }
  if (score >= 90) return { label: 'A (Sangat Baik)', color: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30' };
  if (score >= 80) return { label: 'B (Baik)', color: 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30' };
  if (score >= 75) return { label: 'C (Cukup)', color: 'bg-amber-500/15 text-amber-300 border-amber-500/30' };
  return { label: 'Remedial', color: 'bg-rose-500/15 text-rose-300 border-rose-500/30' };
}

// Stat Calculations for Active Class & Semester
const classAverage = computed(() => {
  if (filteredSiswa.value.length === 0) return 0;
  const avgs = filteredSiswa.value.map(s => getStudentAverage(s.id)).filter(a => a > 0);
  if (avgs.length === 0) return 0;
  const sum = avgs.reduce((a, b) => a + b, 0);
  return Math.round((sum / avgs.length) * 10) / 10;
});

const topStudent = computed(() => {
  if (filteredSiswa.value.length === 0) return '-';
  
  // Prioritas 1: Siswa yang sudah menuntaskan seluruh TP secara lengkap
  const completedStudents = filteredSiswa.value.filter(s => getStudentStats(s.id).isComplete);
  if (completedStudents.length > 0) {
    let best = completedStudents[0];
    let maxScore = getStudentAverage(best.id);
    for (let i = 1; i < completedStudents.length; i++) {
      const score = getStudentAverage(completedStudents[i].id);
      if (score > maxScore) {
        maxScore = score;
        best = completedStudents[i];
      }
    }
    return maxScore > 0 ? `${best.nama} (${maxScore})` : '-';
  }

  // Jika belum ada siswa yang 100% lengkap (semester masih berjalan)
  let best = null;
  let maxScore = -1;
  filteredSiswa.value.forEach(s => {
    const stats = getStudentStats(s.id);
    if (stats.finalScore > maxScore && stats.filledCount > 0) {
      maxScore = stats.finalScore;
      best = s;
    }
  });
  return best && maxScore > 0 ? `${best.nama} (${maxScore})*` : '-';
});

const totalRemidialCount = computed(() => {
  return filteredSiswa.value.filter(s => {
    const stats = getStudentStats(s.id);
    return stats.filledCount > 0 && (stats.finalScore < 75 || !stats.isComplete);
  }).length;
});



// BATCH INPUT NILAI PER TP STATE
const batchInputTpId = ref(activeTpList.value[0]?.id || 1);
const batchRows = ref([]);

// Computed for active target TP in batch input
const currentBatchTp = computed(() => {
  return activeTpList.value.find(t => t.id === Number(batchInputTpId.value)) || activeTpList.value[0];
});

// Search & Filter state for batch input
const batchSearchQuery = ref('');
const batchStatusFilter = ref('Semua'); // 'Semua' | 'BelumDinilai' | 'BelumTuntas' | 'Tuntas'

const filteredBatchRows = computed(() => {
  let list = batchRows.value;
  const kktp = currentBatchTp.value?.kktp || 75;
  if (batchStatusFilter.value === 'Tuntas') {
    list = list.filter(r => r.nilai !== '' && Number(r.nilai) >= kktp);
  } else if (batchStatusFilter.value === 'BelumTuntas') {
    list = list.filter(r => r.nilai !== '' && Number(r.nilai) < kktp);
  } else if (batchStatusFilter.value === 'BelumDinilai') {
    list = list.filter(r => r.nilai === '' || r.nilai === null || r.nilai === undefined);
  }
  if (batchSearchQuery.value.trim()) {
    const q = batchSearchQuery.value.toLowerCase();
    list = list.filter(r => (r.nama && r.nama.toLowerCase().includes(q)) || (r.nis && String(r.nis).includes(q)));
  }
  return list;
});

const batchTuntasCount = computed(() => {
  const kktp = currentBatchTp.value?.kktp || 75;
  return batchRows.value.filter(r => r.nilai !== '' && Number(r.nilai) >= kktp).length;
});

const batchBelumTuntasCount = computed(() => {
  const kktp = currentBatchTp.value?.kktp || 75;
  return batchRows.value.filter(r => r.nilai !== '' && Number(r.nilai) < kktp).length;
});

const batchUnsetCount = computed(() => {
  return batchRows.value.filter(r => r.nilai === '' || r.nilai === null || r.nilai === undefined).length;
});

const batchAvgScore = computed(() => {
  const filled = batchRows.value.filter(r => r.nilai !== '' && !isNaN(r.nilai));
  if (filled.length === 0) return 0;
  const sum = filled.reduce((acc, r) => acc + Number(r.nilai), 0);
  return Math.round((sum / filled.length) * 10) / 10;
});

function fillRemainingWithKktp() {
  const kktp = currentBatchTp.value?.kktp || 75;
  batchRows.value.forEach(r => {
    if (r.nilai === '' || r.nilai === null || r.nilai === undefined) {
      r.nilai = kktp;
    }
  });
  showToast(`Siswa yang belum dinilai otomatis diisi nilai KKTP (${kktp})!`);
}

function initBatchRows() {
  const currentTp = activeTpList.value.find(t => t.id === Number(batchInputTpId.value)) || activeTpList.value[0];
  batchRows.value = filteredSiswa.value.map(s => {
    const existing = currentTp ? getScore(s.id, currentTp.id) : null;
    return {
      siswaId: s.id,
      nama: s.nama,
      nis: s.nis,
      nilai: existing !== null ? existing : '',
      catatan: currentTp ? getCatatan(s.id, currentTp.id) : ''
    };
  });
}

function openBatchInputView() {
  if (activeTpList.value.length === 0) {
    showToast(`Silakan buat Tujuan Pembelajaran (TP) untuk Semester ${selectedSemester.value} terlebih dahulu!`, 'warning');
    return;
  }
  batchInputTpId.value = activeTpList.value[0].id;
  initBatchRows();
  viewMode.value = 'batch-input';
}

function editClassTpScores(tpId) {
  batchInputTpId.value = tpId;
  initBatchRows();
  viewMode.value = 'batch-input';
}

function handleSaveBatchNilai() {
  const currentTp = activeTpList.value.find(t => t.id === Number(batchInputTpId.value)) || activeTpList.value[0];
  emit('save-nilai-batch', {
    kelas: selectedKelasFilter.value,
    tpId: Number(batchInputTpId.value),
    scores: batchRows.value.map(r => ({
      siswaId: r.siswaId,
      nilai: r.nilai !== '' ? Number(r.nilai) : null,
      catatan: r.catatan || null
    })),
    tpKode: currentTp?.kode || ''
  });
  viewMode.value = 'matrix';
}

// SINGLE STUDENT SCORE EDIT MODAL STATE
const showSingleScoreModal = ref(false);
const singleScoreForm = ref({
  siswaId: null,
  namaSiswa: '',
  tpId: null,
  kodeTp: '',
  judulTp: '',
  kktp: 75,
  nilai: '',
  catatan: ''
});

function openSingleScoreModal(siswa, tp) {
  const currentScore = getScore(siswa.id, tp.id);
  singleScoreForm.value = {
    siswaId: siswa.id,
    namaSiswa: siswa.nama,
    tpId: tp.id,
    kodeTp: tp.kode,
    judulTp: tp.judul,
    kktp: tp.kktp || 75,
    nilai: currentScore !== null ? currentScore : '',
    catatan: getCatatan(siswa.id, tp.id)
  };
  showSingleScoreModal.value = true;
}

function handleSaveSingleScore() {
  emit('save-nilai-batch', {
    kelas: selectedKelasFilter.value,
    tpId: singleScoreForm.value.tpId,
    scores: [{
      siswaId: singleScoreForm.value.siswaId,
      nilai: singleScoreForm.value.nilai !== '' ? Number(singleScoreForm.value.nilai) : null,
      catatan: singleScoreForm.value.catatan || null
    }]
  });
  showSingleScoreModal.value = false;
}

// Tarik Nilai dari CBT Modal State
const showPullCbtModal = ref(false);
const isPullingCbt = ref(false);
const pullCbtForm = ref({
  jadwal_id: '',
  mode: 'existing_tp', // 'existing_tp' | 'new_tp'
  tp_id: '',
  kode: '',
  judul: '',
  mapel: '',
  jenis: 'Sumatif',
  kktp: 75,
  semester: 'Ganjil',
  tahun_ajaran_id: '',
  overwrite: true,
});

// Selected CBT schedule object
const selectedPullCbtJadwal = computed(() => {
  return props.cbtJadwalList.find(j => String(j.id) === String(pullCbtForm.value.jadwal_id));
});

// Eligible CBT exams matching current class filter and teacher's subject
const availableCbtJadwals = computed(() => {
  const currentKelas = (selectedKelasFilter.value || '').toLowerCase();
  const teacherMapels = (props.teacherSubject || '')
    .split(',')
    .map(s => s.trim().toLowerCase())
    .filter(Boolean);

  return props.cbtJadwalList.filter(j => {
    const classMatch = !currentKelas || (j.kelasNama || '').toLowerCase().includes(currentKelas);
    const mapelMatch = teacherMapels.length === 0 || teacherMapels.some(m => (j.mapelNama || j.mapel || '').toLowerCase().includes(m));
    return classMatch && mapelMatch;
  });
});

// CBT scores for the selected CBT schedule belonging to the current selected class
const cbtScoresForClass = computed(() => {
  if (!pullCbtForm.value.jadwal_id) return [];
  const studentIds = new Set(filteredSiswa.value.map(s => s.id));
  return props.cbtNilaiList.filter(n => 
    String(n.jadwalId) === String(pullCbtForm.value.jadwal_id) &&
    studentIds.has(n.siswaId)
  );
});

function openPullCbtModal() {
  const defaultJadwal = availableCbtJadwals.value[0];
  const activeTa = props.tahunAjaranList.find(t => t.isAktif) || props.tahunAjaranList[0];
  const targetTp = activeTpList.value[0];

  pullCbtForm.value = {
    jadwal_id: defaultJadwal?.id || '',
    mode: targetTp ? 'existing_tp' : 'new_tp',
    tp_id: targetTp?.id || '',
    kode: 'TP-CBT' + (props.tpList.length + 1),
    judul: defaultJadwal ? `Ujian CBT - ${defaultJadwal.judulUjian}` : 'Ujian CBT',
    mapel: defaultJadwal?.mapelNama || (selectedMapelFilter.value !== 'Semua' ? selectedMapelFilter.value : (props.teacherSubject || 'Informatika')),
    jenis: 'Sumatif',
    kktp: 75,
    semester: selectedSemester.value,
    tahun_ajaran_id: activeTa?.id || '',
    overwrite: true,
  };

  showPullCbtModal.value = true;
}

function onPullJadwalChange() {
  const currentJadwal = selectedPullCbtJadwal.value;
  if (currentJadwal) {
    pullCbtForm.value.mapel = currentJadwal.mapelNama || props.teacherSubject || 'Informatika';
    pullCbtForm.value.judul = `Ujian CBT - ${currentJadwal.judulUjian}`;
    const matched = activeTpList.value.find(t => (t.mapel || '').toLowerCase() === (currentJadwal.mapelNama || '').toLowerCase());
    if (matched) {
      pullCbtForm.value.tp_id = matched.id;
    }
  }
}

function submitPullCbt() {
  if (!pullCbtForm.value.jadwal_id) {
    showToast('Pilih jadwal ujian CBT terlebih dahulu', 'error');
    return;
  }
  if (pullCbtForm.value.mode === 'existing_tp' && !pullCbtForm.value.tp_id) {
    showToast('Pilih Tujuan Pembelajaran tujuan', 'error');
    return;
  }
  if (pullCbtForm.value.mode === 'new_tp' && !pullCbtForm.value.judul) {
    showToast('Nama Tujuan Pembelajaran wajib diisi', 'error');
    return;
  }

  isPullingCbt.value = true;
  router.post(route('guru.cbt.transfer-nilai-tp'), pullCbtForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isPullingCbt.value = false;
      showPullCbtModal.value = false;
      showToast('Nilai ujian CBT berhasil ditarik dan disimpan ke lembar TP!', 'success');
    },
    onError: (err) => {
      isPullingCbt.value = false;
      const firstErr = Object.values(err)[0] || 'Gagal menarik nilai CBT.';
      showToast(firstErr, 'error');
    },
    onFinish: () => {
      isPullingCbt.value = false;
    }
  });
}

// Export CSV Ledger Nilai (Format Universal dengan UTF-8 BOM)
function exportCsv() {
  let csvContent = "";
  csvContent += `"REKAPITULASI LEDGER NILAI TP SISWA KELAS ${selectedKelasFilter.value}"\n`;
  csvContent += `"Mata Pelajaran: ${props.teacherSubject || 'Informatika'}"\n`;
  csvContent += `"Periode: Semester ${selectedSemester.value} — T.A. ${selectedTahunAjaran.value}"\n`;
  csvContent += `"Metode Penilaian: Rata-Rata Seluruh Target TP (Opsi 1 / Standar Rapor)"\n\n`;

  let header = ["No", "NIS", "Nama Siswa"];
  activeTpList.value.forEach(tp => header.push(`"${tp.kode}: ${tp.judul}"`));
  header.push("Nilai Akhir", "Kelengkapan TP", "Predikat Capaian");

  csvContent += header.join(",") + "\n";

  filteredSiswa.value.forEach((s, idx) => {
    let cleanName = (s.nama || '').replace(/"/g, '""');
    let row = [idx + 1, `"${s.nis || '-'}"`, `"${cleanName}"`];
    activeTpList.value.forEach(tp => {
      const score = getScore(s.id, tp.id);
      row.push(score !== null ? score : '-');
    });
    const stats = getStudentStats(s.id);
    const pred = getPredikat(stats.finalScore, stats.isComplete);
    row.push(stats.finalScore, `"${stats.filledCount}/${stats.totalTps} TP"`, `"${pred.label}"`);
    csvContent += row.join(",") + "\n";
  });

  const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `ledger_nilai_${selectedKelasFilter.value}_sem_${selectedSemester.value}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Header Title & Action Buttons -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
            <span>Penilaian TP Siswa</span>
            <span class="text-cyan-400 text-base">📊</span>
          </h1>
          <span
            class="text-xs font-black px-3 py-1 rounded-full border transition-all"
            :class="selectedSemester === 'Genap' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm shadow-amber-500/20' : 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'"
          >
            Semester {{ selectedSemester }}
          </span>
        </div>
        <p class="text-gray-400 text-xs sm:text-sm font-medium">Evaluasi capaian Tujuan Pembelajaran (TP) Kurikulum Merdeka per kelas</p>
      </div>

      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        <!-- Mode Switch: Matriks / Input Massal -->
        <button
          v-if="viewMode === 'matrix'"
          @click="openBatchInputView"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-teal-600 to-indigo-600 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-105 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span>✏️</span>
          <span>Input Nilai per TP</span>
        </button>

        <button
          v-else
          @click="viewMode = 'matrix'"
          class="inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-cyan-300 border border-cyan-500/30 px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm active:scale-95 transition-all min-h-[44px]"
        >
          <span>📊</span>
          <span>Kembali ke Matriks Ledger</span>
        </button>

        <!-- Tarik Nilai dari CBT Trigger -->
        <button
          @click="openPullCbtModal"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-black px-4 py-2.5 rounded-2xl text-xs sm:text-sm shadow-lg shadow-amber-500/25 active:scale-95 transition-all min-h-[44px]"
        >
          <span>⚡</span>
          <span>Tarik Nilai dari CBT</span>
        </button>



        <!-- Export Excel (.csv) -->
        <button
          @click="exportCsv"
          class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-emerald-600/25 active:scale-95 transition-all min-h-[44px]"
        >
          <span>📊</span>
          <span>Export Excel</span>
        </button>
      </div>
    </div>

    <!-- Compact Metrics Bar (1 Baris Ringkas & Rapi) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="bg-slate-900/90 border border-white/10 rounded-2xl px-4 py-3 flex items-center gap-3 shadow-md">
        <span class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-300 flex items-center justify-center text-sm border border-cyan-500/30 shrink-0">📊</span>
        <div class="min-w-0">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Rata-Rata Kelas</p>
          <p class="text-lg font-black text-white truncate">{{ classAverage }}</p>
        </div>
      </div>

      <div class="bg-slate-900/90 border border-white/10 rounded-2xl px-4 py-3 flex items-center gap-3 shadow-md">
        <span class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center text-sm border border-emerald-500/30 shrink-0">🏆</span>
        <div class="min-w-0">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nilai Tertinggi</p>
          <p class="text-sm font-black text-white truncate" :title="topStudent">{{ topStudent }}</p>
        </div>
      </div>

      <div class="bg-slate-900/90 border border-white/10 rounded-2xl px-4 py-3 flex items-center gap-3 shadow-md">
        <span class="w-8 h-8 rounded-xl bg-purple-500/20 text-purple-300 flex items-center justify-center text-sm border border-purple-500/30 shrink-0">🎯</span>
        <div class="min-w-0">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Target TP</p>
          <p class="text-lg font-black text-white truncate">{{ activeTpList.length }} <span class="text-xs font-normal text-gray-400">Sasaran</span></p>
        </div>
      </div>

      <div class="bg-slate-900/90 border border-white/10 rounded-2xl px-4 py-3 flex items-center gap-3 shadow-md">
        <span class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-300 flex items-center justify-center text-sm border border-amber-500/30 shrink-0">⚠️</span>
        <div class="min-w-0">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Remedial / Belum Lengkap</p>
          <p class="text-lg font-black text-amber-300 truncate">{{ totalRemidialCount }} <span class="text-xs font-normal text-gray-400">Siswa</span></p>
        </div>
      </div>
    </div>

    <!-- Filter Controls Bar (Ringkas & Bebas Ramai) -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-2xl p-4 border border-white/10 shadow-lg">
      <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Grup Kiri: Pilih Tingkat & Kelas -->
        <div class="flex items-center gap-2 flex-1 flex-wrap sm:flex-nowrap">
          <div class="w-28 shrink-0">
            <select
              v-model="selectedTingkatFilter"
              @change="onTingkatFilterChange(); initBatchRows();"
              class="w-full bg-slate-800 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white font-bold outline-none min-h-[42px]"
              title="Pilih Tingkat Kelas"
            >
              <option value="X">Kelas X</option>
              <option value="XI">Kelas XI</option>
              <option value="XII">Kelas XII</option>
            </select>
          </div>

          <div class="flex-1 min-w-[150px]">
            <select
              v-model="selectedKelasFilter"
              @change="initBatchRows"
              class="w-full bg-slate-800 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white font-bold outline-none min-h-[42px]"
            >
              <option v-for="k in filteredKelasByTingkat" :key="k.id" :value="k.namaKelas">
                {{ k.namaKelas }} ({{ k.jurusan }})
              </option>
            </select>
          </div>

          <!-- Dropdown Mapel jika mengajar > 1 mapel -->
          <div v-if="teacherAssignedMapels.length > 1" class="w-44 shrink-0">
            <select
              v-model="selectedMapelFilter"
              @change="initBatchRows"
              class="w-full bg-slate-800 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white font-bold outline-none min-h-[42px]"
            >
              <option value="Semua">Semua Mapel</option>
              <option v-for="name in teacherAssignedMapels" :key="name" :value="name">{{ name }}</option>
            </select>
          </div>
        </div>

        <!-- Grup Kanan: Semester Switcher & Cari Siswa -->
        <div class="flex items-center gap-2 flex-1 md:justify-end">
          <!-- Semester Toggle Pills with Active Glow -->
          <div class="inline-flex items-center rounded-xl bg-slate-950 p-1 border border-white/10 shrink-0">
            <button
              type="button"
              @click="setSemester('Ganjil')"
              class="px-3 py-1.5 rounded-lg text-xs font-black transition-all min-h-[34px] flex items-center gap-1.5"
              :class="selectedSemester === 'Ganjil' ? 'bg-gradient-to-r from-cyan-500 to-blue-500 text-slate-950 shadow-md shadow-cyan-500/20' : 'text-gray-400 hover:text-white'"
            >
              <span v-if="selectedSemester === 'Ganjil'" class="w-1.5 h-1.5 rounded-full bg-slate-950"></span>
              <span>Ganjil</span>
            </button>
            <button
              type="button"
              @click="setSemester('Genap')"
              class="px-3 py-1.5 rounded-lg text-xs font-black transition-all min-h-[34px] flex items-center gap-1.5"
              :class="selectedSemester === 'Genap' ? 'bg-gradient-to-r from-amber-400 to-orange-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-gray-400 hover:text-white'"
            >
              <span v-if="selectedSemester === 'Genap'" class="w-1.5 h-1.5 rounded-full bg-slate-950"></span>
              <span>Genap</span>
            </button>
          </div>

          <!-- Cari Nama Siswa -->
          <div class="relative w-full sm:w-56">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari nama siswa..."
              class="w-full pl-8 pr-3 py-2 bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl text-xs text-white placeholder-gray-500 outline-none min-h-[42px]"
            />
            <span class="absolute left-2.5 top-3 text-xs text-gray-400">🔍</span>
          </div>
        </div>
      </div>
    </div>

    <!-- MODE 1: MATRIKS LEDGER NILAI TP SISWA -->
    <div v-if="viewMode === 'matrix'" class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      
      <div class="p-4 sm:p-5 border-b border-white/10 bg-slate-950/60 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-sm font-black text-white">📊 Matriks Ledger Penilaian — Kelas {{ selectedKelasFilter }}</span>
        </div>

        <a
          href="/guru/dashboard?tab=tp"
          class="text-xs font-extrabold text-cyan-300 hover:text-white bg-cyan-500/15 hover:bg-cyan-500/30 px-3.5 py-1.5 rounded-xl border border-cyan-500/30 transition-all flex items-center gap-1.5 min-h-[36px]"
          title="Buka Menu Khusus Kelola Tujuan Pembelajaran"
        >
          <span>🎯</span>
          <span>Master TP ({{ activeTpList.length }}) ↗</span>
        </a>
      </div>

      <!-- Desktop Table Matriks Ledger Nilai -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="px-4 py-3.5 text-center w-12 border-r border-white/5">No</th>
              <th class="px-6 py-3.5 w-56 border-r border-white/5 sticky left-0 z-20 bg-slate-950">Nama Siswa</th>
              
              <th
                v-for="tp in activeTpList"
                :key="tp.id"
                class="px-3 py-3.5 text-center w-28 border-r border-white/5"
                :title="`${tp.kode}: ${tp.judul} (KKTP: ${tp.kktp || 75})`"
              >
                <div class="text-cyan-300 font-black text-xs cursor-help">
                  {{ tp.kode }}
                </div>
                <div class="text-[10px] text-gray-500 font-mono mt-0.5">
                  KKTP {{ tp.kktp || 75 }}
                </div>
              </th>

              <th class="px-6 py-3.5 text-center w-36 border-r border-white/5">
                <div>Nilai Akhir</div>
                <div class="text-[9px] text-cyan-400 font-semibold normal-case">Target {{ activeTpList.length }} TP</div>
              </th>
              <th class="px-6 py-3.5 text-center w-44">
                <div>Predikat Capaian</div>
                <div class="text-[9px] text-gray-400 font-semibold normal-case">Standar KKTP &ge; 75</div>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr v-for="(siswa, index) in filteredSiswa" :key="siswa.id" class="group hover:bg-white/5 transition-colors">
              <td class="px-4 py-4 text-center font-mono text-gray-400 text-xs border-r border-white/5">
                {{ index + 1 }}
              </td>
              <td class="px-6 py-4 font-black text-white text-xs border-r border-white/5 sticky left-0 z-10 bg-slate-900 group-hover:bg-slate-800 transition-colors shadow-sm">
                {{ siswa.nama }}
              </td>

              <!-- Dynamic Score Cells -->
              <td
                v-for="tp in activeTpList"
                :key="tp.id"
                class="px-4 py-4 text-center border-r border-white/5"
              >
                <button
                  @click="openSingleScoreModal(siswa, tp)"
                  class="group relative inline-flex items-center gap-1 font-black text-xs text-white bg-slate-800 hover:bg-cyan-500/20 hover:text-cyan-300 px-3.5 py-1.5 rounded-xl border border-white/10 hover:border-cyan-500/40 active:scale-95 transition-all shadow-sm"
                  title="Klik untuk edit nilai siswa ini"
                >
                  <span>{{ getScore(siswa.id, tp.id) !== null ? getScore(siswa.id, tp.id) : '-' }}</span>
                  <span class="text-[10px] text-cyan-400 opacity-0 group-hover:opacity-100 transition-opacity">✏️</span>
                </button>
              </td>

              <!-- Nilai Akhir (Opsi 1: Pembagi Total Seluruh Target TP) -->
              <td class="px-6 py-4 text-center border-r border-white/5">
                <span
                  class="font-black text-base"
                  :class="getStudentStats(siswa.id).finalScore >= 75 ? 'text-cyan-300' : 'text-rose-400'"
                >
                  {{ getStudentStats(siswa.id).finalScore }}
                </span>
              </td>

              <!-- Predikat Capaian -->
              <td class="px-6 py-4 text-center">
                <span :class="['text-xs font-extrabold px-3.5 py-1 rounded-full border shadow-sm inline-block', getPredikat(getStudentStats(siswa.id).finalScore, getStudentStats(siswa.id).isComplete).color]">
                  {{ getPredikat(getStudentStats(siswa.id).finalScore, getStudentStats(siswa.id).isComplete).label }}
                </span>
              </td>
            </tr>

            <tr v-if="filteredSiswa.length === 0">
              <td colspan="10" class="px-6 py-12 text-center text-gray-400 text-xs font-medium">
                Belum ada siswa pada kelas {{ selectedKelasFilter }}.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Smartphone Card List View -->
      <div class="block md:hidden p-4 space-y-4">
        <div v-for="(siswa, index) in filteredSiswa" :key="siswa.id" class="bg-slate-950/40 rounded-2xl p-4 border border-white/10 space-y-3 shadow-md">
          <div class="flex items-center justify-between">
            <span class="font-black text-xs text-white">{{ index + 1 }}. {{ siswa.nama }}</span>
            <span class="font-mono text-xs text-gray-400">{{ siswa.nis }}</span>
          </div>

          <div class="grid grid-cols-2 gap-2 pt-2 border-t border-white/5">
            <div
              v-for="tp in activeTpList"
              :key="tp.id"
              @click="openSingleScoreModal(siswa, tp)"
              class="bg-slate-900/80 p-3 rounded-2xl border border-white/10 flex items-center justify-between cursor-pointer hover:border-cyan-500/40 active:scale-95 transition-all"
            >
              <span class="text-xs font-extrabold text-cyan-300">{{ tp.kode }}:</span>
              <span class="text-xs font-black text-white flex items-center gap-1">
                {{ getScore(siswa.id, tp.id) !== null ? getScore(siswa.id, tp.id) : '-' }}
                <span class="text-[10px] text-cyan-400">✏️</span>
              </span>
            </div>
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-white/10 flex-wrap gap-2">
            <div class="text-xs font-extrabold text-white flex items-center gap-1.5">
              <span>Nilai Akhir:</span>
              <span
                class="font-black text-sm"
                :class="getStudentStats(siswa.id).finalScore >= 75 ? 'text-cyan-300' : 'text-rose-400'"
              >
                {{ getStudentStats(siswa.id).finalScore }}
              </span>
            </div>
            <span :class="['text-[10px] font-black px-2.5 py-0.5 rounded-full border', getPredikat(getStudentStats(siswa.id).finalScore, getStudentStats(siswa.id).isComplete).color]">
              {{ getPredikat(getStudentStats(siswa.id).finalScore, getStudentStats(siswa.id).isComplete).label }}
            </span>
          </div>
        </div>
      </div>

    </div>

    <!-- MODE 2: FORM INPUT NILAI MASSAL PER TP (RESTRUCTURED & MOBILE OPTIMIZED) -->
    <div v-else class="space-y-6 animate-in fade-in duration-200">
      
      <!-- Top Control & Context Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-950/80 via-slate-900 to-indigo-950/70 border border-cyan-500/25 p-5 sm:p-6 shadow-2xl backdrop-blur-xl">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-2 flex-wrap">
              <button
                type="button"
                @click="viewMode = 'matrix'"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition-all active:scale-95 border border-white/15 min-h-[36px]"
              >
                <span>◀</span>
                <span>Kembali ke Matriks</span>
              </button>
              <span class="px-3 py-1 rounded-xl bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-xs font-bold">
                🏫 Kelas: {{ selectedKelasFilter }}
              </span>
              <span class="px-3 py-1 rounded-xl bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 text-xs font-bold">
                📅 Semester {{ selectedSemester }} ({{ selectedTahunAjaran }})
              </span>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">
              Input Nilai Capaian Siswa per TP
            </h2>
            <p class="text-xs text-slate-300 mt-1 max-w-2xl leading-relaxed">
              Ketikkan perolehan nilai siswa (skala 0 - 100). Status ketuntasan dihitung secara otomatis berdasarkan standar KKTP yang ditetapkan.
            </p>
          </div>

          <!-- Target TP Selection Box -->
          <div class="p-3.5 rounded-2xl bg-slate-950/80 border border-cyan-500/30 shadow-lg min-w-[280px] sm:min-w-[340px]">
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-[11px] font-black text-cyan-300 uppercase tracking-wider flex items-center gap-1">
                <span>🎯</span> Sasaran TP Aktif
              </label>
              <a
                href="/guru/dashboard?tab=tp"
                target="_blank"
                class="text-[10px] text-cyan-400 hover:text-cyan-300 font-bold flex items-center gap-0.5 hover:underline"
              >
                <span>Master TP ↗</span>
              </a>
            </div>
            <select
              v-model="batchInputTpId"
              @change="initBatchRows"
              class="w-full bg-slate-900 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white font-bold cursor-pointer min-h-[44px]"
            >
              <option v-for="tp in activeTpList" :key="tp.id" :value="tp.id">
                {{ tp.kode }} — {{ tp.judul }}
              </option>
            </select>
            <div class="flex items-center justify-between mt-2 pt-2 border-t border-white/10 text-[11px]">
              <span class="text-slate-400">KKTP Minimal: <strong class="text-emerald-400 font-black">{{ currentBatchTp?.kktp || 75 }}</strong></span>
              <span class="text-slate-400">Jenis: <strong class="text-cyan-300">{{ currentBatchTp?.jenis || 'Formatif' }}</strong></span>
            </div>
          </div>
        </div>

        <!-- Live KPI Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 pt-5 border-t border-white/10">
          <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Rata-rata Nilai</p>
            <p class="text-xl font-black text-white mt-0.5">{{ batchAvgScore }}</p>
          </div>
          <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
            <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider">Tuntas (&ge; {{ currentBatchTp?.kktp || 75 }})</p>
            <p class="text-xl font-black text-emerald-300 mt-0.5">{{ batchTuntasCount }} <span class="text-xs font-normal text-slate-400">Siswa</span></p>
          </div>
          <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
            <p class="text-[10px] font-bold text-rose-400 uppercase tracking-wider">Remedial</p>
            <p class="text-xl font-black text-rose-300 mt-0.5">{{ batchBelumTuntasCount }} <span class="text-xs font-normal text-slate-400">Siswa</span></p>
          </div>
          <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
            <p class="text-[10px] font-bold text-amber-400 uppercase tracking-wider">Belum Dinilai</p>
            <p class="text-xl font-black text-amber-300 mt-0.5">{{ batchUnsetCount }} <span class="text-xs font-normal text-slate-400">Siswa</span></p>
          </div>
        </div>
      </div>

      <!-- Quick Action & Filter Bar -->
      <div class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 shadow-lg flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px]">
          <input
            v-model="batchSearchQuery"
            type="text"
            placeholder="Cari siswa atau NIS di kelas ini..."
            class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-slate-950 border border-white/10 focus:border-cyan-400 text-xs text-white placeholder-slate-500 min-h-[44px]"
          />
          <span class="absolute left-3 top-3.5 text-slate-400">🔍</span>
        </div>

        <!-- Filter Status & Bulk Auto-Fill -->
        <div class="flex items-center gap-2 flex-wrap">
          <div class="inline-flex rounded-xl bg-slate-950 p-1 border border-white/10">
            <button
              v-for="st in ['Semua', 'BelumDinilai', 'BelumTuntas', 'Tuntas']"
              :key="st"
              type="button"
              @click="batchStatusFilter = st"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition min-h-[36px]"
              :class="batchStatusFilter === st ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
            >
              {{ st === 'BelumDinilai' ? 'Belum Diisi' : st === 'BelumTuntas' ? 'Remedial' : st }}
            </button>
          </div>

          <button
            type="button"
            @click="fillRemainingWithKktp"
            class="px-3.5 py-2.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 text-xs font-bold transition flex items-center gap-1.5 min-h-[44px]"
            title="Otomatis isi siswa yang nilainya masih kosong dengan nilai KKTP"
          >
            <span>⚡</span>
            <span>Isi Kosong = KKTP</span>
          </button>
        </div>
      </div>

      <!-- DESKTOP TABLE VIEW (hidden md:block) -->
      <div class="hidden md:block bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-gray-300">
            <thead class="bg-slate-950/80 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
              <tr>
                <th class="px-4 py-3.5 text-center w-14">No</th>
                <th class="px-5 py-3.5 w-32">NIS</th>
                <th class="px-5 py-3.5">Nama Siswa</th>
                <th class="px-5 py-3.5 w-48 text-center">Nilai (0 - 100)</th>
                <th class="px-5 py-3.5 w-36 text-center">Status</th>
                <th class="px-5 py-3.5">Catatan / Umpan Balik Guru</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 font-medium">
              <tr
                v-for="(row, idx) in filteredBatchRows"
                :key="row.siswaId"
                class="hover:bg-white/[0.03] transition-colors"
              >
                <td class="px-4 py-3.5 text-center font-mono text-gray-400">{{ idx + 1 }}</td>
                <td class="px-5 py-3.5 font-mono font-bold text-gray-300">{{ row.nis || '-' }}</td>
                <td class="px-5 py-3.5 font-bold text-white">{{ row.nama }}</td>
                <td class="px-5 py-3.5">
                  <div class="flex items-center justify-center gap-2">
                    <input
                      v-model="row.nilai"
                      type="number"
                      min="0"
                      max="100"
                      placeholder="0-100"
                      class="w-24 bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3 py-2 text-center text-white font-black text-sm outline-none min-h-[44px]"
                    />
                    <button
                      type="button"
                      @click="row.nilai = currentBatchTp?.kktp || 75"
                      class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-[10px] font-bold text-cyan-300 border border-white/10"
                      title="Set ke KKTP"
                    >
                      KKTP
                    </button>
                  </div>
                </td>
                <td class="px-5 py-3.5 text-center">
                  <span
                    :class="['text-xs font-extrabold px-3 py-1 rounded-full border inline-flex items-center gap-1', getStatusNilai(row.nilai, currentBatchTp?.kktp || 75).color]"
                  >
                    <span>{{ row.nilai === '' || row.nilai === null ? '⏳' : Number(row.nilai) >= (currentBatchTp?.kktp || 75) ? '✅' : '⚠️' }}</span>
                    <span>{{ getStatusNilai(row.nilai, currentBatchTp?.kktp || 75).label }}</span>
                  </span>
                </td>
                <td class="px-5 py-3.5">
                  <input
                    v-model="row.catatan"
                    type="text"
                    placeholder="Catatan perkembangan belajar siswa..."
                    class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-600 outline-none min-h-[44px]"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MOBILE CARD LIST VIEW (md:hidden) -->
      <div class="md:hidden space-y-3">
        <div
          v-for="(row, idx) in filteredBatchRows"
          :key="row.siswaId"
          class="p-4 rounded-2xl bg-slate-950/70 border border-white/10 shadow-lg space-y-3"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 rounded-full bg-white/5 border border-white/10 text-[11px] font-mono text-slate-400 flex items-center justify-center">
                {{ idx + 1 }}
              </span>
              <div>
                <h4 class="text-sm font-bold text-white">{{ row.nama }}</h4>
                <p class="text-[11px] font-mono text-slate-400">NIS: {{ row.nis || '-' }}</p>
              </div>
            </div>
            <span
              :class="['text-[11px] font-extrabold px-2.5 py-0.5 rounded-full border', getStatusNilai(row.nilai, currentBatchTp?.kktp || 75).color]"
            >
              {{ getStatusNilai(row.nilai, currentBatchTp?.kktp || 75).label }}
            </span>
          </div>

          <!-- Input Nilai & Quick Preset -->
          <div class="grid grid-cols-2 gap-2 pt-1">
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nilai (0 - 100)</label>
              <input
                v-model="row.nilai"
                type="number"
                min="0"
                max="100"
                placeholder="0"
                class="w-full bg-slate-900 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-center text-white font-black text-base min-h-[44px]"
              />
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Preset Cepat</label>
              <div class="grid grid-cols-3 gap-1">
                <button
                  type="button"
                  @click="row.nilai = currentBatchTp?.kktp || 75"
                  class="py-2.5 rounded-lg bg-white/5 hover:bg-white/10 text-[11px] font-bold text-cyan-300 border border-white/10 min-h-[44px]"
                >
                  KKTP
                </button>
                <button
                  type="button"
                  @click="row.nilai = 85"
                  class="py-2.5 rounded-lg bg-white/5 hover:bg-white/10 text-[11px] font-bold text-white border border-white/10 min-h-[44px]"
                >
                  85
                </button>
                <button
                  type="button"
                  @click="row.nilai = 100"
                  class="py-2.5 rounded-lg bg-white/5 hover:bg-white/10 text-[11px] font-bold text-emerald-300 border border-white/10 min-h-[44px]"
                >
                  100
                </button>
              </div>
            </div>
          </div>

          <!-- Catatan -->
          <div>
            <input
              v-model="row.catatan"
              type="text"
              placeholder="Catatan guru untuk siswa ini..."
              class="w-full bg-slate-900 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-600 min-h-[40px]"
            />
          </div>
        </div>
      </div>

      <!-- Bottom Sticky Action Bar -->
      <div class="sticky bottom-0 z-30 p-4 rounded-3xl bg-slate-950/95 border border-white/15 shadow-2xl backdrop-blur-xl flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <span class="text-xs text-slate-300 font-bold">
            Progress: <strong class="text-white">{{ batchRows.length - batchUnsetCount }}</strong> dari {{ batchRows.length }} Siswa Dinilai
          </span>
          <div class="w-32 h-2 rounded-full bg-slate-800 overflow-hidden hidden sm:block">
            <div
              class="h-full bg-gradient-to-r from-cyan-400 to-emerald-400 transition-all duration-300"
              :style="{ width: `${batchRows.length > 0 ? ((batchRows.length - batchUnsetCount) / batchRows.length) * 100 : 0}%` }"
            ></div>
          </div>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
          <button
            type="button"
            @click="viewMode = 'matrix'"
            class="px-5 py-2.5 rounded-xl border border-white/10 text-slate-400 text-xs font-bold hover:bg-white/5 active:scale-95 transition-all min-h-[44px]"
          >
            Batal
          </button>
          <button
            type="button"
            @click="handleSaveBatchNilai"
            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 via-teal-500 to-emerald-500 text-slate-950 text-xs font-black shadow-lg shadow-cyan-500/25 hover:from-cyan-400 hover:to-emerald-400 active:scale-95 transition-all flex items-center gap-2 min-h-[44px]"
          >
            <span>💾</span>
            <span>Simpan Nilai Kelas</span>
          </button>
        </div>
      </div>

    </div>

    <!-- POPUP MODAL: EDIT NILAI INDIVIDUAL SISWA -->
    <div v-if="showSingleScoreModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-white/10 rounded-3xl p-6 max-w-sm w-full shadow-2xl space-y-4 transform transition-all animate-in fade-in zoom-in-95">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="text-base font-black text-white">✏️ Edit Nilai Siswa</h3>
          <button @click="showSingleScoreModal = false" class="text-gray-400 hover:text-white font-bold">✕</button>
        </div>

        <div class="space-y-3 text-xs sm:text-sm">
          <div class="bg-white/5 p-4 rounded-2xl border border-white/10 space-y-1">
            <div class="text-xs text-gray-400 font-medium">Nama Siswa:</div>
            <div class="text-base font-black text-white">{{ singleScoreForm.namaSiswa }}</div>
            <div class="text-xs text-cyan-300 font-bold font-mono pt-1">{{ singleScoreForm.kodeTp }}: {{ singleScoreForm.judulTp }}</div>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nilai TP (0 - 100)</label>
            <input
              v-model="singleScoreForm.nilai"
              type="number"
              min="0"
              max="100"
              placeholder="0-100"
              class="w-full bg-slate-800 border border-cyan-500/40 rounded-2xl px-4 py-3 text-white font-black text-xl text-center focus:border-cyan-400 outline-none min-h-[52px]"
              autofocus
            />
          </div>

          <div class="text-center pt-1">
            <span :class="['text-xs font-extrabold px-4 py-1 rounded-full border', getStatusNilai(singleScoreForm.nilai, singleScoreForm.kktp).color]">
              {{ getStatusNilai(singleScoreForm.nilai, singleScoreForm.kktp).label }}
            </span>
            <span class="ml-2 text-[11px] text-gray-400 font-medium">KKTP Min: {{ singleScoreForm.kktp || 75 }}</span>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Catatan / Notif Guru</label>
            <textarea
              v-model="singleScoreForm.catatan"
              rows="2"
              placeholder="cth: Sudah tuntas, tapi perlu penguatan konsep array..."
              class="w-full bg-slate-800 border border-cyan-500/40 rounded-2xl px-4 py-3 text-white text-sm focus:border-cyan-400 outline-none resize-none"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button type="button" @click="showSingleScoreModal = false" class="px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl text-xs font-bold">Batal</button>
            <button type="button" @click="handleSaveSingleScore" class="px-5 py-2.5 bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 rounded-2xl text-xs font-black shadow-lg hover:shadow-cyan-500/25 active:scale-95 transition-all">Simpan Nilai</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL TARIK NILAI DARI CBT -->
    <div
      v-if="showPullCbtModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto"
    >
      <div class="w-full max-w-2xl my-8 rounded-3xl bg-slate-900 border border-white/10 p-5 sm:p-7 space-y-5 shadow-2xl relative">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-300 flex items-center justify-center text-lg border border-amber-500/30">
              ⚡
            </span>
            <div>
              <h3 class="text-base sm:text-lg font-black text-white">Tarik Nilai Ujian CBT ke Lembar TP</h3>
              <p class="text-xs text-gray-400">Kelas: <strong class="text-white">{{ selectedKelasFilter }}</strong> &bull; Semester {{ selectedSemester }}</p>
            </div>
          </div>
          <button
            @click="showPullCbtModal = false"
            class="p-2 rounded-xl text-gray-400 hover:text-white hover:bg-white/10 transition-colors min-h-[38px] min-w-[38px] flex items-center justify-center"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="submitPullCbt" class="space-y-5">
          <!-- Step 1: Pilih Ujian CBT Sumber -->
          <div class="space-y-2">
            <label class="block text-xs font-black text-amber-400 uppercase tracking-wider">
              1. Pilih Ujian CBT Sumber
            </label>
            <select
              v-model="pullCbtForm.jadwal_id"
              @change="onPullJadwalChange"
              required
              class="w-full py-2.5 px-3.5 rounded-xl bg-slate-800 border border-white/15 text-white text-xs sm:text-sm font-semibold focus:outline-none focus:border-amber-400 min-h-[42px]"
            >
              <option value="" disabled>-- Pilih Ujian CBT --</option>
              <option v-for="j in availableCbtJadwals" :key="j.id" :value="j.id">
                {{ j.judulUjian }} &bull; {{ j.kelasNama }} ({{ j.mapelNama }})
              </option>
            </select>

            <!-- Card Ringkasan Ujian Terpilih -->
            <div
              v-if="selectedPullCbtJadwal"
              class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3 rounded-2xl bg-white/[0.04] border border-white/10 text-xs mt-2"
            >
              <div>
                <span class="text-[10px] text-gray-400 uppercase block font-bold">Kelas CBT</span>
                <span class="font-extrabold text-white text-xs sm:text-sm truncate block">{{ selectedPullCbtJadwal.kelasNama }}</span>
              </div>
              <div>
                <span class="text-[10px] text-gray-400 uppercase block font-bold">Mata Pelajaran</span>
                <span class="font-extrabold text-teal-300 text-xs sm:text-sm truncate block">{{ selectedPullCbtJadwal.mapelNama }}</span>
              </div>
              <div>
                <span class="text-[10px] text-gray-400 uppercase block font-bold">Siswa Siap Ditarik</span>
                <span class="font-extrabold text-emerald-300 text-xs sm:text-sm block">{{ cbtScoresForClass.length }} Siswa</span>
              </div>
              <div>
                <span class="text-[10px] text-gray-400 uppercase block font-bold">Rata-Rata CBT</span>
                <span class="font-extrabold text-cyan-300 text-xs sm:text-sm block">{{ selectedPullCbtJadwal.rataRataNilai || 0 }} / 100</span>
              </div>
            </div>
          </div>

          <!-- Step 2: Tujuan Pembelajaran Target -->
          <div class="space-y-3 pt-2 border-t border-white/10">
            <label class="block text-xs font-black text-amber-400 uppercase tracking-wider">
              2. Tujuan Pembelajaran (TP) Penerima Nilai
            </label>

            <!-- Mode Switch: Existing TP vs New TP -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <label
                :class="[
                  'p-3.5 rounded-2xl border cursor-pointer transition-all flex items-start gap-3',
                  pullCbtForm.mode === 'existing_tp'
                    ? 'bg-amber-500/15 border-amber-500/50 shadow-lg shadow-amber-500/10'
                    : 'bg-white/5 border-white/10 hover:bg-white/[0.08]'
                ]"
              >
                <input
                  type="radio"
                  v-model="pullCbtForm.mode"
                  value="existing_tp"
                  class="mt-1 text-amber-500 focus:ring-amber-400"
                />
                <div>
                  <span class="text-xs sm:text-sm font-extrabold text-white block">Pilih TP yang Ada</span>
                  <span class="text-[11px] text-gray-400 block mt-0.5">Masukkan ke indikator TP yang sudah terdaftar di kelas ini</span>
                </div>
              </label>

              <label
                :class="[
                  'p-3.5 rounded-2xl border cursor-pointer transition-all flex items-start gap-3',
                  pullCbtForm.mode === 'new_tp'
                    ? 'bg-amber-500/15 border-amber-500/50 shadow-lg shadow-amber-500/10'
                    : 'bg-white/5 border-white/10 hover:bg-white/[0.08]'
                ]"
              >
                <input
                  type="radio"
                  v-model="pullCbtForm.mode"
                  value="new_tp"
                  class="mt-1 text-amber-500 focus:ring-amber-400"
                />
                <div>
                  <span class="text-xs sm:text-sm font-extrabold text-white block">Buat TP Baru Otomatis</span>
                  <span class="text-[11px] text-gray-400 block mt-0.5">Generate TP baru langsung dari judul ujian CBT ini</span>
                </div>
              </label>
            </div>

            <!-- Opsi A: Dropdown TP Yang Ada -->
            <div v-if="pullCbtForm.mode === 'existing_tp'" class="space-y-1.5 mt-2">
              <label class="block text-xs font-bold text-gray-300">Pilih Indikator TP</label>
              <select
                v-model="pullCbtForm.tp_id"
                required
                class="w-full py-2.5 px-3.5 rounded-xl bg-slate-800 border border-white/15 text-white text-xs sm:text-sm focus:outline-none focus:border-amber-400 min-h-[42px]"
              >
                <option value="" disabled>-- Pilih Tujuan Pembelajaran --</option>
                <option v-for="tp in activeTpList" :key="tp.id" :value="tp.id">
                  [{{ tp.kode }}] {{ tp.judul }} &bull; (Mapel: {{ tp.mapel || 'Umum' }}, KKTP: {{ tp.kktp }})
                </option>
              </select>
            </div>

            <!-- Opsi B: Buat TP Baru -->
            <div v-else class="space-y-3 p-4 rounded-2xl bg-white/5 border border-white/10 mt-2">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-bold text-gray-300 mb-1">Kode TP</label>
                  <input
                    v-model="pullCbtForm.kode"
                    type="text"
                    required
                    placeholder="misal: TP-CBT01"
                    class="w-full px-3.5 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs sm:text-sm min-h-[40px]"
                  />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-300 mb-1">Mata Pelajaran</label>
                  <input
                    v-model="pullCbtForm.mapel"
                    type="text"
                    required
                    class="w-full px-3.5 py-2 rounded-xl bg-slate-800 border border-white/10 text-teal-300 font-bold text-xs sm:text-sm min-h-[40px]"
                  />
                </div>
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-300 mb-1">Judul / Capaian TP</label>
                <input
                  v-model="pullCbtForm.judul"
                  type="text"
                  required
                  class="w-full px-3.5 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs sm:text-sm min-h-[40px]"
                />
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-bold text-gray-300 mb-1">Jenis Asesmen</label>
                  <select
                    v-model="pullCbtForm.jenis"
                    class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs min-h-[40px]"
                  >
                    <option value="Sumatif">Sumatif</option>
                    <option value="Formatif">Formatif</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-300 mb-1">KKTP</label>
                  <input
                    v-model.number="pullCbtForm.kktp"
                    type="number"
                    min="0"
                    max="100"
                    required
                    class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs min-h-[40px]"
                  />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-300 mb-1">Semester</label>
                  <select
                    v-model="pullCbtForm.semester"
                    class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs min-h-[40px]"
                  >
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: Opsi & Preview Siswa Kelas -->
          <div class="space-y-3 pt-2 border-t border-white/10">
            <label class="flex items-center gap-2.5 cursor-pointer">
              <input
                type="checkbox"
                v-model="pullCbtForm.overwrite"
                class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400 bg-slate-800 border-white/20"
              />
              <span class="text-xs sm:text-sm text-gray-300 font-semibold">
                Perbarui / timpa nilai jika siswa sudah memiliki nilai pada TP ini
              </span>
            </label>

            <!-- Preview Data Siswa -->
            <div class="space-y-1.5">
              <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-gray-400 uppercase text-[10px]">
                  Siswa Kelas {{ selectedKelasFilter }} yang Memiliki Skor CBT ({{ cbtScoresForClass.length }} Siswa)
                </span>
                <span class="text-amber-400 text-[11px] font-bold">Siap Ditarik</span>
              </div>
              <div class="max-h-40 overflow-y-auto rounded-xl bg-slate-950/60 border border-white/10 divide-y divide-white/5 p-1">
                <div
                  v-for="item in cbtScoresForClass"
                  :key="item.id"
                  class="flex items-center justify-between px-3 py-2 text-xs"
                >
                  <div>
                    <span class="font-bold text-white block">{{ item.namaSiswa }}</span>
                    <span class="text-[10px] text-gray-400 font-mono">NIS: {{ item.nis }}</span>
                  </div>
                  <div class="text-right">
                    <span
                      :class="[
                        'font-black px-2 py-0.5 rounded text-xs',
                        item.nilai >= 75 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'
                      ]"
                    >
                      Skor: {{ item.nilai }}
                    </span>
                  </div>
                </div>
                <div v-if="cbtScoresForClass.length === 0" class="text-center py-4 text-xs text-gray-500">
                  Tidak ada peserta ujian pada jadwal ini yang cocok dengan kelas {{ selectedKelasFilter }}.
                </div>
              </div>
            </div>
          </div>

          <!-- Tombol Aksi -->
          <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
            <button
              type="button"
              @click="showPullCbtModal = false"
              :disabled="isPullingCbt"
              class="px-5 py-2.5 rounded-xl bg-white/5 text-gray-400 font-bold text-xs sm:text-sm hover:text-white transition-colors min-h-[42px]"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isPullingCbt || cbtScoresForClass.length === 0"
              class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 via-orange-500 to-teal-500 hover:from-amber-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-xl shadow-amber-500/25 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed min-h-[42px]"
            >
              <svg v-if="isPullingCbt" class="animate-spin w-4 h-4 mr-1 text-slate-950" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isPullingCbt ? 'Menarik Nilai...' : '⚡ Tarik & Simpan Nilai' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
