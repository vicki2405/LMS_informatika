<script setup>
import { computed } from 'vue';

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

const isMoreActive = computed(() => {
  return props.sidebarOpen || props.activeView === 'nilai' || props.activeView === 'jadwal';
});

function selectView(view) {
  emit('update:activeView', view);
  emit('update:sidebarOpen', false);
}

function toggleMore() {
  emit('update:sidebarOpen', !props.sidebarOpen);
}

function closeDrawer() {
  emit('update:sidebarOpen', false);
}
</script>

<template>
  <div>
    <!-- ============================================================ -->
    <!-- SLIDE-UP BOTTOM SHEET DRAWER FOR 'MENU LAIN' (MOBILE ONLY)  -->
    <!-- ============================================================ -->
    <Teleport to="body">
      <!-- Backdrop -->
      <transition
        enter-active-class="transition-opacity duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="sidebarOpen"
          @click="closeDrawer"
          class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 lg:hidden"
        ></div>
      </transition>

      <!-- Bottom Sheet Content -->
      <transition
        enter-active-class="transition-transform duration-300 cubic-bezier(0.16, 1, 0.3, 1)"
        enter-from-class="translate-y-full"
        enter-to-class="translate-y-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-y-0"
        leave-to-class="translate-y-full"
      >
        <div
          v-if="sidebarOpen"
          class="fixed bottom-0 inset-x-0 z-50 lg:hidden bg-slate-900/98 backdrop-blur-2xl border-t border-cyan-500/30 rounded-t-[32px] shadow-[0_-15px_40px_rgba(0,0,0,0.8)] max-h-[85vh] flex flex-col overflow-hidden"
        >
          <!-- Pull indicator handle -->
          <div class="pt-3 pb-2 flex justify-center cursor-pointer" @click="closeDrawer">
            <div class="w-12 h-1.5 rounded-full bg-white/20 active:bg-white/40 transition-colors"></div>
          </div>

          <!-- Bottom Sheet Header -->
          <div class="px-6 py-3 flex items-center justify-between border-b border-white/10">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-cyan-400 to-blue-600 flex items-center justify-center text-lg font-black text-white shadow-lg shadow-cyan-500/25">
                🚀
              </div>
              <div>
                <h3 class="text-sm font-black text-white tracking-tight">Semua Fitur Belajar</h3>
                <p class="text-[11px] text-cyan-400 font-bold">Portal Siswa • SMK Muha 2 Genteng</p>
              </div>
            </div>
            <button
              @click="closeDrawer"
              class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/15 text-gray-300 hover:text-white flex items-center justify-center transition-colors text-sm"
            >
              ✕
            </button>
          </div>

          <!-- Sheet Body with Grid of Features -->
          <div class="p-5 overflow-y-auto space-y-4 pb-10">
            <!-- Grid Navigation Items -->
            <div class="grid grid-cols-2 gap-3">
              <!-- 1. Beranda Kelas -->
              <button
                @click="selectView('dashboard')"
                :class="[
                  'flex items-center gap-3 p-3.5 rounded-2xl border text-left transition-all active:scale-95',
                  activeView === 'dashboard'
                    ? 'bg-cyan-500/20 border-cyan-400/50 shadow-md text-cyan-300'
                    : 'bg-white/5 border-white/10 text-gray-300 hover:bg-white/10'
                ]"
              >
                <div class="w-10 h-10 rounded-xl bg-cyan-500/15 text-cyan-400 flex items-center justify-center text-xl shrink-0">
                  🏠
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-bold text-white truncate">Beranda</div>
                  <div class="text-[10px] text-gray-400 truncate">Ringkasan kelas</div>
                </div>
              </button>

              <!-- 2. Materi Pembelajaran -->
              <button
                @click="selectView('materi')"
                :class="[
                  'flex items-center gap-3 p-3.5 rounded-2xl border text-left transition-all active:scale-95',
                  activeView === 'materi'
                    ? 'bg-indigo-500/20 border-indigo-400/50 shadow-md text-indigo-300'
                    : 'bg-white/5 border-white/10 text-gray-300 hover:bg-white/10'
                ]"
              >
                <div class="w-10 h-10 rounded-xl bg-indigo-500/15 text-indigo-400 flex items-center justify-center text-xl shrink-0">
                  📚
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-bold text-white truncate">Materi Slider</div>
                  <div class="text-[10px] text-gray-400 truncate">Slide PPT & Modul</div>
                </div>
              </button>

              <!-- 3. CBT Ujian Online -->
              <button
                @click="selectView('cbt')"
                :class="[
                  'flex items-center gap-3 p-3.5 rounded-2xl border text-left transition-all active:scale-95',
                  activeView === 'cbt'
                    ? 'bg-gradient-to-r from-cyan-500/25 to-blue-500/25 border-cyan-400/60 shadow-md text-cyan-200 font-extrabold'
                    : 'bg-white/5 border-white/10 text-gray-300 hover:bg-white/10'
                ]"
              >
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 text-white flex items-center justify-center text-xl shrink-0 shadow-md shadow-cyan-500/30">
                  🎯
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-bold text-white truncate">Ujian CBT</div>
                  <div class="text-[10px] text-cyan-400 font-medium truncate">Mulai ujian online</div>
                </div>
              </button>

              <!-- 4. Tugas Siswa -->
              <button
                @click="selectView('tugas')"
                :class="[
                  'flex items-center gap-3 p-3.5 rounded-2xl border text-left transition-all active:scale-95',
                  activeView === 'tugas'
                    ? 'bg-emerald-500/20 border-emerald-400/50 shadow-md text-emerald-300'
                    : 'bg-white/5 border-white/10 text-gray-300 hover:bg-white/10'
                ]"
              >
                <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-xl shrink-0">
                  📝
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-bold text-white truncate">Tugas Siswa</div>
                  <div class="text-[10px] text-gray-400 truncate">Kirim tugas online</div>
                </div>
              </button>

              <!-- 5. Nilai TP & Raport -->
              <button
                @click="selectView('nilai')"
                :class="[
                  'flex items-center gap-3 p-3.5 rounded-2xl border text-left transition-all active:scale-95',
                  activeView === 'nilai'
                    ? 'bg-purple-500/20 border-purple-400/50 shadow-md text-purple-300'
                    : 'bg-white/5 border-white/10 text-gray-300 hover:bg-white/10'
                ]"
              >
                <div class="w-10 h-10 rounded-xl bg-purple-500/15 text-purple-400 flex items-center justify-center text-xl shrink-0">
                  📊
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-bold text-white truncate">Nilai TP</div>
                  <div class="text-[10px] text-gray-400 truncate">Rekap nilai & raport</div>
                </div>
              </button>

              <!-- 6. Jadwal Pelajaran -->
              <button
                @click="selectView('jadwal')"
                :class="[
                  'flex items-center gap-3 p-3.5 rounded-2xl border text-left transition-all active:scale-95',
                  activeView === 'jadwal'
                    ? 'bg-amber-500/20 border-amber-400/50 shadow-md text-amber-300'
                    : 'bg-white/5 border-white/10 text-gray-300 hover:bg-white/10'
                ]"
              >
                <div class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-400 flex items-center justify-center text-xl shrink-0">
                  📅
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-bold text-white truncate">Jadwal Kelas</div>
                  <div class="text-[10px] text-gray-400 truncate">Jadwal tatap muka</div>
                </div>
              </button>
            </div>

            <!-- Student Motivation Card -->
            <div class="p-3.5 rounded-2xl bg-gradient-to-r from-cyan-950/40 via-blue-950/30 to-slate-900 border border-cyan-500/20 flex items-center gap-3">
              <span class="text-2xl shrink-0">💡</span>
              <p class="text-[11px] text-gray-300 leading-snug">
                Pelajari materi presentasi dan selesaikan ujian CBT untuk meningkatkan kompetensi kejuruan Anda.
              </p>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- ============================================================ -->
    <!-- FIXED ULTRA-MODERN 5-TAB FLOATING BOTTOM NAVIGATION BAR       -->
    <!-- ============================================================ -->
    <nav
      class="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-slate-950/90 backdrop-blur-2xl border-t border-white/10 shadow-[0_-8px_30px_rgba(0,0,0,0.8)] pb-[calc(env(safe-area-inset-bottom,0px)+6px)] pt-1 px-3"
    >
      <!-- Top Neon Highlight Line -->
      <div class="absolute -top-[1px] inset-x-0 h-[1.5px] bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-500 opacity-80"></div>

      <div class="max-w-md mx-auto grid grid-cols-5 items-end justify-between relative h-14">
        
        <!-- 1. TAB: BERANDA -->
        <button
          @click="selectView('dashboard')"
          :class="[
            'flex flex-col items-center justify-center h-full py-1 rounded-xl transition-all duration-200 active:scale-90 relative select-none',
            activeView === 'dashboard' ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-gray-200 font-medium'
          ]"
        >
          <!-- Active Dot Glow -->
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

        <!-- 5. TAB: MENU LAIN (DRAWER TOGGLE) -->
        <button
          @click="toggleMore"
          :class="[
            'flex flex-col items-center justify-center h-full py-1 rounded-xl transition-all duration-200 active:scale-90 relative select-none',
            isMoreActive ? 'text-cyan-300 font-extrabold' : 'text-gray-400 hover:text-gray-200 font-medium'
          ]"
        >
          <span
            v-if="isMoreActive"
            class="absolute top-0 w-8 h-1 bg-gradient-to-r from-cyan-400 to-blue-400 rounded-full shadow-[0_0_10px_rgba(34,211,238,0.9)]"
          ></span>

          <div :class="['transition-transform duration-200', isMoreActive ? 'scale-110 text-cyan-300' : 'text-gray-400']">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </div>
          <span class="text-[10px] tracking-tight mt-0.5 leading-none">Menu</span>
        </button>

      </div>
    </nav>
  </div>
</template>

<style scoped>
.w-13 {
  width: 3.25rem;
}
.h-13 {
  height: 3.25rem;
}
</style>
