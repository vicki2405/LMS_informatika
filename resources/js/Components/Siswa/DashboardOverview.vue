<script setup>
import { computed } from 'vue';

const props = defineProps({
  siswaObj: { type: Object, default: () => ({}) },
  kelasSiswa: { type: String, default: 'Kelas' },
  siswaList: { type: Array, default: () => [] },
  cbtJadwalList: { type: Array, default: () => [] },
  cbtNilaiList: { type: Array, default: () => [] },
});

const emit = defineEmits(['change-view']);

// Computed Stats
const totalJadwal = computed(() => props.cbtJadwalList.length);
const totalSelesai = computed(() => props.cbtNilaiList.length);

const rataRataSkor = computed(() => {
  if (!props.cbtNilaiList.length) return 0;
  const total = props.cbtNilaiList.reduce((acc, curr) => acc + (Number(curr.nilai) || 0), 0);
  return (total / props.cbtNilaiList.length).toFixed(1);
});

const totalTuntas = computed(() => {
  return props.cbtNilaiList.filter(n => (Number(n.nilai) >= 75) || n.status === 'Tuntas').length;
});

// Rank Calculation per Jadwal
const rankMap = computed(() => {
  const map = {};
  const groups = {};

  props.cbtNilaiList.forEach((n) => {
    if (!groups[n.jadwalId]) groups[n.jadwalId] = [];
    groups[n.jadwalId].push(n);
  });

  Object.keys(groups).forEach((jId) => {
    const list = [...groups[jId]].sort((a, b) => {
      const scoreDiff = (parseFloat(b.nilai) || 0) - (parseFloat(a.nilai) || 0);
      if (scoreDiff !== 0) return scoreDiff;
      const benarDiff = (b.jumlahBenar || 0) - (a.jumlahBenar || 0);
      if (benarDiff !== 0) return benarDiff;
      return a.id - b.id;
    });

    list.forEach((item, idx) => {
      map[item.id] = {
        rank: idx + 1,
        totalInJadwal: list.length,
      };
    });
  });

  return map;
});

function getRank(nilaiId) {
  return rankMap.value[nilaiId]?.rank || '-';
}

function getTotalInJadwal(nilaiId) {
  return rankMap.value[nilaiId]?.totalInJadwal || 0;
}

// Latest 5 completed exams for quick glance (sorted by id desc)
const latestNilai = computed(() => {
  return [...props.cbtNilaiList].slice(0, 5);
});
</script>

<template>
  <div class="space-y-8">
    <!-- Hero Banner Card (Akun Bersama Kelas) -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-cyan-900/50 via-blue-900/40 to-slate-900 border border-cyan-500/25 p-6 sm:p-8 shadow-2xl">
      <div class="absolute -top-24 -right-24 w-72 h-72 bg-cyan-500/15 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-indigo-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs font-bold mb-3">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            PORTAL CBT SISWA • KELAS {{ kelasSiswa }} • TA {{ $page.props.activeTahunAjaran?.kode || '2025/2026' }}
          </div>
          <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
            Ruang Belajar Kelas {{ kelasSiswa }} 🚀
          </h1>
          <p class="text-gray-300 text-sm mt-2 max-w-2xl leading-relaxed">
            Portal resmi Computer Based Test (CBT) SMKS Muhammadiyah 2 Genteng. Setiap siswa memilih identitas nama masing-masing saat masuk ke ruang ujian agar perolehan nilai otomatis tercatat ke buku nilai guru.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 w-full md:w-auto shrink-0 mt-3 md:mt-0">
          <button
            @click="$emit('change-view', 'cbt')"
            class="flex-1 sm:flex-none justify-center px-5 py-3.5 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-black text-xs shadow-lg shadow-cyan-500/25 hover:opacity-90 active:scale-95 transition-all flex items-center gap-2 min-h-[46px]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span>Mulai Ujian CBT</span>
          </button>
          <button
            @click="$emit('change-view', 'cbt')"
            class="flex-1 sm:flex-none justify-center px-5 py-3.5 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/15 text-white font-bold text-xs transition-all flex items-center gap-2 min-h-[46px]"
          >
            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Riwayat Nilai</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile Quick Navigation Shortcuts Grid -->
    <div class="md:hidden">
      <div class="flex items-center justify-between mb-2.5 px-1">
        <span class="text-xs font-black text-gray-200 flex items-center gap-1.5">
          <span class="text-amber-400">⚡</span>
          <span>Akses Cepat Belajar</span>
        </span>
        <span class="text-[10px] font-mono text-cyan-400 font-bold">Pilih Menu</span>
      </div>
      <div class="grid grid-cols-4 gap-2">
        <!-- 1. CBT -->
        <button
          @click="$emit('change-view', 'cbt')"
          class="flex flex-col items-center justify-center p-2.5 rounded-2xl bg-cyan-500/10 border border-cyan-500/25 active:scale-95 transition-all text-center group"
        >
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-cyan-400 to-blue-600 flex items-center justify-center text-xl text-white shadow-md shadow-cyan-500/30 mb-1 group-hover:scale-105 transition-transform">
            🎯
          </div>
          <span class="text-[11px] font-extrabold text-white leading-tight">Ujian CBT</span>
          <span class="text-[9px] font-medium text-cyan-300">Online</span>
        </button>

        <!-- 2. Materi -->
        <button
          @click="$emit('change-view', 'materi')"
          class="flex flex-col items-center justify-center p-2.5 rounded-2xl bg-indigo-500/10 border border-indigo-500/25 active:scale-95 transition-all text-center group"
        >
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-500 to-blue-600 flex items-center justify-center text-xl text-white shadow-md shadow-indigo-500/30 mb-1 group-hover:scale-105 transition-transform">
            📚
          </div>
          <span class="text-[11px] font-extrabold text-white leading-tight">Materi</span>
          <span class="text-[9px] font-medium text-indigo-300">Slide PPT</span>
        </button>

        <!-- 3. Tugas -->
        <button
          @click="$emit('change-view', 'tugas')"
          class="flex flex-col items-center justify-center p-2.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 active:scale-95 transition-all text-center group"
        >
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-600 flex items-center justify-center text-xl text-white shadow-md shadow-emerald-500/30 mb-1 group-hover:scale-105 transition-transform">
            📝
          </div>
          <span class="text-[11px] font-extrabold text-white leading-tight">Tugas</span>
          <span class="text-[9px] font-medium text-emerald-300">Portofolio</span>
        </button>

        <!-- 4. Nilai TP -->
        <button
          @click="$emit('change-view', 'nilai')"
          class="flex flex-col items-center justify-center p-2.5 rounded-2xl bg-purple-500/10 border border-purple-500/25 active:scale-95 transition-all text-center group"
        >
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-purple-500 to-pink-600 flex items-center justify-center text-xl text-white shadow-md shadow-purple-500/30 mb-1 group-hover:scale-105 transition-transform">
            📊
          </div>
          <span class="text-[11px] font-extrabold text-white leading-tight">Nilai TP</span>
          <span class="text-[9px] font-medium text-purple-300">Raport</span>
        </button>
      </div>
    </div>

    <!-- Live CBT Stat Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
      <!-- 1. Jadwal Ujian Aktif -->
      <div class="group bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 sm:p-6 border border-white/10 hover:border-cyan-500/40 shadow-xl hover:-translate-y-1 transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
          <div class="w-12 h-12 bg-gradient-to-br from-cyan-500/20 to-blue-500/20 border border-cyan-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
            📝
          </div>
          <span class="text-[11px] font-bold text-cyan-300 bg-cyan-500/10 border border-cyan-500/20 px-2.5 py-1 rounded-full font-mono">
            JADWAL
          </span>
        </div>
        <div class="text-3xl sm:text-4xl font-black text-white tracking-tight mb-1">{{ totalJadwal }}</div>
        <div class="text-xs text-gray-400 font-medium">Jadwal Ujian Aktif</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-cyan-500 to-blue-500 h-full rounded-full" :style="{ width: totalJadwal ? '100%' : '0%' }"></div>
        </div>
      </div>

      <!-- 2. Siswa Terdaftar di Kelas -->
      <div class="group bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 sm:p-6 border border-white/10 hover:border-teal-500/40 shadow-xl hover:-translate-y-1 transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
          <div class="w-12 h-12 bg-gradient-to-br from-teal-500/20 to-emerald-500/20 border border-teal-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
            👥
          </div>
          <span class="text-[11px] font-bold text-teal-300 bg-teal-500/10 border border-teal-500/20 px-2.5 py-1 rounded-full font-mono">
            SISWA
          </span>
        </div>
        <div class="text-3xl sm:text-4xl font-black text-white tracking-tight mb-1">{{ siswaList.length }}</div>
        <div class="text-xs text-gray-400 font-medium">Siswa Terdaftar di Kelas</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-teal-500 to-emerald-500 h-full rounded-full w-full"></div>
        </div>
      </div>

      <!-- 3. Total Ujian Selesai -->
      <div class="group bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 sm:p-6 border border-white/10 hover:border-emerald-500/40 shadow-xl hover:-translate-y-1 transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
          <div class="w-12 h-12 bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border border-emerald-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
            ✅
          </div>
          <span class="text-[11px] font-bold text-emerald-300 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 rounded-full font-mono">
            SUBMIT
          </span>
        </div>
        <div class="text-3xl sm:text-4xl font-black text-white tracking-tight mb-1">{{ totalSelesai }}</div>
        <div class="text-xs text-gray-400 font-medium">Total Ujian Dikerjakan</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full rounded-full" :style="{ width: totalSelesai ? '100%' : '0%' }"></div>
        </div>
      </div>

      <!-- 4. Rata-rata Skor Kelas -->
      <div class="group bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 sm:p-6 border border-white/10 hover:border-indigo-500/40 shadow-xl hover:-translate-y-1 transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
          <div class="w-12 h-12 bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
            🏆
          </div>
          <span class="text-[11px] font-bold text-indigo-300 bg-indigo-500/10 border border-indigo-500/20 px-2.5 py-1 rounded-full font-mono">
            RATA-RATA
          </span>
        </div>
        <div class="text-3xl sm:text-4xl font-black text-white tracking-tight mb-1">{{ rataRataSkor }}</div>
        <div class="text-xs text-gray-400 font-medium">Rata-Rata Nilai CBT Kelas</div>
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-full rounded-full" :style="{ width: Math.min(100, Math.max(0, Number(rataRataSkor))) + '%' }"></div>
        </div>
      </div>
    </div>

    <!-- Section 1: Ujian CBT Aktif Saat Ini -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 shadow-xl">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
          <h2 class="text-lg sm:text-xl font-black text-white flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse"></span>
            Jadwal Ujian CBT Aktif
          </h2>
          <p class="text-gray-400 text-xs mt-1">Daftar paket ujian online untuk kelas {{ kelasSiswa }}</p>
        </div>
        <button
          @click="$emit('change-view', 'cbt')"
          class="inline-flex items-center gap-2 text-xs font-bold text-cyan-400 hover:text-cyan-300 transition-colors"
        >
          <span>Buka Menu CBT Lengkap</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>
      </div>

      <!-- If Empty -->
      <div v-if="!cbtJadwalList.length" class="text-center py-12 px-4 border border-dashed border-white/10 rounded-2xl bg-white/[0.02]">
        <div class="w-14 h-14 bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 rounded-2xl flex items-center justify-center mx-auto text-2xl mb-3">
          ☕
        </div>
        <h3 class="text-sm font-bold text-white mb-1">Tidak Ada Ujian CBT Aktif</h3>
        <p class="text-xs text-gray-400 max-w-sm mx-auto">Saat ini belum ada jadwal ujian online yang dibuka untuk kelas Anda. Pantau secara berkala saat jam ujian dimulai.</p>
      </div>

      <!-- Active Exams Cards Grid -->
      <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="j in cbtJadwalList"
          :key="j.id"
          class="bg-slate-800/80 rounded-2xl p-5 border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col justify-between group shadow-lg"
        >
          <div>
            <div class="flex items-center justify-between gap-2 mb-3">
              <span class="text-[10px] font-bold font-mono px-2.5 py-1 rounded-full bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 uppercase truncate max-w-[160px]">
                {{ j.mapelNama }}
              </span>
              <span class="flex items-center gap-1 text-[10px] text-emerald-400 font-bold bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                <span>{{ j.totalSelesai || 0 }} Selesai</span>
              </span>
            </div>

            <h3 class="text-base font-bold text-white group-hover:text-cyan-300 transition-colors line-clamp-2 mb-2">
              {{ j.judulUjian }}
            </h3>

            <div class="space-y-1.5 text-xs text-gray-300 mb-4">
              <div class="flex items-center gap-2">
                <span class="text-gray-500">👨‍🏫 Guru:</span>
                <span class="font-medium text-white truncate">{{ j.guruNama }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-gray-500">⏱️ Durasi:</span>
                <span class="font-medium text-white">{{ j.durasiMenit }} Menit</span>
                <span class="text-gray-500 ml-2">📄 Soal:</span>
                <span class="font-medium text-white">{{ j.jumlahSoal }} Butir</span>
              </div>
            </div>
          </div>

          <button
            @click="$emit('change-view', 'cbt')"
            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold text-xs shadow-md hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2 min-h-[40px]"
          >
            <span>Pilih Nama &amp; Mulai Ujian</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Section 2: Riwayat Ujian Siswa di Kelas Ini -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 shadow-xl">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
          <h2 class="text-lg sm:text-xl font-black text-white flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
            Riwayat Ujian Siswa Kelas Ini
          </h2>
          <p class="text-gray-400 text-xs mt-1">Transkrip hasil ujian yang telah diselesaikan oleh siswa kelas {{ kelasSiswa }}</p>
        </div>
        <button
          v-if="cbtNilaiList.length"
          @click="$emit('change-view', 'cbt')"
          class="inline-flex items-center gap-2 text-xs font-bold text-cyan-400 hover:text-cyan-300 transition-colors"
        >
          <span>Lihat Semua Riwayat CBT</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!cbtNilaiList.length" class="text-center py-10 px-4 border border-dashed border-white/10 rounded-2xl bg-white/[0.02]">
        <div class="w-12 h-12 bg-white/5 text-gray-400 rounded-2xl flex items-center justify-center mx-auto text-xl mb-3">
          📊
        </div>
        <h3 class="text-sm font-bold text-white mb-1">Belum Ada Riwayat Ujian</h3>
        <p class="text-xs text-gray-400 max-w-sm mx-auto">Nilai dan nama siswa yang telah menyelesaikan ujian CBT akan otomatis muncul di sini.</p>
      </div>

      <!-- Desktop Table -->
      <div v-else class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-white/10 text-gray-400 font-mono uppercase tracking-wider">
              <th class="py-3 px-3 text-center">🏆 Rangking</th>
              <th class="py-3 px-4">Nama Peserta (Siswa)</th>
              <th class="py-3 px-4">Nama Ujian CBT</th>
              <th class="py-3 px-4">Mata Pelajaran</th>
              <th class="py-3 px-4 text-center">Benar / Salah</th>
              <th class="py-3 px-4 text-center">Nilai Akhir</th>
              <th class="py-3 px-4 text-center">Status</th>
              <th class="py-3 px-4 text-right">Waktu Submit</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <tr v-for="n in latestNilai" :key="n.id" class="hover:bg-white/5 transition-colors">
              <td class="py-3.5 px-3 text-center">
                <span v-if="getRank(n.id) === 1" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-400/25 border border-amber-400/50 text-amber-300 font-black text-xs">
                  <span>🥇</span>
                  <span>#1</span>
                </span>
                <span v-else-if="getRank(n.id) === 2" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-400/20 border border-slate-300/40 text-slate-200 font-black text-xs">
                  <span>🥈</span>
                  <span>#2</span>
                </span>
                <span v-else-if="getRank(n.id) === 3" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-700/25 border border-amber-600/40 text-amber-300 font-black text-xs">
                  <span>🥉</span>
                  <span>#3</span>
                </span>
                <span v-else class="inline-flex items-center justify-center px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-300 font-mono text-[11px]">
                  #{{ getRank(n.id) }}
                </span>
              </td>
              <td class="py-3.5 px-4 font-bold text-white">
                <div class="flex items-center gap-2">
                  <span class="text-xs">👤</span>
                  <div>
                    <div class="font-bold text-white">{{ n.namaSiswa }}</div>
                    <div class="text-[10px] text-cyan-400 font-mono">NIS: {{ n.nis }}</div>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-4 font-semibold text-gray-200">{{ n.judulUjian }}</td>
              <td class="py-3.5 px-4 text-cyan-300 font-medium">{{ n.mapelNama }}</td>
              <td class="py-3.5 px-4 text-center font-mono">
                <span class="text-emerald-400 font-bold">{{ n.jumlahBenar }}</span>
                <span class="text-gray-500"> / </span>
                <span class="text-rose-400 font-bold">{{ n.jumlahSalah }}</span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span class="text-sm font-black font-mono text-white px-2.5 py-1 rounded-lg bg-white/5 border border-white/10">
                  {{ n.nilai }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-center">
                <span
                  :class="[
                    'px-2.5 py-1 rounded-full text-[10px] font-bold font-mono uppercase border',
                    (Number(n.nilai) >= 75 || n.status === 'Tuntas')
                      ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                      : 'bg-rose-500/15 text-rose-300 border-rose-500/30'
                  ]"
                >
                  {{ (Number(n.nilai) >= 75 || n.status === 'Tuntas') ? 'Tuntas' : 'Belum Tuntas' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right text-gray-400 font-mono text-[11px]">{{ n.waktuSelesai }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card List View -->
      <div v-if="cbtNilaiList.length" class="md:hidden space-y-3">
        <div
          v-for="n in latestNilai"
          :key="'mob-' + n.id"
          class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-2.5"
        >
          <div class="flex items-start justify-between gap-2 border-b border-white/5 pb-2">
            <div>
              <div class="flex items-center gap-1.5 flex-wrap mb-1">
                <span v-if="getRank(n.id) === 1" class="px-2 py-0.5 rounded-md bg-amber-400/25 border border-amber-400/50 text-amber-300 text-[10px] font-black">
                  🥇 #1
                </span>
                <span v-else-if="getRank(n.id) === 2" class="px-2 py-0.5 rounded-md bg-slate-400/25 border border-slate-300/40 text-slate-200 text-[10px] font-black">
                  🥈 #2
                </span>
                <span v-else-if="getRank(n.id) === 3" class="px-2 py-0.5 rounded-md bg-amber-700/30 border border-amber-600/40 text-amber-300 text-[10px] font-black">
                  🥉 #3
                </span>
                <span v-else class="px-1.5 py-0.5 rounded bg-white/10 border border-white/10 text-gray-300 font-mono text-[10px] font-bold">
                  #{{ getRank(n.id) }}
                </span>
                <h4 class="font-bold text-white text-xs">{{ n.namaSiswa }}</h4>
              </div>
              <span class="text-[10px] text-cyan-400 font-mono">NIS: {{ n.nis }}</span>
            </div>
            <div class="text-right shrink-0">
              <div class="text-base font-black font-mono text-white">{{ n.nilai }}</div>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-[9px] font-bold font-mono uppercase border',
                  (Number(n.nilai) >= 75 || n.status === 'Tuntas')
                    ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                    : 'bg-rose-500/15 text-rose-300 border-rose-500/30'
                ]"
              >
                {{ (Number(n.nilai) >= 75 || n.status === 'Tuntas') ? 'Tuntas' : 'Remidi' }}
              </span>
            </div>
          </div>
          <div class="flex items-center justify-between text-xs text-gray-400 font-mono">
            <span class="text-cyan-300">{{ n.mapelNama }} • {{ n.judulUjian }}</span>
            <span class="text-[10px]">{{ n.waktuSelesai }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
