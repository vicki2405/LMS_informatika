<script setup>
import { computed } from 'vue';

const props = defineProps({
  kelasBinaan: { type: String, default: 'X RPL 1' },
  siswaList: { type: Array, default: () => [] },
  rekapTugasList: { type: Array, default: () => [] },
  rekapNilaiSemester: { type: Array, default: () => [] }
});

const emit = defineEmits(['change-view']);

const tuntasPercentage = computed(() => {
  const list = props.rekapTugasList || [];
  if (list.length === 0) return 0;
  const tuntas = list.filter(t => t.status === 'Tuntas').length;
  return Math.round((tuntas / list.length) * 1000) / 10;
});

const nilaiRataRata = computed(() => {
  const list = props.rekapNilaiSemester || [];
  const avgs = list.map(n => Number(n.nilaiAkhir)).filter(v => !isNaN(v) && v > 0);
  if (avgs.length === 0) return null;
  const sum = avgs.reduce((a, b) => a + b, 0);
  return Math.round((sum / avgs.length) * 10) / 10;
});

const predikatMutu = computed(() => {
  const avg = nilaiRataRata.value;
  if (avg === null) return '—';
  if (avg >= 90) return 'A';
  if (avg >= 80) return 'B';
  if (avg >= 75) return 'C';
  return 'D';
});

function barWidth(value) {
  return Math.max(0, Math.min(100, Number(value) || 0));
}
</script>

<template>
  <div class="space-y-8 animate-in fade-in duration-300">
    
    <!-- Hero Welcome Banner Card -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-950/80 via-purple-950/60 to-slate-900 border border-indigo-500/30 p-6 sm:p-8 shadow-2xl">
      <!-- Glow Background Spheres -->
      <div class="absolute -top-24 -right-24 w-80 h-80 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-cyan-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="px-3.5 py-1 rounded-full bg-indigo-500/20 border border-indigo-500/40 text-indigo-300 text-xs font-black shadow-inner">
              🎓 Kelas Binaan: {{ kelasBinaan }}
            </span>
            <span class="px-3.5 py-1 rounded-full bg-purple-500/20 border border-purple-500/40 text-purple-300 text-xs font-black shadow-inner">
              Semester Ganjil 2025/2026
            </span>
          </div>

          <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight">
            Monitoring Akademik Kelas {{ kelasBinaan }} 👨‍🏫
          </h1>
          <p class="text-gray-300 text-xs sm:text-sm max-w-2xl leading-relaxed font-medium">
            Rekapitulasi laporan ketuntasan tugas dari seluruh guru mata pelajaran, evaluasi ketercapaian Tujuan Pembelajaran (TP), dan validasi transkrip nilai rapor siswa.
          </p>
        </div>

        <!-- Quick Hero Action Buttons -->
        <div class="flex flex-wrap items-center gap-3 shrink-0">
          <button
            @click="$emit('change-view', 'absensi')"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-black text-xs sm:text-sm shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 hover:scale-105 active:scale-95 transition-all duration-200 flex items-center gap-2"
          >
            <span>📌</span>
            <span>Rekap &amp; Absen Jam 1-2</span>
          </button>

          <button
            @click="$emit('change-view', 'tugas')"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-indigo-500 via-purple-600 to-indigo-600 text-white font-black text-xs sm:text-sm shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:scale-105 active:scale-95 transition-all duration-200 flex items-center gap-2"
          >
            <span>📋</span>
            <span>Cek Ketuntasan Tugas</span>
          </button>

          <button
            @click="$emit('change-view', 'nilai')"
            class="px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/15 text-white font-extrabold text-xs sm:text-sm transition-all duration-200 active:scale-95 flex items-center gap-2 backdrop-blur-md"
          >
            <span>📝</span>
            <span>Transkrip Nilai Rapor</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      
      <!-- Total Siswa Card -->
      <div
        @click="$emit('change-view', 'siswa')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-indigo-500/50 shadow-xl hover:shadow-indigo-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            👨‍🎓
          </div>
          <span class="text-[11px] font-extrabold text-indigo-300 bg-indigo-500/15 border border-indigo-500/30 px-3 py-1 rounded-full font-mono">
            SISWA
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ siswaList.length }}</div>
        <div class="text-xs text-gray-400 font-semibold">Total Siswa Binaan</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-full rounded-full w-full"></div>
        </div>
      </div>

      <!-- Ketuntasan Tugas Card -->
      <div
        @click="$emit('change-view', 'tugas')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-emerald-500/50 shadow-xl hover:shadow-emerald-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border border-emerald-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            ✅
          </div>
          <span class="text-[11px] font-extrabold text-emerald-300 bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 rounded-full font-mono">
            TUNTAS
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ tuntasPercentage > 0 ? tuntasPercentage + '%' : '—' }}</div>
        <div class="text-xs text-gray-400 font-semibold">Rata-Rata Ketuntasan Tugas</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full rounded-full" :style="{ width: barWidth(tuntasPercentage) + '%' }"></div>
        </div>
      </div>

      <!-- Rata-rata Nilai Semester Card -->
      <div
        @click="$emit('change-view', 'nilai')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-cyan-500/50 shadow-xl hover:shadow-cyan-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-cyan-500/20 to-blue-500/20 border border-cyan-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            📊
          </div>
          <span class="text-[11px] font-extrabold text-cyan-300 bg-cyan-500/15 border border-cyan-500/30 px-3 py-1 rounded-full font-mono">
            SEMESTER
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ nilaiRataRata ?? '—' }}</div>
        <div class="text-xs text-gray-400 font-semibold">Rata-Rata Rapor Kelas</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-cyan-500 to-blue-500 h-full rounded-full" :style="{ width: barWidth(nilaiRataRata) + '%' }"></div>
        </div>
      </div>

      <!-- Predikat Mutu Kelas Card -->
      <div
        @click="$emit('change-view', 'nilai')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-amber-500/50 shadow-xl hover:shadow-amber-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            🏆
          </div>
          <span class="text-[11px] font-extrabold text-amber-300 bg-amber-500/15 border border-amber-500/30 px-3 py-1 rounded-full font-mono">
            PRESTASI
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ predikatMutu }}</div>
        <div class="text-xs text-gray-400 font-semibold">Predikat Mutu Kelas</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-amber-500 to-orange-500 h-full rounded-full" :style="{ width: barWidth(nilaiRataRata) + '%' }"></div>
        </div>
      </div>

    </div>

    <!-- Quick Operational Shortcut Grid -->
    <div class="space-y-4">
      <div class="space-y-1">
        <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Modul Monitoring Wali Kelas</span>
          <span class="text-indigo-400 text-sm">⚡</span>
        </h2>
        <p class="text-gray-400 text-xs font-medium">Pilih modul di bawah ini untuk melihat detail siswa kelas {{ kelasBinaan }}</p>
      </div>

      <div class="grid md:grid-cols-3 gap-6">
        
        <!-- Card 1: Ketuntasan Tugas -->
        <div
          @click="$emit('change-view', 'tugas')"
          class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-6 rounded-3xl border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
        >
          <div class="w-14 h-14 rounded-2xl bg-indigo-500/15 border border-indigo-500/30 flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
            📋
          </div>
          <h3 class="font-extrabold text-white text-lg mb-1.5 group-hover:text-indigo-300 transition-colors">Ketuntasan Tugas Siswa</h3>
          <p class="text-xs text-gray-400 leading-relaxed mb-5 font-medium">
            Pantau laporan pengumpulan tugas siswa dari setiap guru mapel (Hardware, Networking, DB, UI/UX).
          </p>
          <div class="text-xs font-extrabold text-indigo-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
            <span>Lihat Laporan Tugas</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </div>
        </div>

        <!-- Card 2: Nilai Semester -->
        <div
          @click="$emit('change-view', 'nilai')"
          class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-6 rounded-3xl border border-white/10 hover:border-purple-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
        >
          <div class="w-14 h-14 rounded-2xl bg-purple-500/15 border border-purple-500/30 flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
            📝
          </div>
          <h3 class="font-extrabold text-white text-lg mb-1.5 group-hover:text-purple-300 transition-colors">Nilai Siswa Per Semester</h3>
          <p class="text-xs text-gray-400 leading-relaxed mb-5 font-medium">
            Rekapitulasi nilai akhir semester siswa per TP Kurikulum Merdeka dan pencetakan transkrip rapor ringkas.
          </p>
          <div class="text-xs font-extrabold text-purple-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
            <span>Buka Transkrip Nilai</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </div>
        </div>

        <!-- Card 3: Data Siswa Binaan -->
        <div
          @click="$emit('change-view', 'siswa')"
          class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-6 rounded-3xl border border-white/10 hover:border-cyan-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
        >
          <div class="w-14 h-14 rounded-2xl bg-cyan-500/15 border border-cyan-500/30 flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
            👨‍🎓
          </div>
          <h3 class="font-extrabold text-white text-lg mb-1.5 group-hover:text-cyan-300 transition-colors">Data Siswa Binaan</h3>
          <p class="text-xs text-gray-400 leading-relaxed mb-5 font-medium">
            Lihat biodata lengkap siswa kelas binaan, kontak WhatsApp orang tua, dan histori keaktifan.
          </p>
          <div class="text-xs font-extrabold text-cyan-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
            <span>Lihat Roster Siswa</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </div>
        </div>

      </div>
    </div>

  </div>
</template>
