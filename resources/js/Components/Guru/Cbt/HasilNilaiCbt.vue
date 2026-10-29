<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { showToast, showSuccessAlert, confirmDelete } from '@/Utils/sweetalert';

const props = defineProps({
  nilaiList: {
    type: Array,
    default: () => [],
  },
  jadwalList: {
    type: Array,
    default: () => [],
  },
  masterKelasList: {
    type: Array,
    default: () => [],
  },
  jurusanList: {
    type: Array,
    default: () => [],
  },
  mapelList: {
    type: Array,
    default: () => [],
  },
  siswaList: {
    type: Array,
    default: () => [],
  },
  tpList: {
    type: Array,
    default: () => [],
  },
  kelasList: {
    type: Array,
    default: () => [],
  },
  jadwalGuruList: {
    type: Array,
    default: () => [],
  },
  tahunAjaranList: {
    type: Array,
    default: () => [],
  },
  teacherSubject: {
    type: String,
    default: '',
  },
});

// -----------------------------------------------------------------------------
// FILTER STATES
// -----------------------------------------------------------------------------
const selectedMapel = ref('Semua');
const selectedTingkat = ref('Semua');
const selectedOverviewKelas = ref('');
const selectedJadwalId = ref(null);
const selectedKelasFilter = ref('');
const searchQuery = ref('');
const filterStatus = ref('');
const activeEvaluationTab = ref('nilai'); // 'nilai' | 'belum' | 'pelanggaran'

// Modal State
const showDetailModal = ref(false);
const activeNilai = ref(null);

// Helper Tingkat
function extractTingkat(kelasName, tingkatProp) {
  if (tingkatProp) return tingkatProp;
  if (!kelasName) return 'X';
  const upper = String(kelasName).toUpperCase().trim();
  if (upper.startsWith('XII')) return 'XII';
  if (upper.startsWith('XI')) return 'XI';
  return 'X';
}

// -----------------------------------------------------------------------------
// JADWAL LIST DATA
// -----------------------------------------------------------------------------
const effectiveJadwalList = computed(() => {
  const map = new Map();

  (props.jadwalList || []).forEach((j) => {
    map.set(String(j.id), {
      ...j,
      isDeleted: Boolean(j.isDeleted),
    });
  });

  (props.nilaiList || []).forEach((n) => {
    const jId = String(n.jadwalId || '');
    if (jId && !map.has(jId)) {
      map.set(jId, {
        id: n.jadwalId,
        bankSoalId: null,
        bankSoalJudul: n.bankSoalJudul || '-',
        bankSoalKode: '-',
        mapelNama: n.mapelNama || 'Informatika',
        jurusanId: null,
        jurusanNama: n.jurusanNama || 'Semua Jurusan',
        jurusanKode: n.jurusanKode || 'ALL',
        kelasId: null,
        kelasNama: n.kelasNama || 'Semua Kelas',
        tingkat: n.tingkat || extractTingkat(n.kelasNama, null),
        judulUjian: n.judulUjian || 'Ujian CBT',
        waktuMulai: null,
        waktuSelesai: null,
        durasiMenit: 60,
        token: '-',
        status: 'selesai',
        modeUjian: 'formal',
        isDeleted: true,
      });
    }
  });

  return Array.from(map.values());
});

// Available Subjects from Teacher's Schedule & Profile
const availableMapels = computed(() => {
  const set = new Set();
  if (props.teacherSubject) {
    String(props.teacherSubject)
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .forEach((s) => set.add(s));
  }
  (props.jadwalGuruList || []).forEach((jg) => {
    if (jg.mataPelajaran) set.add(jg.mataPelajaran.trim());
  });
  effectiveJadwalList.value.forEach((j) => {
    if (j.mapelNama) set.add(j.mapelNama.trim());
  });
  return Array.from(set).sort();
});

function getJadwalCountByMapel(mapel) {
  return effectiveJadwalList.value.filter((j) => {
    if (!j.mapelNama) return false;
    const parts = String(j.mapelNama).toLowerCase().split(',').map((s) => s.trim());
    return parts.includes(String(mapel).toLowerCase().trim());
  }).length;
}

// Daftar Kelas Ampuan Guru untuk Filter Overview Depan
const overviewKelasList = computed(() => {
  const set = new Set();
  const mapelFilter = selectedMapel.value === 'Semua' ? null : selectedMapel.value.toLowerCase().trim();

  (props.jadwalGuruList || []).forEach((jg) => {
    const jgMapel = (jg.mataPelajaran || '').toLowerCase().trim();
    if (!mapelFilter || jgMapel.includes(mapelFilter) || mapelFilter.includes(jgMapel)) {
      const kName = jg.kelas || jg.namaKelas;
      if (kName && kName !== '-') set.add(kName);
    }
  });

  if (set.size === 0) {
    (props.kelasList || []).forEach((k) => {
      const kName = k.namaKelas || k.nama;
      if (kName && kName !== '-') set.add(kName);
    });
  }

  return Array.from(set).sort((a, b) =>
    a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
  );
});

// Filtered Schedule Cards (Overview)
const filteredJadwalCards = computed(() => {
  return effectiveJadwalList.value.filter((j) => {
    const matchMapel = selectedMapel.value === 'Semua' || (
      j.mapelNama &&
      String(j.mapelNama).toLowerCase().split(',').map((s) => s.trim()).includes(selectedMapel.value.toLowerCase().trim())
    );
    const itemTingkat = extractTingkat(j.kelasNama, j.tingkat);
    const matchTingkat = selectedTingkat.value === 'Semua' || itemTingkat === selectedTingkat.value;
    const matchKelas = !selectedOverviewKelas.value ||
      j.kelasNama === 'Semua Kelas' ||
      j.kelasNama === selectedOverviewKelas.value;
    const q = searchQuery.value.toLowerCase().trim();
    const matchSearch = !q ||
      (j.judulUjian && j.judulUjian.toLowerCase().includes(q)) ||
      (j.kelasNama && j.kelasNama.toLowerCase().includes(q)) ||
      (j.mapelNama && j.mapelNama.toLowerCase().includes(q));

    return matchMapel && matchTingkat && matchKelas && matchSearch;
  }).map((j) => {
    // Total students targeted
    const nilaisInJadwal = (props.nilaiList || []).filter((n) => String(n.jadwalId) === String(j.id));
    const sudahUjianCount = nilaisInJadwal.length;

    let totalSiswaKelas = 0;
    if (j.kelasNama && j.kelasNama !== 'Semua Kelas') {
      totalSiswaKelas = props.siswaList.filter((s) => s.kelas === j.kelasNama || String(s.kelasId) === String(j.kelasId)).length;
    } else {
      // All classes taught by teacher for this mapel
      const targetMapel = (j.mapelNama || '').toLowerCase().trim();
      const teacherClassNames = new Set();
      (props.jadwalGuruList || []).forEach((jg) => {
        const jgMapel = (jg.mataPelajaran || '').toLowerCase().trim();
        if (!targetMapel || jgMapel.includes(targetMapel) || targetMapel.includes(jgMapel)) {
          if (jg.kelas || jg.namaKelas) teacherClassNames.add(jg.kelas || jg.namaKelas);
        }
      });
      totalSiswaKelas = props.siswaList.filter((s) => teacherClassNames.has(s.kelas)).length;
    }

    const totalTarget = totalSiswaKelas > 0 ? totalSiswaKelas : (sudahUjianCount > 0 ? sudahUjianCount : 1);
    const partisipasiPersen = Math.min(100, Math.round((sudahUjianCount / totalTarget) * 100));

    const scores = nilaisInJadwal.map((n) => parseFloat(n.nilai) || 0);
    const avgScore = scores.length > 0 ? Math.round((scores.reduce((a, b) => a + b, 0) / scores.length) * 10) / 10 : 0;
    const tuntasCount = nilaisInJadwal.filter((n) => (parseFloat(n.nilai) || 0) >= 75).length;
    const pelanggaranCount = nilaisInJadwal.reduce((acc, curr) => acc + (curr.jumlahPelanggaran || 0), 0);

    return {
      ...j,
      totalSiswaKelas: totalSiswaKelas || sudahUjianCount,
      sudahUjianCount,
      belumUjianCount: Math.max(0, (totalSiswaKelas || sudahUjianCount) - sudahUjianCount),
      partisipasiPersen,
      avgScore,
      tuntasCount,
      pelanggaranCount,
    };
  });
});

// Currently Selected CBT Schedule
const selectedJadwal = computed(() => {
  if (!selectedJadwalId.value) return null;
  return effectiveJadwalList.value.find((j) => String(j.id) === String(selectedJadwalId.value)) || null;
});

// -----------------------------------------------------------------------------
// RELEVANT CLASSES (KUNCI DARI JADWAL MENGAJAR GURU UNTUK MAPEL UJIAN INI)
// -----------------------------------------------------------------------------
const relevantKelasList = computed(() => {
  if (!selectedJadwal.value) return [];

  // Jika jadwal diset khusus untuk 1 kelas tertentu
  if (selectedJadwal.value.kelasId && selectedJadwal.value.kelasNama && selectedJadwal.value.kelasNama !== 'Semua Kelas') {
    return [selectedJadwal.value.kelasNama];
  }

  // Jika jadwal Semua Kelas: Kunci selalu diambil dari data jadwal guru untuk mapel ini
  const set = new Set();
  const mapelTarget = (selectedJadwal.value.mapelNama || '').toLowerCase().trim();

  (props.jadwalGuruList || []).forEach((jg) => {
    const jgMapel = (jg.mataPelajaran || '').toLowerCase().trim();
    if (!mapelTarget || jgMapel.includes(mapelTarget) || mapelTarget.includes(jgMapel)) {
      const kName = jg.kelas || jg.namaKelas;
      if (kName && kName !== '-') set.add(kName);
    }
  });

  // Gabungkan kelas peserta dari nilai yang sudah masuk (jika ada)
  (props.nilaiList || []).forEach((n) => {
    if (String(n.jadwalId) === String(selectedJadwal.value.id) && n.kelasNama && n.kelasNama !== '-') {
      set.add(n.kelasNama);
    }
  });

  // Fallback ke kelasList ampuan guru
  if (set.size === 0) {
    (props.kelasList || []).forEach((k) => {
      const kName = k.namaKelas || k.nama;
      if (kName && kName !== '-') set.add(kName);
    });
  }

  return Array.from(set).sort((a, b) =>
    a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
  );
});

// All students expected to take this exam (filtered by selected rombel or all relevant classes)
const classStudents = computed(() => {
  if (!selectedJadwal.value) return [];
  const targetClasses = selectedKelasFilter.value
    ? [selectedKelasFilter.value]
    : relevantKelasList.value;

  if (targetClasses.length === 0) {
    if (selectedJadwal.value.kelasNama && selectedJadwal.value.kelasNama !== 'Semua Kelas') {
      return props.siswaList.filter((s) => s.kelas === selectedJadwal.value.kelasNama);
    }
    return props.siswaList;
  }

  return props.siswaList.filter((s) => targetClasses.includes(s.kelas));
});

// Completed Nilai for the selected schedule (optionally filtered by rombel class)
const completedNilais = computed(() => {
  if (!selectedJadwal.value) return [];
  return (props.nilaiList || []).filter((n) => {
    if (String(n.jadwalId) !== String(selectedJadwal.value.id)) return false;
    if (selectedKelasFilter.value) {
      return n.kelasNama === selectedKelasFilter.value;
    }
    if (relevantKelasList.value.length > 0) {
      return relevantKelasList.value.includes(n.kelasNama);
    }
    return true;
  });
});

// Uncompleted Students (Belum Ujian)
const uncompletedStudents = computed(() => {
  if (!selectedJadwal.value) return [];
  const completedIds = new Set(
    (props.nilaiList || [])
      .filter((n) => String(n.jadwalId) === String(selectedJadwal.value.id))
      .map((n) => n.siswaId)
  );
  return classStudents.value.filter((s) => !completedIds.has(s.id));
});

// Violating Students
const violatingStudents = computed(() => {
  return completedNilais.value.filter((n) => (n.jumlahPelanggaran || 0) > 0);
});

// -----------------------------------------------------------------------------
// RANKING COMPUTATION
// -----------------------------------------------------------------------------
const rankMap = computed(() => {
  const map = {};
  const sorted = [...completedNilais.value].sort((a, b) => {
    const scoreDiff = (parseFloat(b.nilai) || 0) - (parseFloat(a.nilai) || 0);
    if (scoreDiff !== 0) return scoreDiff;
    const benarDiff = (b.jumlahBenar || 0) - (a.jumlahBenar || 0);
    if (benarDiff !== 0) return benarDiff;
    return a.id - b.id;
  });

  sorted.forEach((item, idx) => {
    map[item.id] = {
      rank: idx + 1,
      totalInJadwal: sorted.length,
    };
  });

  return map;
});

function getRank(nilaiId) {
  return rankMap.value[nilaiId]?.rank || '-';
}

// Sorted Grades for the Table
const sortedScheduleGrades = computed(() => {
  return [...completedNilais.value].filter((n) => {
    const q = searchQuery.value.toLowerCase().trim();
    const matchSearch = !q ||
      (n.namaSiswa && n.namaSiswa.toLowerCase().includes(q)) ||
      (n.nis && n.nis.toLowerCase().includes(q)) ||
      (n.kelasNama && n.kelasNama.toLowerCase().includes(q));

    const matchStatus = !filterStatus.value || n.status === filterStatus.value;
    return matchSearch && matchStatus;
  }).sort((a, b) => {
    const rA = rankMap.value[a.id]?.rank ?? 999;
    const rB = rankMap.value[b.id]?.rank ?? 999;
    return rA - rB;
  });
});

// Top 3 Podium
const topThreePodium = computed(() => {
  const sorted = [...completedNilais.value].sort((a, b) => {
    return (rankMap.value[a.id]?.rank ?? 999) - (rankMap.value[b.id]?.rank ?? 999);
  });
  return sorted.slice(0, 3);
});

// -----------------------------------------------------------------------------
// KPI METRICS FOR SELECTED SCHEDULE
// -----------------------------------------------------------------------------
const totalTargetKelas = computed(() => {
  const inClass = classStudents.value.length;
  const inNilai = completedNilais.value.length;
  return inClass > 0 ? inClass : inNilai;
});

const totalSudahUjian = computed(() => completedNilais.value.length);
const totalBelumUjian = computed(() => Math.max(0, totalTargetKelas.value - totalSudahUjian.value));

const rataRataSkor = computed(() => {
  if (completedNilais.value.length === 0) return 0;
  const total = completedNilais.value.reduce((acc, curr) => acc + (parseFloat(curr.nilai) || 0), 0);
  return Math.round((total / completedNilais.value.length) * 10) / 10;
});

const skorTertinggi = computed(() => {
  if (completedNilais.value.length === 0) return 0;
  return Math.max(...completedNilais.value.map((n) => parseFloat(n.nilai) || 0));
});

const skorTerendah = computed(() => {
  if (completedNilais.value.length === 0) return 0;
  return Math.min(...completedNilais.value.map((n) => parseFloat(n.nilai) || 0));
});

const totalTuntas = computed(() => completedNilais.value.filter((n) => (parseFloat(n.nilai) || 0) >= 75).length);
const totalBelumTuntas = computed(() => completedNilais.value.filter((n) => (parseFloat(n.nilai) || 0) < 75).length);
const totalPelanggaranSesi = computed(() => completedNilais.value.reduce((acc, curr) => acc + (curr.jumlahPelanggaran || 0), 0));

// -----------------------------------------------------------------------------
// NAVIGATION & ACTIONS
// -----------------------------------------------------------------------------
function selectSchedule(id) {
  selectedJadwalId.value = String(id);
  selectedKelasFilter.value = '';
  searchQuery.value = '';
  filterStatus.value = '';
  activeEvaluationTab.value = 'nilai';
}

function backToOverview() {
  selectedJadwalId.value = null;
  selectedKelasFilter.value = '';
  searchQuery.value = '';
  filterStatus.value = '';
}

function resetAllFilters() {
  selectedMapel.value = 'Semua';
  selectedTingkat.value = 'Semua';
  selectedOverviewKelas.value = '';
  searchQuery.value = '';
}

function openDetail(n) {
  activeNilai.value = n;
  showDetailModal.value = true;
}

function resetStudentSession(n) {
  confirmDelete({
    title: 'Izinkan Ujian Ulang / Remedial?',
    text: `Siswa "${n.namaSiswa}" akan diizinkan mengikuti ujian CBT ini kembali. Sesi sebelumnya akan direset.`,
    confirmButtonText: 'Ya, Izinkan Ujian Ulang',
    cancelButtonText: 'Batal',
  }).then((res) => {
    if (res.isConfirmed) {
      if (n.sesiId) {
        router.post(route('guru.cbt.sesi.reset', n.sesiId), {}, {
          preserveScroll: true,
          onSuccess: () => {
            showSuccessAlert('Sesi Berhasil Direset', `Siswa ${n.namaSiswa} kini dapat masuk kembali untuk mengulang ujian CBT.`);
            showDetailModal.value = false;
          },
          onError: () => {
            showToast('Gagal mereset sesi siswa.', 'error');
          }
        });
      } else {
        router.delete(route('guru.cbt.nilai.delete', n.id), {
          preserveScroll: true,
          onSuccess: () => {
            showSuccessAlert('Nilai Direset', `Nilai ${n.namaSiswa} telah dihapus. Siswa dapat mengulang ujian CBT.`);
            showDetailModal.value = false;
          },
        });
      }
    }
  });
}

// -----------------------------------------------------------------------------
// TRANSFER CBT NILAI KE TP STATE & METHODS
// -----------------------------------------------------------------------------
const showTransferModal = ref(false);
const isTransferring = ref(false);
const transferForm = ref({
  mode: 'existing_tp',
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

const availableTpsForSelectedJadwal = computed(() => {
  if (!selectedJadwal.value) return props.tpList || [];
  const mapel = (selectedJadwal.value.mapelNama || '').trim().toLowerCase();
  return (props.tpList || []).filter((t) => {
    if (!t.mapel) return true;
    return t.mapel.trim().toLowerCase() === mapel;
  });
});

function openTransferModal() {
  if (!selectedJadwal.value) return;
  const currentMapel = selectedJadwal.value.mapelNama || props.teacherSubject || 'Informatika';
  const defaultTa = (props.tahunAjaranList || []).find((t) => t.is_aktif || t.isAktif)?.id || (props.tahunAjaranList || [])[0]?.id || '';

  const tps = availableTpsForSelectedJadwal.value;
  const initialTpId = tps.length > 0 ? tps[0].id : '';

  transferForm.value = {
    mode: tps.length > 0 ? 'existing_tp' : 'new_tp',
    tp_id: initialTpId,
    kode: `TP-CBT-${Math.floor(10 + Math.random() * 90)}`,
    judul: `Ujian CBT: ${selectedJadwal.value.judulUjian}`,
    mapel: currentMapel,
    jenis: 'Sumatif',
    kktp: 75,
    semester: selectedJadwal.value.semester || 'Ganjil',
    tahun_ajaran_id: defaultTa,
    overwrite: true,
  };

  showTransferModal.value = true;
}

function submitTransfer() {
  if (!selectedJadwal.value) return;

  if (transferForm.value.mode === 'existing_tp' && !transferForm.value.tp_id) {
    showToast('Pilih Tujuan Pembelajaran (TP) tujuan transfer!', 'warning');
    return;
  }

  if (transferForm.value.mode === 'new_tp' && !transferForm.value.judul.trim()) {
    showToast('Judul Tujuan Pembelajaran baru wajib diisi!', 'warning');
    return;
  }

  isTransferring.value = true;

  const payload = {
    jadwal_id: selectedJadwal.value.id,
    mode: transferForm.value.mode,
    tp_id: transferForm.value.mode === 'existing_tp' ? transferForm.value.tp_id : null,
    kode: transferForm.value.kode,
    judul: transferForm.value.judul,
    mapel: transferForm.value.mapel,
    jenis: transferForm.value.jenis,
    kktp: Number(transferForm.value.kktp) || 75,
    semester: transferForm.value.semester,
    tahun_ajaran_id: transferForm.value.tahun_ajaran_id || null,
    overwrite: transferForm.value.overwrite,
  };

  router.post(route('guru.cbt.transfer-nilai-tp'), payload, {
    preserveScroll: true,
    onSuccess: () => {
      showTransferModal.value = false;
      showSuccessAlert(
        'Nilai Berhasil Ditransfer!',
        'Nilai hasil ujian CBT telah berhasil disinkronkan ke ledger Tujuan Pembelajaran (TP).'
      );
    },
    onError: (errors) => {
      const msg = Object.values(errors)[0] || 'Gagal mentransfer nilai ke TP.';
      showToast(msg, 'error');
    },
    onFinish: () => {
      isTransferring.value = false;
    },
  });
}
</script>

<template>
  <div class="space-y-6">

    <!-- ======================================================================= -->
    <!-- HEADER RINGKAS (TANPA ELEMEN DEKORATIF BERLEBIH)                        -->
    <!-- ======================================================================= -->
    <div class="p-5 sm:p-6 rounded-2xl bg-slate-900 border border-white/10 shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-teal-500/10 border border-teal-500/30 text-teal-300 text-[11px] font-bold uppercase tracking-wider mb-2 font-mono">
          <span>📊 Hasil Ujian CBT</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
          Rekapitulasi &amp; Nilai CBT
        </h1>
        <p class="text-xs text-gray-400 mt-1 max-w-xl">
          Pantau hasil evaluasi, kehadiran siswa per rombel kelas, catatan integritas anti-cheat, dan peringkat nilai siswa.
        </p>
      </div>

      <!-- Quick Status Counter -->
      <div class="flex items-center gap-3 text-xs font-mono shrink-0">
        <div class="px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-center">
          <span class="text-gray-400 text-[10px] block">Total Sesi CBT</span>
          <span class="text-base font-black text-teal-300">{{ effectiveJadwalList.length }}</span>
        </div>
        <div class="px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-center">
          <span class="text-gray-400 text-[10px] block">Total Nilai Masuk</span>
          <span class="text-base font-black text-emerald-300">{{ (props.nilaiList || []).length }}</span>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- VIEW A: OVERVIEW DAFTAR SESI UJIAN (Saat Belum Memilih Sesi)            -->
    <!-- ======================================================================= -->
    <div v-if="!selectedJadwalId" class="space-y-4">
      
      <!-- Filter Bar: Mapel Guru, Jenjang Tingkat & Pencarian -->
      <div class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 space-y-3 shadow-md">
        <!-- Filter Baris 1: Mata Pelajaran Guru -->
        <div class="flex items-center gap-2.5 flex-wrap text-xs">
          <span class="text-[11px] font-bold uppercase tracking-wider text-teal-400 font-mono">
            Mapel:
          </span>
          <button
            type="button"
            @click="selectedMapel = 'Semua'"
            :class="[
              'px-3 py-1.5 rounded-xl font-bold transition-all min-h-[34px] flex items-center gap-1.5',
              selectedMapel === 'Semua'
                ? 'bg-teal-500 text-slate-950 font-black shadow-sm'
                : 'bg-white/5 hover:bg-white/10 text-gray-300'
            ]"
          >
            <span>Semua Mapel</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/20 font-mono">{{ effectiveJadwalList.length }}</span>
          </button>
          <button
            v-for="mapel in availableMapels"
            :key="mapel"
            type="button"
            @click="selectedMapel = mapel"
            :class="[
              'px-3 py-1.5 rounded-xl font-bold transition-all min-h-[34px] flex items-center gap-1.5',
              selectedMapel === mapel
                ? 'bg-teal-500 text-slate-950 font-black shadow-sm'
                : 'bg-white/5 hover:bg-white/10 text-gray-300'
            ]"
          >
            <span>📖 {{ mapel }}</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/20 font-mono">{{ getJadwalCountByMapel(mapel) }}</span>
          </button>
        </div>

        <!-- Filter Baris 2: Jenjang, Kelas Ampuan & Pencarian -->
        <div class="pt-2 border-t border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3 overflow-x-auto text-xs flex-wrap">
            <div class="flex items-center gap-1.5 shrink-0">
              <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mr-1 font-mono">
                Jenjang:
              </span>
              <button
                type="button"
                v-for="tingkat in ['Semua', 'X', 'XI', 'XII']"
                :key="tingkat"
                @click="selectedTingkat = tingkat"
                :class="[
                  'px-3 py-1 rounded-xl font-bold transition-all shrink-0 text-xs min-h-[32px]',
                  selectedTingkat === tingkat
                    ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 font-black'
                    : 'bg-white/5 text-gray-400 hover:text-white border border-transparent'
                ]"
              >
                {{ tingkat === 'Semua' ? 'Semua Jenjang' : 'Kelas ' + tingkat }}
              </button>
            </div>

            <!-- Dropdown Filter Kelas Ampuan Guru di Overview -->
            <div v-if="overviewKelasList.length > 0" class="flex items-center gap-1.5 shrink-0">
              <span class="text-[11px] font-bold uppercase tracking-wider text-teal-400 font-mono">
                Kelas:
              </span>
              <select
                v-model="selectedOverviewKelas"
                class="py-1 px-2.5 rounded-xl bg-slate-800 border border-cyan-500/30 text-cyan-300 font-bold text-xs focus:outline-none focus:border-cyan-400 min-h-[32px]"
                title="Saring jadwal ujian berdasarkan kelas yang diajar guru"
              >
                <option value="">Semua Kelas Ampuan ({{ overviewKelasList.length }})</option>
                <option v-for="k in overviewKelasList" :key="k" :value="k">
                  Kelas {{ k }}
                </option>
              </select>
            </div>
          </div>

          <!-- Live Search -->
          <div class="relative min-w-[240px]">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari judul ujian atau kelas..."
              class="w-full pl-3 pr-8 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-teal-400 min-h-[34px]"
            />
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-xs"
            >✕</button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredJadwalCards.length === 0" class="text-center py-16 rounded-2xl bg-slate-900 border border-white/10 space-y-3">
        <span class="text-4xl block">📋</span>
        <h3 class="text-sm font-bold text-white">Tidak Ditemukan Sesi Ujian CBT</h3>
        <p class="text-xs text-gray-400 max-w-md mx-auto">
          Belum ada jadwal ujian CBT yang sesuai dengan filter mata pelajaran atau pencarian saat ini.
        </p>
        <button
          v-if="selectedMapel !== 'Semua' || selectedTingkat !== 'Semua' || searchQuery"
          type="button"
          @click="resetAllFilters"
          class="px-4 py-1.5 rounded-xl bg-teal-500/20 text-teal-300 border border-teal-500/30 text-xs font-bold"
        >
          Reset Filter
        </button>
      </div>

      <!-- MODE TABEL: TABEL MATRIKS RINGKAS (UNTUK BANYAK KELAS / ROMBEL) -->
      <div v-else class="space-y-4">
        <!-- Desktop Table View (hidden md:block) -->
        <div class="hidden md:block overflow-x-auto rounded-2xl bg-slate-900 border border-white/10 shadow-xl">
          <table class="w-full text-left border-collapse text-xs min-w-[700px]">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-bold text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-3.5 px-4">Kelas &amp; Jurusan</th>
                <th class="py-3.5 px-5">Mata Pelajaran &amp; Sesi Ujian</th>
                <th class="py-3.5 px-5 text-center min-w-[180px]">Partisipasi Siswa</th>
                <th class="py-3.5 px-4 text-center">Rata-Rata Nilai</th>
                <th class="py-3.5 px-5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="j in filteredJadwalCards" :key="j.id" class="hover:bg-white/[0.03] transition-colors group">
                <td class="py-3.5 px-4">
                  <span class="font-bold text-white block">{{ j.kelasNama }}</span>
                  <span class="text-[11px] text-gray-400 font-mono">{{ j.jurusanNama || '-' }}</span>
                </td>
                <td class="py-3.5 px-5">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-teal-500/15 text-teal-300 border border-teal-500/30 text-[11px] font-bold font-mono">
                      📖 {{ j.mapelNama }}
                    </span>
                    <p class="font-bold text-white group-hover:text-teal-300 transition-colors">{{ j.judulUjian }}</p>
                    <span v-if="j.isDeleted" class="px-2 py-0.5 rounded-lg bg-amber-500/15 text-amber-300 border border-amber-500/30 text-[10px] font-bold">
                      📁 Arsip Selesai
                    </span>
                  </div>
                  <div class="flex items-center gap-3 text-[11px] text-gray-400 font-mono mt-1">
                    <span>Token: <strong class="text-amber-300">{{ j.token }}</strong></span>
                    <span>&bull;</span>
                    <span>Durasi: {{ j.durasiMenit }} Menit</span>
                    <span v-if="j.pelanggaranCount > 0" class="text-rose-400 font-bold">
                      &bull; ⚠️ {{ j.pelanggaranCount }} Pelanggaran
                    </span>
                  </div>
                </td>
                <td class="py-3.5 px-5">
                  <div class="space-y-1">
                    <div class="flex items-center justify-between text-[11px] font-mono">
                      <span class="text-gray-400 font-bold">{{ j.sudahUjianCount }}/{{ j.totalSiswaKelas }} Siswa</span>
                      <span class="text-emerald-400 font-black">{{ j.partisipasiPersen }}%</span>
                    </div>
                    <div class="w-full h-1.5 rounded-full bg-white/10 overflow-hidden">
                      <div
                        class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-400"
                        :style="{ width: j.partisipasiPersen + '%' }"
                      ></div>
                    </div>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-center font-mono">
                  <span class="font-black text-cyan-300 text-sm">{{ j.avgScore }}</span>
                  <span class="text-[10px] text-gray-400 block font-normal">Tuntas: {{ j.tuntasCount }}</span>
                </td>
                <td class="py-3.5 px-5 text-right">
                  <button
                    type="button"
                    @click="selectSchedule(j.id)"
                    class="px-3.5 py-1.5 rounded-xl bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 border border-teal-500/40 text-xs font-bold transition-all inline-flex items-center gap-1.5 min-h-[36px] cursor-pointer"
                  >
                    <span>Evaluasi</span>
                    <span>➔</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card List View (md:hidden) -->
        <div class="md:hidden space-y-3.5">
          <div
            v-for="j in filteredJadwalCards"
            :key="'mob-jadwal-' + j.id"
            class="p-4 rounded-2xl bg-slate-900 border border-white/10 space-y-3.5 shadow-lg"
          >
            <!-- Header: Title, Mapel, Class Badge -->
            <div class="flex items-start justify-between gap-2.5">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                  <span class="px-2 py-0.5 rounded-md bg-teal-500/15 text-teal-300 border border-teal-500/30 text-[10px] font-bold font-mono">
                    📖 {{ j.mapelNama }}
                  </span>
                  <span class="px-2 py-0.5 rounded-md bg-white/5 text-gray-300 border border-white/10 text-[10px] font-mono font-bold">
                    {{ j.kelasNama }}
                  </span>
                  <span v-if="j.isDeleted" class="px-1.5 py-0.5 rounded-md bg-amber-500/15 text-amber-300 border border-amber-500/30 text-[9px] font-bold">
                    📁 Arsip
                  </span>
                </div>
                <h4 class="font-bold text-white text-sm leading-snug">{{ j.judulUjian }}</h4>
              </div>

              <!-- Avg Score Badge -->
              <div class="text-right shrink-0">
                <span class="text-[10px] text-gray-400 block uppercase font-mono">Rata-rata</span>
                <span class="text-lg font-black font-mono text-cyan-300">{{ j.avgScore }}</span>
              </div>
            </div>

            <!-- Participation Bar -->
            <div class="bg-slate-950/60 p-3 rounded-xl border border-white/5 space-y-1.5">
              <div class="flex items-center justify-between text-[11px] font-mono">
                <span class="text-gray-400">Partisipasi Siswa</span>
                <span class="text-emerald-400 font-bold">{{ j.sudahUjianCount }}/{{ j.totalSiswaKelas }} ({{ j.partisipasiPersen }}%)</span>
              </div>
              <div class="w-full h-2 rounded-full bg-slate-900 overflow-hidden border border-white/5">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-400"
                  :style="{ width: j.partisipasiPersen + '%' }"
                ></div>
              </div>
            </div>

            <!-- Token & Action Footer -->
            <div class="flex items-center justify-between pt-1 border-t border-white/5 text-xs">
              <div class="text-[11px] text-gray-400 font-mono">
                <span>Token: <strong class="text-amber-300">{{ j.token }}</strong></span>
                <span class="mx-1">•</span>
                <span>{{ j.durasiMenit }} Menit</span>
              </div>

              <button
                type="button"
                @click="selectSchedule(j.id)"
                class="px-4 py-2 rounded-xl bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 border border-teal-500/40 text-xs font-bold transition-all inline-flex items-center gap-1.5 min-h-[38px] active:scale-95 cursor-pointer"
              >
                <span>Evaluasi</span>
                <span>➔</span>
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- VIEW B:<!-- VIEW B: DETAIL BERITA ACARA & NILAI (Saat Sesi Ujian Dipilih)            -->
    <!-- ======================================================================= -->
    <div v-else-if="selectedJadwal" class="space-y-6">

      <!-- Top Nav Bar Kembali & Aksi Transfer -->
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <button
          type="button"
          @click="backToOverview"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white text-xs font-bold transition-all min-h-[38px] border border-white/10"
        >
          <span>«</span>
          <span>Kembali ke Daftar Ujian</span>
        </button>

        <div class="flex items-center gap-2 flex-wrap">
          <button
            type="button"
            @click="openTransferModal"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 hover:from-emerald-400 hover:to-cyan-400 text-slate-950 text-xs font-black shadow-lg shadow-emerald-500/20 transition-all min-h-[38px] active:scale-95 cursor-pointer"
            title="Transfer hasil ujian CBT ini ke ledger Tujuan Pembelajaran (TP)"
          >
            <span>📥</span>
            <span>Transfer Nilai ke TP</span>
          </button>

          <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-bold font-mono">
            <span>✓ Data Tersinkronisasi Otomatis</span>
          </div>
        </div>
      </div>

      <!-- Schedule Meta Card -->
      <div class="p-5 sm:p-6 rounded-2xl bg-slate-900 border border-teal-500/30 shadow-xl space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-white/10 pb-4">
          <div>
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
              <span class="px-2.5 py-0.5 rounded-lg bg-teal-500/20 text-teal-300 border border-teal-500/30 text-xs font-bold font-mono">
                📖 {{ selectedJadwal.mapelNama }}
              </span>
              <span class="px-2.5 py-0.5 rounded-lg bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-xs font-bold font-mono">
                👥 Target: {{ selectedJadwal.kelasNama }}
              </span>
              <span v-if="selectedJadwal.jurusanNama" class="px-2.5 py-0.5 rounded-lg bg-white/10 text-gray-300 text-xs font-mono">
                🏢 {{ selectedJadwal.jurusanNama }}
              </span>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white">
              {{ selectedJadwal.judulUjian }}
            </h2>
          </div>

          <div class="flex items-center gap-2 flex-wrap text-xs font-mono">
            <div class="px-3 py-1.5 rounded-xl bg-white/5 border border-white/10">
              <span class="text-gray-400 text-[10px] block">Durasi</span>
              <span class="font-bold text-white">{{ selectedJadwal.durasiMenit }} Menit</span>
            </div>
            <div class="px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/30">
              <span class="text-amber-400 text-[10px] block">Token CBT</span>
              <span class="font-black text-amber-300">{{ selectedJadwal.token }}</span>
            </div>
          </div>
        </div>

        <!-- 4 KPI Metrics -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="p-3.5 rounded-xl bg-white/5 border border-white/10 space-y-0.5">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Partisipasi Siswa</span>
            <p class="text-xl font-black text-white font-mono">
              {{ totalSudahUjian }} <span class="text-xs text-gray-400 font-normal">/ {{ totalTargetKelas }} Siswa</span>
            </p>
            <span class="text-[10.5px] text-emerald-400 font-bold block">
              ✓ {{ Math.min(100, Math.round((totalSudahUjian / (totalTargetKelas || 1)) * 100)) }}% Selesai
            </span>
          </div>

          <div class="p-3.5 rounded-xl bg-white/5 border border-white/10 space-y-0.5">
            <span class="text-[10px] font-bold text-cyan-400 uppercase tracking-wider block">Rata-Rata Nilai</span>
            <p class="text-xl font-black text-cyan-300 font-mono">
              {{ rataRataSkor }} <span class="text-xs text-gray-400 font-normal">/ 100</span>
            </p>
            <span class="text-[10.5px] text-gray-400 font-mono block">
              Tinggi: {{ skorTertinggi }} • Rendah: {{ skorTerendah }}
            </span>
          </div>

          <div class="p-3.5 rounded-xl bg-slate-900 border border-emerald-500/30 space-y-0.5">
            <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider block">Ketuntasan (KKM 75)</span>
            <p class="text-xl font-black text-emerald-300 font-mono">
              {{ totalTuntas }} <span class="text-xs text-emerald-400/70 font-normal">Tuntas</span>
            </p>
            <span class="text-[10.5px] text-rose-300 font-bold block">
              {{ totalBelumTuntas }} Belum Tuntas
            </span>
          </div>

          <div class="p-3.5 rounded-xl bg-slate-900 border border-rose-500/30 space-y-0.5">
            <span class="text-[10px] font-bold text-rose-400 uppercase tracking-wider block">Integritas Anti-Cheat</span>
            <p class="text-xl font-black text-rose-300 font-mono">
              {{ totalPelanggaranSesi }} <span class="text-xs text-rose-400/70 font-normal">Pelanggaran</span>
            </p>
            <span class="text-[10.5px] text-gray-400 block">
              {{ violatingStudents.length }} siswa melanggar
            </span>
          </div>
        </div>
      </div>

      <!-- Podium Top 3 (Tampil jika sudah ada nilai) -->
      <div v-if="completedNilais.length >= 2" class="p-5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-slate-900 to-teal-500/10 border border-amber-500/30 space-y-3">
        <h3 class="text-xs font-bold text-amber-300 uppercase tracking-wider font-mono flex items-center gap-2">
          <span>🏆 Peringkat Teratas (Podium)</span>
          <span v-if="selectedKelasFilter" class="text-teal-300 font-normal">• Kelas {{ selectedKelasFilter }}</span>
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
          <!-- 2nd Place -->
          <div v-if="topThreePodium[1]" class="order-2 sm:order-1 p-3.5 rounded-xl bg-slate-800/80 border border-slate-400/30 text-center space-y-1">
            <span class="text-lg block">🥈</span>
            <span class="text-[10px] font-black text-slate-300 uppercase font-mono">Juara 2</span>
            <h4 class="font-bold text-white text-xs line-clamp-1">{{ topThreePodium[1].namaSiswa }}</h4>
            <span class="text-xs font-mono text-gray-400 block">{{ topThreePodium[1].kelasNama }}</span>
            <div class="pt-1.5 border-t border-white/10 font-mono text-xs font-black text-slate-200">
              Skor: {{ topThreePodium[1].nilai }}
            </div>
          </div>

          <!-- 1st Place -->
          <div v-if="topThreePodium[0]" class="order-1 sm:order-2 p-4 rounded-xl bg-amber-500/20 border-2 border-amber-400 text-center space-y-1 shadow-lg shadow-amber-500/10">
            <span class="text-2xl block">👑</span>
            <span class="text-[11px] font-black text-amber-200 uppercase font-mono">🥇 Juara 1 (Champion)</span>
            <h4 class="font-black text-white text-sm line-clamp-1">{{ topThreePodium[0].namaSiswa }}</h4>
            <span class="text-xs font-mono text-amber-200/80 block">{{ topThreePodium[0].kelasNama }}</span>
            <div class="pt-2 border-t border-amber-400/30 font-mono text-sm font-black text-amber-300">
              Skor: {{ topThreePodium[0].nilai }} (✓ {{ topThreePodium[0].jumlahBenar }} Benar)
            </div>
          </div>

          <!-- 3rd Place -->
          <div v-if="topThreePodium[2]" class="order-3 p-3.5 rounded-xl bg-slate-800/80 border border-amber-700/40 text-center space-y-1">
            <span class="text-lg block">🥉</span>
            <span class="text-[10px] font-black text-amber-400 uppercase font-mono">Juara 3</span>
            <h4 class="font-bold text-white text-xs line-clamp-1">{{ topThreePodium[2].namaSiswa }}</h4>
            <span class="text-xs font-mono text-gray-400 block">{{ topThreePodium[2].kelasNama }}</span>
            <div class="pt-1.5 border-t border-white/10 font-mono text-xs font-black text-amber-300">
              Skor: {{ topThreePodium[2].nilai }}
            </div>
          </div>
        </div>
      </div>

      <!-- 3 Tab Evaluasi -->
      <div class="flex items-center gap-2 border-b border-white/10 pb-2 text-xs font-mono overflow-x-auto">
        <button
          type="button"
          @click="activeEvaluationTab = 'nilai'"
          :class="[
            'px-4 py-2 rounded-xl font-bold transition-all flex items-center gap-2 shrink-0 min-h-[38px]',
            activeEvaluationTab === 'nilai'
              ? 'bg-teal-500 text-slate-950 font-black shadow-md shadow-teal-500/20'
              : 'bg-white/5 hover:bg-white/10 text-gray-300'
          ]"
        >
          <span>🏆 Rekap Nilai</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] bg-black/20 font-mono">{{ completedNilais.length }}</span>
        </button>

        <button
          type="button"
          @click="activeEvaluationTab = 'belum'"
          :class="[
            'px-4 py-2 rounded-xl font-bold transition-all flex items-center gap-2 shrink-0 min-h-[38px]',
            activeEvaluationTab === 'belum'
              ? 'bg-amber-500 text-slate-950 font-black shadow-md shadow-amber-500/20'
              : 'bg-white/5 hover:bg-white/10 text-gray-300'
          ]"
        >
          <span>⏳ Belum Ujian / Susulan</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] bg-black/20 font-mono">{{ uncompletedStudents.length }}</span>
        </button>

        <button
          type="button"
          @click="activeEvaluationTab = 'pelanggaran'"
          :class="[
            'px-4 py-2 rounded-xl font-bold transition-all flex items-center gap-2 shrink-0 min-h-[38px]',
            activeEvaluationTab === 'pelanggaran'
              ? 'bg-rose-500 text-white font-black shadow-md shadow-rose-500/20'
              : 'bg-white/5 hover:bg-white/10 text-gray-300'
          ]"
        >
          <span>⚠️ Catatan Pelanggaran</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] bg-black/20 font-mono">{{ violatingStudents.length }}</span>
        </button>
      </div>

      <!-- TOOLBAR FILTER TERPADU (FILTER KELAS DARI JADWAL GURU, STATUS, SEARCH) -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-slate-900 border border-white/10">
        <div class="flex items-center gap-2.5 flex-wrap text-xs">
          <!-- Dropdown Filter Rombel Kelas (Kunci Jadwal Guru) -->
          <div v-if="relevantKelasList.length > 1" class="flex items-center gap-1.5">
            <span class="text-gray-400 font-mono text-[11px]">Rombel:</span>
            <select
              v-model="selectedKelasFilter"
              class="py-1.5 px-3 rounded-xl bg-slate-800 border border-cyan-500/30 text-cyan-300 font-bold text-xs focus:outline-none focus:border-cyan-400 min-h-[36px]"
              title="Pilih kelas tertentu yang diajar untuk menyaring siswa"
            >
              <option value="">🏢 Semua Rombel Kelas ({{ relevantKelasList.length }})</option>
              <option v-for="k in relevantKelasList" :key="k" :value="k">
                Kelas {{ k }}
              </option>
            </select>
          </div>

          <!-- Filter Status Nilai (Hanya di Tab Nilai) -->
          <div v-if="activeEvaluationTab === 'nilai'" class="flex items-center gap-1.5">
            <span class="text-gray-400 font-mono text-[11px]">Status:</span>
            <select
              v-model="filterStatus"
              class="py-1.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs focus:outline-none focus:border-teal-400 min-h-[36px]"
            >
              <option value="">Semua Status</option>
              <option value="Tuntas">Tuntas (≥ 75)</option>
              <option value="Belum Tuntas">Belum Tuntas (&lt; 75)</option>
            </select>
          </div>
        </div>

        <!-- Search Input -->
        <div class="relative min-w-[200px] sm:w-64">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari siswa atau NIS..."
            class="w-full pl-3 pr-8 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-teal-400 min-h-[36px]"
          />
          <button
            v-if="searchQuery"
            type="button"
            @click="searchQuery = ''"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-xs"
          >✕</button>
        </div>
      </div>

      <!-- ===================================================================== -->
      <!-- TAB 1: DAFTAR NILAI & PERINGKAT                                       -->
      <!-- ===================================================================== -->
      <div v-if="activeEvaluationTab === 'nilai'" class="space-y-4">
        <!-- Desktop Table View (hidden md:block) -->
        <div v-if="sortedScheduleGrades.length > 0" class="hidden md:block overflow-x-auto rounded-2xl bg-slate-900 border border-white/10 shadow-xl">
          <table class="w-full text-left border-collapse text-xs min-w-[650px]">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-bold text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-3.5 px-4 text-center w-24">Peringkat</th>
                <th class="py-3.5 px-5">Nama Siswa &amp; NIS</th>
                <th class="py-3.5 px-4">Kelas Rombel</th>
                <th class="py-3.5 px-4 text-center">Nilai Akhir</th>
                <th class="py-3.5 px-4 text-center">Status</th>
                <th class="py-3.5 px-5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="n in sortedScheduleGrades" :key="n.id" class="hover:bg-white/[0.03] transition-colors group">
                <!-- Rangking Badge -->
                <td class="py-3.5 px-4 text-center">
                  <span v-if="getRank(n.id) === 1" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-amber-500/20 border border-amber-400/50 text-amber-300 font-bold text-xs">
                    🥇 #1
                  </span>
                  <span v-else-if="getRank(n.id) === 2" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-slate-400/20 border border-slate-300/40 text-slate-200 font-bold text-xs">
                    🥈 #2
                  </span>
                  <span v-else-if="getRank(n.id) === 3" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-amber-700/25 border border-amber-600/40 text-amber-300 font-bold text-xs">
                    🥉 #3
                  </span>
                  <span v-else class="inline-flex items-center justify-center px-2 py-0.5 rounded-md bg-white/5 text-gray-400 font-mono font-bold text-xs">
                    #{{ getRank(n.id) }}
                  </span>
                </td>

                <!-- Nama Siswa & NIS -->
                <td class="py-3.5 px-5">
                  <p class="font-bold text-white text-sm group-hover:text-teal-300 transition-colors">
                    {{ n.namaSiswa }}
                  </p>
                  <span class="text-[11px] text-gray-400 font-mono">NIS: {{ n.nis }}</span>
                </td>

                <!-- Kelas Rombel -->
                <td class="py-3.5 px-4">
                  <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-300 font-mono font-bold text-[11px]">
                    {{ n.kelasNama }}
                  </span>
                </td>

                <!-- Nilai Akhir -->
                <td class="py-3.5 px-4 text-center">
                  <span
                    :class="[
                      'inline-flex items-center justify-center px-3 py-1 rounded-xl font-black text-sm border font-mono',
                      n.nilai >= 75
                        ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                        : 'bg-rose-500/20 text-rose-300 border-rose-500/30'
                    ]"
                  >
                    {{ n.nilai }}
                  </span>
                </td>

                <!-- Status -->
                <td class="py-3.5 px-4 text-center">
                  <span
                    :class="[
                      'px-2.5 py-0.5 rounded-full font-bold text-[10.5px] border',
                      n.status === 'Tuntas'
                        ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'
                        : 'bg-amber-500/10 text-amber-300 border-amber-500/30'
                    ]"
                  >
                    {{ n.status }}
                  </span>
                </td>

                <!-- Aksi (Detail & Reset Sesi Ulang) -->
                <td class="py-3.5 px-5 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="openDetail(n)"
                      class="px-2.5 py-1.5 rounded-xl bg-teal-500/15 hover:bg-teal-500/25 text-teal-300 border border-teal-500/30 text-xs font-bold transition-colors min-h-[36px] flex items-center gap-1"
                      title="Lihat Rincian Jawaban & Lembar Ujian"
                    >
                      <span>📄 Detail</span>
                    </button>
                    <button
                      type="button"
                      @click="resetStudentSession(n)"
                      class="p-2 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 text-xs font-bold transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                      title="Izinkan Ujian Ulang / Remedial"
                    >
                      <span>🔄</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card List View for Student Grades (md:hidden) -->
        <div v-if="sortedScheduleGrades.length > 0" class="md:hidden space-y-3">
          <div
            v-for="n in sortedScheduleGrades"
            :key="'mob-grade-' + n.id"
            class="p-4 rounded-2xl bg-slate-900 border border-white/10 space-y-3 shadow-lg"
          >
            <!-- Top: Rank, Student Info, Score Badge -->
            <div class="flex items-start justify-between gap-2.5">
              <div class="flex items-start gap-2.5 min-w-0">
                <!-- Rank -->
                <div class="shrink-0 pt-0.5">
                  <span v-if="getRank(n.id) === 1" class="inline-flex items-center px-2 py-0.5 rounded-lg bg-amber-500/20 border border-amber-400/50 text-amber-300 font-bold text-xs">
                    🥇 #1
                  </span>
                  <span v-else-if="getRank(n.id) === 2" class="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-400/20 border border-slate-300/40 text-slate-200 font-bold text-xs">
                    🥈 #2
                  </span>
                  <span v-else-if="getRank(n.id) === 3" class="inline-flex items-center px-2 py-0.5 rounded-lg bg-amber-700/25 border border-amber-600/40 text-amber-300 font-bold text-xs">
                    🥉 #3
                  </span>
                  <span v-else class="inline-flex items-center justify-center px-2 py-0.5 rounded-md bg-white/5 text-gray-400 font-mono font-bold text-xs">
                    #{{ getRank(n.id) }}
                  </span>
                </div>

                <div class="min-w-0">
                  <p class="font-bold text-white text-sm truncate">{{ n.namaSiswa }}</p>
                  <div class="flex items-center gap-1.5 text-[11px] text-gray-400 font-mono mt-0.5">
                    <span>NIS: {{ n.nis }}</span>
                    <span>•</span>
                    <span class="text-teal-300 font-bold">{{ n.kelasNama }}</span>
                  </div>
                </div>
              </div>

              <!-- Nilai -->
              <div class="text-right shrink-0">
                <span
                  :class="[
                    'inline-flex items-center justify-center px-3 py-1 rounded-xl font-black text-sm border font-mono',
                    n.nilai >= 75
                      ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                      : 'bg-rose-500/20 text-rose-300 border-rose-500/30'
                  ]"
                >
                  {{ n.nilai }}
                </span>
                <span
                  :class="[
                    'block mt-1 text-[10px] font-bold',
                    n.status === 'Tuntas' ? 'text-emerald-400' : 'text-amber-400'
                  ]"
                >
                  {{ n.status }}
                </span>
              </div>
            </div>

            <!-- Action Buttons Footer -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/5">
              <button
                type="button"
                @click="openDetail(n)"
                class="px-3 py-2 rounded-xl bg-teal-500/15 hover:bg-teal-500/25 text-teal-300 border border-teal-500/30 text-xs font-bold transition-colors min-h-[38px] flex items-center gap-1.5 active:scale-95"
              >
                <span>📄 Detail Lembar Ujian</span>
              </button>
              <button
                type="button"
                @click="resetStudentSession(n)"
                class="px-3 py-2 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 text-xs font-bold transition-colors min-h-[38px] flex items-center gap-1.5 active:scale-95"
                title="Izinkan Ujian Ulang / Remedial"
              >
                <span>🔄 Ujian Ulang</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State in Table -->
        <div v-else class="text-center py-12 rounded-2xl bg-slate-900 border border-white/10 space-y-2">
          <span class="text-3xl block">🔍</span>
          <h4 class="text-sm font-bold text-white">Tidak Ditemukan Siswa</h4>
          <p class="text-xs text-gray-400">
            Tidak ada siswa yang sesuai dengan filter kelas atau kata kunci pencarian.
          </p>
        </div>
      </div>

      <!-- ===================================================================== -->
      <!-- TAB 2: SISWA BELUM UJIAN / SUSULAN (BUG FIXED)                        -->
      <!-- ===================================================================== -->
      <div v-else-if="activeEvaluationTab === 'belum'" class="space-y-4">
        <div class="p-4 rounded-2xl bg-amber-950/30 border border-amber-500/30 flex items-start gap-3">
          <span class="text-2xl shrink-0">⏳</span>
          <div>
            <h4 class="text-sm font-black text-amber-300">Daftar Siswa Belum Mengikuti Ujian CBT</h4>
            <p class="text-xs text-gray-300 mt-0.5">
              Ditemukan <strong>{{ uncompletedStudents.length }} siswa</strong> yang belum menyelesaikan ujian CBT ini.
              <span v-if="selectedKelasFilter"> (Khusus Kelas {{ selectedKelasFilter }})</span>
            </p>
          </div>
        </div>

        <!-- Desktop View (hidden md:block) -->
        <div v-if="uncompletedStudents.length > 0" class="hidden md:block overflow-x-auto rounded-2xl bg-slate-900 border border-white/10 shadow-xl">
          <table class="w-full text-left border-collapse text-xs min-w-[600px]">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-bold text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-3 px-4 w-16 text-center">No</th>
                <th class="py-3 px-4">NIS</th>
                <th class="py-3 px-5">Nama Siswa</th>
                <th class="py-3 px-4">Kelas Rombel</th>
                <th class="py-3 px-4 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="(s, idx) in uncompletedStudents" :key="s.id" class="hover:bg-white/[0.03] transition-colors">
                <td class="py-3 px-4 text-center font-mono text-gray-400">{{ idx + 1 }}</td>
                <td class="py-3 px-4 font-mono text-gray-300">{{ s.nis }}</td>
                <td class="py-3 px-5 font-bold text-white">{{ s.nama }}</td>
                <td class="py-3 px-4 font-mono text-gray-300">{{ s.kelas }}</td>
                <td class="py-3 px-4 text-center">
                  <span class="px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30 text-[10.5px] font-bold font-mono">
                    Belum Mengikuti
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card List View for Uncompleted Students (md:hidden) -->
        <div v-if="uncompletedStudents.length > 0" class="md:hidden space-y-3">
          <div
            v-for="(s, idx) in uncompletedStudents"
            :key="'mob-uncompleted-' + s.id"
            class="p-4 rounded-2xl bg-slate-900 border border-amber-500/20 space-y-2 shadow-md"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-bold text-white text-sm">{{ s.nama }}</p>
                <div class="flex items-center gap-1.5 text-[11px] text-gray-400 font-mono mt-0.5">
                  <span>#{{ idx + 1 }}</span>
                  <span>•</span>
                  <span>NIS: {{ s.nis }}</span>
                  <span>•</span>
                  <span class="text-amber-300 font-bold">{{ s.kelas }}</span>
                </div>
              </div>
              <span class="px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30 text-[10px] font-bold font-mono shrink-0">
                Belum Ujian
              </span>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-12 rounded-2xl bg-slate-900 border border-white/10 space-y-2">
          <span class="text-3xl block">🎉</span>
          <h4 class="text-sm font-bold text-white">Semua Siswa Telah Mengerjakan!</h4>
          <p class="text-xs text-gray-400">100% siswa di kelas target telah menyelesaikan ujian CBT ini.</p>
        </div>
      </div>

      <!-- ===================================================================== -->
      <!-- TAB 3: CATATAN PELANGGARAN INTEGRITAS ANTI-CHEAT                      -->
      <!-- ===================================================================== -->
      <div v-else-if="activeEvaluationTab === 'pelanggaran'" class="space-y-4">
        <div class="p-4 rounded-2xl bg-rose-950/30 border border-rose-500/30 flex items-start gap-3">
          <span class="text-2xl shrink-0">🛡️</span>
          <div>
            <h4 class="text-sm font-black text-rose-300">Catatan Pelanggaran Integritas Sistem (Anti-Cheat)</h4>
            <p class="text-xs text-gray-300 mt-0.5">
              Log deteksi otomatis saat siswa berpindah tab browser, membuka aplikasi lain, atau keluar dari layar ujian CBT.
            </p>
          </div>
        </div>

        <!-- Desktop View (hidden md:block) -->
        <div v-if="violatingStudents.length > 0" class="hidden md:block overflow-x-auto rounded-2xl bg-slate-900 border border-white/10 shadow-xl">
          <table class="w-full text-left border-collapse text-xs min-w-[600px]">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-bold text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-3 px-4">Nama Siswa</th>
                <th class="py-3 px-4">Kelas</th>
                <th class="py-3 px-4 text-center">Jumlah Pelanggaran</th>
                <th class="py-3 px-4 text-center">Status Sesi</th>
                <th class="py-3 px-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="v in violatingStudents" :key="v.id" class="hover:bg-white/[0.03] transition-colors">
                <td class="py-3 px-4">
                  <p class="font-bold text-white">{{ v.namaSiswa }}</p>
                  <span class="text-[11px] text-gray-400 font-mono">NIS: {{ v.nis }}</span>
                </td>
                <td class="py-3 px-4 font-mono text-gray-300">{{ v.kelasNama }}</td>
                <td class="py-3 px-4 text-center font-mono">
                  <span class="px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 font-bold">
                    ⚠️ {{ v.jumlahPelanggaran }}x Terdeteksi
                  </span>
                </td>
                <td class="py-3 px-4 text-center">
                  <span class="px-2.5 py-0.5 rounded-full bg-slate-800 text-gray-300 font-mono text-[11px]">
                    {{ v.sesiStatus || 'selesai' }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right">
                  <button
                    type="button"
                    @click="openDetail(v)"
                    class="px-3 py-1.5 rounded-xl bg-teal-500/20 text-teal-300 border border-teal-500/30 text-xs font-bold min-h-[36px]"
                  >
                    Detail
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card List View for Violating Students (md:hidden) -->
        <div v-if="violatingStudents.length > 0" class="md:hidden space-y-3">
          <div
            v-for="v in violatingStudents"
            :key="'mob-violating-' + v.id"
            class="p-4 rounded-2xl bg-slate-900 border border-rose-500/30 space-y-3 shadow-md"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-bold text-white text-sm">{{ v.namaSiswa }}</p>
                <div class="flex items-center gap-1.5 text-[11px] text-gray-400 font-mono mt-0.5">
                  <span>NIS: {{ v.nis }}</span>
                  <span>•</span>
                  <span class="text-cyan-300 font-bold">{{ v.kelasNama }}</span>
                </div>
              </div>

              <span class="px-2.5 py-1 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 font-bold text-[10px] shrink-0 font-mono">
                ⚠️ {{ v.jumlahPelanggaran }}x Pelanggaran
              </span>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-white/5 text-xs">
              <span class="text-gray-400 font-mono text-[11px]">Sesi: {{ v.sesiStatus || 'selesai' }}</span>
              <button
                type="button"
                @click="openDetail(v)"
                class="px-3 py-1.5 rounded-xl bg-teal-500/20 text-teal-300 border border-teal-500/30 text-xs font-bold min-h-[36px] active:scale-95"
              >
                Detail Lembar Ujian
              </button>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-12 rounded-2xl bg-slate-900 border border-white/10 space-y-2">
          <span class="text-3xl block">🛡️</span>
          <h4 class="text-sm font-bold text-white">Tidak Ada Catatan Pelanggaran</h4>
          <p class="text-xs text-gray-400">Seluruh siswa mengerjakan ujian secara tertib tanpa terdeteksi keluar layar.</p>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- MODAL DETAIL SISWA (RINGKAS & BERSIH)                                   -->
    <!-- ======================================================================= -->
    <div v-if="showDetailModal && activeNilai" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
      <div class="w-full max-w-md rounded-2xl bg-slate-900 border border-white/10 p-5 sm:p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>📄 Rincian Lembar Hasil Siswa</span>
          </h3>
          <button @click="showDetailModal = false" class="p-1.5 rounded-lg text-gray-400 hover:text-white text-sm">✕</button>
        </div>

        <div class="space-y-3 text-xs">
          <!-- Identitas Siswa -->
          <div class="bg-white/5 p-3.5 rounded-xl space-y-0.5">
            <p class="font-bold text-white text-sm">{{ activeNilai.namaSiswa }}</p>
            <p class="text-gray-400 font-mono">NIS: {{ activeNilai.nis }} &bull; Kelas: {{ activeNilai.kelasNama }}</p>
          </div>

          <!-- Skor & Peringkat -->
          <div class="grid grid-cols-2 gap-2 text-center font-mono">
            <div class="p-3 rounded-xl bg-white/5 border border-white/5">
              <span class="text-[10px] text-gray-400 uppercase font-bold block">Nilai Akhir</span>
              <span class="text-2xl font-black text-emerald-400">{{ activeNilai.nilai }}</span>
            </div>
            <div class="p-3 rounded-xl bg-white/5 border border-white/5">
              <span class="text-[10px] text-gray-400 uppercase font-bold block">Peringkat</span>
              <span class="text-xl font-black text-amber-300 mt-1 block">
                #{{ getRank(activeNilai.id) }} / {{ completedNilais.length }}
              </span>
            </div>
          </div>

          <!-- Detail Pengerjaan -->
          <div class="p-3.5 rounded-xl bg-white/5 space-y-2 text-gray-300 font-mono">
            <div class="flex items-center justify-between">
              <span class="text-gray-400">Mapel:</span>
              <span class="font-bold text-white">{{ activeNilai.mapelNama }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400">Durasi:</span>
              <span class="font-bold text-white">{{ activeNilai.durasiMenitRiil ? activeNilai.durasiMenitRiil + ' Menit' : '-' }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400">Jawaban Benar:</span>
              <span class="font-bold text-emerald-400">{{ activeNilai.jumlahBenar }} / {{ activeNilai.jumlahSoal }} Soal</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400">Jawaban Salah:</span>
              <span class="font-bold text-rose-400">{{ activeNilai.jumlahSalah }} Soal</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400">Catatan Pelanggaran:</span>
              <span :class="(activeNilai.jumlahPelanggaran || 0) > 0 ? 'text-rose-400 font-bold' : 'text-emerald-400'">
                {{ (activeNilai.jumlahPelanggaran || 0) > 0 ? activeNilai.jumlahPelanggaran + 'x Terdeteksi' : '0 (Bersih)' }}
              </span>
            </div>
            <div class="flex items-center justify-between pt-1 border-t border-white/5">
              <span class="text-gray-400">Status:</span>
              <span :class="activeNilai.status === 'Tuntas' ? 'text-emerald-400 font-bold' : 'text-amber-400 font-bold'">
                {{ activeNilai.status }}
              </span>
            </div>
          </div>
        </div>

        <div class="pt-2 flex justify-between gap-2">
          <button
            type="button"
            @click="resetStudentSession(activeNilai)"
            class="px-3.5 py-2 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-bold hover:bg-amber-500/30 transition-colors"
          >
            🔄 Izinkan Ujian Ulang
          </button>
          <button
            type="button"
            @click="showDetailModal = false"
            class="px-4 py-2 rounded-xl bg-white/10 text-white font-bold text-xs min-h-[38px] hover:bg-white/15 transition-colors"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- MODAL DIALOG: TRANSFER NILAI CBT KE TUJUAN PEMBELAJARAN (TP)            -->
    <!-- ======================================================================= -->
    <div
      v-if="showTransferModal && selectedJadwal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto"
      @click.self="showTransferModal = false"
    >
      <div class="w-full max-w-lg rounded-3xl bg-slate-900 border border-teal-500/30 shadow-2xl p-6 space-y-5 animate-in fade-in zoom-in duration-200">
        
        <!-- Header Modal -->
        <div class="flex items-start justify-between gap-3 border-b border-white/10 pb-4">
          <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-[11px] font-bold uppercase tracking-wider mb-1 font-mono">
              <span>📥 Sinkronisasi Nilai</span>
            </div>
            <h3 class="text-lg font-black text-white">Transfer Nilai CBT ke TP</h3>
            <p class="text-xs text-gray-400 mt-0.5">
              Masukkan nilai ujian ini langsung ke ledger capaian Tujuan Pembelajaran siswa.
            </p>
          </div>
          <button
            type="button"
            @click="showTransferModal = false"
            class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-all text-xs"
          >
            ✕
          </button>
        </div>

        <!-- Info Sesi CBT Ringkas -->
        <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1.5 text-xs font-mono">
          <div class="flex items-center justify-between text-gray-300">
            <span class="text-gray-400">Judul Ujian:</span>
            <span class="font-bold text-white text-right">{{ selectedJadwal.judulUjian }}</span>
          </div>
          <div class="flex items-center justify-between text-gray-300">
            <span class="text-gray-400">Mata Pelajaran:</span>
            <span class="font-bold text-teal-300">{{ selectedJadwal.mapelNama }}</span>
          </div>
          <div class="flex items-center justify-between text-gray-300">
            <span class="text-gray-400">Target Rombel:</span>
            <span class="font-bold text-cyan-300">{{ selectedJadwal.kelasNama }}</span>
          </div>
          <div class="flex items-center justify-between text-gray-300">
            <span class="text-gray-400">Siswa yang Sudah Ujian:</span>
            <span class="font-bold text-emerald-300">{{ completedNilais.length }} Siswa</span>
          </div>
        </div>

        <!-- Pilihan Mode Transfer -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-gray-300">Mode Tujuan Transfer:</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              @click="transferForm.mode = 'existing_tp'"
              :class="[
                'p-2.5 rounded-xl border text-xs font-bold transition-all text-center',
                transferForm.mode === 'existing_tp'
                  ? 'bg-teal-500/20 border-teal-500 text-teal-300 shadow-sm'
                  : 'bg-white/5 border-white/10 text-gray-400 hover:text-white'
              ]"
            >
              📋 TP yang Sudah Ada
            </button>
            <button
              type="button"
              @click="transferForm.mode = 'new_tp'"
              :class="[
                'p-2.5 rounded-xl border text-xs font-bold transition-all text-center',
                transferForm.mode === 'new_tp'
                  ? 'bg-teal-500/20 border-teal-500 text-teal-300 shadow-sm'
                  : 'bg-white/5 border-white/10 text-gray-400 hover:text-white'
              ]"
            >
              ✨ Buat TP Baru Otomatis
            </button>
          </div>
        </div>

        <!-- Form Mode: TP yang Sudah Ada -->
        <div v-if="transferForm.mode === 'existing_tp'" class="space-y-2">
          <label class="block text-xs font-bold text-gray-300">Pilih Tujuan Pembelajaran (TP): *</label>
          <select
            v-if="availableTpsForSelectedJadwal.length > 0"
            v-model="transferForm.tp_id"
            class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white focus:border-teal-400 outline-none"
          >
            <option value="" disabled>-- Pilih Salah Satu TP --</option>
            <option
              v-for="tp in availableTpsForSelectedJadwal"
              :key="tp.id"
              :value="tp.id"
            >
              [{{ tp.kode }}] {{ tp.judul }} ({{ tp.jenis || 'Sumatif' }})
            </option>
          </select>
          <div v-else class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs">
            Belum ada TP untuk mapel {{ selectedJadwal.mapelNama }}. Silakan beralih ke mode <strong>"Buat TP Baru Otomatis"</strong>.
          </div>
        </div>

        <!-- Form Mode: Buat TP Baru Otomatis -->
        <div v-else class="space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-bold text-gray-400 mb-1">Kode TP *</label>
              <input
                v-model="transferForm.kode"
                type="text"
                placeholder="Contoh: TP-CBT-01"
                class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:border-teal-400 outline-none font-mono"
              />
            </div>
            <div>
              <label class="block text-[11px] font-bold text-gray-400 mb-1">KKTP (Target Nilai Minimum) *</label>
              <input
                v-model.number="transferForm.kktp"
                type="number"
                min="0"
                max="100"
                class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:border-teal-400 outline-none font-mono"
              />
            </div>
          </div>

          <div>
            <label class="block text-[11px] font-bold text-gray-400 mb-1">Judul / Capaian Pembelajaran *</label>
            <input
              v-model="transferForm.judul"
              type="text"
              placeholder="Contoh: Evaluasi Akhir Materi..."
              class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:border-teal-400 outline-none"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-bold text-gray-400 mb-1">Kategori Penilaian</label>
              <select
                v-model="transferForm.jenis"
                class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:border-teal-400 outline-none"
              >
                <option value="Sumatif">Sumatif</option>
                <option value="Formatif">Formatif</option>
                <option value="CBT Online">CBT Online</option>
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-gray-400 mb-1">Semester</label>
              <select
                v-model="transferForm.semester"
                class="w-full bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:border-teal-400 outline-none"
              >
                <option value="Ganjil">Semester Ganjil</option>
                <option value="Genap">Semester Genap</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Opsi Overwrite Nilai -->
        <label class="flex items-center gap-2 cursor-pointer pt-2 border-t border-white/10">
          <input
            v-model="transferForm.overwrite"
            type="checkbox"
            class="rounded border-white/20 bg-slate-800 text-teal-500 focus:ring-teal-400"
          />
          <span class="text-xs text-gray-300 font-medium">
            Perbarui / timpa nilai siswa jika sebelumnya sudah ada nilai pada TP ini.
          </span>
        </label>

        <!-- Footer Action Buttons -->
        <div class="pt-3 border-t border-white/10 flex items-center justify-end gap-2.5">
          <button
            type="button"
            @click="showTransferModal = false"
            :disabled="isTransferring"
            class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white text-xs font-bold transition-all min-h-[38px]"
          >
            Batal
          </button>
          <button
            type="button"
            @click="submitTransfer"
            :disabled="isTransferring"
            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 text-xs font-black shadow-lg shadow-emerald-500/20 transition-all min-h-[38px] flex items-center gap-2 disabled:opacity-50 active:scale-95 cursor-pointer"
          >
            <span v-if="isTransferring" class="animate-spin text-sm">⏳</span>
            <span v-else>🚀</span>
            <span>{{ isTransferring ? 'Mentransfer...' : 'Transfer Sekarang' }}</span>
          </button>
        </div>

      </div>
    </div>

  </div>
</template>
