<script setup>
defineProps({
  guruList: { type: Array, default: () => [] },
  waliKelasList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  userAccountsList: { type: Array, default: () => [] }
});

const emit = defineEmits(['change-view']);
</script>

<template>
  <div class="space-y-8 animate-in fade-in duration-300">
    
    <!-- Hero Welcome Banner Card -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-purple-950/80 via-indigo-950/60 to-slate-900 border border-purple-500/30 p-6 sm:p-8 shadow-2xl">
      <!-- Glow Background Spheres -->
      <div class="absolute -top-24 -right-24 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-cyan-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div class="space-y-3">
          <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-purple-500/20 border border-purple-500/40 text-purple-300 text-xs font-extrabold shadow-inner">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            PORTAL ADMINISTRATOR UTAMA
          </div>
          <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight">
            Selamat Datang di System Panel Admin 🚀
          </h1>
          <p class="text-gray-300 text-xs sm:text-sm max-w-2xl leading-relaxed font-medium">
            Pusat kendali master data pengajar, alokasi wali kelas, master rombel/jurusan, dan manajemen keamanan role akun pengguna seluruh sekolah.
          </p>
        </div>

        <!-- Hero Actions -->
        <div class="flex flex-wrap items-center gap-3 shrink-0">
          <button
            @click="$emit('change-view', 'users')"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-purple-500 via-indigo-600 to-purple-600 text-white font-extrabold text-xs shadow-lg shadow-purple-500/30 hover:shadow-purple-500/50 hover:scale-105 active:scale-95 transition-all duration-200 flex items-center gap-2"
          >
            <span class="text-base">🔐</span>
            <span>Kelola Hak Akses</span>
          </button>

          <button
            @click="$emit('change-view', 'users')"
            class="px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/15 text-white font-extrabold text-xs transition-all duration-200 active:scale-95 flex items-center gap-2 backdrop-blur-md"
          >
            <span>🔑</span>
            <span>Reset Password Role</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      
      <!-- Total Guru Card -->
      <div
        @click="$emit('change-view', 'users')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-purple-500/50 shadow-xl hover:shadow-purple-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-purple-500/20 to-indigo-500/20 border border-purple-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            👨‍🏫
          </div>
          <span class="text-[11px] font-extrabold text-purple-300 bg-purple-500/15 border border-purple-500/30 px-3 py-1 rounded-full font-mono">
            GURU
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ guruList.length }}</div>
        <div class="text-xs text-gray-400 font-semibold">Total Guru Pengajar</div>
        
        <!-- Progress mini bar -->
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-purple-500 to-indigo-500 h-full rounded-full w-3/4"></div>
        </div>
      </div>

      <!-- Wali Kelas Card -->
      <div
        @click="$emit('change-view', 'users')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-indigo-500/50 shadow-xl hover:shadow-indigo-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-indigo-500/20 to-cyan-500/20 border border-indigo-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            👤
          </div>
          <span class="text-[11px] font-extrabold text-indigo-300 bg-indigo-500/15 border border-indigo-500/30 px-3 py-1 rounded-full font-mono">
            WALI KELAS
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ waliKelasList.length }}</div>
        <div class="text-xs text-gray-400 font-semibold">Master Wali Kelas</div>
        
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-indigo-500 to-cyan-500 h-full rounded-full w-2/3"></div>
        </div>
      </div>

      <!-- Siswa Card -->
      <div
        @click="$emit('change-view', 'siswa')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-cyan-500/50 shadow-xl hover:shadow-cyan-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-cyan-500/20 to-teal-500/20 border border-cyan-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            🎓
          </div>
          <span class="text-[11px] font-extrabold text-cyan-300 bg-cyan-500/15 border border-cyan-500/30 px-3 py-1 rounded-full font-mono">
            SISWA
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ siswaList.length }}</div>
        <div class="text-xs text-gray-400 font-semibold">Total Siswa Terdaftar</div>
        
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-cyan-500 to-teal-500 h-full rounded-full w-5/6"></div>
        </div>
      </div>

      <!-- Total Akun Active Card -->
      <div
        @click="$emit('change-view', 'users')"
        class="group relative bg-slate-900/80 backdrop-blur-xl rounded-3xl p-6 border border-white/10 hover:border-emerald-500/50 shadow-xl hover:shadow-emerald-950/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="w-13 h-13 bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border border-emerald-500/30 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300 shadow-md">
            🔐
          </div>
          <span class="text-[11px] font-extrabold text-emerald-300 bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 rounded-full font-mono">
            USER ACTIVE
          </span>
        </div>
        <div class="text-3xl font-black text-white tracking-tight mb-1">{{ userAccountsList.length }}</div>
        <div class="text-xs text-gray-400 font-semibold">Total Akun Role Active</div>
        
        <div class="w-full bg-white/5 rounded-full h-1.5 mt-4 overflow-hidden">
          <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full rounded-full w-full"></div>
        </div>
      </div>

    </div>

    <!-- Quick Control Shortcut Cards Grid Header -->
    <div class="space-y-1 pt-2">
      <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
        <span>Pintasan Fitur Utama Admin</span>
        <span class="text-purple-400 text-sm">✦</span>
      </h2>
      <p class="text-gray-400 text-xs font-medium">Pilih menu di bawah ini untuk memulai pengolahan data sistem secara cepat</p>
    </div>

    <!-- Shortcut Cards Grid -->
    <div class="grid md:grid-cols-3 gap-6">
      
      <!-- Card 1: Master Kelas -->
      <div
        @click="$emit('change-view', 'kelas')"
        class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-6 rounded-3xl border border-white/10 hover:border-purple-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
      >
        <div class="w-14 h-14 rounded-2xl bg-purple-500/15 border border-purple-500/30 flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
          🏢
        </div>
        <h3 class="font-extrabold text-white text-lg mb-1.5 group-hover:text-purple-300 transition-colors">Master Kelas & Jurusan</h3>
        <p class="text-xs text-gray-400 leading-relaxed mb-5 font-medium">
          Kelola master data rombongan belajar, jurusan, dan manajemen penempatan wali kelas.
        </p>
        <div class="text-xs font-extrabold text-purple-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
          <span>Buka Master Kelas</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </div>
      </div>

      <!-- Card 2: Master Mapel -->
      <div
        @click="$emit('change-view', 'mapel')"
        class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-6 rounded-3xl border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
      >
        <div class="w-14 h-14 rounded-2xl bg-indigo-500/15 border border-indigo-500/30 flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
          📚
        </div>
        <h3 class="font-extrabold text-white text-lg mb-1.5 group-hover:text-indigo-300 transition-colors">Mata Pelajaran</h3>
        <p class="text-xs text-gray-400 leading-relaxed mb-5 font-medium">
          Pengaturan kurikulum mata pelajaran, kelompok mapel (Muatan Nasional, Kejuruan, dll).
        </p>
        <div class="text-xs font-extrabold text-indigo-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
          <span>Buka Mata Pelajaran</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </div>
      </div>

      <!-- Card 3: Reset Password Role -->
      <div
        @click="$emit('change-view', 'users')"
        class="group relative overflow-hidden bg-slate-900/80 backdrop-blur-xl p-6 rounded-3xl border border-white/10 hover:border-cyan-500/50 cursor-pointer transition-all duration-300 shadow-xl hover:-translate-y-1"
      >
        <div class="w-14 h-14 rounded-2xl bg-cyan-500/15 border border-cyan-500/30 flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform duration-300 shadow-md">
          🔐
        </div>
        <h3 class="font-extrabold text-white text-lg mb-1.5 group-hover:text-cyan-300 transition-colors">User & Password Role</h3>
        <p class="text-xs text-gray-400 leading-relaxed mb-5 font-medium">
          Manajemen akun pengguna, reset password role instan, dan penyesuaian hak akses (Admin, Guru, Wali, Siswa).
        </p>
        <div class="text-xs font-extrabold text-cyan-400 flex items-center gap-1.5 group-hover:translate-x-1.5 transition-transform duration-200">
          <span>Buka Kelola Password</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </div>
      </div>

    </div>
  </div>
</template>
