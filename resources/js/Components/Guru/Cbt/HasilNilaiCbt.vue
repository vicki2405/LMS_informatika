<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmDelete, showSuccessAlert, showToast } from '@/Utils/sweetalert';

const props = defineProps({
  nilaiList: { type: Array, default: () => [] },
  jadwalList: { type: Array, default: () => [] },
  masterKelasList: { type: Array, default: () => [] },
  jurusanList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  tpList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  tahunAjaranList: { type: Array, default: () => [] },
  teacherSubject: { type: String, default: 'Informatika' },
});

// -----------------------------------------------------------------------------
// HIERARCHICAL NAVIGATION & FILTERS
// -----------------------------------------------------------------------------
const selectedMapel = ref('Semua');
const selectedTingkat = ref('Semua');
const selectedJadwalId = ref('');
const searchQuery = ref('');
const filterStatus = ref('');
const activeEvaluationTab = ref('nilai'); // 'nilai' | 'belum' | 'pelanggaran'

// Unique Subject List strictly restricted to THIS teacher's subjects (split comma-separated subjects)
const availableMapels = computed(() => {
  const set = new Set();
  if (props.teacherSubject) {
    String(props.teacherSubject)
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .forEach((s) => set.add(s));
  }
  props.jadwalList.forEach((j) => {
    if (j.mapelNama) {
      String(j.mapelNama)
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean)
        .forEach((s) => set.add(s));
    }
  });
  return Array.from(set).filter(Boolean);
});

function getJadwalCountByMapel(mapel) {
  return props.jadwalList.filter((j) => {
    if (!j.mapelNama) return false;
    const parts = String(j.mapelNama).toLowerCase().split(',').map((s) => s.trim());
    return parts.includes(String(mapel).toLowerCase().trim());
  }).length;
}

// Helper to extract grade level (X, XI, XII)
function extractTingkat(kelasName, tingkatProp) {
  if (tingkatProp) return tingkatProp;
  if (!kelasName) return 'X';
  const upper = String(kelasName).toUpperCase().trim();
  if (upper.startsWith('XII')) return 'XII';
  if (upper.startsWith('XI')) return 'XI';
  return 'X';
}

// -----------------------------------------------------------------------------
// FILTERED SCHEDULE OVERVIEW CARDS (When no specific exam is selected)
// -----------------------------------------------------------------------------
const filteredJadwalCards = computed(() => {
  return props.jadwalList.filter((j) => {
    const matchMapel = selectedMapel.value === 'Semua' || (
      j.mapelNama &&
      String(j.mapelNama).toLowerCase().split(',').map((s) => s.trim()).includes(selectedMapel.value.toLowerCase().trim())
    );
    const itemTingkat = extractTingkat(j.kelasNama, j.tingkat);
    const matchTingkat = selectedTingkat.value === 'Semua' || itemTingkat === selectedTingkat.value;
    const matchSearch = !searchQuery.value ||
      (j.judulUjian && j.judulUjian.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
      (j.kelasNama && j.kelasNama.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
      (j.mapelNama && j.mapelNama.toLowerCase().includes(searchQuery.value.toLowerCase()));

    return matchMapel && matchTingkat && matchSearch;
  }).map((j) => {
    // Total students in this class
    const totalSiswaKelas = props.siswaList.filter((s) => {
      return s.kelas === j.kelasNama || String(s.kelasId) === String(j.kelasId);
    }).length;

    // Students with grades in this exam
    const nilaisInJadwal = props.nilaiList.filter((n) => String(n.jadwalId) === String(j.id));
    const sudahUjianCount = nilaisInJadwal.length;
    const totalTarget = totalSiswaKelas > 0 ? totalSiswaKelas : (sudahUjianCount > 0 ? sudahUjianCount : 1);
    const partisipasiPersen = Math.min(100, Math.round((sudahUjianCount / totalTarget) * 100));

    // Stats
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

// Dual View Mode for Overview: 'cards' | 'table'
const overviewDisplayMode = ref('cards');

// Grouped by Tingkat for structured overview (Scalable to 15+ classes across levels)
const groupedJadwalCards = computed(() => {
  const map = {
    'X': [],
    'XI': [],
    'XII': [],
  };
  filteredJadwalCards.value.forEach((j) => {
    const t = extractTingkat(j.kelasNama, j.tingkat);
    if (!map[t]) map[t] = [];
    map[t].push(j);
  });
  return map;
});

// Active Tingkats that have exam sessions
const activeTingkats = computed(() => {
  const list = ['X', 'XI', 'XII'];
  if (selectedTingkat.value !== 'Semua') {
    return [selectedTingkat.value].filter((t) => (groupedJadwalCards.value[t] || []).length > 0);
  }
  return list.filter((t) => (groupedJadwalCards.value[t] || []).length > 0);
});

// Currently Selected CBT Schedule Object
const selectedJadwal = computed(() => {
  if (!selectedJadwalId.value) return null;
  return props.jadwalList.find((j) => String(j.id) === String(selectedJadwalId.value)) || null;
});

// All students belonging to the selected schedule's class
const classStudents = computed(() => {
  if (!selectedJadwal.value) return [];
  const targetKelas = selectedJadwal.value.kelasNama;
  return props.siswaList.filter((s) => {
    return s.kelas === targetKelas || String(s.kelasId) === String(selectedJadwal.value.kelasId);
  });
});

// Students who have completed the exam for the selected schedule
const completedNilais = computed(() => {
  if (!selectedJadwal.value) return [];
  return props.nilaiList.filter((n) => String(n.jadwalId) === String(selectedJadwal.value.id));
});

// Students who have NOT taken the exam yet (Belum Mengerjakan / Absen)
const uncompletedStudents = computed(() => {
  if (!selectedJadwal.value) return [];
  const completedIds = new Set(completedNilais.value.map((n) => n.siswaId));
  return classStudents.value.filter((s) => !completedIds.has(s.id));
});

// Students who committed anti-cheat violations
const violatingStudents = computed(() => {
  return completedNilais.value.filter((n) => (n.jumlahPelanggaran || 0) > 0);
});

// -----------------------------------------------------------------------------
// RANKING COMPUTATION FOR THE SELECTED SCHEDULE
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
    const matchSearch = !searchQuery.value ||
      n.namaSiswa.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      n.nis.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchStatus = !filterStatus.value || n.status === filterStatus.value;
    return matchSearch && matchStatus;
  }).sort((a, b) => {
    const rA = rankMap.value[a.id]?.rank ?? 999;
    const rB = rankMap.value[b.id]?.rank ?? 999;
    return rA - rB;
  });
});

// Top 3 Podium for Selected Schedule
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
const totalPelanggaranSesi = computed(() => {
  return completedNilais.value.reduce((acc, curr) => acc + (curr.jumlahPelanggaran || 0), 0);
});

// -----------------------------------------------------------------------------
// ACTIONS & NAVIGATION
// -----------------------------------------------------------------------------
function selectSchedule(jadwalId) {
  selectedJadwalId.value = String(jadwalId);
  activeEvaluationTab.value = 'nilai';
  searchQuery.value = '';
  filterStatus.value = '';
}

function backToOverview() {
  selectedJadwalId.value = '';
  searchQuery.value = '';
  filterStatus.value = '';
}

function resetAllFilters() {
  selectedMapel.value = 'Semua';
  selectedTingkat.value = 'Semua';
  searchQuery.value = '';
  filterStatus.value = '';
}

// Reset Student Session for Retake / Remedial
function resetStudentSession(n) {
  if (!n) return;
  confirmDelete({
    title: `Izinkan Ujian Ulang untuk ${n.namaSiswa}?`,
    text: `Lembar jawaban dan nilai saat ini (${n.nilai}) akan direset sehingga siswa dapat masuk kembali ke ruang CBT untuk perbaikan/remedial.`,
    confirmButtonText: 'Ya, Reset & Izinkan Ulang!',
  }).then((res) => {
    if (res.isConfirmed) {
      if (n.sesiId) {
        router.post(route('guru.cbt.sesi.reset', n.sesiId), {}, {
          preserveScroll: true,
          onSuccess: () => {
            showSuccessAlert('Sesi Berhasil Direset 🎉', `Siswa ${n.namaSiswa} kini dapat masuk kembali untuk mengulang ujian CBT.`);
          },
          onError: () => {
            showToast('Gagal mereset sesi siswa.', 'error');
          }
        });
      } else {
        router.delete(route('guru.cbt.nilai.delete', n.id), {
          preserveScroll: true,
          onSuccess: () => {
            showSuccessAlert('Nilai Direset', `Nilai ${n.namaSiswa} telah dihapus. Siswa dapat mengulang pengerjaan CBT.`);
          },
        });
      }
    }
  });
}

function deleteNilai(id) {
  confirmDelete({
    title: 'Hapus Rekap Nilai Siswa?',
    text: 'Hasil pengerjaan dan skor siswa ini akan dihapus permanen!',
    confirmButtonText: 'Ya, Hapus!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(route('guru.cbt.nilai.delete', id), {
        preserveScroll: true,
        onSuccess: () => showSuccessAlert('Berhasil Dihapus', 'Data nilai ujian berhasil dihapus.'),
      });
    }
  });
}

// -----------------------------------------------------------------------------
// MODALS STATE: DETAIL SISWA & TRANSFER TO NILAI TP
// -----------------------------------------------------------------------------
const showDetailModal = ref(false);
const activeNilai = ref(null);

function openDetail(n) {
  activeNilai.value = n;
  showDetailModal.value = true;
}

const showTransferModal = ref(false);
const isTransferring = ref(false);
const transferForm = ref({
  jadwal_id: '',
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

const selectedTransferJadwal = computed(() => {
  return props.jadwalList.find(j => String(j.id) === String(transferForm.value.jadwal_id));
});

const matchingTpList = computed(() => {
  const j = selectedTransferJadwal.value;
  if (!j) return props.tpList;
  const targetMapel = (j.mapelNama || '').toLowerCase();
  const matched = props.tpList.filter(t => (t.mapel || '').toLowerCase() === targetMapel);
  return matched.length > 0 ? matched : props.tpList;
});

function openTransferModal(targetJadwalId = null) {
  const jId = targetJadwalId || selectedJadwalId.value || props.jadwalList[0]?.id || '';
  const currentJadwal = props.jadwalList.find(j => String(j.id) === String(jId));
  const activeTa = props.tahunAjaranList.find(t => t.isAktif) || props.tahunAjaranList[0];

  const matchedTp = props.tpList.find(t => 
    currentJadwal && (t.mapel || '').toLowerCase() === (currentJadwal.mapelNama || '').toLowerCase()
  ) || props.tpList[0];

  transferForm.value = {
    jadwal_id: jId,
    mode: matchedTp ? 'existing_tp' : 'new_tp',
    tp_id: matchedTp?.id || '',
    kode: 'TP-CBT' + (props.tpList.length + 1),
    judul: currentJadwal ? `Ujian CBT - ${currentJadwal.judulUjian}` : 'Ujian CBT',
    mapel: currentJadwal?.mapelNama || availableMapels.value[0] || 'Informatika',
    jenis: 'Sumatif',
    kktp: 75,
    semester: 'Ganjil',
    tahun_ajaran_id: activeTa?.id || '',
    overwrite: true,
  };

  showTransferModal.value = true;
}

function onTransferJadwalChange() {
  const currentJadwal = selectedTransferJadwal.value;
  if (currentJadwal) {
    transferForm.value.mapel = currentJadwal.mapelNama || 'Informatika';
    transferForm.value.judul = `Ujian CBT - ${currentJadwal.judulUjian}`;
    const m = matchingTpList.value[0];
    if (m) {
      transferForm.value.tp_id = m.id;
    }
  }
}

function submitTransferToNilai() {
  if (!transferForm.value.jadwal_id) {
    showToast('Pilih sesi ujian CBT terlebih dahulu', 'error');
    return;
  }
  if (transferForm.value.mode === 'existing_tp' && !transferForm.value.tp_id) {
    showToast('Pilih Tujuan Pembelajaran (TP) tujuan transfer', 'error');
    return;
  }
  if (transferForm.value.mode === 'new_tp' && !transferForm.value.judul) {
    showToast('Nama Tujuan Pembelajaran wajib diisi', 'error');
    return;
  }

  isTransferring.value = true;
  router.post(route('guru.cbt.transfer-nilai-tp'), transferForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      isTransferring.value = false;
      showTransferModal.value = false;
      
      if (typeof window !== 'undefined' && window.Swal) {
        window.Swal.fire({
          title: 'Transfer Nilai Berhasil! 🎉',
          html: `
            <div class="text-left text-xs sm:text-sm space-y-2 text-slate-300">
              <p class="text-white font-semibold">Hasil nilai ujian CBT berhasil dimasukkan ke menu Penilaian TP Siswa sesuai kelas dan mata pelajaran.</p>
              <p class="text-emerald-400 font-bold">Data nilai tersimpan secara permanen dan otomatis sinkron dengan rapor & ledger guru.</p>
            </div>
          `,
          icon: 'success',
          showCancelButton: true,
          confirmButtonColor: '#10b981',
          cancelButtonColor: '#64748b',
          confirmButtonText: 'Buka Lembar Penilaian TP ➔',
          cancelButtonText: 'Tetap di Sini',
          background: '#0f172a',
          color: '#f8fafc',
          customClass: {
            popup: 'border border-emerald-500/30 rounded-3xl backdrop-blur-xl',
            confirmButton: 'px-5 py-2.5 rounded-xl font-bold',
            cancelButton: 'px-5 py-2.5 rounded-xl font-bold'
          }
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = route('guru.dashboard') + '?tab=nilai';
          }
        });
      } else {
        showSuccessAlert('Transfer Nilai Berhasil', 'Nilai CBT berhasil dimasukkan ke Penilaian TP.');
      }
    },
    onError: (err) => {
      isTransferring.value = false;
      const firstErr = Object.values(err)[0] || 'Gagal mentransfer nilai CBT ke TP.';
      showToast(firstErr, 'error');
    },
    onFinish: () => {
      isTransferring.value = false;
    }
  });
}
</script>

<template>
  <div class="space-y-6">

    <!-- ======================================================================= -->
    <!-- HEADER HERO BANNER                                                      -->
    <!-- ======================================================================= -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-950/70 via-slate-900 to-teal-950/70 border border-emerald-500/20 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
      <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-black tracking-wider uppercase mb-2">
            <span>📊 Evaluasi &amp; Berita Acara CBT</span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            Hasil &amp; Rekapitulasi Ujian CBT
          </h1>
          <p class="text-xs sm:text-sm text-gray-400 mt-1 max-w-2xl leading-relaxed">
            Rangkuman lengkap pasca-ujian: pantau waktu pelaksanaan, kehadiran peserta, siswa yang belum ujian, catatan pelanggaran integritas, dan peringkat nilai terstruktur per mata pelajaran dan rombel kelas.
          </p>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- TOP HIERARCHICAL NAVIGATION (MAPEL & JENJANG FILTER)                    -->
    <!-- ======================================================================= -->
    <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/80 border border-white/10 backdrop-blur-xl space-y-4 shadow-lg">
      
      <!-- Baris 1: Mata Pelajaran Guru (Hanya Mapel Akun Guru yang Login) -->
      <div class="flex items-center gap-3 flex-wrap">
        <span class="text-[11px] font-black uppercase tracking-wider text-teal-400 font-mono">
          1️⃣ Mata Pelajaran Guru:
        </span>

        <!-- Jika hanya 1 mapel (misal: Informatika) -->
        <div v-if="availableMapels.length <= 1" class="flex items-center gap-2">
          <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-teal-500/15 text-teal-300 border border-teal-500/30 text-xs font-black font-mono shadow-sm">
            <span>📖</span>
            <span>{{ availableMapels[0] || 'Informatika' }}</span>
          </span>
          <span class="text-xs text-gray-400 font-mono">({{ props.jadwalList.length }} Sesi Ujian Aktif)</span>
        </div>

        <!-- Jika guru mengampu lebih dari 1 mapel -->
        <div v-else class="flex items-center gap-2 flex-wrap text-xs">
          <button
            type="button"
            @click="selectedMapel = 'Semua'"
            :class="[
              'px-3.5 py-1.5 rounded-xl font-bold transition-all min-h-[36px] flex items-center gap-1.5',
              selectedMapel === 'Semua'
                ? 'bg-teal-500 text-slate-950 shadow-md shadow-teal-500/20 font-black'
                : 'bg-white/5 hover:bg-white/10 text-gray-300'
            ]"
          >
            <span>Semua Mapel Guru</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/20 font-mono">{{ props.jadwalList.length }}</span>
          </button>

          <button
            v-for="mapel in availableMapels"
            :key="mapel"
            type="button"
            @click="selectedMapel = mapel"
            :class="[
              'px-3.5 py-1.5 rounded-xl font-bold transition-all min-h-[36px] flex items-center gap-1.5',
              selectedMapel === mapel
                ? 'bg-teal-500 text-slate-950 shadow-md shadow-teal-500/20 font-black'
                : 'bg-white/5 hover:bg-white/10 text-gray-300'
            ]"
          >
            <span>📖 {{ mapel }}</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/20 font-mono">{{ getJadwalCountByMapel(mapel) }}</span>
          </button>
        </div>
      </div>

      <!-- Baris 2: Pilihan Jenjang/Tingkat & Quick Search -->
      <div class="pt-2 border-t border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
          <span class="text-[11px] font-black uppercase tracking-wider text-gray-400 mr-1 shrink-0 font-mono">
            2️⃣ Jenjang:
          </span>
          <button
            type="button"
            v-for="tingkat in ['Semua', 'X', 'XI', 'XII']"
            :key="tingkat"
            @click="selectedTingkat = tingkat"
            :class="[
              'px-3 py-1.5 rounded-xl font-bold transition-all shrink-0 text-xs min-h-[34px]',
              selectedTingkat === tingkat
                ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 font-black'
                : 'bg-white/5 text-gray-400 hover:text-white border border-transparent'
            ]"
          >
            {{ tingkat === 'Semua' ? 'Semua Jenjang' : 'Kelas ' + tingkat }}
          </button>
        </div>

        <!-- Search Bar -->
        <div class="relative min-w-[240px]">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari ujian, kelas, atau siswa..."
            class="w-full pl-9 pr-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-teal-400 min-h-[36px]"
          />
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- VIEW A: OVERVIEW CARDS PER SESI KELAS (Saat Belum Memilih Sesi Ujian)   -->
    <!-- ======================================================================= -->
    <div v-if="!selectedJadwalId" class="space-y-4">
      
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h3 class="text-sm font-black text-gray-200 uppercase tracking-wider font-mono flex items-center gap-2">
          <span>📁 Daftar Sesi Ujian per Kelas</span>
          <span class="text-xs text-teal-400">({{ filteredJadwalCards.length }} Sesi)</span>
        </h3>

        <div class="flex items-center gap-3 flex-wrap">
          <!-- Dual-View Toggle: Kartu Grid vs Matriks Tabel (Skalabel untuk 15+ Kelas) -->
          <div class="inline-flex items-center p-1 rounded-xl bg-slate-800/90 border border-white/10 text-xs font-mono">
            <button
              type="button"
              @click="overviewDisplayMode = 'cards'"
              :class="[
                'px-3 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5 min-h-[32px] cursor-pointer',
                overviewDisplayMode === 'cards'
                  ? 'bg-teal-500 text-slate-950 shadow-sm font-black'
                  : 'text-gray-400 hover:text-white'
              ]"
            >
              <span>🗂️</span>
              <span>Kartu Grid</span>
            </button>
            <button
              type="button"
              @click="overviewDisplayMode = 'table'"
              :class="[
                'px-3 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5 min-h-[32px] cursor-pointer',
                overviewDisplayMode === 'table'
                  ? 'bg-teal-500 text-slate-950 shadow-sm font-black'
                  : 'text-gray-400 hover:text-white'
              ]"
            >
              <span>📊</span>
              <span>Matriks Tabel</span>
            </button>
          </div>

          <button
            v-if="selectedMapel !== 'Semua' || selectedTingkat !== 'Semua' || searchQuery"
            type="button"
            @click="resetAllFilters"
            class="text-xs text-amber-400 hover:underline font-semibold"
          >
            ✕ Reset Filter
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredJadwalCards.length === 0" class="text-center py-16 px-4 rounded-3xl bg-slate-900/60 border border-white/10">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-teal-500/10 text-teal-400 flex items-center justify-center text-3xl mb-3">
          📚
        </div>
        <h3 class="text-base font-bold text-white">Tidak Ditemukan Sesi Ujian</h3>
        <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
          Belum ada jadwal CBT untuk mata pelajaran atau jenjang kelas yang Anda pilih. Silakan sesuaikan filter di atas.
        </p>
      </div>

      <!-- MODE 1: KARTU GRID TERSTRUKTUR (DIKELOMPOKKAN PER JENJANG KELAS) -->
      <div v-else-if="overviewDisplayMode === 'cards'" class="space-y-6">
        <div v-for="tingkat in activeTingkats" :key="tingkat" class="space-y-3">
          <!-- Section Header per Jenjang -->
          <div class="flex items-center justify-between border-b border-white/10 pb-2">
            <div class="flex items-center gap-2">
              <span class="px-3 py-1 rounded-xl bg-teal-500/15 border border-teal-500/30 text-teal-300 font-mono text-xs font-black">
                📁 Jenjang Kelas {{ tingkat }}
              </span>
              <span class="text-xs text-gray-400 font-mono">
                ({{ (groupedJadwalCards[tingkat] || []).length }} Rombel Sesi Ujian)
              </span>
            </div>
            <span class="text-[11px] text-gray-400 font-mono hidden sm:inline-block">
              Partisipasi: {{ (groupedJadwalCards[tingkat] || []).reduce((acc, c) => acc + c.sudahUjianCount, 0) }} Siswa Selesai
            </span>
          </div>

          <!-- Cards Grid for this Tingkat -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="j in groupedJadwalCards[tingkat]"
              :key="j.id"
              class="p-5 rounded-3xl bg-slate-900/85 border border-white/10 hover:border-teal-500/40 transition-all flex flex-col justify-between shadow-xl backdrop-blur-xl group relative overflow-hidden"
            >
              <!-- Top Badges -->
              <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                  <span class="px-2.5 py-1 rounded-xl bg-teal-500/15 text-teal-300 border border-teal-500/30 text-[11px] font-black font-mono">
                    📖 {{ j.mapelNama }}
                  </span>
                  <span class="px-2.5 py-1 rounded-xl bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 text-[11px] font-black font-mono">
                    👥 {{ j.kelasNama }}
                  </span>
                </div>

                <div>
                  <h4 class="text-base font-black text-white group-hover:text-teal-300 transition-colors line-clamp-1">
                    {{ j.judulUjian }}
                  </h4>
                  <p class="text-xs text-gray-400 mt-0.5">
                    {{ j.jurusanNama }} ({{ j.jurusanKode }})
                  </p>
                </div>

                <!-- Schedule & Duration -->
                <div class="p-3 rounded-2xl bg-white/5 border border-white/5 text-xs text-gray-300 space-y-1.5 font-mono">
                  <div class="flex items-center justify-between">
                    <span class="text-gray-400">⏰ Pelaksanaan:</span>
                    <span class="font-bold text-white text-[11px]">{{ j.waktuMulai ? j.waktuMulai : 'Fleksibel' }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-gray-400">⏱️ Durasi:</span>
                    <span class="font-bold text-white">{{ j.durasiMenit }} Menit</span>
                  </div>
                </div>

                <!-- Participation Progress Bar -->
                <div class="space-y-1.5 pt-1">
                  <div class="flex items-center justify-between text-xs font-mono">
                    <span class="text-gray-400 font-bold">Partisipasi Siswa:</span>
                    <span class="text-emerald-400 font-black">
                      {{ j.sudahUjianCount }} / {{ j.totalSiswaKelas }} Siswa ({{ j.partisipasiPersen }}%)
                    </span>
                  </div>
                  <div class="w-full h-2 rounded-full bg-white/10 overflow-hidden">
                    <div
                      class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-400 transition-all duration-500"
                      :style="{ width: j.partisipasiPersen + '%' }"
                    ></div>
                  </div>
                </div>

                <!-- Stats Pill Summary -->
                <div class="grid grid-cols-3 gap-2 text-center pt-2 font-mono">
                  <div class="p-2 rounded-xl bg-white/5">
                    <span class="text-[9px] text-gray-400 block uppercase font-bold">Rata-Rata</span>
                    <span class="text-sm font-black text-cyan-300">{{ j.avgScore }}</span>
                  </div>
                  <div class="p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                    <span class="text-[9px] text-emerald-400 block uppercase font-bold">Tuntas</span>
                    <span class="text-sm font-black text-emerald-300">{{ j.tuntasCount }}</span>
                  </div>
                  <div class="p-2 rounded-xl bg-amber-500/10 border border-amber-500/20">
                    <span class="text-[9px] text-amber-400 block uppercase font-bold">Belum Ujian</span>
                    <span class="text-sm font-black text-amber-300">{{ j.belumUjianCount }}</span>
                  </div>
                </div>

                <!-- Violations Alert Badge if any -->
                <div v-if="j.pelanggaranCount > 0" class="px-3 py-1 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-[10.5px] font-bold flex items-center gap-1.5">
                  <span>⚠️</span>
                  <span>{{ j.pelanggaranCount }} Pelanggaran Integritas Terdeteksi</span>
                </div>
              </div>

              <!-- Card Actions (Strictly 1 Action Button to View Details) -->
              <div class="pt-4 mt-4 border-t border-white/10 flex items-center">
                <button
                  type="button"
                  @click="selectSchedule(j.id)"
                  class="w-full py-2.5 px-3 rounded-xl bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 border border-teal-500/40 text-xs font-black transition-all flex items-center justify-center gap-1.5 min-h-[40px] cursor-pointer"
                >
                  <span>Buka Evaluasi Kelas</span>
                  <span>➔</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- MODE 2: TABEL MATRIKS RINGKAS (UNTUK BANYAK KELAS / 15+ ROMBEL) -->
      <div v-else-if="overviewDisplayMode === 'table'" class="space-y-4">
        <!-- Desktop Table (hidden md:block) -->
        <div class="hidden md:block overflow-hidden rounded-2xl bg-slate-900/80 border border-white/10 shadow-xl backdrop-blur-xl">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-3.5 px-4">Mata Pelajaran</th>
                <th class="py-3.5 px-4">Kelas &amp; Jurusan</th>
                <th class="py-3.5 px-5">Judul Ujian</th>
                <th class="py-3.5 px-4 text-center">Durasi</th>
                <th class="py-3.5 px-5 text-center min-w-[180px]">Partisipasi Siswa</th>
                <th class="py-3.5 px-4 text-center">Rata-Rata</th>
                <th class="py-3.5 px-4 text-center">Ketuntasan</th>
                <th class="py-3.5 px-4 text-center">Integritas</th>
                <th class="py-3.5 px-5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="j in filteredJadwalCards" :key="j.id" class="hover:bg-white/[0.03] transition-colors group">
                <td class="py-3.5 px-4 font-mono">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-teal-500/15 text-teal-300 border border-teal-500/30 text-xs font-bold">
                    📖 {{ j.mapelNama }}
                  </span>
                </td>
                <td class="py-3.5 px-4">
                  <span class="font-bold text-white block">{{ j.kelasNama }}</span>
                  <span class="text-[11px] text-gray-400 font-mono">{{ j.jurusanNama }}</span>
                </td>
                <td class="py-3.5 px-5">
                  <p class="font-bold text-white group-hover:text-teal-300 transition-colors">{{ j.judulUjian }}</p>
                  <span class="text-[11px] text-gray-500 font-mono">{{ j.waktuMulai ? j.waktuMulai : 'Fleksibel' }}</span>
                </td>
                <td class="py-3.5 px-4 text-center font-mono text-gray-300">
                  {{ j.durasiMenit }} Menit
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
                </td>
                <td class="py-3.5 px-4 text-center font-mono text-[11px]">
                  <span class="text-emerald-400 font-bold block">{{ j.tuntasCount }} Tuntas</span>
                  <span v-if="j.belumUjianCount > 0" class="text-amber-400 block text-[10px]">{{ j.belumUjianCount }} Belum</span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span v-if="j.pelanggaranCount > 0" class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/40 text-[10px] font-mono font-bold">
                    ⚠️ {{ j.pelanggaranCount }}x
                  </span>
                  <span v-else class="text-gray-500 font-mono text-[11px]">✓ Aman</span>
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

        <!-- Mobile Card List for Table Mode (md:hidden) -->
        <div class="md:hidden space-y-3">
          <div
            v-for="j in filteredJadwalCards"
            :key="j.id"
            class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 space-y-3 shadow-lg"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="px-2 py-0.5 rounded-md bg-teal-500/15 text-teal-300 text-[10.5px] font-bold font-mono">
                    {{ j.mapelNama }}
                  </span>
                  <span class="px-2 py-0.5 rounded-md bg-indigo-500/15 text-indigo-300 text-[10.5px] font-bold font-mono">
                    {{ j.kelasNama }}
                  </span>
                </div>
                <h4 class="font-black text-white text-sm mt-1">{{ j.judulUjian }}</h4>
              </div>
              <span class="px-2.5 py-1 rounded-xl bg-white/5 font-black text-xs font-mono text-cyan-300">
                ⭐ {{ j.avgScore }}
              </span>
            </div>

            <div class="space-y-1 pt-1 font-mono text-xs">
              <div class="flex items-center justify-between text-gray-400 text-[11px]">
                <span>Partisipasi ({{ j.sudahUjianCount }}/{{ j.totalSiswaKelas }} Siswa):</span>
                <span class="text-emerald-400 font-bold">{{ j.partisipasiPersen }}%</span>
              </div>
              <div class="w-full h-1.5 rounded-full bg-white/10 overflow-hidden">
                <div class="h-full rounded-full bg-teal-500" :style="{ width: j.partisipasiPersen + '%' }"></div>
              </div>
            </div>

            <div class="pt-2 border-t border-white/5 flex items-center justify-between">
              <div class="text-[11px] font-mono text-gray-400">
                <span v-if="j.pelanggaranCount > 0" class="text-rose-400 font-bold">⚠️ {{ j.pelanggaranCount }} Pelanggaran</span>
                <span v-else class="text-emerald-400">✓ Integritas Aman</span>
              </div>
              <button
                type="button"
                @click="selectSchedule(j.id)"
                class="px-3 py-1.5 rounded-xl bg-teal-500/20 text-teal-300 border border-teal-500/30 text-xs font-bold min-h-[36px] flex items-center gap-1 cursor-pointer"
              >
                <span>Buka Evaluasi ➔</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- VIEW B: BERITA ACARA & EVALUASI KELAS (Saat Sesi Ujian Dipilih)          -->
    <!-- ======================================================================= -->
    <div v-else-if="selectedJadwal" class="space-y-6">

      <!-- Breadcrumb Nav & Action -->
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <button
          type="button"
          @click="backToOverview"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white text-xs font-bold transition-all min-h-[38px]"
        >
          <span>«</span>
          <span>Kembali ke Daftar Semua Kelas</span>
        </button>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="openTransferModal(selectedJadwal.id)"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-black text-xs shadow-md shadow-indigo-500/20 min-h-[38px]"
          >
            <span>📤 Masukkan Nilai Kelas Ini ke TP</span>
          </button>
        </div>
      </div>

      <!-- Detail Sesi Ujian Banner (Berita Acara) -->
      <div class="p-5 sm:p-6 rounded-3xl bg-slate-900/90 border border-teal-500/30 shadow-2xl backdrop-blur-xl space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/10 pb-4">
          <div>
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
              <span class="px-2.5 py-0.5 rounded-full bg-teal-500/20 text-teal-300 border border-teal-500/40 text-xs font-black font-mono">
                📖 {{ selectedJadwal.mapelNama }}
              </span>
              <span class="px-2.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 text-xs font-black font-mono">
                👥 {{ selectedJadwal.kelasNama }}
              </span>
              <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-gray-300 text-xs font-mono">
                🏢 {{ selectedJadwal.jurusanNama }}
              </span>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white">
              {{ selectedJadwal.judulUjian }}
            </h2>
          </div>

          <!-- Schedule Meta Pills -->
          <div class="flex items-center gap-2 flex-wrap text-xs font-mono">
            <div class="px-3 py-1.5 rounded-xl bg-white/5 border border-white/10">
              <span class="text-gray-400 text-[10px] block">Waktu Mulai</span>
              <span class="font-bold text-teal-300">{{ selectedJadwal.waktuMulai || 'Fleksibel' }}</span>
            </div>
            <div class="px-3 py-1.5 rounded-xl bg-white/5 border border-white/10">
              <span class="text-gray-400 text-[10px] block">Durasi</span>
              <span class="font-bold text-white">{{ selectedJadwal.durasiMenit }} Menit</span>
            </div>
            <div class="px-3 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/20">
              <span class="text-amber-400 text-[10px] block">Token CBT</span>
              <span class="font-black text-amber-300">{{ selectedJadwal.token }}</span>
            </div>
          </div>
        </div>

        <!-- 4 KPI Summary Grid Sesi Ini -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-0.5">
            <span class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider block">Partisipasi Ujian</span>
            <p class="text-xl font-black text-white font-mono">
              {{ totalSudahUjian }} <span class="text-xs text-gray-400 font-normal">/ {{ totalTargetKelas }} Siswa</span>
            </p>
            <span class="text-[10.5px] text-emerald-400 font-bold block">
              ✓ {{ Math.min(100, Math.round((totalSudahUjian / (totalTargetKelas || 1)) * 100)) }}% Selesai
            </span>
          </div>

          <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-0.5">
            <span class="text-[10px] font-extrabold text-cyan-400 uppercase tracking-wider block">Rata-Rata Kelas</span>
            <p class="text-xl font-black text-cyan-300 font-mono">
              {{ rataRataSkor }} <span class="text-xs text-gray-400 font-normal">/ 100</span>
            </p>
            <span class="text-[10.5px] text-gray-400 font-mono block">
              Tertinggi: {{ skorTertinggi }} • Terendah: {{ skorTerendah }}
            </span>
          </div>

          <div class="p-3.5 rounded-2xl bg-slate-900 border border-emerald-500/30 space-y-0.5">
            <span class="text-[10px] font-extrabold text-emerald-400 uppercase tracking-wider block">Ketuntasan (KKM 75)</span>
            <p class="text-xl font-black text-emerald-300 font-mono">
              {{ totalTuntas }} <span class="text-xs text-emerald-400/70 font-normal">Tuntas</span>
            </p>
            <span class="text-[10.5px] text-rose-300 font-bold block">
              {{ totalBelumTuntas }} Siswa Belum Tuntas
            </span>
          </div>

          <div class="p-3.5 rounded-2xl bg-slate-900 border border-rose-500/30 space-y-0.5">
            <span class="text-[10px] font-extrabold text-rose-400 uppercase tracking-wider block">Integritas Ujian</span>
            <p class="text-xl font-black text-rose-300 font-mono">
              {{ totalPelanggaranSesi }} <span class="text-xs text-rose-400/70 font-normal">Pelanggaran</span>
            </p>
            <span class="text-[10.5px] text-gray-400 block">
              {{ violatingStudents.length }} siswa melanggar
            </span>
          </div>
        </div>
      </div>

      <!-- ===================================================================== -->
      <!-- 3 TAB NAVIGASI EVALUASI KEGIATAN UJIAN                                 -->
      <!-- ===================================================================== -->
      <div class="flex items-center gap-2 border-b border-white/10 pb-2 text-xs font-mono overflow-x-auto">
        <button
          type="button"
          @click="activeEvaluationTab = 'nilai'"
          :class="[
            'px-4 py-2 rounded-xl font-black transition-all flex items-center gap-2 shrink-0 min-h-[38px]',
            activeEvaluationTab === 'nilai'
              ? 'bg-teal-500 text-slate-950 shadow-md shadow-teal-500/20'
              : 'bg-white/5 hover:bg-white/10 text-gray-300'
          ]"
        >
          <span>🏆 Rangking &amp; Nilai</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] bg-black/20 font-mono">{{ completedNilais.length }}</span>
        </button>

        <button
          type="button"
          @click="activeEvaluationTab = 'belum'"
          :class="[
            'px-4 py-2 rounded-xl font-black transition-all flex items-center gap-2 shrink-0 min-h-[38px]',
            activeEvaluationTab === 'belum'
              ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20'
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
            'px-4 py-2 rounded-xl font-black transition-all flex items-center gap-2 shrink-0 min-h-[38px]',
            activeEvaluationTab === 'pelanggaran'
              ? 'bg-rose-500 text-white shadow-md shadow-rose-500/20'
              : 'bg-white/5 hover:bg-white/10 text-gray-300'
          ]"
        >
          <span>⚠️ Catatan Pelanggaran</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] bg-black/20 font-mono">{{ violatingStudents.length }}</span>
        </button>
      </div>

      <!-- ===================================================================== -->
      <!-- TAB 1: RANGKING & HASIL NILAI SISWA (SUDAH MENGERJAKAN)               -->
      <!-- ===================================================================== -->
      <div v-if="activeEvaluationTab === 'nilai'" class="space-y-6">

        <!-- Top 3 Podium Showcase Kelas Ini -->
        <div v-if="topThreePodium.length > 0" class="p-5 sm:p-6 rounded-3xl bg-gradient-to-b from-amber-500/10 via-slate-900/90 to-slate-900 border border-amber-500/30 shadow-2xl backdrop-blur-xl space-y-4">
          <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
            <div class="flex items-center gap-2.5">
              <span class="text-2xl">🏆</span>
              <div>
                <h3 class="text-base sm:text-lg font-black text-transparent bg-clip-text bg-gradient-to-r from-yellow-300 via-amber-200 to-amber-400">
                  Podium 3 Besar Teratas Kelas {{ selectedJadwal.kelasNama }}
                </h3>
                <p class="text-xs text-gray-400 font-mono">Peringkat nilai tertinggi dari total {{ completedNilais.length }} peserta ujian</p>
              </div>
            </div>
            <span class="px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 font-mono text-xs font-black shrink-0">
              ⭐ TOP 3 KELAS
            </span>
          </div>

          <!-- Podium Cards Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
            <!-- 2nd Place -->
            <div v-if="topThreePodium[1]" class="order-2 sm:order-1 p-4 rounded-2xl bg-slate-800/80 border border-slate-400/40 text-center flex flex-col justify-between shadow-lg">
              <div class="space-y-1">
                <div class="w-12 h-12 mx-auto rounded-full bg-slate-400/20 border-2 border-slate-300 flex items-center justify-center text-xl shadow-inner">🥈</div>
                <span class="inline-block px-2.5 py-0.5 rounded-full bg-slate-400/20 text-slate-200 font-mono text-[10px] font-black">JUARA 2</span>
                <h4 class="font-black text-white text-sm line-clamp-1">{{ topThreePodium[1].namaSiswa }}</h4>
                <p class="text-[11px] text-gray-400 font-mono">NIS: {{ topThreePodium[1].nis }}</p>
              </div>
              <div class="mt-3 pt-2 border-t border-white/10 flex items-center justify-around font-mono">
                <span class="text-xl font-black text-slate-200">{{ topThreePodium[1].nilai }}</span>
                <span class="text-[10px] text-emerald-400 font-bold">✓ {{ topThreePodium[1].jumlahBenar }} Benar</span>
              </div>
            </div>

            <!-- 1st Place (Champion) -->
            <div v-if="topThreePodium[0]" class="order-1 sm:order-2 p-5 rounded-2xl bg-gradient-to-b from-amber-500/20 to-slate-800/90 border-2 border-amber-400 text-center flex flex-col justify-between shadow-2xl shadow-amber-500/20 relative scale-100 sm:scale-105 z-10">
              <div class="space-y-1">
                <div class="w-14 h-14 mx-auto rounded-full bg-amber-400/25 border-2 border-amber-300 flex items-center justify-center text-2xl shadow-lg shadow-amber-400/30 animate-pulse">👑</div>
                <span class="inline-block px-3 py-0.5 rounded-full bg-amber-400/30 text-amber-200 font-mono text-xs font-black border border-amber-400/50">🥇 JUARA 1 (CHAMPION)</span>
                <h4 class="font-black text-white text-base line-clamp-1">{{ topThreePodium[0].namaSiswa }}</h4>
                <p class="text-xs text-amber-200/70 font-mono">NIS: {{ topThreePodium[0].nis }}</p>
              </div>
              <div class="mt-3 pt-2.5 border-t border-amber-400/30 flex items-center justify-around font-mono">
                <span class="text-2xl font-black text-amber-300">{{ topThreePodium[0].nilai }}</span>
                <span class="text-xs text-emerald-300 font-black">✓ {{ topThreePodium[0].jumlahBenar }} Benar</span>
              </div>
            </div>

            <!-- 3rd Place -->
            <div v-if="topThreePodium[2]" class="order-3 p-4 rounded-2xl bg-slate-800/80 border border-amber-700/50 text-center flex flex-col justify-between shadow-lg">
              <div class="space-y-1">
                <div class="w-12 h-12 mx-auto rounded-full bg-amber-700/20 border-2 border-amber-600 flex items-center justify-center text-xl shadow-inner">🥉</div>
                <span class="inline-block px-2.5 py-0.5 rounded-full bg-amber-700/25 text-amber-300 font-mono text-[10px] font-black">JUARA 3</span>
                <h4 class="font-black text-white text-sm line-clamp-1">{{ topThreePodium[2].namaSiswa }}</h4>
                <p class="text-[11px] text-gray-400 font-mono">NIS: {{ topThreePodium[2].nis }}</p>
              </div>
              <div class="mt-3 pt-2 border-t border-white/10 flex items-center justify-around font-mono">
                <span class="text-xl font-black text-amber-300">{{ topThreePodium[2].nilai }}</span>
                <span class="text-[10px] text-emerald-400 font-bold">✓ {{ topThreePodium[2].jumlahBenar }} Benar</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Sub-Bar Nilai -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 font-mono">Filter Status:</span>
            <select
              v-model="filterStatus"
              class="py-1.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs focus:outline-none focus:border-teal-400 min-h-[36px]"
            >
              <option value="">Semua Status</option>
              <option value="Tuntas">Tuntas (&ge; 75)</option>
              <option value="Belum Tuntas">Belum Tuntas (&lt; 75)</option>
            </select>
          </div>
          <span class="text-xs text-gray-400 font-mono">Menampilkan {{ sortedScheduleGrades.length }} siswa</span>
        </div>

        <!-- Desktop View: Table (hidden md:block) -->
        <div v-if="sortedScheduleGrades.length > 0" class="hidden md:block overflow-hidden rounded-2xl bg-slate-900/80 border border-white/10 shadow-xl backdrop-blur-xl">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-4 px-4 text-center">🏆 Rangking</th>
                <th class="py-4 px-5">Nama Siswa (NIS)</th>
                <th class="py-4 px-4 text-center">Waktu Pengerjaan</th>
                <th class="py-4 px-4 text-center">Jawaban (B / S)</th>
                <th class="py-4 px-4 text-center">⚠️ Integritas</th>
                <th class="py-4 px-4 text-center">Nilai Akhir</th>
                <th class="py-4 px-4 text-center">Status</th>
                <th class="py-4 px-5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-xs">
              <tr v-for="n in sortedScheduleGrades" :key="n.id" class="hover:bg-white/[0.03] transition-colors group">
                <!-- Rangking Badge -->
                <td class="py-4 px-4 text-center">
                  <div class="inline-flex items-center justify-center">
                    <span v-if="getRank(n.id) === 1" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-gradient-to-r from-amber-500/25 to-yellow-400/25 border border-amber-400/50 text-amber-300 font-black text-xs shadow-md shadow-amber-500/20">
                      <span>🥇</span>
                      <span>#1 Top</span>
                    </span>
                    <span v-else-if="getRank(n.id) === 2" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-400/20 border border-slate-300/40 text-slate-200 font-black text-xs">
                      <span>🥈</span>
                      <span>#2</span>
                    </span>
                    <span v-else-if="getRank(n.id) === 3" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-amber-700/25 border border-amber-600/40 text-amber-300 font-black text-xs">
                      <span>🥉</span>
                      <span>#3</span>
                    </span>
                    <span v-else class="inline-flex items-center justify-center px-2 py-0.5 rounded-lg bg-white/5 border border-white/10 text-gray-300 font-mono font-bold text-xs">
                      #{{ getRank(n.id) }}
                    </span>
                  </div>
                </td>

                <!-- Siswa -->
                <td class="py-4 px-5">
                  <p class="font-bold text-white text-sm group-hover:text-teal-300 transition-colors">
                    {{ n.namaSiswa }}
                  </p>
                  <span class="text-[11px] text-gray-400 font-mono">NIS: {{ n.nis }}</span>
                </td>

                <!-- Waktu / Durasi -->
                <td class="py-4 px-4 text-center font-mono">
                  <span class="text-gray-300 font-bold block">{{ n.durasiMenitRiil ? n.durasiMenitRiil + ' Menit' : '-' }}</span>
                  <span class="text-[10px] text-gray-500">{{ n.waktuSelesai }}</span>
                </td>

                <!-- Jawaban Benar / Salah -->
                <td class="py-4 px-4 text-center">
                  <div class="inline-flex items-center gap-1.5 text-xs font-black">
                    <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                      {{ n.jumlahBenar }} B
                    </span>
                    <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30">
                      {{ n.jumlahSalah }} S
                    </span>
                  </div>
                </td>

                <!-- Pelanggaran Anti-Cheat -->
                <td class="py-4 px-4 text-center">
                  <span
                    v-if="(n.jumlahPelanggaran || 0) > 0"
                    class="px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/40 text-[10.5px] font-mono font-black"
                  >
                    ⚠️ {{ n.jumlahPelanggaran }}x
                  </span>
                  <span v-else class="text-gray-500 font-mono text-[11px]">
                    Bersih ✓
                  </span>
                </td>

                <!-- Nilai Akhir -->
                <td class="py-4 px-4 text-center">
                  <span
                    :class="[
                      'inline-flex items-center justify-center px-3 py-1 rounded-xl font-black text-sm border font-mono',
                      n.nilai >= 75
                        ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30 shadow-md shadow-emerald-500/10'
                        : 'bg-rose-500/20 text-rose-300 border-rose-500/30'
                    ]"
                  >
                    {{ n.nilai }}
                  </span>
                </td>

                <!-- Status -->
                <td class="py-4 px-4 text-center">
                  <span
                    :class="[
                      'px-2.5 py-1 rounded-full font-extrabold text-[11px] border',
                      n.status === 'Tuntas'
                        ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'
                        : 'bg-amber-500/10 text-amber-300 border-amber-500/30'
                    ]"
                  >
                    {{ n.status }}
                  </span>
                </td>

                <!-- Aksi -->
                <td class="py-4 px-5 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="openDetail(n)"
                      title="Lihat Rincian Siswa"
                      class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                    <button
                      type="button"
                      @click="resetStudentSession(n)"
                      title="Izinkan Ujian Ulang / Reset Remedial"
                      class="p-2 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                    >
                      <span class="text-xs">🔄</span>
                    </button>
                    <button
                      type="button"
                      @click="deleteNilai(n.id)"
                      title="Hapus Nilai"
                      class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile View: Card List (md:hidden) -->
        <div v-if="sortedScheduleGrades.length > 0" class="md:hidden space-y-3">
          <div
            v-for="n in sortedScheduleGrades"
            :key="n.id"
            class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 space-y-3 shadow-lg"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span v-if="getRank(n.id) === 1" class="px-2 py-0.5 rounded-md bg-amber-400/25 border border-amber-400/50 text-amber-300 text-[10px] font-black">
                    🥇 #1 Top
                  </span>
                  <span v-else-if="getRank(n.id) === 2" class="px-2 py-0.5 rounded-md bg-slate-400/25 border border-slate-300/40 text-slate-200 text-[10px] font-black">
                    🥈 #2
                  </span>
                  <span v-else-if="getRank(n.id) === 3" class="px-2 py-0.5 rounded-md bg-amber-700/30 border border-amber-600/40 text-amber-300 text-[10px] font-black">
                    🥉 #3
                  </span>
                  <span v-else class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-gray-400 font-mono text-[10px] font-bold">
                    #{{ getRank(n.id) }}
                  </span>
                  <h4 class="font-black text-white text-sm">{{ n.namaSiswa }}</h4>
                </div>
                <span class="text-xs text-gray-400 font-mono">NIS: {{ n.nis }}</span>
              </div>
              <span
                :class="[
                  'px-2.5 py-1 rounded-xl font-black text-sm border font-mono',
                  n.nilai >= 75
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                    : 'bg-rose-500/20 text-rose-300 border-rose-500/30'
                ]"
              >
                {{ n.nilai }}
              </span>
            </div>

            <div class="flex items-center justify-between text-xs pt-2 border-t border-white/5 font-mono">
              <div class="flex items-center gap-1.5">
                <span class="text-emerald-400 font-bold">✓ {{ n.jumlahBenar }}</span>
                <span>•</span>
                <span class="text-rose-400 font-bold">✕ {{ n.jumlahSalah }}</span>
                <span v-if="(n.jumlahPelanggaran || 0) > 0" class="text-rose-400 font-bold">
                  • ⚠️ {{ n.jumlahPelanggaran }}x
                </span>
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="openDetail(n)"
                  class="px-2.5 py-1 rounded-lg bg-white/10 text-white font-bold text-xs min-h-[32px]"
                >
                  Detail
                </button>
                <button
                  type="button"
                  @click="resetStudentSession(n)"
                  class="px-2.5 py-1 rounded-lg bg-amber-500/20 text-amber-300 font-bold text-xs border border-amber-500/30 min-h-[32px]"
                >
                  🔄 Ulang
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-10 rounded-2xl bg-white/5 text-gray-400 text-xs">
          Belum ada siswa yang memenuhi filter status pencarian.
        </div>
      </div>

      <!-- ===================================================================== -->
      <!-- TAB 2: SISWA BELUM MENGERJAKAN (ABSEN / BUTUH SUSULAN)                 -->
      <!-- ===================================================================== -->
      <div v-else-if="activeEvaluationTab === 'belum'" class="space-y-4">
        <div class="p-4 rounded-2xl bg-amber-950/30 border border-amber-500/30 flex items-start gap-3">
          <span class="text-2xl shrink-0">⏳</span>
          <div>
            <h4 class="text-sm font-black text-amber-300">Daftar Siswa Belum Mengikuti Ujian CBT</h4>
            <p class="text-xs text-gray-300 mt-0.5">
              Ditemukan <strong>{{ uncompletedStudents.length }} siswa</strong> di kelas {{ selectedJadwal.kelasNama }} yang belum menyelesaikan ujian CBT ini. Berikan token atau jadwalkan ujian susulan jika diperlukan.
            </p>
          </div>
        </div>

        <div v-if="uncompletedStudents.length > 0" class="overflow-hidden rounded-2xl bg-slate-900/80 border border-white/10 shadow-xl">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-3 px-4">No</th>
                <th class="py-3 px-4">NIS</th>
                <th class="py-3 px-5">Nama Siswa</th>
                <th class="py-3 px-4">Jenis Kelamin</th>
                <th class="py-3 px-4 text-center">Status Kehadiran</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="(s, idx) in uncompletedStudents" :key="s.id" class="hover:bg-white/[0.02]">
                <td class="py-3 px-4 font-mono text-gray-400">{{ idx + 1 }}</td>
                <td class="py-3 px-4 font-mono text-teal-300">{{ s.nis }}</td>
                <td class="py-3 px-5 font-bold text-white">{{ s.nama }}</td>
                <td class="py-3 px-4 text-gray-300">{{ s.jenisKelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
                <td class="py-3 px-4 text-center">
                  <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 font-mono font-bold text-[10.5px]">
                    Belum Mengerjakan / Absen
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-12 rounded-2xl bg-slate-900/50 border border-white/10">
          <span class="text-3xl block mb-2">🎉</span>
          <h4 class="text-sm font-bold text-white">Semua Siswa Telah Mengerjakan!</h4>
          <p class="text-xs text-gray-400 mt-1">100% siswa kelas {{ selectedJadwal.kelasNama }} telah menyelesaikan ujian CBT ini.</p>
        </div>
      </div>

      <!-- ===================================================================== -->
      <!-- TAB 3: CATATAN PELANGGARAN INTEGRITAS ANTI-CHEAT                      -->
      <!-- ===================================================================== -->
      <div v-else-if="activeEvaluationTab === 'pelanggaran'" class="space-y-4">
        <div class="p-4 rounded-2xl bg-rose-950/30 border border-rose-500/30 flex items-start gap-3">
          <span class="text-2xl shrink-0">🛡️</span>
          <div>
            <h4 class="text-sm font-black text-rose-300">Catatan Pelanggaran Integritas Sistem (*Anti-Cheat*)</h4>
            <p class="text-xs text-gray-300 mt-0.5">
              Sistem mencatat setiap aktivitas mencurigakan saat ujian berlangsung, seperti berpindah tab peramban, meminimalkan layar, atau keluar dari jendela ujian penuh (*fullscreen*).
            </p>
          </div>
        </div>

        <div v-if="violatingStudents.length > 0" class="overflow-hidden rounded-2xl bg-slate-900/80 border border-white/10 shadow-xl">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-3 px-4">No</th>
                <th class="py-3 px-4">Siswa (NIS)</th>
                <th class="py-3 px-4 text-center">Jumlah Deteksi Pelanggaran</th>
                <th class="py-3 px-4 text-center">Nilai Akhir</th>
                <th class="py-3 px-4 text-right">Tindakan Guru</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="(v, idx) in violatingStudents" :key="v.id" class="hover:bg-white/[0.02]">
                <td class="py-3 px-4 font-mono text-gray-400">{{ idx + 1 }}</td>
                <td class="py-3 px-4">
                  <p class="font-bold text-white">{{ v.namaSiswa }}</p>
                  <span class="text-[11px] text-gray-400 font-mono">NIS: {{ v.nis }}</span>
                </td>
                <td class="py-3 px-4 text-center">
                  <span class="px-3 py-1 rounded-xl bg-rose-500/20 text-rose-300 border border-rose-500/40 font-mono font-black text-xs inline-flex items-center gap-1.5">
                    <span>⚠️</span>
                    <span>{{ v.jumlahPelanggaran }} Kali Terdeteksi</span>
                  </span>
                </td>
                <td class="py-3 px-4 text-center font-mono font-bold text-white">
                  {{ v.nilai }} ({{ v.status }})
                </td>
                <td class="py-3 px-4 text-right">
                  <button
                    type="button"
                    @click="resetStudentSession(v)"
                    class="px-3 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 text-xs font-bold transition-all inline-flex items-center gap-1.5"
                  >
                    <span>🔄 Reset Sesi Ujian</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-12 rounded-2xl bg-slate-900/50 border border-white/10">
          <span class="text-3xl block mb-2">🛡️</span>
          <h4 class="text-sm font-bold text-white">Tidak Ada Catatan Pelanggaran</h4>
          <p class="text-xs text-gray-400 mt-1">Seluruh siswa di kelas ini mengerjakan ujian secara tertib dan jujur tanpa terdeteksi keluar dari layar ujian.</p>
        </div>
      </div>

    </div>

    <!-- ======================================================================= -->
    <!-- MODAL RINCIAN DETAIL SISWA                                              -->
    <!-- ======================================================================= -->
    <div v-if="showDetailModal && activeNilai" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
      <div class="w-full max-w-md rounded-3xl bg-slate-900 border border-white/10 p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="text-base font-black text-white flex items-center gap-2">
            <span>📄 Rincian Lembar Hasil Ujian</span>
          </h3>
          <button @click="showDetailModal = false" class="p-1.5 rounded-lg text-gray-400 hover:text-white">✕</button>
        </div>

        <div class="space-y-3.5 text-xs">
          <!-- Identity Box -->
          <div class="bg-white/5 p-3.5 rounded-2xl space-y-1">
            <p class="font-black text-white text-base">{{ activeNilai.namaSiswa }}</p>
            <p class="text-gray-400 font-mono">NIS: {{ activeNilai.nis }} &bull; Kelas: {{ activeNilai.kelasNama }}</p>
            <p class="text-teal-400 font-semibold">{{ activeNilai.jurusanNama }}</p>
          </div>

          <!-- Score & Rank Grid -->
          <div class="grid grid-cols-2 gap-2 text-center font-mono">
            <div class="p-3 rounded-xl bg-white/5 border border-white/5">
              <span class="text-[10px] text-gray-400 uppercase font-bold block">Skor / Nilai Akhir</span>
              <span class="text-2xl font-black text-emerald-400">{{ activeNilai.nilai }}</span>
            </div>
            <div class="p-3 rounded-xl bg-white/5 border border-white/5">
              <span class="text-[10px] text-gray-400 uppercase font-bold block">Peringkat Kelas</span>
              <span class="text-xl font-black text-amber-300 mt-1 block">
                #{{ getRank(activeNilai.id) }} / {{ completedNilais.length }}
              </span>
            </div>
          </div>

          <!-- Performance Details -->
          <div class="p-3.5 rounded-2xl bg-white/5 space-y-2 text-gray-300 font-mono">
            <div class="flex items-center justify-between">
              <span class="text-gray-400">📖 Mata Pelajaran:</span>
              <span class="font-bold text-white">{{ activeNilai.mapelNama }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400">⏱️ Durasi Pengerjaan:</span>
              <span class="font-bold text-white">{{ activeNilai.durasiMenitRiil ? activeNilai.durasiMenitRiil + ' Menit' : '-' }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400">✓ Jawaban Benar:</span>
              <span class="font-bold text-emerald-400">{{ activeNilai.jumlahBenar }} dari {{ activeNilai.jumlahSoal }} Soal</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400">✕ Jawaban Salah:</span>
              <span class="font-bold text-rose-400">{{ activeNilai.jumlahSalah }} Soal</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400">⚠️ Catatan Pelanggaran:</span>
              <span :class="(activeNilai.jumlahPelanggaran || 0) > 0 ? 'text-rose-400 font-bold' : 'text-emerald-400'">
                {{ (activeNilai.jumlahPelanggaran || 0) > 0 ? activeNilai.jumlahPelanggaran + ' Kali Terdeteksi' : '0 (Bersih)' }}
              </span>
            </div>
            <div class="flex items-center justify-between pt-1 border-t border-white/5">
              <span class="text-gray-400">Status Ketuntasan:</span>
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
            class="px-4 py-2 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-bold"
          >
            🔄 Izinkan Ujian Ulang
          </button>
          <button
            type="button"
            @click="showDetailModal = false"
            class="px-5 py-2 rounded-xl bg-white/10 text-white font-bold text-xs min-h-[38px]"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- MODAL TRANSFER NILAI CBT KE PENILAIAN TP                                -->
    <!-- ======================================================================= -->
    <div v-if="showTransferModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
      <div class="w-full max-w-lg rounded-3xl bg-slate-900 border border-white/10 p-6 sm:p-7 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <div class="flex items-center gap-2.5">
            <span class="text-xl">📤</span>
            <div>
              <h3 class="text-base font-black text-white">Masukkan Nilai CBT ke Buku Nilai TP</h3>
              <p class="text-xs text-gray-400">Sinkronkan hasil ujian ke lembar penilaian resmi guru</p>
            </div>
          </div>
          <button @click="showTransferModal = false" class="p-1.5 rounded-lg text-gray-400 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="submitTransferToNilai" class="space-y-4">
          <!-- Pilih Sesi CBT -->
          <div>
            <label class="block text-xs font-bold text-gray-300 uppercase mb-1">Pilih Sesi Ujian CBT *</label>
            <select
              v-model="transferForm.jadwal_id"
              @change="onTransferJadwalChange"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/10 text-white text-xs sm:text-sm min-h-[42px]"
            >
              <option value="">Pilih Sesi Ujian</option>
              <option v-for="j in jadwalList" :key="j.id" :value="j.id">
                [{{ j.kelasNama }}] {{ j.judulUjian }} ({{ j.mapelNama }})
              </option>
            </select>
          </div>

          <!-- Mode Transfer -->
          <div class="space-y-2">
            <label class="block text-xs font-bold text-gray-300 uppercase">Target Penilaian TP</label>
            <div class="grid grid-cols-2 gap-2">
              <label :class="['p-3 rounded-2xl border text-xs font-bold cursor-pointer transition-all flex items-center gap-2', transferForm.mode === 'existing_tp' ? 'bg-indigo-500/20 border-indigo-400 text-white' : 'bg-white/5 border-white/10 text-gray-400']">
                <input type="radio" v-model="transferForm.mode" value="existing_tp" class="hidden" />
                <span>📌 Gunakan TP yang Ada</span>
              </label>
              <label :class="['p-3 rounded-2xl border text-xs font-bold cursor-pointer transition-all flex items-center gap-2', transferForm.mode === 'new_tp' ? 'bg-indigo-500/20 border-indigo-400 text-white' : 'bg-white/5 border-white/10 text-gray-400']">
                <input type="radio" v-model="transferForm.mode" value="new_tp" class="hidden" />
                <span>✨ Buat TP Baru</span>
              </label>
            </div>
          </div>

          <!-- If existing TP -->
          <div v-if="transferForm.mode === 'existing_tp'">
            <label class="block text-xs font-bold text-gray-300 uppercase mb-1">Pilih TP Tujuan *</label>
            <select
              v-model="transferForm.tp_id"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/10 text-white text-xs sm:text-sm min-h-[42px]"
            >
              <option value="">Pilih Tujuan Pembelajaran</option>
              <option v-for="tp in matchingTpList" :key="tp.id" :value="tp.id">
                {{ tp.kode }} - {{ tp.judul }} ({{ tp.mapel }})
              </option>
            </select>
          </div>

          <!-- If new TP -->
          <div v-else class="space-y-3 p-3.5 rounded-2xl bg-white/5 border border-white/5">
            <div>
              <label class="block text-xs font-bold text-gray-300 uppercase mb-1">Nama / Judul TP Baru *</label>
              <input
                v-model="transferForm.judul"
                type="text"
                required
                placeholder="Contoh: Sumatif Akhir Lingkup Materi Bab 1"
                class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs sm:text-sm min-h-[40px]"
              />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1">Jenis Penilaian</label>
                <select v-model="transferForm.jenis" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs min-h-[38px]">
                  <option value="Sumatif">Sumatif</option>
                  <option value="Formatif">Formatif</option>
                  <option value="Tugas">Tugas</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1">KKTP / KKM</label>
                <input v-model.number="transferForm.kktp" type="number" class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-white text-xs min-h-[38px]" />
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-3 border-t border-white/10">
            <button
              type="button"
              @click="showTransferModal = false"
              class="px-4 py-2 rounded-xl bg-white/5 text-gray-400 font-bold text-xs hover:text-white min-h-[38px]"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isTransferring"
              class="px-5 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-black text-xs shadow-lg shadow-indigo-500/20 active:scale-95 min-h-[38px] disabled:opacity-50"
            >
              {{ isTransferring ? 'Memproses Transfer...' : '🚀 Transfer Sekarang' }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
