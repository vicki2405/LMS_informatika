<script setup>
import { computed } from 'vue';

const props = defineProps({
  user: Object,
  kelasList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  jadwalList: { type: Array, default: () => [] },
  absensiList: { type: Array, default: () => [] },
  jurnalList: { type: Array, default: () => [] },
  tpList: { type: Array, default: () => [] },
  nilaiList: { type: Array, default: () => [] },
  materiList: { type: Array, default: () => [] },
  teacherSubject: { type: String, default: 'Informatika' }
});

const emit = defineEmits(['change-view', 'open-full-input-absensi']);

// Helper: Overall Student TP Average Score
const overallTpAverage = computed(() => {
  if (!props.nilaiList || props.nilaiList.length === 0) return 0;
  const validScores = props.nilaiList.filter(n => n.nilai !== null && !isNaN(n.nilai));
  if (validScores.length === 0) return 0;
  const sum = validScores.reduce((acc, curr) => acc + Number(curr.nilai), 0);
  return Math.round((sum / validScores.length) * 10) / 10;
});

// Helper: Overall Student Attendance Rate
const attendancePercentage = computed(() => {
  if (!props.absensiList || props.absensiList.length === 0) return 0;
  const total = props.absensiList.length;
  const hadir = props.absensiList.filter(a => a.status === 'Hadir').length;
  return Math.round((hadir / total) * 100);
});

// Helper: Today's date string YYYY-MM-DD local
function getTodayString() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
</script>

<template>
  <div class="space-y-8 animate-in fade-in duration-300">

    <!-- Hero Welcome Banner Card -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-cyan-950/80 via-teal-950/60 to-slate-900 border border-cyan-500/30 p-6 sm:p-8 shadow-2xl">
      <!-- Glow Background Spheres -->
      <div class="absolute -top-24 -right-24 w-80 h-80 bg-cyan-500/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-teal-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="px-3.5 py-1 rounded-full bg-cyan-500/20 border border-cyan-500/40 text-cyan-300 text-xs font-extrabold shadow-inner">
              📖 Mata Pelajaran: {{ teacherSubject }}
            </span>
            <span class="px-3.5 py-1 rounded-full bg-teal-500/20 border border-teal-500/40 text-teal-300 text-xs font-extrabold shadow-inner">
              Semester Ganjil {{ $page.props.activeTahunAjaran?.kode || '2025/2026' }}
            </span>
          </div>
          
          <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight">
            Selamat Datang, {{ user?.name || 'Budi Santoso, S.Kom.' }} 👨‍🏫
          </h1>
          <p class="text-gray-300 text-xs sm:text-sm max-w-2xl leading-relaxed font-medium">
            Portal terpadu kegiatan belajar mengajar (KBM), presensi kehadiran siswa, pencatatan jurnal harian, dan evaluasi berbasis Tujuan Pembelajaran (TP).
          </p>
        </div>

        <!-- Quick Hero Action Buttons -->
        <div class="flex flex-wrap items-center gap-3 shrink-0">
          <button
            @click="$emit('open-full-input-absensi', { kelas: (typeof window !== 'undefined' && localStorage.getItem('guru_active_absensi_kelas')) || kelasList[0]?.namaKelas || 'X RPL 1', tanggal: getTodayString() })"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-cyan-500 via-teal-600 to-cyan-600 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 hover:scale-105 active:scale-95 transition-all duration-200 flex items-center gap-2"
          >
            <span>📋</span>
            <span>Input Presensi Hari Ini</span>
          </button>

          <button
            @click="$emit('change-view', 'jurnal')"
            class="px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/15 text-white font-extrabold text-xs sm:text-sm transition-all duration-200 active:scale-95 flex items-center gap-2 backdrop-blur-md"
          >
            <span>📝</span>
            <span>Input Jurnal KBM</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      
      <!-- Kelas Ampuan Card -->
      <div
        @click="$emit('change-view', 'kelas')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-cyan-500/50 shadow-xl hover:shadow-cyan-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-cyan-500/20 to-teal-500/20 border border-cyan-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            🏫
          </div>
          <span class="text-[11px] font-extrabold text-cyan-300 bg-cyan-500/15 border border-cyan-500/30 px-3 py-1 rounded-full font-mono">
            KELAS
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ kelasList.length }}</div>
        <div class="text-xs text-gray-400 font-semibold">Total Rombel Ampuan</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-cyan-500 to-teal-500 h-full rounded-full w-3/4"></div>
        </div>
      </div>

      <!-- Siswa Ampuan Card -->
      <div
        @click="$emit('change-view', 'siswa')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-teal-500/50 shadow-xl hover:shadow-teal-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-teal-500/20 to-emerald-500/20 border border-teal-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            👨‍🎓
          </div>
          <span class="text-[11px] font-extrabold text-teal-300 bg-teal-500/15 border border-teal-500/30 px-3 py-1 rounded-full font-mono">
            SISWA
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ siswaList.length }}</div>
        <div class="text-xs text-gray-400 font-semibold">Total Siswa Terdaftar</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-teal-500 to-emerald-500 h-full rounded-full w-5/6"></div>
        </div>
      </div>

      <!-- Kehadiran Rate Card -->
      <div
        @click="$emit('change-view', 'absensi')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-emerald-500/50 shadow-xl hover:shadow-emerald-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border border-emerald-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            📋
          </div>
          <span class="text-[11px] font-extrabold text-emerald-300 bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 rounded-full font-mono">
            PRESENSI
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ attendancePercentage }}%</div>
        <div class="text-xs text-gray-400 font-semibold">Tingkat Kehadiran Siswa</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full rounded-full" :style="{ width: attendancePercentage + '%' }"></div>
        </div>
      </div>

      <!-- Rata-Rata Nilai TP Card -->
      <div
        @click="$emit('change-view', 'nilai')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-indigo-500/50 shadow-xl hover:shadow-indigo-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            📊
          </div>
          <span class="text-[11px] font-extrabold text-indigo-300 bg-indigo-500/15 border border-indigo-500/30 px-3 py-1 rounded-full font-mono">
            NILAI TP
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ overallTpAverage > 0 ? overallTpAverage : '—' }}</div>
        <div class="text-xs text-gray-400 font-semibold">Rata-Rata KKTP Kelas</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-full rounded-full" :style="{ width: overallTpAverage + '%' }"></div>
        </div>
      </div>

    </div>

    <!-- Quick Operational Menu Shortcut Grid -->
    <div class="space-y-4">
      <div class="space-y-1">
        <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Pintasan Fitur KBM Guru</span>
          <span class="text-cyan-400 text-sm">⚡</span>
        </h2>
        <p class="text-gray-400 text-xs font-medium">Akses cepat kegiatan belajar mengajar dan evaluasi siswa</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Card 1: Absensi Siswa -->
        <div
          @click="$emit('change-view', 'absensi')"
          class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-5 sm:p-6 rounded-3xl border border-white/10 hover:border-cyan-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
        >
          <div class="w-12 h-12 rounded-2xl bg-cyan-500/15 border border-cyan-500/30 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
            📋
          </div>
          <h3 class="font-extrabold text-white text-base mb-1.5 group-hover:text-cyan-300 transition-colors">Presensi Siswa</h3>
          <p class="text-xs text-gray-400 leading-relaxed mb-4 font-medium">
            Entri kehadiran harian kelas (Hadir, Sakit, Izin, Alpa) dan rekap bulanan.
          </p>
          <div class="text-xs font-extrabold text-cyan-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
            <span>Buka Presensi</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
          </div>
        </div>

        <!-- Card 2: Jurnal Mengajar -->
        <div
          @click="$emit('change-view', 'jurnal')"
          class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-5 sm:p-6 rounded-3xl border border-white/10 hover:border-teal-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
        >
          <div class="w-12 h-12 rounded-2xl bg-teal-500/15 border border-teal-500/30 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
            📝
          </div>
          <h3 class="font-extrabold text-white text-base mb-1.5 group-hover:text-teal-300 transition-colors">Jurnal Mengajar</h3>
          <p class="text-xs text-gray-400 leading-relaxed mb-4 font-medium">
            Pencatatan materi pembelajaran harian, kegiatan kelas, dan catatan KBM.
          </p>
          <div class="text-xs font-extrabold text-teal-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
            <span>Buka Jurnal</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
          </div>
        </div>

        <!-- Card 3: Penilaian TP -->
        <div
          @click="$emit('change-view', 'nilai')"
          class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-5 sm:p-6 rounded-3xl border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
        >
          <div class="w-12 h-12 rounded-2xl bg-indigo-500/15 border border-indigo-500/30 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
            📊
          </div>
          <h3 class="font-extrabold text-white text-base mb-1.5 group-hover:text-indigo-300 transition-colors">Penilaian TP</h3>
          <p class="text-xs text-gray-400 leading-relaxed mb-4 font-medium">
            Pengisian nilai Tujuan Pembelajaran Kurikulum Merdeka dan capaian KKTP.
          </p>
          <div class="text-xs font-extrabold text-indigo-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
            <span>Buka Penilaian</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
          </div>
        </div>

        <!-- Card 4: CBT & Ujian Online -->
        <div
          @click="$emit('change-view', 'cbt-jadwal')"
          class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-5 sm:p-6 rounded-3xl border border-white/10 hover:border-amber-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
        >
          <div class="w-12 h-12 rounded-2xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
            💻
          </div>
          <h3 class="font-extrabold text-white text-base mb-1.5 group-hover:text-amber-300 transition-colors">CBT & Ujian</h3>
          <p class="text-xs text-gray-400 leading-relaxed mb-4 font-medium">
            Jadwal ujian CBT aktif, live monitoring proctoring, dan rekap nilai ujian.
          </p>
          <div class="text-xs font-extrabold text-amber-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
            <span>Buka Jadwal CBT</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
          </div>
        </div>

      </div>
    </div>

  </div>
</template>
