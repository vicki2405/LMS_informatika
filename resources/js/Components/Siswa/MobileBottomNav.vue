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
  <!-- FIXED ULTRA-MODERN 5-TAB FLOATING BOTTOM NAVIGATION BAR       -->
  <!-- ============================================================ -->
  <nav
    class="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-slate-950/95 backdrop-blur-2xl border-t border-white/10 shadow-[0_-8px_30px_rgba(0,0,0,0.8)] pb-[calc(env(safe-area-inset-bottom,0px)+6px)] pt-1 px-3 select-none"
  >
    <!-- Top Neon Highlight Line -->
    <div class="absolute -top-[1px] inset-x-0 h-[1.5px] bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-500 opacity-80"></div>

    <div class="max-w-md mx-auto grid grid-cols-5 items-end justify-between relative h-14">
      
      <!-- 1. TAB: BERANDA -->
      <button
        @click="selectView('dashboard')"
        type="button"
        :class="[
          'flex flex-col items-center justify-center h-full py-1 rounded-xl transition-all duration-200 active:scale-90 relative select-none',
          activeView === 'dashboard' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-gray-200 font-medium'
        ]"
      >
        <span
          v-if="activeView === 'dashboard'"
          class="absolute top-0 w-8 h-1 bg-gradient-to-r from-cyan-400 to-blue-400 rounded-full shadow-[0_0_10px_rgba(34,211,238,0.9)]"
        ></span>
        
        <div :class="['transition-transform duration-200', activeView === 'dashboard' ? 'scale-110 text-cyan-300' : 'text-gray-400']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight mt-0.5 leading-none">Beranda</span>
      </button>

      <!-- 2. TAB: MATERI -->
      <button
        @click="selectView('materi')"
        type="button"
        :class="[
          'flex flex-col items-center justify-center h-full py-1 rounded-xl transition-all duration-200 active:scale-90 relative select-none',
          activeView === 'materi' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-gray-200 font-medium'
        ]"
      >
        <span
          v-if="activeView === 'materi'"
          class="absolute top-0 w-8 h-1 bg-gradient-to-r from-cyan-400 to-blue-400 rounded-full shadow-[0_0_10px_rgba(34,211,238,0.9)]"
        ></span>

        <div :class="['transition-transform duration-200', activeView === 'materi' ? 'scale-110 text-cyan-300' : 'text-gray-400']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight mt-0.5 leading-none">Materi</span>
      </button>

      <!-- 3. CENTER HERO ACTION: CBT UJIAN (ELEVATED GLOWING BUTTON) -->
      <div class="relative flex flex-col items-center justify-end h-full">
        <button
          @click="selectView('cbt')"
          type="button"
          title="Masuk Ruang Ujian CBT"
          :class="[
            'absolute -top-5 flex flex-col items-center justify-center w-13 h-13 rounded-full shadow-2xl transition-all duration-300 active:scale-95 group',
            activeView === 'cbt'
              ? 'bg-gradient-to-tr from-cyan-400 via-blue-500 to-indigo-600 shadow-[0_0_25px_rgba(6,182,212,0.8)] ring-4 ring-slate-900 border-2 border-cyan-300'
              : 'bg-gradient-to-tr from-cyan-500 via-blue-600 to-indigo-700 shadow-[0_0_18px_rgba(6,182,212,0.5)] ring-4 ring-slate-900 border-2 border-cyan-400/50 hover:brightness-110'
          ]"
        >
          <!-- Pulse Glow Ring effect -->
          <span class="absolute inset-0 rounded-full bg-cyan-400 animate-ping opacity-25 pointer-events-none"></span>

          <div class="flex items-center justify-center text-white">
            <svg class="w-6 h-6 transform group-hover:scale-110 transition-transform drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
          </div>
        </button>
        
        <span
          :class="[
            'text-[10px] font-black tracking-tight mt-0.5 pb-1',
            activeView === 'cbt' ? 'text-cyan-300' : 'text-cyan-400'
          ]"
        >
          CBT
        </span>
      </div>

      <!-- 4. TAB: TUGAS -->
      <button
        @click="selectView('tugas')"
        type="button"
        :class="[
          'flex flex-col items-center justify-center h-full py-1 rounded-xl transition-all duration-200 active:scale-90 relative select-none',
          activeView === 'tugas' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-gray-200 font-medium'
        ]"
      >
        <span
          v-if="activeView === 'tugas'"
          class="absolute top-0 w-8 h-1 bg-gradient-to-r from-cyan-400 to-blue-400 rounded-full shadow-[0_0_10px_rgba(34,211,238,0.9)]"
        ></span>

        <div :class="['transition-transform duration-200', activeView === 'tugas' ? 'scale-110 text-cyan-300' : 'text-gray-400']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight mt-0.5 leading-none">Tugas</span>
      </button>

      <!-- 5. TAB: NILAI TP (DIRECT INSTANT ACCESS) -->
      <button
        @click="selectView('nilai')"
        type="button"
        :class="[
          'flex flex-col items-center justify-center h-full py-1 rounded-xl transition-all duration-200 active:scale-90 relative select-none',
          activeView === 'nilai' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-gray-200 font-medium'
        ]"
      >
        <span
          v-if="activeView === 'nilai'"
          class="absolute top-0 w-8 h-1 bg-gradient-to-r from-cyan-400 to-blue-400 rounded-full shadow-[0_0_10px_rgba(34,211,238,0.9)]"
        ></span>

        <div :class="['transition-transform duration-200', activeView === 'nilai' ? 'scale-110 text-cyan-300' : 'text-gray-400']">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
        </div>
        <span class="text-[10px] tracking-tight mt-0.5 leading-none">Nilai TP</span>
      </button>

    </div>
  </nav>
</template>

<style scoped>
.w-13 {
  width: 3.25rem;
}
.h-13 {
  height: 3.25rem;
}
</style>
