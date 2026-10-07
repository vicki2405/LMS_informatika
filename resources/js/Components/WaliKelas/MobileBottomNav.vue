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
  { id: 'absensi', label: 'Presensi', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  { id: 'siswa', label: 'Siswa', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { id: 'jadwal', label: 'Jadwal', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { id: 'kasus', label: 'Kasus', icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
  { id: 'tugas', label: 'Tugas', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
  { id: 'nilai', label: 'Nilai Rapor', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { id: 'more', label: 'Semua Menu', icon: 'M4 6h16M4 12h16M4 18h16' }
];

function isNavActive(id) {
  if (id === 'more') return props.sidebarOpen;
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
    <div class="h-[2px] w-full bg-gradient-to-r from-indigo-500 via-purple-500 to-cyan-400"></div>

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
            'shrink-0 flex flex-col items-center justify-center min-w-[70px] h-full px-2 py-1 rounded-2xl transition-all duration-200 active:scale-95 relative select-none min-h-[48px]',
            isNavActive(item.id)
              ? 'text-indigo-300 font-extrabold bg-indigo-500/15 border border-indigo-500/35'
              : 'text-gray-400 hover:text-white font-medium hover:bg-white/5 border border-transparent'
          ]"
        >
          <!-- Active Glow Bar -->
          <span
            v-if="isNavActive(item.id)"
            class="absolute -top-1.5 w-7 h-1 bg-gradient-to-r from-indigo-400 to-purple-400 rounded-full shadow-[0_2px_8px_rgba(99,102,241,0.9)]"
          ></span>

          <div :class="['p-1 rounded-xl transition-all', isNavActive(item.id) ? 'text-indigo-300 scale-110' : 'text-gray-400']">
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
