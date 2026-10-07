<script setup>
import { computed } from 'vue';

const props = defineProps({
  activeView: String,
  sidebarOpen: Boolean,
  userPermissions: Array,
  user: Object
});

const emit = defineEmits(['update:activeView', 'update:sidebarOpen']);

const hasMultipleTeacherRoles = computed(() => {
  const roles = props.user?.roles || [];
  const roleNames = Array.isArray(roles)
    ? roles.map(r => typeof r === 'string' ? r : (r.name || ''))
    : [];
  return roleNames.includes('guru') && roleNames.includes('wali_kelas');
});

function selectView(view) {
  emit('update:activeView', view);
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    emit('update:sidebarOpen', false);
  }
}
</script>

<template>
  <div>
    <!-- Sidebar Backdrop Overlay for Mobile -->
    <div
      v-if="sidebarOpen"
      @click="$emit('update:sidebarOpen', false)"
      class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-40 lg:hidden transition-opacity duration-300"
    ></div>

    <!-- Sidebar Navigation Drawer -->
    <aside
      :class="[
        'fixed lg:sticky top-16 left-0 h-[calc(100vh-8rem)] lg:h-[calc(100vh-4rem)] w-72 bg-slate-900/95 border-r border-white/10 z-40 transition-all duration-300 overflow-y-auto shrink-0 shadow-2xl lg:shadow-none backdrop-blur-2xl custom-scrollbar',
        sidebarOpen ? 'translate-x-0 ml-0' : '-translate-x-full -ml-72'
      ]"
    >
      <div class="p-5 sm:p-6 space-y-6">
        
        <!-- Sidebar Header Title & Mobile Close Button -->
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div class="min-w-0 flex-1">
            <h2 class="text-base font-black text-white tracking-tight flex items-center gap-2">
              <span class="bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 to-teal-300">GURU PENGAJAR</span>
              <span class="text-sm">👨‍🏫</span>
            </h2>
            <p class="text-[11px] text-cyan-400 font-semibold truncate mt-0.5">Portal Kegiatan Belajar Mengajar</p>
          </div>
          <button
            @click="$emit('update:sidebarOpen', false)"
            title="Sembunyikan Sidebar"
            class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-all active:scale-95 shrink-0 lg:hidden"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Categorized Navigation Menu Links -->
        <nav class="space-y-6">
          
          <!-- KATEGORI 1: UTAMA -->
          <div class="space-y-2">
            <div class="px-3 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
              <span>UTAMA</span>
            </div>
            <button
              @click="selectView('dashboard')"
              :class="[
                'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                activeView === 'dashboard'
                  ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <span v-if="activeView === 'dashboard'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
              <div :class="['p-2 rounded-xl transition-colors', activeView === 'dashboard' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
              </div>
              <span class="truncate">Dashboard Overview</span>
            </button>
          </div>

          <!-- KATEGORI 2: KBM & PRESENSI -->
          <div class="space-y-2">
            <div class="px-3 text-[10px] font-extrabold text-cyan-400 uppercase tracking-widest flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-teal-400"></span>
              <span>KBM & PRESENSI</span>
            </div>
            <div class="space-y-1.5">
              
              <!-- 1. Kelas Ampuan -->
              <button
                @click="selectView('kelas')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'kelas'
                    ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'kelas'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'kelas' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                  </svg>
                </div>
                <span class="truncate">Kelas Ampuan</span>
              </button>

              <!-- 2. Jadwal Mengajar -->
              <button
                @click="selectView('jadwal')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'jadwal'
                    ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'jadwal'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'jadwal' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                <span class="truncate">Jadwal Mengajar</span>
              </button>

              <!-- 3. Presensi & Absensi Siswa -->
              <button
                @click="selectView('absensi')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'absensi' || activeView === 'input-absensi'
                    ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'absensi' || activeView === 'input-absensi'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'absensi' || activeView === 'input-absensi' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                  </svg>
                </div>
                <span class="truncate">Presensi & Absensi Siswa</span>
              </button>

              <!-- 4. Data Siswa -->
              <button
                @click="selectView('siswa')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'siswa'
                    ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'siswa'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'siswa' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                  </svg>
                </div>
                <span class="truncate">Data Siswa</span>
              </button>

              <!-- 5. Jurnal KBM Mengajar -->
              <button
                @click="selectView('jurnal')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'jurnal'
                    ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'jurnal'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'jurnal' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                  </svg>
                </div>
                <span class="truncate">Jurnal KBM Mengajar</span>
              </button>

            </div>
          </div>

          <!-- KATEGORI 3: EVALUASI & MATERI -->
          <div class="space-y-2">
            <div class="px-3 text-[10px] font-extrabold text-emerald-400 uppercase tracking-widest flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
              <span>EVALUASI & MATERI</span>
            </div>
            <div class="space-y-1.5">
              
              <!-- Master Tujuan Pembelajaran (TP) -->
              <button
                @click="selectView('tp')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'tp'
                    ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'tp'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'tp' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                  </svg>
                </div>
                <div class="text-left min-w-0">
                  <span class="truncate block">Tujuan Pembelajaran (TP)</span>
                  <span class="text-[10px] text-gray-500 font-normal block">Master Sasaran &amp; KKTP</span>
                </div>
              </button>
              
              <!-- Penilaian TP Siswa -->
              <button
                @click="selectView('nilai')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'nilai'
                    ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'nilai'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'nilai' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                  </svg>
                </div>
                <span class="truncate">Penilaian TP Siswa</span>
              </button>

              <!-- Kelola Modul Materi -->
              <button
                @click="selectView('materi')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'materi'
                    ? 'bg-gradient-to-r from-cyan-500/25 via-teal-500/20 to-cyan-500/10 text-white border border-cyan-500/40 shadow-lg shadow-cyan-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'materi'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-cyan-400 to-teal-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'materi' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400 group-hover:text-cyan-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                  </svg>
                </div>
                <span class="truncate">Kelola Modul Materi</span>
              </button>
            </div>
          </div>

          <!-- KATEGORI CBT: MASTER MENU CBT (COMPUTER BASED TEST) -->
          <div class="space-y-2">
            <div class="px-3 text-[10px] font-extrabold text-purple-400 uppercase tracking-widest flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span>
              <span>CBT (UJIAN ONLINE)</span>
            </div>
            <div class="space-y-1.5">
              
              <!-- 1. Bank Soal (CRUD Pilihan Ganda A-E) -->
              <button
                @click="selectView('cbt-bank-soal')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'cbt-bank-soal'
                    ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-cyan-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'cbt-bank-soal'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'cbt-bank-soal' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                  </svg>
                </div>
                <div class="text-left min-w-0">
                  <span class="truncate block">Bank Soal</span>
                  <span class="text-[10px] text-gray-500 font-normal block">Pilihan Ganda (A - E)</span>
                </div>
              </button>

              <!-- 2. Jadwal CBT (Pengaturan Aktif/Nonaktif) -->
              <button
                @click="selectView('cbt-jadwal')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'cbt-jadwal'
                    ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-cyan-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'cbt-jadwal'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'cbt-jadwal' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <div class="text-left min-w-0">
                  <span class="truncate block">Jadwal CBT</span>
                  <span class="text-[10px] text-gray-500 font-normal block">Aktif &amp; Non-Aktif Soal</span>
                </div>
              </button>

              <!-- 3. Hasil Nilai CBT -->
              <button
                @click="selectView('cbt-nilai')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'cbt-nilai'
                    ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-cyan-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'cbt-nilai'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'cbt-nilai' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                  </svg>
                </div>
                <div class="text-left min-w-0">
                  <span class="truncate block">Hasil Nilai CBT</span>
                  <span class="text-[10px] text-gray-500 font-normal block">Jurusan, Kelas &amp; Siswa</span>
                </div>
              </button>

            </div>
          </div>

          <!-- KATEGORI 4: BERALIH PORTAL (Dual-Role Only) -->
          <div v-if="hasMultipleTeacherRoles" class="space-y-2 pt-4 border-t border-white/10">
            <div class="px-3 text-[10px] font-extrabold text-indigo-400 uppercase tracking-widest flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
              <span>Beralih Portal</span>
            </div>
            <a
              href="/wali-kelas/dashboard"
              class="w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 border border-indigo-500/30 bg-indigo-500/10 text-indigo-300 hover:bg-indigo-500/20 hover:text-white"
            >
              <span class="text-lg">🔄</span>
              <span>Portal Wali Kelas 📋</span>
            </a>
          </div>

        </nav>
      </div>
    </aside>
  </div>
</template>
