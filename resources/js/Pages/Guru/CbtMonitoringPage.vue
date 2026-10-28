<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal, { confirmDelete, showToast } from '@/Utils/sweetalert';

const props = defineProps({
  jadwal: {
    type: Object,
    required: true,
  },
  stats: {
    type: Object,
    default: () => ({
      totalPeserta: 0,
      menunggu: 0,
      mengerjakan: 0,
      selesai: 0,
      terkunci: 0,
      totalPelanggaran: 0,
      siswaMelanggar: 0,
    }),
  },
  peserta: {
    type: Array,
    default: () => [],
  },
  masterKelasList: {
    type: Array,
    default: () => [],
  },
});

// Navigation
function goBack() {
  router.visit('/guru/dashboard?tab=cbt-jadwal');
}

// Local Reactive State for Real-Time Polling
const pesertaList = ref([...props.peserta]);
const statsData = ref({ ...props.stats });
const isRefreshing = ref(false);
const autoRefreshEnabled = ref(true);
const tokenCopied = ref(false);
const lastUpdatedAt = ref(
  new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);
let pollingTimer = null;

// Search & Filter Tabs
const searchQuery = ref('');
const activeFilterTab = ref('semua'); // 'semua', 'mengerjakan', 'selesai', 'menunggu', 'melanggar'
const selectedKelasFilter = ref('');

// Computed unique classes from teacher schedule (masterKelasList) and active exam participants
const availableKelasList = computed(() => {
  const set = new Set();
  (props.masterKelasList || []).forEach((k) => {
    if (k.namaKelas) set.add(k.namaKelas);
  });
  pesertaList.value.forEach((p) => {
    if (p.kelas && p.kelas !== '-') set.add(p.kelas);
  });
  return Array.from(set).sort((a, b) =>
    a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
  );
});

// Filtered Participants
const filteredPeserta = computed(() => {
  let list = pesertaList.value;

  // Filter Rombel Kelas
  if (selectedKelasFilter.value) {
    list = list.filter((p) => p.kelas === selectedKelasFilter.value);
  }

  if (activeFilterTab.value !== 'semua') {
    if (activeFilterTab.value === 'melanggar') {
      list = list.filter((p) => (p.jumlahPelanggaran && p.jumlahPelanggaran > 0) || p.status === 'terkunci');
    } else {
      list = list.filter((p) => p.status === activeFilterTab.value);
    }
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(
      (p) =>
        (p.namaSiswa && p.namaSiswa.toLowerCase().includes(q)) ||
        (p.nis && String(p.nis).toLowerCase().includes(q)) ||
        (p.kelas && p.kelas.toLowerCase().includes(q))
    );
  }

  return list;
});

// Completion percentage calculation
const completionPercentage = computed(() => {
  const total = statsData.value.totalPeserta || 0;
  if (total === 0) return 0;
  return Math.round(((statsData.value.selesai || 0) / total) * 100);
});

// Active working percentage calculation
const workingPercentage = computed(() => {
  const total = statsData.value.totalPeserta || 0;
  if (total === 0) return 0;
  return Math.round(((statsData.value.mengerjakan || 0) / total) * 100);
});

// Waiting percentage calculation
const waitingPercentage = computed(() => {
  const total = statsData.value.totalPeserta || 0;
  if (total === 0) return 0;
  return Math.round(((statsData.value.menunggu || 0) / total) * 100);
});

// Locked percentage calculation
const lockedPercentage = computed(() => {
  const total = statsData.value.totalPeserta || 0;
  if (total === 0) return 0;
  return Math.round(((statsData.value.terkunci || 0) / total) * 100);
});

// Fetch Latest Monitoring Data (Silent background polling or manual refresh)
async function fetchMonitoringData(isManual = false) {
  if (isManual) isRefreshing.value = true;
  try {
    const res = await axios.get(route('guru.cbt.jadwal.monitoring', props.jadwal.id), {
      headers: { Accept: 'application/json' },
    });
    if (res.data && res.data.success) {
      pesertaList.value = res.data.peserta || [];
      statsData.value = res.data.stats || statsData.value;
      lastUpdatedAt.value = new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      });
      if (isManual) {
        showToast('Data monitoring berhasil diperbarui.', 'success');
      }
    }
  } catch (err) {
    console.error('Monitoring fetch error:', err);
    if (isManual) {
      showToast('Gagal memuat pembaruan data.', 'error');
    }
  } finally {
    if (isManual) isRefreshing.value = false;
  }
}

// Polling Life Cycle
function startPolling() {
  stopPolling();
  pollingTimer = setInterval(() => {
    if (autoRefreshEnabled.value) {
      fetchMonitoringData(false);
    }
  }, 5000);
}

function stopPolling() {
  if (pollingTimer) {
    clearInterval(pollingTimer);
    pollingTimer = null;
  }
}

function toggleAutoRefresh() {
  autoRefreshEnabled.value = !autoRefreshEnabled.value;
  if (autoRefreshEnabled.value) {
    fetchMonitoringData(false);
    showToast('Auto-refresh (5s) diaktifkan', 'info');
  } else {
    showToast('Auto-refresh dijeda', 'info');
  }
}

onMounted(() => {
  startPolling();
});

onUnmounted(() => {
  stopPolling();
});

// Copy Token with Visual Feedback
function copyToken() {
  if (!props.jadwal.token) return;
  navigator.clipboard.writeText(props.jadwal.token);
  tokenCopied.value = true;
  showToast(`Token ${props.jadwal.token} berhasil disalin!`, 'success');
  setTimeout(() => {
    tokenCopied.value = false;
  }, 2500);
}

// Student Session Actions
function bukaKunciSesi(sesiId, namaSiswa) {
  Swal.fire({
    title: 'Buka Kunci Ujian?',
    html: `Kunci ujian siswa <b class="text-emerald-400 font-bold">${namaSiswa}</b> akan dibuka.<br><span class="text-gray-400 mt-1 inline-block">Pelanggaran direset ke 0 dan siswa dapat langsung melanjutkan ujian.</span>`,
    icon: 'question',
    iconColor: '#10b981',
    showCancelButton: true,
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#475569',
    confirmButtonText: '<span class="font-extrabold px-3">🔓 Ya, Buka Kunci!</span>',
    cancelButtonText: '<span class="font-semibold">Batal</span>',
    background: '#0f172a',
    color: '#f8fafc',
    customClass: {
      popup: 'rounded-3xl border border-emerald-500/30 shadow-2xl backdrop-blur-xl',
      title: 'text-lg font-black text-white',
      htmlContainer: 'text-xs text-gray-300 font-medium',
      confirmButton: 'rounded-2xl px-5 py-2.5 text-xs shadow-lg shadow-emerald-500/30 font-bold',
      cancelButton: 'rounded-2xl px-5 py-2.5 text-xs font-bold'
    }
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(
        route('guru.cbt.sesi.buka-kunci', sesiId),
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            showToast(`Kunci ujian ${namaSiswa} berhasil dibuka!`, 'success');
            fetchMonitoringData(false);
          },
          onError: () => {
            showToast('Gagal membuka kunci ujian.', 'error');
          }
        }
      );
    }
  });
}

function kunciSesi(sesiId, namaSiswa) {
  confirmDelete({
    title: 'Kunci Layar Ujian?',
    text: `Layar siswa ${namaSiswa} akan langsung terkunci. Siswa tidak dapat melanjutkan sampai dibuka kembali oleh guru.`,
    confirmButtonText: 'Ya, Kunci Ujian!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(
        route('guru.cbt.sesi.kunci', sesiId),
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            showToast(`Ujian siswa ${namaSiswa} telah dikunci!`, 'warning');
            fetchMonitoringData(false);
          },
        }
      );
    }
  });
}

function resetSesi(sesiId, namaSiswa) {
  confirmDelete({
    title: 'Reset Sesi Ujian?',
    text: `Sesi ujian ${namaSiswa} akan dikembalikan ke status menunggu dan pelanggaran direset ke 0. Cocok jika terjadi kendala teknis perangkat.`,
    confirmButtonText: 'Ya, Reset Sesi!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(
        route('guru.cbt.sesi.reset', sesiId),
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            showToast(`Sesi ujian ${namaSiswa} berhasil direset!`, 'success');
            fetchMonitoringData(false);
          },
        }
      );
    }
  });
}

function deleteSesi(sesiId, namaSiswa) {
  confirmDelete({
    title: 'Hapus Peserta dari Monitoring?',
    text: `Data sesi peserta ${namaSiswa} akan dihapus dari monitoring jadwal ujian ini.`,
    confirmButtonText: 'Ya, Hapus!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(route('guru.cbt.sesi.delete', sesiId), {
        preserveScroll: true,
        onSuccess: () => {
          showToast(`Peserta ${namaSiswa} dihapus dari monitoring.`, 'success');
          fetchMonitoringData(false);
        },
      });
    }
  });
}

// Helpers
function getInitials(name) {
  if (!name) return 'S';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[1][0]).toUpperCase();
}

function formatRemainingSeconds(sec) {
  if (sec === null || sec === undefined || sec <= 0) return '00:00';
  const m = Math.floor(sec / 60);
  const s = sec % 60;
  return `${m}m ${s < 10 ? '0' : ''}${s}s`;
}

function getProgressPercent(terjawab) {
  const total = props.jadwal.totalSoal || 0;
  if (!total || total === 0) return 0;
  return Math.min(100, Math.round(((terjawab || 0) / total) * 100));
}
</script>

<template>
  <Head :title="`Live Monitoring CBT: ${jadwal.judulUjian || 'Ujian'} — SMKS Muhammadiyah 2 Genteng`" />

  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans pb-20 selection:bg-cyan-500 selection:text-slate-950">
    
    <!-- 1. TOP STICKY NAVBAR WITH SMOOTH BLUR -->
    <header class="sticky top-0 z-40 bg-slate-950/85 backdrop-blur-md border-b border-white/10 shadow-lg">
      <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
        <!-- Back Button & Live Status Pill -->
        <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0">
          <button
            @click="goBack"
            type="button"
            class="px-3 sm:px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white border border-white/10 text-xs font-bold transition-all flex items-center gap-1.5 sm:gap-2 min-h-[38px] shrink-0 active:scale-95"
            title="Kembali ke Jadwal CBT"
          >
            <span>←</span>
            <span class="hidden sm:inline">Jadwal CBT</span>
          </button>

          <div class="flex items-center gap-2 truncate">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-[11px] font-black tracking-wider uppercase shrink-0">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
              <span>LIVE PROCTORING</span>
            </span>
            <span class="text-xs text-gray-400 font-mono hidden md:inline truncate">
              {{ jadwal.judulUjian }}
            </span>
          </div>
        </div>

        <!-- Realtime Controls Group (Single Source of Refresh) -->
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-[11px] text-gray-400 font-mono hidden lg:inline">
            Update: {{ lastUpdatedAt }}
          </span>

          <!-- Auto-Refresh Toggle -->
          <button
            @click="toggleAutoRefresh"
            :class="[
              'px-3 py-1.5 rounded-xl border text-xs font-mono font-bold flex items-center gap-2 transition-all min-h-[38px] active:scale-95',
              autoRefreshEnabled
                ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 hover:bg-emerald-500/25'
                : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10'
            ]"
            :title="autoRefreshEnabled ? 'Auto-refresh aktif tiap 5 detik (klik untuk menjeda)' : 'Auto-refresh dijeda (klik untuk mengaktifkan)'"
          >
            <span
              class="w-2 h-2 rounded-full"
              :class="autoRefreshEnabled ? 'bg-emerald-400 animate-pulse' : 'bg-gray-500'"
            ></span>
            <span class="hidden sm:inline">{{ autoRefreshEnabled ? 'Polling 5s' : 'Jeda' }}</span>
            <span class="sm:hidden">{{ autoRefreshEnabled ? '5s' : 'Off' }}</span>
          </button>

          <!-- Manual Refresh Button -->
          <button
            @click="fetchMonitoringData(true)"
            :disabled="isRefreshing"
            class="p-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-white transition-all min-h-[38px] min-w-[38px] flex items-center justify-center active:scale-95 disabled:opacity-50"
            title="Muat Ulang Data Sekarang"
          >
            <svg
              class="w-4 h-4"
              :class="{ 'animate-spin': isRefreshing }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
              />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <!-- 2. MAIN CONTAINER -->
    <main class="w-full max-w-7xl mx-auto px-4 sm:px-6 pt-5 sm:pt-6 space-y-6">

      <!-- HERO BANNER: EXAM INFO & TOKEN SPOTLIGHT (Responsive Layout) -->
      <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-cyan-950/40 border border-white/10 p-5 sm:p-7 shadow-xl">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-5">
          <!-- Left: Exam Metadata Hierarchy -->
          <div class="space-y-3 min-w-0 flex-1">
            <div class="flex items-center gap-2 text-xs text-gray-400 font-medium">
              <span>Sesi Ujian Aktif</span>
              <span>•</span>
              <span class="text-teal-400 font-bold uppercase tracking-wider">{{ jadwal.bankSoalJudul || 'CBT' }}</span>
            </div>

            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-white tracking-tight leading-tight">
              {{ jadwal.judulUjian }}
            </h1>

            <!-- Badges List -->
            <div class="flex items-center gap-2 flex-wrap text-xs">
              <span class="px-2.5 py-1 rounded-lg bg-teal-500/10 border border-teal-500/30 text-teal-300 font-semibold flex items-center gap-1.5">
                <span>📖</span>
                <span>{{ jadwal.mapel }}</span>
              </span>
              <span class="px-2.5 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 font-semibold flex items-center gap-1.5">
                <span>🏢</span>
                <span>{{ jadwal.kelasNama }}</span>
              </span>
              <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 font-semibold flex items-center gap-1.5">
                <span>⏱️</span>
                <span>{{ jadwal.durasiMenit }} Menit</span>
              </span>
              <span v-if="jadwal.totalSoal" class="px-2.5 py-1 rounded-lg bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 font-semibold flex items-center gap-1.5">
                <span>📝</span>
                <span>{{ jadwal.totalSoal }} Butir Soal</span>
              </span>
              <span class="px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-gray-300 font-medium flex items-center gap-1.5">
                <span>🎮</span>
                <span>{{ jadwal.modeUjian === 'game' ? 'Game Mode' : 'Formal CBT' }}</span>
              </span>
            </div>
          </div>

          <!-- Right: Token Spotlight Card (High Visibility) -->
          <div class="shrink-0">
            <div class="p-4 sm:p-5 rounded-2xl bg-cyan-950/60 border border-cyan-500/40 shadow-lg shadow-cyan-950/30 flex items-center justify-between lg:flex-col lg:items-center gap-3 sm:gap-4 min-w-[220px]">
              <div class="text-left lg:text-center">
                <span class="text-[10px] font-black uppercase tracking-widest text-cyan-400 block">
                  KODE TOKEN SISWA
                </span>
                <span class="text-2xl sm:text-3xl font-black font-mono tracking-widest text-white block mt-0.5 select-all">
                  {{ jadwal.token }}
                </span>
              </div>

              <button
                @click="copyToken"
                type="button"
                :class="[
                  'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 min-h-[38px] active:scale-95 shrink-0',
                  tokenCopied
                    ? 'bg-emerald-500 text-slate-950 font-black'
                    : 'bg-cyan-500 hover:bg-cyan-400 text-slate-950 shadow-md shadow-cyan-500/20'
                ]"
                title="Salin token ke clipboard"
              >
                <span>{{ tokenCopied ? '✓ Tersalin!' : '📋 Salin Token' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Multi-Segmented Exam Completion Bar (At a glance overview) -->
        <div class="mt-6 pt-5 border-t border-white/10 space-y-2.5">
          <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
              <span class="font-bold text-white">Status Penyelesaian Kelas:</span>
              <span class="text-emerald-400 font-mono font-bold">{{ completionPercentage }}% Tuntas</span>
            </div>
            <span class="text-gray-400 font-mono text-[11px]">
              {{ statsData.selesai || 0 }} dari {{ statsData.totalPeserta || 0 }} Siswa
            </span>
          </div>

          <!-- Segmented Progress Bar -->
          <div class="h-2.5 w-full bg-slate-950 rounded-full overflow-hidden flex border border-white/10">
            <div
              :style="{ width: `${completionPercentage}%` }"
              class="h-full bg-emerald-500 transition-all duration-500"
              title="Telah Selesai"
            ></div>
            <div
              :style="{ width: `${workingPercentage}%` }"
              class="h-full bg-teal-400 transition-all duration-500"
              title="Sedang Mengerjakan"
            ></div>
            <div
              :style="{ width: `${waitingPercentage}%` }"
              class="h-full bg-cyan-400 transition-all duration-500"
              title="Di Ruang Tunggu"
            ></div>
            <div
              :style="{ width: `${lockedPercentage}%` }"
              class="h-full bg-rose-500 transition-all duration-500"
              title="Terkunci"
            ></div>
          </div>

          <!-- Bar Legend -->
          <div class="flex items-center gap-4 flex-wrap text-[11px] text-gray-400 pt-1">
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>Selesai: <strong class="text-white">{{ statsData.selesai || 0 }}</strong></span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-teal-400"></span>
              <span>Mengerjakan: <strong class="text-white">{{ statsData.mengerjakan || 0 }}</strong></span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
              <span>Ruang Tunggu: <strong class="text-white">{{ statsData.menunggu || 0 }}</strong></span>
            </div>
            <div v-if="(statsData.terkunci || 0) > 0" class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-rose-500"></span>
              <span class="text-rose-300">Terkunci: <strong class="text-rose-200">{{ statsData.terkunci }}</strong></span>
            </div>
          </div>
        </div>
      </section>

      <!-- 4 KPI SUMMARY CARDS -->
      <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- 1. Total Peserta -->
        <div class="p-4 sm:p-5 rounded-2xl bg-slate-900 border border-white/10 space-y-1 shadow-sm hover:border-white/20 transition-all">
          <div class="flex items-center justify-between text-gray-400">
            <span class="text-[11px] font-bold uppercase tracking-wider">Total Siswa</span>
            <span class="text-lg">👥</span>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-black text-white font-mono">
              {{ statsData.totalPeserta || 0 }}
            </span>
            <span class="text-xs text-gray-400">Peserta</span>
          </div>
          <span class="text-[11px] text-teal-400 font-medium block truncate">
            {{ statsData.menunggu || 0 }} siswa di ruang tunggu
          </span>
        </div>

        <!-- 2. Sedang Mengerjakan -->
        <div class="p-4 sm:p-5 rounded-2xl bg-slate-900 border border-teal-500/30 space-y-1 shadow-sm hover:border-teal-500/50 transition-all">
          <div class="flex items-center justify-between text-teal-300">
            <span class="text-[11px] font-bold uppercase tracking-wider">Sedang Ujian</span>
            <span class="text-lg">✍️</span>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-black text-teal-300 font-mono">
              {{ statsData.mengerjakan || 0 }}
            </span>
            <span class="text-xs text-gray-400">Aktif</span>
          </div>
          <span class="text-[11px] text-gray-400 font-medium block truncate">
            {{ workingPercentage }}% dari total siswa
          </span>
        </div>

        <!-- 3. Telah Selesai -->
        <div class="p-4 sm:p-5 rounded-2xl bg-slate-900 border border-emerald-500/30 space-y-1 shadow-sm hover:border-emerald-500/50 transition-all">
          <div class="flex items-center justify-between text-emerald-300">
            <span class="text-[11px] font-bold uppercase tracking-wider">Telah Selesai</span>
            <span class="text-lg">✅</span>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-black text-emerald-300 font-mono">
              {{ statsData.selesai || 0 }}
            </span>
            <span class="text-xs text-gray-400">Tuntas</span>
          </div>
          <span class="text-[11px] text-gray-400 font-medium block truncate">
            Nilai otomatis terekam
          </span>
        </div>

        <!-- 4. Anti-Cheat & Keamanan -->
        <div
          :class="[
            'p-4 sm:p-5 rounded-2xl border space-y-1 shadow-sm transition-all',
            (statsData.siswaMelanggar || 0) > 0 || (statsData.terkunci || 0) > 0
              ? 'bg-rose-950/30 border-rose-500/60 shadow-rose-950/20'
              : 'bg-slate-900 border-white/10 hover:border-white/20'
          ]"
        >
          <div class="flex items-center justify-between" :class="(statsData.siswaMelanggar || 0) > 0 || (statsData.terkunci || 0) > 0 ? 'text-rose-300' : 'text-gray-400'">
            <span class="text-[11px] font-bold uppercase tracking-wider">Anti-Cheat</span>
            <span class="text-lg">{{ (statsData.siswaMelanggar || 0) > 0 ? '🚨' : '🛡️' }}</span>
          </div>
          <div class="flex items-baseline gap-2">
            <span
              class="text-2xl sm:text-3xl font-black font-mono"
              :class="(statsData.siswaMelanggar || 0) > 0 || (statsData.terkunci || 0) > 0 ? 'text-rose-400' : 'text-emerald-400'"
            >
              {{ statsData.siswaMelanggar || 0 }}
            </span>
            <span class="text-xs text-gray-400">
              {{ (statsData.siswaMelanggar || 0) > 0 ? 'Siswa Melanggar' : 'Pelanggaran' }}
            </span>
          </div>
          <span
            class="text-[11px] font-medium block truncate"
            :class="(statsData.siswaMelanggar || 0) > 0 || (statsData.terkunci || 0) > 0 ? 'text-rose-300 font-bold' : 'text-emerald-400'"
          >
            {{ (statsData.terkunci || 0) > 0 ? `🔒 ${statsData.terkunci} Sesi Terkunci` : '✓ Semua Siswa Tertib' }}
          </span>
        </div>
      </section>

      <!-- TOOLBAR: FILTER TABS & SEARCH (Neat, Elegant, Mobile-Optimized) -->
      <section class="p-3 sm:p-4 rounded-2xl bg-slate-900 border border-white/10 shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
          
          <!-- Filter Tabs (Horizontal Scrollable on mobile without ugly scrollbar) -->
          <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 scrollbar-none">
            <!-- All -->
            <button
              @click="activeFilterTab = 'semua'"
              type="button"
              :class="[
                'px-3 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap min-h-[38px]',
                activeFilterTab === 'semua'
                  ? 'bg-white/15 text-white border border-white/20'
                  : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10'
              ]"
            >
              Semua ({{ pesertaList.length }})
            </button>

            <!-- Sedang Ujian -->
            <button
              @click="activeFilterTab = 'mengerjakan'"
              type="button"
              :class="[
                'px-3 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap min-h-[38px] flex items-center gap-1.5',
                activeFilterTab === 'mengerjakan'
                  ? 'bg-teal-500/20 text-teal-300 border border-teal-500/40'
                  : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10'
              ]"
            >
              <span>✍️ Sedang Ujian</span>
              <span class="px-1.5 py-0.2 rounded-md bg-white/10 text-[10px] font-mono">{{ statsData.mengerjakan || 0 }}</span>
            </button>

            <!-- Selesai -->
            <button
              @click="activeFilterTab = 'selesai'"
              type="button"
              :class="[
                'px-3 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap min-h-[38px] flex items-center gap-1.5',
                activeFilterTab === 'selesai'
                  ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                  : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10'
              ]"
            >
              <span>✅ Selesai</span>
              <span class="px-1.5 py-0.2 rounded-md bg-white/10 text-[10px] font-mono">{{ statsData.selesai || 0 }}</span>
            </button>

            <!-- Ruang Tunggu -->
            <button
              @click="activeFilterTab = 'menunggu'"
              type="button"
              :class="[
                'px-3 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap min-h-[38px] flex items-center gap-1.5',
                activeFilterTab === 'menunggu'
                  ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40'
                  : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10'
              ]"
            >
              <span>⏳ Ruang Tunggu</span>
              <span class="px-1.5 py-0.2 rounded-md bg-white/10 text-[10px] font-mono">{{ statsData.menunggu || 0 }}</span>
            </button>

            <!-- Melanggar / Terkunci -->
            <button
              @click="activeFilterTab = 'melanggar'"
              type="button"
              :class="[
                'px-3 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap min-h-[38px] flex items-center gap-1.5',
                activeFilterTab === 'melanggar'
                  ? 'bg-rose-500/25 text-rose-300 border border-rose-500/50'
                  : (statsData.siswaMelanggar || 0) > 0 || (statsData.terkunci || 0) > 0
                    ? 'bg-rose-950/30 text-rose-400 hover:bg-rose-950/50 border border-rose-500/30'
                    : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10'
              ]"
            >
              <span>🚨 Melanggar / Terkunci</span>
              <span
                class="px-1.5 py-0.2 rounded-md text-[10px] font-mono"
                :class="(statsData.siswaMelanggar || 0) > 0 || (statsData.terkunci || 0) > 0 ? 'bg-rose-600 text-white font-bold' : 'bg-white/10'"
              >
                {{ (statsData.siswaMelanggar || 0) + (statsData.terkunci || 0) }}
              </span>
            </button>
          </div>

          <!-- Dropdown Filter Rombel Kelas (Untuk Membedakan Antar Kelas saat Ujian Semua Kelas) -->
          <div v-if="jadwal.kelasNama === 'Semua Kelas' || availableKelasList.length > 1" class="shrink-0">
            <select
              v-model="selectedKelasFilter"
              class="w-full sm:w-auto px-3 py-2 rounded-xl bg-slate-950 border border-cyan-500/30 text-cyan-300 text-xs font-bold focus:outline-none focus:border-cyan-400 transition-colors min-h-[38px]"
              title="Pilih kelas tertentu untuk menyaring siswa"
            >
              <option value="">🏢 Semua Rombel Kelas ({{ availableKelasList.length }})</option>
              <option v-for="k in availableKelasList" :key="k" :value="k">
                Kelas: {{ k }}
              </option>
            </select>
          </div>

          <!-- Search Input -->
          <div class="relative w-full md:w-72 shrink-0">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari siswa, NIS, kelas..."
              class="w-full pl-9 pr-8 py-2 rounded-xl bg-slate-950 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-cyan-400 transition-colors min-h-[38px]"
            />
            <span class="absolute left-3 top-2.5 text-gray-500 text-xs">🔍</span>
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-2.5 top-2 text-gray-400 hover:text-white p-0.5 rounded-full"
              title="Bersihkan pencarian"
            >
              ✕
            </button>
          </div>

        </div>
      </section>

      <!-- 5. PARTICIPANTS ROSTER (DESKTOP TABLE + MOBILE CARDS) -->
      <section class="space-y-4">
        <!-- Empty State -->
        <div
          v-if="filteredPeserta.length === 0"
          class="text-center py-16 rounded-3xl bg-slate-900/60 border border-white/10 text-gray-400 text-xs sm:text-sm space-y-3"
        >
          <div class="text-4xl">👥</div>
          <p class="font-bold text-white text-base">Tidak ada data peserta</p>
          <p class="text-gray-400 text-xs max-w-md mx-auto leading-relaxed">
            {{ searchQuery || activeFilterTab !== 'semua'
              ? 'Tidak ada siswa yang cocok dengan filter atau pencarian saat ini.'
              : 'Belum ada siswa yang masuk ke sesi ujian ini. Instruksikan siswa memasukkan Token: ' + jadwal.token + ' pada menu CBT Siswa.' }}
          </p>
          <button
            v-if="searchQuery || activeFilterTab !== 'semua'"
            @click="searchQuery = ''; activeFilterTab = 'semua'"
            class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-bold transition-all min-h-[36px]"
          >
            Reset Filter
          </button>
        </div>

        <!-- Desktop & Tablet View Table (hidden md:block) -->
        <div
          v-else
          class="hidden md:block overflow-hidden rounded-2xl bg-slate-900 border border-white/10 shadow-sm"
        >
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-bold text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-3.5 px-4">Peserta Ujian</th>
                <th class="py-3.5 px-3">Kelas</th>
                <th class="py-3.5 px-3 text-center">Status</th>
                <th class="py-3.5 px-3">Progress Pengerjaan</th>
                <th class="py-3.5 px-3 text-center">Anti-Cheat</th>
                <th class="py-3.5 px-3 text-center">Nilai</th>
                <th class="py-3.5 px-4 text-right">Aksi Kontrol</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr
                v-for="p in filteredPeserta"
                :key="p.id"
                :class="[
                  'transition-colors',
                  p.status === 'terkunci' || p.jumlahPelanggaran > 3
                    ? 'bg-rose-950/25 hover:bg-rose-950/35 border-l-4 border-l-rose-500'
                    : p.jumlahPelanggaran > 0
                      ? 'bg-amber-950/15 hover:bg-amber-950/25 border-l-4 border-l-amber-500'
                      : 'hover:bg-white/[0.02]'
                ]"
              >
                <!-- Peserta Column -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <div class="relative shrink-0">
                      <div class="w-9 h-9 rounded-full bg-slate-800 border border-white/10 text-cyan-400 font-black text-xs flex items-center justify-center">
                        {{ getInitials(p.namaSiswa) }}
                      </div>
                      <span
                        class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-slate-900"
                        :class="p.isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-gray-600'"
                        :title="p.isOnline ? 'Online Aktif' : 'Offline / Tidak ada sinyal'"
                      ></span>
                    </div>
                    <div>
                      <div class="font-bold text-white text-xs sm:text-sm flex items-center gap-1.5">
                        <span>{{ p.namaSiswa }}</span>
                        <span
                          v-if="p.status === 'terkunci'"
                          class="px-1.5 py-0.5 rounded bg-rose-600 text-white text-[9px] font-mono font-black uppercase tracking-wider"
                        >
                          LOCKED
                        </span>
                      </div>
                      <div class="text-[11px] text-gray-400 font-mono">NIS: {{ p.nis }}</div>
                    </div>
                  </div>
                </td>

                <!-- Kelas Column -->
                <td class="py-3 px-3 text-gray-300 font-medium whitespace-nowrap">
                  {{ p.kelas }}
                </td>

                <!-- Status Column -->
                <td class="py-3 px-3 text-center whitespace-nowrap">
                  <span
                    :class="[
                      'px-2.5 py-1 rounded-full text-[10px] font-bold uppercase font-mono border inline-flex items-center gap-1',
                      p.status === 'terkunci' ? 'bg-rose-600/20 border-rose-500/40 text-rose-300' :
                      p.status === 'mengerjakan' ? 'bg-teal-500/20 border-teal-500/40 text-teal-300' :
                      p.status === 'selesai' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300' :
                      'bg-cyan-500/20 border-cyan-500/40 text-cyan-300'
                    ]"
                  >
                    <span>{{ p.status === 'terkunci' ? '🔒 Terkunci' : p.status === 'mengerjakan' ? '✍️ Ujian' : p.status === 'selesai' ? '✅ Selesai' : '⏳ Terminal' }}</span>
                  </span>
                </td>

                <!-- Progress Column -->
                <td class="py-3 px-3 min-w-[140px]">
                  <div class="space-y-1">
                    <div class="flex items-center justify-between text-[11px] font-mono">
                      <span class="text-white font-bold">{{ p.soalTerjawab || 0 }}<span v-if="jadwal.totalSoal" class="text-gray-400">/{{ jadwal.totalSoal }}</span> Butir</span>
                      <span v-if="jadwal.totalSoal" class="text-teal-400 font-bold">{{ getProgressPercent(p.soalTerjawab) }}%</span>
                    </div>
                    <div v-if="jadwal.totalSoal" class="w-full h-1.5 bg-slate-950 rounded-full overflow-hidden border border-white/5">
                      <div
                        :style="{ width: `${getProgressPercent(p.soalTerjawab)}%` }"
                        class="h-full bg-gradient-to-r from-teal-500 to-cyan-400 rounded-full transition-all duration-300"
                      ></div>
                    </div>
                    <div v-if="p.sisaWaktuDetik !== null && p.status === 'mengerjakan'" class="text-[10px] text-gray-400 font-mono">
                      Sisa: {{ formatRemainingSeconds(p.sisaWaktuDetik) }}
                    </div>
                  </div>
                </td>

                <!-- Anti-Cheat Column -->
                <td class="py-3 px-3 text-center whitespace-nowrap">
                  <div v-if="p.jumlahPelanggaran > 3 || (p.status === 'terkunci' && p.jumlahPelanggaran > 0)" class="space-y-0.5">
                    <span class="px-2.5 py-1 rounded-full bg-rose-600/30 border border-rose-500 text-rose-200 font-mono font-bold text-[10px] inline-flex items-center gap-1 shadow-sm shadow-rose-950">
                      🚨 {{ p.jumlahPelanggaran }}x Keluar (BLOKIR)
                    </span>
                  </div>
                  <span
                    v-else-if="p.jumlahPelanggaran > 0"
                    class="px-2.5 py-1 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 font-mono font-bold text-[10px] inline-flex items-center gap-1"
                  >
                    ⚠️ {{ p.jumlahPelanggaran }}x Keluar Layar
                  </span>
                  <span v-else class="text-emerald-400 text-[11px] font-medium font-mono">
                    ✓ Tertib
                  </span>
                </td>

                <!-- Nilai Column -->
                <td class="py-3 px-3 text-center font-mono whitespace-nowrap">
                  <span v-if="p.nilai !== null" class="font-black text-emerald-300 text-sm">
                    {{ p.nilai }}
                  </span>
                  <span v-else class="text-gray-500">-</span>
                </td>

                <!-- Aksi Kontrol (Contextual & Clean) -->
                <td class="py-3 px-4 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5">
                    <!-- Toggle Kunci / Buka Kunci -->
                    <button
                      v-if="p.status === 'terkunci'"
                      @click="bukaKunciSesi(p.id, p.namaSiswa)"
                      class="px-2.5 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 text-[11px] font-bold transition-all min-h-[32px] flex items-center gap-1 active:scale-95"
                      title="Buka Kunci Ujian"
                    >
                      🔓 Buka Kunci
                    </button>
                    <button
                      v-else-if="p.status === 'mengerjakan' || p.status === 'menunggu'"
                      @click="kunciSesi(p.id, p.namaSiswa)"
                      class="px-2.5 py-1.5 rounded-lg bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 border border-rose-500/30 text-[11px] font-bold transition-all min-h-[32px] flex items-center gap-1 active:scale-95"
                      title="Kunci Layar Ujian"
                    >
                      🔒 Kunci
                    </button>

                    <!-- Reset Sesi -->
                    <button
                      @click="resetSesi(p.id, p.namaSiswa)"
                      class="px-2.5 py-1.5 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 text-[11px] font-bold transition-all min-h-[32px] flex items-center gap-1 active:scale-95"
                      title="Reset Sesi Ujian"
                    >
                      🔄 Reset
                    </button>

                    <!-- Hapus Sesi -->
                    <button
                      @click="deleteSesi(p.id, p.namaSiswa)"
                      class="p-1.5 rounded-lg bg-white/5 hover:bg-rose-500/20 text-gray-400 hover:text-rose-300 border border-white/10 text-[11px] font-bold transition-all min-h-[32px] min-w-[32px] flex items-center justify-center active:scale-95"
                      title="Hapus Peserta dari Monitoring"
                    >
                      🗑️
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card View (md:hidden - Tailored for Smartphone Screens) -->
        <div v-if="filteredPeserta.length > 0" class="md:hidden space-y-3.5">
          <div
            v-for="p in filteredPeserta"
            :key="'mob-' + p.id"
            :class="[
              'p-4 rounded-2xl border space-y-3 shadow-md transition-all',
              p.status === 'terkunci' || p.jumlahPelanggaran > 3
                ? 'bg-rose-950/25 border-rose-500/50'
                : p.jumlahPelanggaran > 0
                  ? 'bg-amber-950/20 border-amber-500/40'
                  : 'bg-slate-900 border-white/10'
            ]"
          >
            <!-- Card Header: Student Avatar, Name, NIS, Status -->
            <div class="flex items-start justify-between gap-2.5 border-b border-white/5 pb-3">
              <div class="flex items-center gap-3">
                <div class="relative shrink-0">
                  <div class="w-10 h-10 rounded-full bg-slate-800 border border-white/10 text-cyan-400 font-black text-sm flex items-center justify-center">
                    {{ getInitials(p.namaSiswa) }}
                  </div>
                  <span
                    class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-slate-900"
                    :class="p.isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-gray-600'"
                  ></span>
                </div>
                <div>
                  <h4 class="font-bold text-white text-sm flex items-center gap-1.5">
                    <span>{{ p.namaSiswa }}</span>
                    <span
                      v-if="p.status === 'terkunci'"
                      class="px-1.5 py-0.2 rounded bg-rose-600 text-white text-[9px] font-mono font-black uppercase"
                    >
                      LOCKED
                    </span>
                  </h4>
                  <div class="text-[11px] text-gray-400 font-mono mt-0.5">
                    NIS: {{ p.nis }} • <span class="text-cyan-300 font-medium">{{ p.kelas }}</span>
                  </div>
                </div>
              </div>

              <!-- Status Badge -->
              <span
                :class="[
                  'px-2.5 py-1 rounded-full text-[10px] font-bold uppercase font-mono border shrink-0',
                  p.status === 'terkunci' ? 'bg-rose-600/20 border-rose-500/40 text-rose-300' :
                  p.status === 'mengerjakan' ? 'bg-teal-500/20 border-teal-500/40 text-teal-300' :
                  p.status === 'selesai' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300' :
                  'bg-cyan-500/20 border-cyan-500/40 text-cyan-300'
                ]"
              >
                {{ p.status === 'terkunci' ? '🔒 Terkunci' : p.status === 'mengerjakan' ? '✍️ Ujian' : p.status === 'selesai' ? '✅ Selesai' : '⏳ Terminal' }}
              </span>
            </div>

            <!-- Card Metrics (Progress, Violations, Score) -->
            <div class="space-y-2 bg-slate-950/60 p-3 rounded-xl border border-white/5 font-mono text-xs">
              <div class="flex items-center justify-between text-[11px]">
                <span class="text-gray-400">Progress Soal:</span>
                <span class="text-white font-bold">
                  {{ p.soalTerjawab || 0 }}<span v-if="jadwal.totalSoal" class="text-gray-400">/{{ jadwal.totalSoal }}</span> Butir
                  <span v-if="jadwal.totalSoal" class="text-teal-400">({{ getProgressPercent(p.soalTerjawab) }}%)</span>
                </span>
              </div>
              <div v-if="jadwal.totalSoal" class="w-full h-1.5 bg-slate-900 rounded-full overflow-hidden">
                <div
                  :style="{ width: `${getProgressPercent(p.soalTerjawab)}%` }"
                  class="h-full bg-teal-400 rounded-full"
                ></div>
              </div>

              <div class="grid grid-cols-2 gap-2 pt-1 border-t border-white/5 text-[11px]">
                <div>
                  <span class="text-gray-400 block text-[10px] font-sans">Pelanggaran:</span>
                  <span
                    class="font-bold"
                    :class="p.jumlahPelanggaran > 0 ? 'text-amber-400' : 'text-emerald-400'"
                  >
                    {{ p.jumlahPelanggaran > 0 ? `🚨 ${p.jumlahPelanggaran}x Keluar` : '✓ Tertib' }}
                  </span>
                </div>
                <div>
                  <span class="text-gray-400 block text-[10px] font-sans">Nilai:</span>
                  <span class="text-emerald-300 font-bold">
                    {{ p.nilai !== null ? p.nilai : '-' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Card Action Buttons (Spaced out for touch ergonomics) -->
            <div class="grid grid-cols-3 gap-2 pt-1">
              <button
                v-if="p.status === 'terkunci'"
                @click="bukaKunciSesi(p.id, p.namaSiswa)"
                class="py-2.5 px-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 text-xs font-bold min-h-[42px] flex items-center justify-center gap-1 active:scale-95"
              >
                🔓 Buka
              </button>
              <button
                v-else-if="p.status === 'mengerjakan' || p.status === 'menunggu'"
                @click="kunciSesi(p.id, p.namaSiswa)"
                class="py-2.5 px-2 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 border border-rose-500/30 text-xs font-bold min-h-[42px] flex items-center justify-center gap-1 active:scale-95"
              >
                🔒 Kunci
              </button>
              <div v-else class="text-center py-2 text-xs text-gray-500 flex items-center justify-center min-h-[42px]">
                -
              </div>

              <button
                @click="resetSesi(p.id, p.namaSiswa)"
                class="py-2.5 px-2 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 text-xs font-bold min-h-[42px] flex items-center justify-center gap-1 active:scale-95"
              >
                🔄 Reset
              </button>

              <button
                @click="deleteSesi(p.id, p.namaSiswa)"
                class="py-2.5 px-2 rounded-xl bg-white/5 hover:bg-rose-500/20 text-gray-400 hover:text-rose-300 border border-white/10 text-xs font-bold min-h-[42px] flex items-center justify-center gap-1 active:scale-95"
              >
                🗑️ Hapus
              </button>
            </div>
          </div>
        </div>
      </section>

    </main>
  </div>
</template>
