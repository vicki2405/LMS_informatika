<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { showToast } from '@/Utils/sweetalert';

const props = defineProps({
  materi: {
    type: Object,
    required: true,
  },
  summary: {
    type: Object,
    default: () => ({
      totalSiswa: 0,
      selesaiCount: 0,
      membacaCount: 0,
      belumBukaCount: 0,
      persentaseSelesai: 0,
    }),
  },
  students: {
    type: Array,
    default: () => [],
  },
});

// Navigation
function goBack() {
  router.visit('/guru/dashboard?tab=materi');
}

// Live Refresh
const isRefreshing = ref(false);
function refreshData() {
  isRefreshing.value = true;
  router.reload({
    only: ['students', 'summary'],
    onFinish: () => {
      isRefreshing.value = false;
      showToast('Data monitoring pembaca berhasil diperbarui.', 'info');
    },
  });
}

// Filter and Search
const statusFilter = ref('semua');
const searchQuery = ref('');

const filteredStudents = computed(() => {
  let list = props.students || [];

  if (statusFilter.value !== 'semua') {
    list = list.filter((s) => s.status === statusFilter.value);
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(
      (s) =>
        (s.nama && s.nama.toLowerCase().includes(q)) ||
        (s.nis && String(s.nis).toLowerCase().includes(q))
    );
  }

  return list;
});

// Helper styling
function getTypeBadge(tipe) {
  switch (tipe) {
    case 'slider':
      return { label: 'Slider Presentasi Interaktif', color: 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30' };
    case 'pdf':
      return { label: 'Dokumen PDF / PPT', color: 'bg-rose-500/15 text-rose-300 border-rose-500/30' };
    case 'video':
      return { label: 'Video Pembelajaran', color: 'bg-amber-500/15 text-amber-300 border-amber-500/30' };
    default:
      return { label: 'Artikel & Teks Interaktif', color: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30' };
  }
}

function getInitials(name) {
  if (!name) return 'S';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[1][0]).toUpperCase();
}
</script>

<template>
  <Head :title="`Monitoring Pembaca: ${materi.judul} — SMKS Muhammadiyah 2 Genteng`" />

  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans pb-16 selection:bg-cyan-500 selection:text-slate-950">
    <!-- TOP STICKY NAVBAR -->
    <header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-white/10 shadow-lg">
      <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-4">
        <!-- Back & Breadcrumb -->
        <div class="flex items-center gap-3 min-w-0">
          <button
            @click="goBack"
            type="button"
            class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white border border-white/10 text-xs font-bold transition-all flex items-center gap-2 min-h-[40px] shrink-0 active:scale-95"
            title="Kembali ke Kelola Materi"
          >
            <span>←</span>
            <span class="hidden sm:inline">Kembali ke Materi</span>
          </button>

          <div class="min-w-0">
            <div class="flex items-center gap-1.5 text-[11px] text-gray-400 font-medium">
              <span class="hover:underline cursor-pointer" @click="goBack">Kelola Materi</span>
              <span>›</span>
              <span class="text-cyan-400 font-bold">Monitoring Pembaca Siswa</span>
            </div>
            <h1 class="text-sm sm:text-base font-black text-white truncate max-w-md sm:max-w-xl">
              {{ materi.judul }}
            </h1>
          </div>
        </div>

        <!-- Live Refresh Action -->
        <button
          @click="refreshData"
          :disabled="isRefreshing"
          type="button"
          class="px-4 py-2 rounded-xl bg-cyan-500/15 hover:bg-cyan-500/25 text-cyan-300 hover:text-cyan-200 border border-cyan-500/30 text-xs font-bold transition-all flex items-center gap-2 min-h-[40px] shrink-0 active:scale-95 disabled:opacity-50"
          title="Perbarui data aktivitas pembaca terkini"
        >
          <span :class="['text-sm', isRefreshing ? 'animate-spin' : '']">🔄</span>
          <span class="hidden sm:inline">{{ isRefreshing ? 'Memperbarui...' : 'Segarkan Data' }}</span>
        </button>
      </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="w-full max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

      <!-- HERO BANNER: MATERI METADATA -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-cyan-950/40 border border-white/10 p-5 sm:p-7 shadow-2xl">
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
          <div class="space-y-2.5 max-w-3xl">
            <!-- Badges -->
            <div class="flex items-center gap-2 flex-wrap">
              <span :class="['px-3 py-1 rounded-full text-xs font-extrabold border', getTypeBadge(materi.tipe).color]">
                {{ getTypeBadge(materi.tipe).label }}
              </span>
              <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-white/5 border border-white/10 text-gray-300">
                📖 {{ materi.mapel || 'Umum' }}
              </span>
              <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-teal-500/15 border border-teal-500/30 text-teal-300">
                👥 Target: {{ materi.targetKelas || 'Semua Kelas' }}
              </span>
              <span
                v-if="materi.cbtJadwal"
                class="px-3 py-1 rounded-full text-xs font-extrabold bg-purple-500/20 border border-purple-500/40 text-purple-300 flex items-center gap-1"
              >
                <span>🔗 Terhubung CBT:</span>
                <span>{{ materi.cbtJadwal.judul }}</span>
              </span>
            </div>

            <!-- Title -->
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-white tracking-tight leading-snug">
              {{ materi.judul }}
            </h2>

            <p class="text-xs sm:text-sm text-gray-400 leading-relaxed">
              Pantau siapa saja siswa yang telah membuka, sedang membaca, atau sudah tuntas mempelajari materi ini secara akurat.
            </p>
          </div>

          <!-- Circular / Hero Quick Stat -->
          <div class="flex items-center gap-4 bg-slate-950/80 border border-white/10 p-4 rounded-2xl shrink-0 self-start lg:self-auto">
            <div class="w-14 h-14 rounded-2xl bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 flex items-center justify-center text-2xl font-black">
              📊
            </div>
            <div>
              <div class="text-[11px] font-bold uppercase text-gray-400">Ketuntasan Kelas</div>
              <div class="text-2xl font-black text-white font-mono">
                {{ summary.persentaseSelesai }}%
              </div>
              <div class="text-[11px] font-semibold text-emerald-400">
                {{ summary.selesaiCount }} dari {{ summary.totalSiswa }} Siswa Tuntas
              </div>
            </div>
          </div>
        </div>

        <!-- Global Progress Bar -->
        <div class="mt-6 pt-5 border-t border-white/10 space-y-2">
          <div class="flex items-center justify-between text-xs font-bold">
            <span class="text-gray-300 flex items-center gap-1.5">
              <span>Tingkat Ketuntasan Membaca Kelas</span>
              <span class="text-gray-500">•</span>
              <span class="text-gray-400">{{ summary.selesaiCount }}/{{ summary.totalSiswa }} Siswa</span>
            </span>
            <span class="text-cyan-300 font-mono font-black">{{ summary.persentaseSelesai }}% Tuntas</span>
          </div>
          <div class="w-full h-3 bg-slate-950 rounded-full overflow-hidden border border-white/10 p-0.5">
            <div
              class="h-full bg-gradient-to-r from-teal-500 via-cyan-400 to-emerald-400 rounded-full transition-all duration-700 shadow-sm shadow-cyan-400/50"
              :style="{ width: `${Math.min(100, Math.max(0, summary.persentaseSelesai))}%` }"
            ></div>
          </div>
        </div>
      </div>

      <!-- 4 STAT METRICS CARDS -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Card 1: Total Siswa -->
        <div class="rounded-2xl bg-slate-900/90 border border-white/10 p-4 sm:p-5 shadow-lg space-y-1">
          <div class="flex items-center justify-between text-gray-400 text-xs font-bold">
            <span class="uppercase tracking-wider text-[10.5px]">Total Siswa</span>
            <span class="text-base">👥</span>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-white font-mono">
            {{ summary.totalSiswa }}
          </div>
          <p class="text-[11px] text-gray-400 truncate">Siswa terdaftar di kelas</p>
        </div>

        <!-- Card 2: Selesai Membaca -->
        <div class="rounded-2xl bg-slate-900/90 border border-emerald-500/30 p-4 sm:p-5 shadow-lg space-y-1 bg-gradient-to-b from-emerald-500/[0.05] to-transparent">
          <div class="flex items-center justify-between text-emerald-400 text-xs font-bold">
            <span class="uppercase tracking-wider text-[10.5px]">🟢 Selesai Baca</span>
            <span class="text-xs font-mono font-black px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-500/30">
              {{ summary.persentaseSelesai }}%
            </span>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-emerald-300 font-mono">
            {{ summary.selesaiCount }}
          </div>
          <p class="text-[11px] text-emerald-400/80 truncate">Telah tuntas membaca</p>
        </div>

        <!-- Card 3: Sedang Membaca -->
        <div class="rounded-2xl bg-slate-900/90 border border-amber-500/30 p-4 sm:p-5 shadow-lg space-y-1 bg-gradient-to-b from-amber-500/[0.05] to-transparent">
          <div class="flex items-center justify-between text-amber-400 text-xs font-bold">
            <span class="uppercase tracking-wider text-[10.5px]">🟡 Sedang Baca</span>
            <span class="text-base">📖</span>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-amber-300 font-mono">
            {{ summary.membacaCount }}
          </div>
          <p class="text-[11px] text-amber-400/80 truncate">Progres sebagian terbaca</p>
        </div>

        <!-- Card 4: Belum Membuka -->
        <div class="rounded-2xl bg-slate-900/90 border border-white/10 p-4 sm:p-5 shadow-lg space-y-1 bg-gradient-to-b from-white/[0.02] to-transparent">
          <div class="flex items-center justify-between text-gray-400 text-xs font-bold">
            <span class="uppercase tracking-wider text-[10.5px]">⚪ Belum Buka</span>
            <span class="text-base">⏳</span>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-gray-300 font-mono">
            {{ summary.belumBukaCount }}
          </div>
          <p class="text-[11px] text-gray-500 truncate">Belum ada riwayat buka</p>
        </div>
      </div>

      <!-- FILTER & SEARCH TOOLBAR -->
      <div class="rounded-2xl bg-slate-900/80 border border-white/10 p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 shadow-md">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto custom-scrollbar pb-1 md:pb-0">
          <button
            type="button"
            @click="statusFilter = 'semua'"
            :class="[
              'px-3.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 min-h-[38px] flex items-center gap-1.5',
              statusFilter === 'semua'
                ? 'bg-cyan-500 text-slate-950 font-black shadow-md shadow-cyan-500/30'
                : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/5'
            ]"
          >
            <span>Semua Siswa</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/20">{{ summary.totalSiswa }}</span>
          </button>

          <button
            type="button"
            @click="statusFilter = 'selesai'"
            :class="[
              'px-3.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 min-h-[38px] flex items-center gap-1.5',
              statusFilter === 'selesai'
                ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/30'
                : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-emerald-300 border border-white/5'
            ]"
          >
            <span>🟢 Selesai</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/20">{{ summary.selesaiCount }}</span>
          </button>

          <button
            type="button"
            @click="statusFilter = 'sedang_membaca'"
            :class="[
              'px-3.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 min-h-[38px] flex items-center gap-1.5',
              statusFilter === 'sedang_membaca'
                ? 'bg-amber-500 text-slate-950 font-black shadow-md shadow-amber-500/30'
                : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-amber-300 border border-white/5'
            ]"
          >
            <span>🟡 Sedang Baca</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/20">{{ summary.membacaCount }}</span>
          </button>

          <button
            type="button"
            @click="statusFilter = 'belum_baca'"
            :class="[
              'px-3.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 min-h-[38px] flex items-center gap-1.5',
              statusFilter === 'belum_baca'
                ? 'bg-slate-700 text-white font-black shadow-md'
                : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/5'
            ]"
          >
            <span>⚪ Belum Buka</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/20">{{ summary.belumBukaCount }}</span>
          </button>
        </div>

        <!-- Live Search Input -->
        <div class="relative w-full md:w-72 shrink-0">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama atau NIS siswa..."
            class="w-full pl-9 pr-8 py-2 rounded-xl bg-slate-950 border border-white/15 focus:border-cyan-400 text-white placeholder-gray-500 text-xs outline-none transition min-h-[38px]"
          />
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs">🔍</span>
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            type="button"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-xs w-5 h-5 flex items-center justify-center rounded-full hover:bg-white/10"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- DATA LISTING: DESKTOP TABLE (hidden md:block) -->
      <div class="hidden md:block rounded-3xl bg-slate-900/90 border border-white/10 overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-950 text-gray-400 uppercase text-[10.5px] font-black tracking-wider border-b border-white/10">
              <tr>
                <th class="py-4 px-4 text-center w-12">#</th>
                <th class="py-4 px-5">Siswa</th>
                <th class="py-4 px-4 text-center">Status Baca</th>
                <th class="py-4 px-5 w-48">Progres Baca</th>
                <th class="py-4 px-4">Pertama Dibuka</th>
                <th class="py-4 px-4">Selesai Membaca</th>
                <th class="py-4 px-4 text-right">Terakhir Aktif</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 font-medium">
              <tr
                v-for="(s, idx) in filteredStudents"
                :key="s.id"
                class="hover:bg-white/[0.03] transition-colors"
              >
                <!-- Number -->
                <td class="py-3.5 px-4 text-center font-mono text-gray-500 text-xs">
                  {{ idx + 1 }}
                </td>

                <!-- Student Identity -->
                <td class="py-3.5 px-5">
                  <div class="flex items-center gap-3">
                    <div
                      :class="[
                        'w-9 h-9 rounded-xl flex items-center justify-center text-xs font-black shrink-0 border',
                        s.gender === 'P'
                          ? 'bg-rose-500/15 text-rose-300 border-rose-500/30'
                          : 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30'
                      ]"
                    >
                      {{ getInitials(s.nama) }}
                    </div>
                    <div class="min-w-0">
                      <div class="font-bold text-white text-xs truncate max-w-xs">
                        {{ s.nama }}
                      </div>
                      <div class="flex items-center gap-1.5 text-[11px] text-gray-400 font-mono mt-0.5">
                        <span>NIS: {{ s.nis || '-' }}</span>
                        <span>•</span>
                        <span :class="s.gender === 'P' ? 'text-rose-300 font-semibold' : 'text-cyan-300 font-semibold'">
                          {{ s.gender === 'P' ? 'Perempuan' : 'Laki-laki' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Status Badge -->
                <td class="py-3.5 px-4 text-center">
                  <span
                    v-if="s.status === 'selesai'"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40"
                  >
                    <span>✓</span>
                    <span>Selesai</span>
                  </span>
                  <span
                    v-else-if="s.status === 'sedang_membaca'"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40"
                  >
                    <span>📖</span>
                    <span>Sedang Baca</span>
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-bold bg-white/5 text-gray-400 border border-white/10"
                  >
                    <span>⚪</span>
                    <span>Belum Buka</span>
                  </span>
                </td>

                <!-- Progress Bar -->
                <td class="py-3.5 px-5">
                  <div class="space-y-1">
                    <div class="flex items-center justify-between text-[11px]">
                      <span class="font-mono font-bold text-gray-300">{{ s.progressPersen || 0 }}%</span>
                      <span v-if="s.status === 'selesai'" class="text-[10px] text-emerald-400 font-bold">Tuntas</span>
                    </div>
                    <div class="w-full h-2 bg-slate-950 rounded-full overflow-hidden border border-white/10 p-0.5">
                      <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="s.status === 'selesai' ? 'bg-emerald-400' : (s.progressPersen > 0 ? 'bg-amber-400' : 'bg-transparent')"
                        :style="{ width: `${s.progressPersen || 0}%` }"
                      ></div>
                    </div>
                  </div>
                </td>

                <!-- Opened At -->
                <td class="py-3.5 px-4 text-gray-300 text-[11.5px] font-mono">
                  {{ s.openedAt || '-' }}
                </td>

                <!-- Completed At -->
                <td class="py-3.5 px-4 text-gray-300 text-[11.5px] font-mono">
                  {{ s.completedAt || '-' }}
                </td>

                <!-- Last Active -->
                <td class="py-3.5 px-4 text-right text-gray-400 text-[11.5px] font-mono">
                  {{ s.terakhirDibaca || '-' }}
                </td>
              </tr>

              <!-- Empty Table Row -->
              <tr v-if="filteredStudents.length === 0">
                <td colspan="7" class="py-12 text-center space-y-2">
                  <div class="text-3xl">🔍</div>
                  <p class="text-sm font-bold text-gray-300">Tidak ada siswa yang sesuai kriteria.</p>
                  <p class="text-xs text-gray-500">Coba ubah kata kunci pencarian atau ganti filter status di atas.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- DATA LISTING: MOBILE CARDS (md:hidden) -->
      <div class="md:hidden space-y-3">
        <div
          v-for="(s, idx) in filteredStudents"
          :key="s.id"
          class="rounded-2xl bg-slate-900 border border-white/10 p-4 space-y-3 shadow-lg"
        >
          <!-- Top Row: Avatar, Name, NIS, Status -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <div
                :class="[
                  'w-10 h-10 rounded-xl flex items-center justify-center text-xs font-black shrink-0 border',
                  s.gender === 'P'
                    ? 'bg-rose-500/15 text-rose-300 border-rose-500/30'
                    : 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30'
                ]"
              >
                {{ getInitials(s.nama) }}
              </div>
              <div class="min-w-0">
                <div class="font-bold text-white text-xs truncate">
                  {{ idx + 1 }}. {{ s.nama }}
                </div>
                <div class="text-[11px] text-gray-400 font-mono">
                  NIS: {{ s.nis || '-' }} • {{ s.gender === 'P' ? 'Perempuan' : 'Laki-laki' }}
                </div>
              </div>
            </div>

            <!-- Status Pill -->
            <span
              v-if="s.status === 'selesai'"
              class="shrink-0 px-2.5 py-1 rounded-full text-[10.5px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40"
            >
              🟢 Selesai
            </span>
            <span
              v-else-if="s.status === 'sedang_membaca'"
              class="shrink-0 px-2.5 py-1 rounded-full text-[10.5px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40"
            >
              🟡 Membaca
            </span>
            <span
              v-else
              class="shrink-0 px-2.5 py-1 rounded-full text-[10.5px] font-bold bg-white/5 text-gray-400 border border-white/10"
            >
              ⚪ Belum Buka
            </span>
          </div>

          <!-- Progress Bar on Mobile -->
          <div class="space-y-1 bg-slate-950/60 p-2.5 rounded-xl border border-white/5">
            <div class="flex items-center justify-between text-[11px]">
              <span class="text-gray-400">Progres Baca</span>
              <span class="font-mono font-bold text-white">{{ s.progressPersen || 0 }}%</span>
            </div>
            <div class="w-full h-2 bg-slate-900 rounded-full overflow-hidden border border-white/10">
              <div
                class="h-full rounded-full transition-all duration-300"
                :class="s.status === 'selesai' ? 'bg-emerald-400' : (s.progressPersen > 0 ? 'bg-amber-400' : 'bg-transparent')"
                :style="{ width: `${s.progressPersen || 0}%` }"
              ></div>
            </div>
          </div>

          <!-- Timestamp details -->
          <div class="grid grid-cols-2 gap-2 text-[10.5px] font-mono text-gray-400 bg-white/[0.02] p-2.5 rounded-xl border border-white/5">
            <div>
              <span class="text-gray-500 block text-[9.5px] uppercase">Pertama Buka:</span>
              <span class="text-gray-300">{{ s.openedAt || '-' }}</span>
            </div>
            <div>
              <span class="text-gray-500 block text-[9.5px] uppercase">Selesai Baca:</span>
              <span class="text-gray-300">{{ s.completedAt || '-' }}</span>
            </div>
            <div class="col-span-2 pt-1 border-t border-white/5 flex items-center justify-between">
              <span class="text-gray-500 text-[9.5px] uppercase">Terakhir Aktif:</span>
              <span class="text-cyan-300 font-bold">{{ s.terakhirDibaca || '-' }}</span>
            </div>
          </div>
        </div>

        <!-- Mobile Empty State -->
        <div v-if="filteredStudents.length === 0" class="py-12 text-center rounded-2xl bg-slate-900/60 border border-white/10 p-6 space-y-2">
          <div class="text-3xl">🔍</div>
          <p class="text-sm font-bold text-gray-300">Tidak ada siswa yang sesuai.</p>
          <p class="text-xs text-gray-500">Coba ubah kata kunci pencarian atau filter status.</p>
        </div>
      </div>

    </main>
  </div>
</template>
