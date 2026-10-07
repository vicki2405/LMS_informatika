<script setup>
import { computed } from 'vue';

const props = defineProps({
  activeView: String,
  sidebarOpen: Boolean,
  user: Object,
  kelasBinaan: {
    type: String,
    default: 'X RPL 1'
  }
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
    <!-- Sidebar overlay mobile -->
    <div v-if="sidebarOpen" @click="$emit('update:sidebarOpen', false)" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-40 lg:hidden"></div>

    <!-- Sidebar Navigation -->
    <aside :class="['fixed lg:sticky top-16 left-0 h-[calc(100vh-8rem)] lg:h-[calc(100vh-4rem)] w-72 bg-slate-900/95 backdrop-blur-2xl border-r border-white/10 z-40 transition-all duration-300 overflow-y-auto shrink-0 custom-scrollbar', sidebarOpen ? 'translate-x-0 ml-0' : '-translate-x-full -ml-72']">
      <div class="p-6 space-y-6">
        
        <!-- Header Info -->
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div>
            <h2 class="text-base font-black text-white flex items-center gap-2">
              <span>Wali Kelas Panel</span>
              <span class="text-indigo-400">👩‍🏫</span>
            </h2>
            <p class="text-xs text-indigo-400 font-bold mt-0.5">Pembina Kelas {{ kelasBinaan }}</p>
          </div>
          <button @click="$emit('update:sidebarOpen', false)" title="Sembunyikan Sidebar" class="p-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-colors lg:hidden">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
          </button>
        </div>

        <!-- Sidebar Menu Categorized Links -->
        <nav class="space-y-6">
          
          <!-- Kategori 1: UTAMA -->
          <div class="space-y-1.5">
            <div class="px-3 text-[10px] font-black text-gray-500 uppercase tracking-widest font-mono">UTAMA</div>
            
            <button
              @click="selectView('dashboard')"
              :class="[
                'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs sm:text-sm font-extrabold transition-all duration-200 relative group',
                activeView === 'dashboard'
                  ? 'bg-gradient-to-r from-indigo-500/20 to-purple-500/20 text-indigo-300 border border-indigo-500/40 shadow-lg shadow-indigo-500/10'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div v-if="activeView === 'dashboard'" class="absolute left-0 top-2 bottom-2 w-1 bg-indigo-400 rounded-r-full shadow-lg shadow-indigo-400"></div>
              <span class="text-lg">📊</span>
              <span>Dashboard Ringkasan</span>
            </button>
          </div>

          <!-- Kategori 2: DATA KELAS BINAAN -->
          <div class="space-y-1.5">
            <div class="px-3 text-[10px] font-black text-gray-500 uppercase tracking-widest font-mono">DATA KELAS</div>

            <button
              @click="selectView('absensi')"
              :class="[
                'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs sm:text-sm font-extrabold transition-all duration-200 relative group',
                activeView === 'absensi'
                  ? 'bg-gradient-to-r from-indigo-500/20 to-purple-500/20 text-indigo-300 border border-indigo-500/40 shadow-lg shadow-indigo-500/10'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div v-if="activeView === 'absensi'" class="absolute left-0 top-2 bottom-2 w-1 bg-indigo-400 rounded-r-full shadow-lg shadow-indigo-400"></div>
              <span class="text-lg">📌</span>
              <span>Rekap &amp; Presensi Jam 1 - 2</span>
            </button>

            <button
              @click="selectView('siswa')"
              :class="[
                'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs sm:text-sm font-extrabold transition-all duration-200 relative group',
                activeView === 'siswa'
                  ? 'bg-gradient-to-r from-indigo-500/20 to-purple-500/20 text-indigo-300 border border-indigo-500/40 shadow-lg shadow-indigo-500/10'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div v-if="activeView === 'siswa'" class="absolute left-0 top-2 bottom-2 w-1 bg-indigo-400 rounded-r-full shadow-lg shadow-indigo-400"></div>
              <span class="text-lg">👨‍🎓</span>
              <span>Data Siswa Binaan</span>
            </button>

            <button
              @click="selectView('jadwal')"
              :class="[
                'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs sm:text-sm font-extrabold transition-all duration-200 relative group',
                activeView === 'jadwal'
                  ? 'bg-gradient-to-r from-indigo-500/20 to-purple-500/20 text-indigo-300 border border-indigo-500/40 shadow-lg shadow-indigo-500/10'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div v-if="activeView === 'jadwal'" class="absolute left-0 top-2 bottom-2 w-1 bg-indigo-400 rounded-r-full shadow-lg shadow-indigo-400"></div>
              <span class="text-lg">📅</span>
              <span>Jadwal Pelajaran Kelas</span>
            </button>

            <button
              @click="selectView('kasus')"
              :class="[
                'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs sm:text-sm font-extrabold transition-all duration-200 relative group',
                activeView === 'kasus'
                  ? 'bg-gradient-to-r from-indigo-500/20 to-purple-500/20 text-indigo-300 border border-indigo-500/40 shadow-lg shadow-indigo-500/10'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div v-if="activeView === 'kasus'" class="absolute left-0 top-2 bottom-2 w-1 bg-indigo-400 rounded-r-full shadow-lg shadow-indigo-400"></div>
              <span class="text-lg">🚨</span>
              <span>Data Kasus Siswa</span>
            </button>
          </div>

          <!-- Kategori 3: MONITORING AKADEMIK -->
          <div class="space-y-1.5">
            <div class="px-3 text-[10px] font-black text-gray-500 uppercase tracking-widest font-mono">MONITORING & RAPOR</div>

            <button
              @click="selectView('tugas')"
              :class="[
                'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs sm:text-sm font-extrabold transition-all duration-200 relative group',
                activeView === 'tugas'
                  ? 'bg-gradient-to-r from-indigo-500/20 to-purple-500/20 text-indigo-300 border border-indigo-500/40 shadow-lg shadow-indigo-500/10'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div v-if="activeView === 'tugas'" class="absolute left-0 top-2 bottom-2 w-1 bg-indigo-400 rounded-r-full shadow-lg shadow-indigo-400"></div>
              <span class="text-lg">📋</span>
              <span>Ketuntasan Tugas Siswa</span>
            </button>

            <button
              @click="selectView('nilai')"
              :class="[
                'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs sm:text-sm font-extrabold transition-all duration-200 relative group',
                activeView === 'nilai'
                  ? 'bg-gradient-to-r from-indigo-500/20 to-purple-500/20 text-indigo-300 border border-indigo-500/40 shadow-lg shadow-indigo-500/10'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div v-if="activeView === 'nilai'" class="absolute left-0 top-2 bottom-2 w-1 bg-indigo-400 rounded-r-full shadow-lg shadow-indigo-400"></div>
              <span class="text-lg">📝</span>
              <span>Nilai Siswa Per Semester</span>
            </button>
          </div>

          <!-- Kategori 4: PORTAL LAIN (Dual-Role Only) -->
          <div v-if="hasMultipleTeacherRoles" class="space-y-1.5 pt-4 border-t border-white/10">
            <div class="px-3 text-[10px] font-black text-gray-500 uppercase tracking-widest font-mono">Beralih Portal</div>
            <a
              href="/guru/dashboard"
              class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs sm:text-sm font-extrabold transition-all duration-200 border border-cyan-500/30 bg-cyan-500/10 text-cyan-300 hover:bg-cyan-500/20 hover:text-white"
            >
              <span class="text-lg">🔄</span>
              <span>Portal Guru Mapel ⚡</span>
            </a>
          </div>

        </nav>
      </div>
    </aside>
  </div>
</template>
