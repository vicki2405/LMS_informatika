<script setup>
const props = defineProps({
  activeView: {
    type: String,
    default: 'dashboard'
  },
  sidebarOpen: {
    type: Boolean,
    default: false
  },
  kelasSiswa: {
    type: String,
    default: 'Kelas Siswa'
  }
});

const emit = defineEmits(['update:activeView', 'update:sidebarOpen']);

function selectView(view) {
  emit('update:activeView', view);
  emit('update:sidebarOpen', false);
}
</script>

<template>
  <!-- ============================================================ -->
  <!-- FIXED ULTRA-MODERN 5-TAB BOTTOM NAVIGATION BAR (BALANCED DOCK)-->
  <!-- ============================================================ -->
  <div
    class="fixed bottom-0 left-0 right-0 z-50 lg:hidden bg-slate-900/95 backdrop-blur-2xl border-t border-white/10 shadow-[0_-10px_25px_-5px_rgba(0,0,0,0.6)] pb-[calc(env(safe-area-inset-bottom,0px)+6px)]"
    style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; transform: translateZ(0); -webkit-transform: translateZ(0);"
  >
    <!-- Top Radiant Cyan/Blue Accent Glow Line -->
    <div class="h-[2px] w-full bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-500"></div>

    <nav class="grid grid-cols-5 h-16 max-w-md mx-auto px-1 select-none">
      
      <!-- 1. TAB: BERANDA -->
      <button
        @click="selectView('dashboard')"
        type="button"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none min-h-[44px]',
          activeView === 'dashboard' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span
          v-if="activeView === 'dashboard'"
          class="absolute top-0 w-8 h-1 bg-cyan-400 rounded-b-full shadow-[0_2px_8px_rgba(34,211,238,0.9)]"
        ></span>
        
        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'dashboard' ? 'bg-cyan-500/20 text-cyan-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Beranda</span>
      </button>

      <!-- 2. TAB: MATERI -->
      <button
        @click="selectView('materi')"
        type="button"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none min-h-[44px]',
          activeView === 'materi' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span
          v-if="activeView === 'materi'"
          class="absolute top-0 w-8 h-1 bg-cyan-400 rounded-b-full shadow-[0_2px_8px_rgba(34,211,238,0.9)]"
        ></span>

        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'materi' ? 'bg-cyan-500/20 text-cyan-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Materi</span>
      </button>

      <!-- 3. TAB: CBT (UJIAN ONLINE) -->
      <button
        @click="selectView('cbt')"
        type="button"
        title="Masuk Ruang Ujian CBT"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none min-h-[44px]',
          activeView === 'cbt' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span
          v-if="activeView === 'cbt'"
          class="absolute top-0 w-8 h-1 bg-cyan-400 rounded-b-full shadow-[0_2px_8px_rgba(34,211,238,0.9)]"
        ></span>

        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'cbt' ? 'bg-cyan-500/20 text-cyan-300 scale-110 shadow-lg shadow-cyan-500/20' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">CBT</span>
      </button>

      <!-- 4. TAB: TUGAS -->
      <button
        @click="selectView('tugas')"
        type="button"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none min-h-[44px]',
          activeView === 'tugas' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span
          v-if="activeView === 'tugas'"
          class="absolute top-0 w-8 h-1 bg-cyan-400 rounded-b-full shadow-[0_2px_8px_rgba(34,211,238,0.9)]"
        ></span>

        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'tugas' ? 'bg-cyan-500/20 text-cyan-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Tugas</span>
      </button>

      <!-- 5. TAB: NILAI TP -->
      <button
        @click="selectView('nilai')"
        type="button"
        :class="[
          'flex flex-col items-center justify-center gap-1 transition-all duration-200 active:scale-95 relative select-none min-h-[44px]',
          activeView === 'nilai' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-white font-medium'
        ]"
      >
        <span
          v-if="activeView === 'nilai'"
          class="absolute top-0 w-8 h-1 bg-cyan-400 rounded-b-full shadow-[0_2px_8px_rgba(34,211,238,0.9)]"
        ></span>

        <div :class="['p-1.5 rounded-xl transition-all', activeView === 'nilai' ? 'bg-cyan-500/20 text-cyan-300 scale-110' : '']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight">Nilai TP</span>
      </button>

    </nav>
  </div>
</template>
