<script setup>
defineProps({
  activeView: String,
  sidebarOpen: Boolean,
  userPermissions: Array
});

const emit = defineEmits(['update:activeView', 'update:sidebarOpen']);

function selectView(view) {
  emit('update:activeView', view);
  // Auto-close drawer on mobile when selecting a menu item
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
              <span class="bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-indigo-300">ADMINISTRATOR</span>
              <span class="text-sm">⚡</span>
            </h2>
            <p class="text-[11px] text-purple-400 font-semibold truncate mt-0.5">Control Center System Admin</p>
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
              <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span>
              <span>UTAMA</span>
            </div>
            <button
              @click="selectView('dashboard')"
              :class="[
                'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                activeView === 'dashboard'
                  ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-purple-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <!-- Active Accent Bar Left -->
              <span v-if="activeView === 'dashboard'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
              
              <div :class="['p-2 rounded-xl transition-colors', activeView === 'dashboard' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
              </div>
              <span class="truncate">Dashboard Overview</span>
            </button>
          </div>

          <!-- KATEGORI 2: MASTER AKADEMIK -->
          <div class="space-y-2">
            <div class="px-3 text-[10px] font-extrabold text-purple-400 uppercase tracking-widest flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
              <span>MASTER AKADEMIK</span>
            </div>
            <div class="space-y-1.5">
              
              <!-- 1. Master Tahun Pelajaran -->
              <button
                @click="selectView('tahun-ajaran')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'tahun-ajaran'
                    ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-purple-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'tahun-ajaran'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'tahun-ajaran' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                  <span class="text-base">📅</span>
                </div>
                <span class="truncate">Tahun Pelajaran (Default)</span>
              </button>

              <!-- 2. Jurusan & Kelas -->
              <button
                @click="selectView('kelas')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'kelas'
                    ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-purple-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'kelas'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'kelas' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                  </svg>
                </div>
                <span class="truncate">Master Jurusan & Kelas</span>
              </button>

              <!-- 2. Master Mata Pelajaran -->
              <button
                @click="selectView('mapel')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'mapel'
                    ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-purple-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'mapel'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'mapel' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                  <span class="text-base">📚</span>
                </div>
                <span class="truncate">Master Mata Pelajaran</span>
              </button>

              <!-- 3. Master Data Guru -->
              <button
                @click="selectView('guru')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'guru'
                    ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-purple-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'guru'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'guru' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                  <span class="text-base">👨‍🏫</span>
                </div>
                <span class="truncate">Master Data Guru</span>
              </button>

              <!-- 4. Data Siswa -->
              <button
                @click="selectView('siswa')"
                :class="[
                  'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                  activeView === 'siswa'
                    ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-purple-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
                ]"
              >
                <span v-if="activeView === 'siswa'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
                <div :class="['p-2 rounded-xl transition-colors', activeView === 'siswa' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                  </svg>
                </div>
                <span class="truncate">Master Data Siswa</span>
              </button>

            </div>
          </div>

          <!-- KATEGORI 3: HAK AKSES & AKUN -->
          <div class="space-y-2">
            <div class="px-3 text-[10px] font-extrabold text-cyan-400 uppercase tracking-widest flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
              <span>HAK AKSES & AKUN</span>
            </div>
            <button
              @click="selectView('users')"
              :class="[
                'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                activeView === 'users'
                  ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-purple-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <span v-if="activeView === 'users'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
              <div :class="['p-2 rounded-xl transition-colors', activeView === 'users' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
              </div>
              <span class="truncate">User & Password Role</span>
            </button>

            <!-- Backup & Restore Database -->
            <button
              @click="selectView('backup')"
              :class="[
                'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-200 group relative',
                activeView === 'backup'
                  ? 'bg-gradient-to-r from-purple-500/25 via-indigo-500/20 to-purple-500/10 text-white border border-purple-500/40 shadow-lg shadow-purple-950/30'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <span v-if="activeView === 'backup'" class="absolute left-0 top-2 bottom-2 w-1.5 bg-gradient-to-b from-purple-400 to-indigo-500 rounded-r-full shadow-md"></span>
              <div :class="['p-2 rounded-xl transition-colors', activeView === 'backup' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-gray-400 group-hover:text-purple-400 group-hover:bg-white/10']">
                <span class="text-base">🗄️</span>
              </div>
              <span class="truncate">Backup & Restore DB</span>
            </button>
          </div>

        </nav>

        <!-- Admin Super Access Card Widget -->
        <div class="p-4 bg-gradient-to-br from-purple-950/40 to-indigo-950/40 rounded-3xl border border-purple-500/25 relative overflow-hidden shadow-inner">
          <div class="absolute -right-6 -bottom-6 w-20 h-20 bg-purple-500/10 rounded-full blur-xl pointer-events-none"></div>
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-sm">🛡️</span>
            <h4 class="text-xs font-extrabold text-purple-300 uppercase tracking-wider">Akses Super Admin</h4>
          </div>
          <p class="text-[11px] text-gray-300 leading-relaxed font-medium">
            Memiliki hak akses penuh untuk mengelola master data sekolah, akun guru, siswa, serta reset kata sandi seluruh sistem.
          </p>
        </div>

      </div>
    </aside>
  </div>
</template>
