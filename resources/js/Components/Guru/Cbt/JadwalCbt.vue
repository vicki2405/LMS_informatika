<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { confirmDelete, showSuccessAlert, showToast } from '@/Utils/sweetalert';

const props = defineProps({
  jadwalList: { type: Array, default: () => [] },
  bankSoalList: { type: Array, default: () => [] },
  masterKelasList: { type: Array, default: () => [] },
  jurusanList: { type: Array, default: () => [] },
});

// Search & Filter
const searchQuery = ref('');
const filterStatus = ref('');
const filterJurusan = ref('');
const filterKelas = ref('');

// Live Monitoring Modal State
const showMonitoringModal = ref(false);
const selectedJadwalForMonitoring = ref(null);
const monitoringLoading = ref(false);
const monitoringData = ref({
  jadwal: {},
  stats: {
    totalPeserta: 0,
    menunggu: 0,
    mengerjakan: 0,
    selesai: 0,
    terkunci: 0,
    totalPelanggaran: 0,
    siswaMelanggar: 0,
  },
  peserta: [],
});
const searchPeserta = ref('');
const filterStatusPeserta = ref('');
let monitoringPollingInterval = null;
const autoRefreshEnabled = ref(true);

// Master kelas list filtered by filterJurusan for the top filter bar
const availableKelasForFilter = computed(() => {
  if (!filterJurusan.value) return props.masterKelasList;
  return props.masterKelasList.filter((k) => !k.jurusanId || String(k.jurusanId) === String(filterJurusan.value));
});

// Filtered Schedules
const filteredJadwals = computed(() => {
  return props.jadwalList.filter((j) => {
    const matchSearch = !searchQuery.value ||
      j.judulUjian.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      j.bankSoalJudul.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (j.token && j.token.toLowerCase().includes(searchQuery.value.toLowerCase()));

    const matchStatus = !filterStatus.value || j.status === filterStatus.value;
    const matchJurusan = !filterJurusan.value || String(j.jurusanId) === String(filterJurusan.value);
    const matchKelas = !filterKelas.value || String(j.kelasId) === String(filterKelas.value);

    return matchSearch && matchStatus && matchJurusan && matchKelas;
  });
});

function openCreateModal() {
  router.visit(route('guru.cbt.jadwal.create'));
}

function openEditModal(j) {
  router.visit(route('guru.cbt.jadwal.edit', j.id));
}

function toggleStatus(j) {
  router.patch(route('guru.cbt.jadwal.toggle-status', j.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      showToast(
        j.status === 'aktif' ? 'Jadwal CBT telah dinonaktifkan' : 'Jadwal CBT sekarang aktif & dapat dikerjakan siswa',
        'success'
      );
    },
  });
}

function deleteJadwal(id) {
  confirmDelete({
    title: 'Hapus Jadwal CBT?',
    text: 'Jadwal akan dihapus dari daftar aktif. Riwayat nilai siswa yang sudah masuk tetap tersimpan aman di sistem.',
    confirmButtonText: 'Ya, Hapus Jadwal!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(route('guru.cbt.jadwal.delete', id), {
        preserveScroll: true,
        onSuccess: () => showSuccessAlert('Berhasil Dihapus', 'Jadwal CBT telah dihapus.'),
      });
    }
  });
}

function copyToken(token) {
  if (!token) return;
  navigator.clipboard.writeText(token);
  showToast(`Token ${token} berhasil disalin!`, 'success');
}

// -----------------------------------------------------------------------------
// LIVE CBT & ANTI-CHEAT MONITORING METHODS
// -----------------------------------------------------------------------------
function openMonitoringModal(j) {
  selectedJadwalForMonitoring.value = j;
  showMonitoringModal.value = true;
  monitoringLoading.value = true;
  searchPeserta.value = '';
  filterStatusPeserta.value = '';
  fetchMonitoringData(j.id);
  startMonitoringPolling();
}

function closeMonitoringModal() {
  showMonitoringModal.value = false;
  selectedJadwalForMonitoring.value = null;
  stopMonitoringPolling();
}

async function fetchMonitoringData(jadwalId = null) {
  const id = jadwalId || selectedJadwalForMonitoring.value?.id;
  if (!id) return;

  try {
    const res = await axios.get(route('guru.cbt.jadwal.monitoring', id));
    if (res.data && res.data.success) {
      monitoringData.value = {
        jadwal: res.data.jadwal || {},
        stats: res.data.stats || {},
        peserta: res.data.peserta || [],
      };
    }
  } catch (e) {
    console.error('Monitoring fetch error:', e);
  } finally {
    monitoringLoading.value = false;
  }
}

function startMonitoringPolling() {
  stopMonitoringPolling();
  monitoringPollingInterval = setInterval(() => {
    if (showMonitoringModal.value && autoRefreshEnabled.value && selectedJadwalForMonitoring.value) {
      fetchMonitoringData();
    }
  }, 5000);
}

function stopMonitoringPolling() {
  if (monitoringPollingInterval) {
    clearInterval(monitoringPollingInterval);
    monitoringPollingInterval = null;
  }
}

onUnmounted(() => {
  stopMonitoringPolling();
});

// Reset Sesi Siswa (misal jika ada kendala teknis PC)
function resetSesi(sesiId, namaSiswa) {
  confirmDelete({
    title: 'Reset Sesi Ujian Siswa?',
    text: `Sesi ujian atas nama ${namaSiswa} akan dikembalikan ke status menunggu di terminal dan catatan pelanggaran direset ke 0.`,
    confirmButtonText: 'Ya, Reset Sesi!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('guru.cbt.sesi.reset', sesiId), {}, {
        preserveScroll: true,
        onSuccess: () => {
          showToast(`Sesi ujian ${namaSiswa} berhasil direset!`, 'success');
          fetchMonitoringData();
        },
      });
    }
  });
}

// Kunci Sesi Siswa (karena kecurangan fatal / split screen)
function kunciSesi(sesiId, namaSiswa) {
  confirmDelete({
    title: 'Kunci Ujian Siswa?',
    text: `Layar siswa ${namaSiswa} akan langsung terkunci dan siswa tidak dapat melanjutkan pengerjaan soal sampai dibuka oleh guru.`,
    confirmButtonText: 'Ya, Kunci Ujian!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('guru.cbt.sesi.kunci', sesiId), {}, {
        preserveScroll: true,
        onSuccess: () => {
          showToast(`Ujian siswa ${namaSiswa} telah dikunci!`, 'warning');
          fetchMonitoringData();
        },
      });
    }
  });
}

// Buka Kunci Sesi Siswa
function bukaKunciSesi(sesiId, namaSiswa) {
  confirmDelete({
    title: 'Buka Kunci Ujian Siswa?',
    text: `Kunci ujian atas nama ${namaSiswa} akan dibuka kembali dan siswa dapat langsung melanjutkan pengerjaan soal.`,
    confirmButtonText: 'Ya, Buka Kunci!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('guru.cbt.sesi.buka-kunci', sesiId), {}, {
        preserveScroll: true,
        onSuccess: () => {
          showToast(`Kunci ujian ${namaSiswa} berhasil dibuka!`, 'success');
          fetchMonitoringData();
        },
      });
    }
  });
}

// Hapus Sesi Peserta dari Monitoring
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
          showToast(`Peserta ${namaSiswa} berhasil dihapus dari monitoring!`, 'success');
          fetchMonitoringData();
        },
      });
    }
  });
}

const filteredPesertaMonitoring = computed(() => {
  return (monitoringData.value.peserta || []).filter((p) => {
    const q = searchPeserta.value.toLowerCase().trim();
    const matchSearch = !q ||
      p.namaSiswa.toLowerCase().includes(q) ||
      p.nis.toLowerCase().includes(q) ||
      p.kelas.toLowerCase().includes(q);

    const matchStatus = !filterStatusPeserta.value ||
      (filterStatusPeserta.value === 'melanggar' ? p.jumlahPelanggaran > 0 :
       filterStatusPeserta.value === 'terblokir' ? (p.status === 'terkunci' || p.jumlahPelanggaran > 3) :
       p.status === filterStatusPeserta.value);

    return matchSearch && matchStatus;
  });
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-teal-900/60 via-slate-900 to-cyan-950/70 border border-teal-500/20 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
      <div class="absolute -top-24 -right-24 w-72 h-72 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/10 border border-teal-500/30 text-teal-300 text-xs font-black tracking-wider uppercase mb-2">
            <span>⏱️ Pengaturan &amp; Pengawasan CBT</span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            Jadwal Pelaksanaan CBT
          </h1>
          <p class="text-xs sm:text-sm text-gray-400 mt-1 max-w-2xl">
            Atur sesi ujian, token akses, kelas sasaran, serta kontrol <strong>Monitoring Real-Time &amp; Anti-Cheat</strong> peserta secara langsung.
          </p>
        </div>
        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2.5 px-5 py-3 rounded-2xl bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-400 hover:to-cyan-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-teal-500/20 transition-all duration-200 active:scale-95 shrink-0 min-h-[44px]"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
          </svg>
          <span>Tambah Jadwal CBT</span>
        </button>
      </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/80 border border-white/10 backdrop-blur-xl space-y-3 sm:space-y-0 sm:flex sm:items-center sm:gap-4 shadow-lg">
      <div class="flex-1 relative">
        <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari judul ujian, bank soal, atau token..."
          class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs sm:text-sm placeholder-gray-500 focus:outline-none focus:border-teal-400 transition-colors min-h-[40px]"
        />
      </div>

      <!-- Filter Status -->
      <select
        v-model="filterStatus"
        class="w-full sm:w-44 py-2.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs sm:text-sm focus:outline-none focus:border-teal-400 min-h-[40px]"
      >
        <option value="">Semua Status</option>
        <option value="aktif">🟢 Aktif (Terbuka)</option>
        <option value="nonaktif">⚪ Non-Aktif (Terkunci)</option>
      </select>

      <!-- Filter Jurusan -->
      <select
        v-model="filterJurusan"
        @change="filterKelas = ''"
        class="w-full sm:w-44 py-2.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs sm:text-sm focus:outline-none focus:border-teal-400 min-h-[40px]"
      >
        <option value="">Semua Jurusan</option>
        <option v-for="j in jurusanList" :key="j.id" :value="j.id">{{ j.kode }} - {{ j.nama }}</option>
      </select>

      <!-- Filter Kelas -->
      <select
        v-model="filterKelas"
        class="w-full sm:w-44 py-2.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs sm:text-sm focus:outline-none focus:border-teal-400 min-h-[40px]"
      >
        <option value="">Semua Kelas</option>
        <option v-for="k in availableKelasForFilter" :key="k.id" :value="k.id">{{ k.namaKelas }}</option>
      </select>
    </div>

    <!-- Empty State -->
    <div v-if="filteredJadwals.length === 0" class="text-center py-16 px-4 rounded-3xl bg-slate-900/60 border border-white/10">
      <div class="w-16 h-16 mx-auto rounded-2xl bg-teal-500/10 text-teal-400 flex items-center justify-center text-3xl mb-3">
        📅
      </div>
      <h3 class="text-base font-bold text-white">Belum Ada Jadwal CBT</h3>
      <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
        Belum ada jadwal pelaksanaan ujian yang dibuat. Klik "Tambah Jadwal CBT" untuk membuka sesi ujian bagi siswa.
      </p>
    </div>

    <!-- Desktop View: Table (hidden md:block) -->
    <div v-if="filteredJadwals.length > 0" class="hidden md:block overflow-hidden rounded-2xl bg-slate-900/80 border border-white/10 shadow-xl backdrop-blur-xl">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
            <th class="py-4 px-5">Sesi / Judul Ujian</th>
            <th class="py-4 px-4">Bank Soal &amp; Mapel</th>
            <th class="py-4 px-4">Jurusan &amp; Kelas</th>
            <th class="py-4 px-4 text-center">Token Akses</th>
            <th class="py-4 px-4 text-center">Status Ujian</th>
            <th class="py-4 px-4 text-center">Pengawasan (Live)</th>
            <th class="py-4 px-5 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5 text-xs">
          <tr v-for="j in filteredJadwals" :key="j.id" class="hover:bg-white/[0.03] transition-colors group">
            <td class="py-4 px-5">
              <p class="font-bold text-white text-sm group-hover:text-teal-300 transition-colors">
                {{ j.judulUjian }}
              </p>
              <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                <span>🕒 {{ j.waktuMulai || 'Fleksibel' }}</span>
                <span>s/d</span>
                <span>{{ j.waktuSelesai || 'Selesai' }}</span>
              </div>
              <div class="flex flex-wrap items-center gap-1.5 mt-2">
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-bold text-[10px] border',
                    j.modeUjian === 'game'
                      ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm shadow-amber-500/10'
                      : 'bg-cyan-500/10 text-cyan-300 border-cyan-500/20'
                  ]"
                >
                  {{ j.modeUjian === 'game' ? '🎮 Mode Game' : '🏛️ CBT Formal' }}
                </span>

                <span
                  v-if="j.acakSoal"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-300 font-bold text-[10px]"
                  title="Urutan soal diacak untuk setiap siswa"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  Soal Acak
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-400 font-medium text-[10px]"
                >
                  Soal Urut
                </span>

                <span
                  v-if="j.acakOpsi"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30 text-purple-300 font-bold text-[10px]"
                  title="Pilihan ganda A-E diacak per butir soal"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                  Opsi Acak
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-400 font-medium text-[10px]"
                >
                  Opsi Urut
                </span>
              </div>
            </td>
            <td class="py-4 px-4">
              <p class="font-semibold text-gray-200">{{ j.bankSoalJudul }}</p>
              <span class="inline-flex items-center gap-1 text-[11px] text-teal-400 mt-0.5">
                📖 {{ j.mapelNama }}
              </span>
            </td>
            <td class="py-4 px-4">
              <div class="space-y-1">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/20 text-cyan-300 font-bold text-[11px]">
                  🏢 {{ j.jurusanNama || 'Semua Jurusan' }}
                </span>
                <div class="text-[11px] text-gray-300 flex items-center gap-1 font-semibold">
                  <span>🏫</span>
                  <span>{{ j.kelasNama || 'Semua Kelas' }}</span>
                </div>
                <p class="text-[10px] text-gray-400">⏱️ {{ j.durasiMenit }} Menit</p>
              </div>
            </td>
            <td class="py-4 px-4 text-center">
              <button
                @click="copyToken(j.token)"
                title="Klik untuk salin token"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 font-mono font-black text-cyan-300 text-xs transition-colors"
              >
                <span>{{ j.token }}</span>
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              </button>
            </td>
            <td class="py-4 px-4 text-center">
              <!-- Quick Toggle Status Switch -->
              <button
                @click="toggleStatus(j)"
                :title="j.status === 'aktif' ? 'Klik untuk Non-Aktifkan Soal' : 'Klik untuk Aktifkan Soal'"
                :class="[
                  'inline-flex items-center gap-2 px-3 py-1.5 rounded-full font-extrabold text-xs transition-all active:scale-95 border min-h-[36px]',
                  j.status === 'aktif'
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-lg shadow-emerald-500/10 hover:bg-emerald-500/30'
                    : 'bg-rose-500/15 text-rose-300 border-rose-500/30 hover:bg-rose-500/25'
                ]"
              >
                <span class="w-2 h-2 rounded-full" :class="j.status === 'aktif' ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                <span>{{ j.status === 'aktif' ? 'AKTIF' : 'NON-AKTIF' }}</span>
              </button>
            </td>
            <!-- LIVE MONITORING RADAR BUTTON -->
            <td class="py-4 px-4 text-center">
              <button
                @click="openMonitoringModal(j)"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-cyan-500/20 to-teal-500/20 hover:from-cyan-500/30 hover:to-teal-500/30 text-cyan-300 border border-cyan-500/40 font-black text-xs shadow-md shadow-cyan-500/10 active:scale-95 transition-all min-h-[36px]"
              >
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                <span>📡 Pantau Live</span>
              </button>
            </td>
            <td class="py-4 px-5 text-right">
              <div class="inline-flex items-center gap-2">
                <button
                  @click="openEditModal(j)"
                  title="Edit Jadwal"
                  class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button
                  @click="deleteJadwal(j.id)"
                  title="Hapus Jadwal"
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
    <div v-if="filteredJadwals.length > 0" class="md:hidden space-y-4">
      <div
        v-for="j in filteredJadwals"
        :key="j.id"
        class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 space-y-3 shadow-lg"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <h4 class="font-black text-white text-sm">{{ j.judulUjian }}</h4>
            <p class="text-xs text-teal-400 mt-0.5">📖 {{ j.bankSoalJudul }} ({{ j.mapelNama }})</p>
          </div>
          <button
            @click="copyToken(j.token)"
            class="px-2.5 py-1 rounded bg-white/5 border border-white/10 text-cyan-300 font-mono font-bold text-xs shrink-0"
          >
            🔑 {{ j.token }}
          </button>
        </div>

        <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-300 bg-white/5 p-2.5 rounded-xl font-mono">
          <div>
            <span class="text-gray-400 block text-[10px] uppercase font-sans">Jurusan &amp; Kelas:</span>
            <span class="font-bold text-cyan-300 truncate block">🏢 {{ j.jurusanKode || 'ALL' }} - {{ j.kelasNama }}</span>
          </div>
          <div>
            <span class="text-gray-400 block text-[10px] uppercase font-sans">Durasi Ujian:</span>
            <span class="font-bold text-teal-300">⏱️ {{ j.durasiMenit }} Menit</span>
          </div>
        </div>

        <!-- Badges Acak Soal, Opsi & Mode Mobile -->
        <div class="flex flex-wrap items-center gap-1.5">
          <span
            :class="[
              'inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-bold text-[10px] border',
              j.modeUjian === 'game'
                ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                : 'bg-cyan-500/10 text-cyan-300 border-cyan-500/20'
            ]"
          >
            {{ j.modeUjian === 'game' ? '🎮 Mode Game' : '🏛️ CBT Formal' }}
          </span>

          <span
            v-if="j.acakSoal"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-300 font-bold text-[10px]"
          >
            🔀 Soal Acak
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-400 font-medium text-[10px]"
          >
            Soal Urut
          </span>
          <span
            v-if="j.acakOpsi"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30 text-purple-300 font-bold text-[10px]"
          >
            🔀 Opsi Acak
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-400 font-medium text-[10px]"
          >
            Opsi Urut
          </span>
        </div>

        <!-- Monitoring Button Mobile -->
        <div>
          <button
            @click="openMonitoringModal(j)"
            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500/20 to-teal-500/20 hover:from-cyan-500/30 text-cyan-300 border border-cyan-500/40 font-black text-xs flex items-center justify-center gap-2 min-h-[44px]"
          >
            <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
            <span>📡 Pantau Peserta &amp; Pelanggaran (Live)</span>
          </button>
        </div>

        <div class="flex items-center justify-between pt-1 border-t border-white/10">
          <!-- Toggle Status -->
          <button
            @click="toggleStatus(j)"
            :class="[
              'inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-black min-h-[40px]',
              j.status === 'aktif'
                ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                : 'bg-rose-500/15 text-rose-300 border border-rose-500/30'
            ]"
          >
            <span class="w-2 h-2 rounded-full" :class="j.status === 'aktif' ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
            <span>{{ j.status === 'aktif' ? 'AKTIF' : 'NON-AKTIF' }}</span>
          </button>

          <div class="flex items-center gap-2">
            <button
              @click="openEditModal(j)"
              class="p-2 rounded-xl bg-white/5 text-gray-300 border border-white/10 min-h-[40px] min-w-[40px] flex items-center justify-center"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>
            <button
              @click="deleteJadwal(j.id)"
              class="p-2 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 min-h-[40px] min-w-[40px] flex items-center justify-center"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- LIVE MONITORING MODAL (DASHBOARD KONTROL GURU REAL-TIME)                 -->
    <!-- ======================================================================= -->
    <div
      v-if="showMonitoringModal && selectedJadwalForMonitoring"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-black/85 backdrop-blur-md overflow-y-auto animate-in fade-in"
    >
      <div class="w-full max-w-5xl rounded-3xl bg-slate-900 border border-cyan-500/40 p-5 sm:p-7 shadow-2xl space-y-6 my-6 max-h-[92vh] overflow-y-auto">
        
        <!-- Header Monitoring -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/10 pb-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 flex items-center justify-center text-xl shrink-0">
              📡
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span class="text-[10px] font-mono font-black text-emerald-400 uppercase tracking-widest">LIVE MONITORING CBT &amp; ANTI-CHEAT</span>
              </div>
              <h3 class="text-base sm:text-lg font-black text-white leading-tight">
                {{ selectedJadwalForMonitoring.judulUjian }}
              </h3>
              <p class="text-xs text-teal-400 mt-0.5">
                📖 {{ selectedJadwalForMonitoring.mapelNama }} • Token: <strong class="font-mono text-white">{{ selectedJadwalForMonitoring.token }}</strong>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
            <!-- Auto-Refresh Toggle -->
            <button
              @click="autoRefreshEnabled = !autoRefreshEnabled"
              :class="[
                'px-3 py-1.5 rounded-xl border text-[11px] font-mono font-bold flex items-center gap-1.5 transition-all min-h-[38px]',
                autoRefreshEnabled ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300' : 'bg-white/5 border-white/10 text-gray-400'
              ]"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="autoRefreshEnabled ? 'bg-emerald-400 animate-pulse' : 'bg-gray-500'"></span>
              <span>{{ autoRefreshEnabled ? 'Auto-Poll (5s)' : 'Pause Poll' }}</span>
            </button>

            <!-- Manual Refresh -->
            <button
              @click="fetchMonitoringData()"
              class="p-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-white min-h-[38px] min-w-[38px] flex items-center justify-center"
              title="Refresh Data Sekarang"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>

            <!-- Close Modal -->
            <button
              @click="closeMonitoringModal"
              class="p-2 rounded-xl bg-white/5 hover:bg-rose-500/20 text-gray-400 hover:text-rose-300 min-h-[38px] min-w-[38px] flex items-center justify-center"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>

        <!-- 4 Modern Responsive KPI Cards (2x2 on Mobile, 4x1 on Desktop) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
          
          <!-- Card 1: Total Sesi -->
          <div class="p-4 rounded-2xl bg-gradient-to-br from-cyan-950/40 to-slate-900 border border-cyan-500/30 space-y-1">
            <span class="text-[10px] text-gray-400 uppercase font-black tracking-wider block">Total Tergabung:</span>
            <div class="flex items-baseline gap-2">
              <span class="text-2xl sm:text-3xl font-black text-cyan-300 font-mono">{{ monitoringData.stats.totalPeserta || 0 }}</span>
              <span class="text-xs text-gray-400">Siswa</span>
            </div>
            <span class="text-[10px] text-cyan-400 font-medium">Dalam Sesi Ujian</span>
          </div>

          <!-- Card 2: Menunggu di Ruang Tunggu -->
          <div class="p-4 rounded-2xl bg-gradient-to-br from-amber-950/30 to-slate-900 border border-amber-500/30 space-y-1">
            <span class="text-[10px] text-gray-400 uppercase font-black tracking-wider block">Di Ruang Tunggu:</span>
            <div class="flex items-baseline gap-2">
              <span class="text-2xl sm:text-3xl font-black text-amber-300 font-mono">{{ monitoringData.stats.menunggu || 0 }}</span>
              <span class="text-xs text-gray-400">Siswa</span>
            </div>
            <span class="text-[10px] text-amber-400 font-medium">Menunggu Token</span>
          </div>

          <!-- Card 3: Sedang Mengerjakan -->
          <div class="p-4 rounded-2xl bg-gradient-to-br from-teal-950/40 to-slate-900 border border-teal-500/30 space-y-1">
            <span class="text-[10px] text-gray-400 uppercase font-black tracking-wider block">Sedang Mengerjakan:</span>
            <div class="flex items-baseline gap-2">
              <span class="text-2xl sm:text-3xl font-black text-teal-300 font-mono">{{ monitoringData.stats.mengerjakan || 0 }}</span>
              <span class="text-xs text-gray-400">Siswa</span>
            </div>
            <span class="text-[10px] text-emerald-400 font-medium">{{ monitoringData.stats.selesai || 0 }} Telah Selesai</span>
          </div>

          <!-- Card 4: Siswa Melanggar (Red Warning) -->
          <div
            :class="[
              'p-4 rounded-2xl border space-y-1 transition-all',
              (monitoringData.stats.siswaMelanggar || 0) > 0
                ? 'bg-rose-950/50 border-rose-500/60 shadow-lg shadow-rose-500/20 animate-pulse'
                : 'bg-slate-900/60 border-white/10'
            ]"
          >
            <span class="text-[10px] uppercase font-black tracking-wider block" :class="(monitoringData.stats.siswaMelanggar || 0) > 0 ? 'text-rose-300' : 'text-gray-400'">
              🚨 Terdeteksi Melanggar:
            </span>
            <div class="flex items-baseline gap-2">
              <span class="text-2xl sm:text-3xl font-black font-mono" :class="(monitoringData.stats.siswaMelanggar || 0) > 0 ? 'text-rose-400' : 'text-gray-400'">
                {{ monitoringData.stats.siswaMelanggar || 0 }}
              </span>
              <span class="text-xs text-gray-400">Siswa</span>
            </div>
            <span class="text-[10px] font-medium" :class="(monitoringData.stats.siswaMelanggar || 0) > 0 ? 'text-rose-300 font-bold' : 'text-gray-500'">
              {{ monitoringData.stats.totalPelanggaran || 0 }} Kali Tab Switch / AI
            </span>
          </div>

        </div>

        <!-- Filter & Search Inside Modal -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3 rounded-2xl bg-white/5 border border-white/10">
          <div class="relative flex-1">
            <input
              v-model="searchPeserta"
              type="text"
              placeholder="🔍 Cari nama siswa, NIS, atau kelas..."
              class="w-full px-4 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-cyan-400 min-h-[38px]"
            />
          </div>

          <div class="flex items-center gap-2">
            <select
              v-model="filterStatusPeserta"
              class="px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs focus:outline-none focus:border-cyan-400 min-h-[38px]"
            >
              <option value="">Semua Status ({{ monitoringData.peserta?.length || 0 }})</option>
              <option value="menunggu">⏳ Ruang Tunggu</option>
              <option value="mengerjakan">✍️ Sedang Ujian</option>
              <option value="selesai">✅ Selesai</option>
              <option value="terblokir">🚨 Terblokir (>3x Keluar)</option>
              <option value="terkunci">🔒 Terkunci</option>
              <option value="melanggar">⚠️ Semua Pelanggaran</option>
            </select>
          </div>
        </div>

        <!-- Participant Roster List -->
        <div>
          <!-- Empty State -->
          <div v-if="filteredPesertaMonitoring.length === 0" class="text-center py-12 rounded-2xl bg-white/5 border border-white/10 text-gray-400 text-xs">
            Belum ada data peserta yang cocok dengan filter atau belum ada siswa yang masuk ke sesi ini.
          </div>

          <!-- Desktop Table (hidden md:block) -->
          <div v-else class="hidden md:block overflow-hidden rounded-2xl bg-slate-950/60 border border-white/10 shadow-lg">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
                  <th class="py-3 px-4">Nama Siswa / Peserta</th>
                  <th class="py-3 px-3">Kelas</th>
                  <th class="py-3 px-3 text-center">Status Live</th>
                  <th class="py-3 px-3 text-center">Soal Terjawab</th>
                  <th class="py-3 px-3 text-center">Pelanggaran &amp; Blokir</th>
                  <th class="py-3 px-3 text-center">Nilai / Hasil</th>
                  <th class="py-3 px-4 text-right">Aksi Kontrol Guru</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <tr
                  v-for="p in filteredPesertaMonitoring"
                  :key="p.id"
                  :class="[
                    'transition-colors',
                    p.status === 'terkunci' || p.jumlahPelanggaran > 3
                      ? 'bg-rose-950/30 hover:bg-rose-950/40 border-l-4 border-l-rose-500'
                      : p.jumlahPelanggaran > 0
                        ? 'bg-amber-950/20 hover:bg-amber-950/30 border-l-4 border-l-amber-500'
                        : 'hover:bg-white/[0.03]'
                  ]"
                >
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-2.5">
                      <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="p.isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-gray-600'" :title="p.isOnline ? 'Aktif Online' : 'Tidak Aktif'"></span>
                      <div>
                        <div class="font-bold text-white text-xs flex items-center gap-1.5">
                          <span>{{ p.namaSiswa }}</span>
                          <span v-if="p.status === 'terkunci'" class="px-1.5 py-0.2 rounded bg-rose-600 text-white text-[9px] font-mono font-black uppercase tracking-wider">LOCKED</span>
                        </div>
                        <div class="text-[10px] text-cyan-400 font-mono">NIS: {{ p.nis }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 px-3 text-gray-300 font-medium">
                    {{ p.kelas }}
                  </td>
                  <td class="py-3 px-3 text-center">
                    <span
                      :class="[
                        'px-2.5 py-1 rounded-full text-[10px] font-black uppercase font-mono border inline-flex items-center gap-1',
                        p.status === 'terkunci' ? 'bg-rose-600/25 border-rose-500 text-rose-200' :
                        p.status === 'mengerjakan' ? 'bg-amber-500/20 border-amber-500/40 text-amber-300' :
                        p.status === 'selesai' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300' :
                        'bg-cyan-500/20 border-cyan-500/40 text-cyan-300'
                      ]"
                    >
                      <span>{{ p.status === 'terkunci' ? '🔒 Terkunci' : p.status === 'mengerjakan' ? '✍️ Ujian' : p.status === 'selesai' ? '✅ Selesai' : '⏳ Terminal' }}</span>
                    </span>
                  </td>
                  <td class="py-3 px-3 text-center font-mono font-bold text-gray-300">
                    {{ p.soalTerjawab || 0 }} Butir
                  </td>
                  <td class="py-3 px-3 text-center">
                    <div v-if="p.jumlahPelanggaran > 3 || (p.status === 'terkunci' && p.jumlahPelanggaran > 0)" class="space-y-0.5">
                      <span class="px-2.5 py-0.5 rounded-full bg-rose-600/30 border border-rose-500 text-rose-200 font-mono font-black text-[11px] animate-pulse inline-flex items-center gap-1 shadow-sm shadow-rose-900/50">
                        🚨 {{ p.jumlahPelanggaran }}x (DIBLOKIR)
                      </span>
                      <span class="block text-[9px] text-rose-400 font-sans font-medium">>3x keluar sistem</span>
                    </div>
                    <span
                      v-else-if="p.jumlahPelanggaran > 0"
                      class="px-2.5 py-1 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 font-mono font-bold text-[11px] inline-flex items-center gap-1"
                    >
                      <span>⚠️ {{ p.jumlahPelanggaran }}x Keluar</span>
                    </span>
                    <span v-else class="text-emerald-400 text-[11px] font-mono">
                      ✓ Bersih
                    </span>
                  </td>
                  <td class="py-3 px-3 text-center font-mono">
                    <span v-if="p.nilai !== null" class="font-black text-emerald-300">
                      {{ p.nilai }} ({{ p.nilaiStatus || 'Tuntas' }})
                    </span>
                    <span v-else class="text-gray-500">-</span>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <div class="inline-flex items-center gap-1.5">
                      <!-- 1. Buka Kunci (jika terkunci) OR Kunci (jika tidak terkunci) -->
                      <button
                        v-if="p.status === 'terkunci'"
                        @click="bukaKunciSesi(p.id, p.namaSiswa)"
                        title="Buka Kunci Ujian Siswa"
                        class="px-2.5 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 text-[11px] font-bold transition-all min-h-[32px] flex items-center gap-1"
                      >
                        🔓 Buka Kunci
                      </button>
                      <button
                        v-else
                        @click="kunciSesi(p.id, p.namaSiswa)"
                        title="Kunci Layar Ujian Siswa"
                        class="px-2.5 py-1 rounded-lg bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 border border-rose-500/30 text-[11px] font-bold transition-all min-h-[32px] flex items-center gap-1"
                      >
                        🔒 Kunci
                      </button>

                      <!-- 2. Reset Sesi -->
                      <button
                        @click="resetSesi(p.id, p.namaSiswa)"
                        title="Reset Sesi Ujian Siswa"
                        class="px-2.5 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 text-[11px] font-bold transition-all min-h-[32px] flex items-center gap-1"
                      >
                        🔄 Reset
                      </button>

                      <!-- 3. Delete Sesi -->
                      <button
                        @click="deleteSesi(p.id, p.namaSiswa)"
                        title="Hapus Sesi Peserta dari Monitoring"
                        class="px-2.5 py-1 rounded-lg bg-red-500/15 hover:bg-red-500/25 text-red-400 border border-red-500/30 text-[11px] font-bold transition-all min-h-[32px] flex items-center gap-1"
                      >
                        🗑️ Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile Card List (md:hidden) -->
          <div v-if="filteredPesertaMonitoring.length > 0" class="md:hidden space-y-3">
            <div
              v-for="p in filteredPesertaMonitoring"
              :key="'mob-' + p.id"
              :class="[
                'p-4 rounded-2xl border space-y-3 shadow-md',
                p.status === 'terkunci' || p.jumlahPelanggaran > 3
                  ? 'bg-rose-950/40 border-rose-500/50'
                  : p.jumlahPelanggaran > 0
                    ? 'bg-amber-950/30 border-amber-500/40'
                    : 'bg-slate-950/70 border-white/10'
              ]"
            >
              <div class="flex items-start justify-between gap-2 border-b border-white/5 pb-2.5">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="p.isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-gray-600'"></span>
                  <div>
                    <h4 class="font-black text-white text-sm flex items-center gap-1.5">
                      <span>{{ p.namaSiswa }}</span>
                      <span v-if="p.status === 'terkunci'" class="px-1.5 py-0.2 rounded bg-rose-600 text-white text-[9px] font-mono font-black uppercase">LOCKED</span>
                    </h4>
                    <span class="text-[10px] text-cyan-400 font-mono">NIS: {{ p.nis }} • {{ p.kelas }}</span>
                  </div>
                </div>

                <span
                  :class="[
                    'px-2.5 py-1 rounded-full text-[10px] font-black uppercase font-mono border',
                    p.status === 'terkunci' ? 'bg-rose-600/25 border-rose-500 text-rose-200' :
                    p.status === 'mengerjakan' ? 'bg-amber-500/20 border-amber-500/40 text-amber-300' :
                    p.status === 'selesai' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300' :
                    'bg-cyan-500/20 border-cyan-500/40 text-cyan-300'
                  ]"
                >
                  {{ p.status === 'terkunci' ? '🔒 Terkunci' : p.status === 'mengerjakan' ? '✍️ Ujian' : p.status === 'selesai' ? '✅ Selesai' : '⏳ Terminal' }}
                </span>
              </div>

              <div class="grid grid-cols-2 gap-2 text-[11px] bg-white/5 p-2.5 rounded-xl font-mono">
                <div>
                  <span class="text-gray-400 block text-[10px] uppercase font-sans">Pelanggaran:</span>
                  <div v-if="p.jumlahPelanggaran > 3 || (p.status === 'terkunci' && p.jumlahPelanggaran > 0)">
                    <span class="text-rose-400 font-black text-xs block animate-pulse">
                      🚨 {{ p.jumlahPelanggaran }}x DIBLOKIR
                    </span>
                    <span class="text-[9px] text-rose-300 font-sans">>3x keluar sistem</span>
                  </div>
                  <span v-else-if="p.jumlahPelanggaran > 0" class="text-amber-400 font-bold">
                    ⚠️ {{ p.jumlahPelanggaran }}x Terdeteksi
                  </span>
                  <span v-else class="text-emerald-400 font-bold">
                    ✓ Bersih
                  </span>
                </div>
                <div>
                  <span class="text-gray-400 block text-[10px] uppercase font-sans">Soal Terjawab:</span>
                  <span class="text-cyan-300 font-bold">{{ p.soalTerjawab || 0 }} Butir</span>
                </div>
              </div>

              <!-- Action Buttons Mobile (Grid 3 Columns with min 38px touch target) -->
              <div class="grid grid-cols-3 gap-2 pt-1">
                <button
                  v-if="p.status === 'terkunci'"
                  @click="bukaKunciSesi(p.id, p.namaSiswa)"
                  class="py-2 px-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/40 text-xs font-bold min-h-[38px] flex items-center justify-center gap-1 active:scale-95"
                >
                  🔓 Buka
                </button>
                <button
                  v-else
                  @click="kunciSesi(p.id, p.namaSiswa)"
                  class="py-2 px-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/40 text-xs font-bold min-h-[38px] flex items-center justify-center gap-1 active:scale-95"
                >
                  🔒 Kunci
                </button>
                <button
                  @click="resetSesi(p.id, p.namaSiswa)"
                  class="py-2 px-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 text-xs font-bold min-h-[38px] flex items-center justify-center gap-1 active:scale-95"
                >
                  🔄 Reset
                </button>
                <button
                  @click="deleteSesi(p.id, p.namaSiswa)"
                  class="py-2 px-2 rounded-xl bg-red-500/20 hover:bg-red-500/30 text-red-300 border border-red-500/40 text-xs font-bold min-h-[38px] flex items-center justify-center gap-1 active:scale-95"
                >
                  🗑️ Hapus
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Modal -->
        <div class="flex items-center justify-between border-t border-white/10 pt-4 text-xs text-gray-400">
          <span>* Monitoring diperbarui otomatis setiap 5 detik secara berkala.</span>
          <button
            @click="closeMonitoringModal"
            class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold min-h-[40px]"
          >
            Tutup
          </button>
        </div>

      </div>
    </div>
  </div>
</template>
