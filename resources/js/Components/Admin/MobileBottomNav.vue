<script setup>
defineProps({
  activeView: String,
  sidebarOpen: Boolean
});

const emit = defineEmits(['update:activeView', 'update:sidebarOpen']);

function selectNav(view) {
  if (view === 'more') {
    emit('update:sidebarOpen', true);
  } else {
    emit('update:activeView', view);
    emit('update:sidebarOpen', false);
  }
}
</script>

<template>
  <div class="fixed bottom-0 left-0 right-0 z-50 lg:hidden bg-slate-900/95 backdrop-blur-2xl border-t border-white/10 shadow-[0_-10px_25px_-5px_rgba(0,0,0,0.5)]">
    <!-- Top Accent Glow Line -->
    <div class="h-[2px] w-full bg-gradient-to-r from-purple-500 via-pink-500 to-cyan-400"></div>

    <nav class="grid grid-cols-5 h-16 max-w-md mx-auto px-1">
      
      <!-- 1. Home / Dashboard -->
      <button
        @click="selectNav('dashboard')"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none',
          activeView === 'dashboard' ? 'text-purple-400 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span v-if="activeView === 'dashboard'" class="absolute top-0 w-8 h-1 bg-purple-400 rounded-b-full shadow-[0_2px_8px_rgba(192,132,252,0.8)]"></span>
        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'dashboard' ? 'bg-purple-500/20 text-purple-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Home</span>
      </button>

      <!-- 2. Guru -->
      <button
        @click="selectNav('guru')"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none',
          activeView === 'guru' ? 'text-purple-400 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span v-if="activeView === 'guru'" class="absolute top-0 w-8 h-1 bg-purple-400 rounded-b-full shadow-[0_2px_8px_rgba(192,132,252,0.8)]"></span>
        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'guru' ? 'bg-purple-500/20 text-purple-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Guru</span>
      </button>

      <!-- 3. Kelas & Jurusan -->
      <button
        @click="selectNav('kelas')"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none',
          activeView === 'kelas' ? 'text-purple-400 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span v-if="activeView === 'kelas'" class="absolute top-0 w-8 h-1 bg-purple-400 rounded-b-full shadow-[0_2px_8px_rgba(192,132,252,0.8)]"></span>
        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'kelas' ? 'bg-purple-500/20 text-purple-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Kelas</span>
      </button>

      <!-- 4. User & Password -->
      <button
        @click="selectNav('users')"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none',
          activeView === 'users' ? 'text-purple-400 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span v-if="activeView === 'users'" class="absolute top-0 w-8 h-1 bg-purple-400 rounded-b-full shadow-[0_2px_8px_rgba(192,132,252,0.8)]"></span>
        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'users' ? 'bg-purple-500/20 text-purple-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Akun</span>
      </button>

      <!-- 5. Lainnya / Menu Sidebar Drawer -->
      <button
        @click="selectNav('more')"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none',
          sidebarOpen ? 'text-purple-400 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span v-if="sidebarOpen" class="absolute top-0 w-8 h-1 bg-purple-400 rounded-b-full shadow-[0_2px_8px_rgba(192,132,252,0.8)]"></span>
        <div :class="['p-1.5 rounded-xl transition-all', sidebarOpen ? 'bg-purple-500/20 text-purple-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Lainnya</span>
      </button>

    </nav>
  </div>
</template>
