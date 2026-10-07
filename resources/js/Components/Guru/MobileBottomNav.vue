<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
  activeView: String,
  sidebarOpen: Boolean
});

const emit = defineEmits(['update:activeView', 'update:sidebarOpen']);

const navContainer = ref(null);

const navItems = [
  { id: 'dashboard', label: 'Home', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { id: 'jadwal', label: 'Jadwal', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { id: 'kelas', label: 'Kelas', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
  { id: 'siswa', label: 'Siswa', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { id: 'absensi', label: 'Presensi', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  { id: 'jurnal', label: 'Jurnal KBM', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
  { id: 'tp', label: 'Kelola TP', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
  { id: 'nilai', label: 'Nilai TP', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { id: 'materi', label: 'Materi', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { id: 'cbt-bank-soal', label: 'Bank Soal', icon: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4' },
  { id: 'cbt-jadwal', label: 'Jadwal CBT', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { id: 'cbt-nilai', label: 'Nilai CBT', icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
  { id: 'more', label: 'Semua Menu', icon: 'M4 6h16M4 12h16M4 18h16' }
];

function isNavActive(id) {
  if (id === 'more') return props.sidebarOpen;
  if (id === 'absensi') return props.activeView === 'absensi' || props.activeView === 'input-absensi';
  return props.activeView === id;
}

function selectNav(id) {
  if (id === 'more') {
    emit('update:sidebarOpen', true);
  } else {
    emit('update:activeView', id);
    emit('update:sidebarOpen', false);
  }
}

function scrollToActive() {
  nextTick(() => {
    if (!navContainer.value) return;
    const activeEl = navContainer.value.querySelector('[data-active="true"]');
    if (activeEl) {
      activeEl.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
  });
}

watch(() => props.activeView, () => scrollToActive());
watch(() => props.sidebarOpen, () => scrollToActive());
onMounted(() => scrollToActive());
</script>

<template>
  <div class="fixed bottom-0 left-0 right-0 z-50 lg:hidden bg-slate-900/95 backdrop-blur-2xl border-t border-white/10 shadow-[0_-10px_25px_-5px_rgba(0,0,0,0.6)] safe-area-bottom">
    <!-- Top Accent Glow Line -->
    <div class="h-[2px] w-full bg-gradient-to-r from-cyan-500 via-teal-400 to-indigo-500"></div>

    <div class="relative w-full">
      <!-- Left & Right Fade Indicators to hint horizontal scrollability -->
      <div class="pointer-events-none absolute left-0 top-0 bottom-0 w-4 bg-gradient-to-r from-slate-900 via-slate-900/60 to-transparent z-10"></div>
      <div class="pointer-events-none absolute right-0 top-0 bottom-0 w-4 bg-gradient-to-l from-slate-900 via-slate-900/60 to-transparent z-10"></div>

      <!-- Horizontal Scrollable Navigation Strip -->
      <nav
        ref="navContainer"
        class="flex items-center gap-1 overflow-x-auto scroll-smooth py-1.5 px-3 h-16 touch-pan-x no-scrollbar"
      >
        <button
          v-for="item in navItems"
          :key="item.id"
          @click="selectNav(item.id)"
          :data-active="isNavActive(item.id)"
          :class="[
            'shrink-0 flex flex-col items-center justify-center min-w-[68px] h-full px-2 py-1 rounded-2xl transition-all duration-200 active:scale-95 relative select-none min-h-[48px]',
            isNavActive(item.id)
              ? 'text-cyan-300 font-extrabold bg-cyan-500/10 border border-cyan-500/30'
              : 'text-gray-400 hover:text-white font-medium hover:bg-white/5 border border-transparent'
          ]"
        >
          <!-- Active Glow Bar -->
          <span
            v-if="isNavActive(item.id)"
            class="absolute -top-1.5 w-7 h-1 bg-gradient-to-r from-cyan-400 to-teal-300 rounded-full shadow-[0_2px_8px_rgba(34,211,238,0.9)]"
          ></span>

          <div :class="['p-1 rounded-xl transition-all', isNavActive(item.id) ? 'text-cyan-300 scale-110' : 'text-gray-400']">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"/>
            </svg>
          </div>
          <span class="text-[10px] tracking-tight whitespace-nowrap mt-0.5 leading-tight">{{ item.label }}</span>
        </button>
      </nav>
    </div>
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
