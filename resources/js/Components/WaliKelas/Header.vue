<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import ToastNotification from '@/Components/Common/ToastNotification.vue';

const props = defineProps({
  user: Object,
  userInitials: String,
  unreadNotifCount: Number,
  notifications: Array,
  kelasBinaan: {
    type: String,
    default: 'X RPL 1'
  }
});

const emit = defineEmits(['toggle-sidebar', 'logout', 'change-view', 'mark-read']);

const searchQuery = ref('');
const userMenuOpen = ref(false);
const notifMenuOpen = ref(false);
const headerRef = ref(null);

const hasMultipleTeacherRoles = computed(() => {
  const roles = props.user?.roles || [];
  const roleNames = Array.isArray(roles)
    ? roles.map(r => typeof r === 'string' ? r : (r.name || ''))
    : [];
  return roleNames.includes('guru') && roleNames.includes('wali_kelas');
});

function handleOutsideClick(event) {
  if (headerRef.value && !headerRef.value.contains(event.target)) {
    userMenuOpen.value = false;
    notifMenuOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick);
});

function markAllNotifRead() {
  emit('mark-read');
}

function handleLogout() {
  emit('logout');
}

function navigateTo(view) {
  userMenuOpen.value = false;
  notifMenuOpen.value = false;
  emit('change-view', view);
}
</script>

<template>
  <div>
    <!-- Global CRUD Feedback Toast Notification -->
    <ToastNotification />

    <nav ref="headerRef" class="bg-slate-900/90 backdrop-blur-2xl border-b border-white/10 sticky top-0 z-50 shadow-2xl shadow-indigo-950/20">
    <!-- Top Accent Bar Strip -->
    <div class="h-1 w-full bg-gradient-to-r from-indigo-500 via-purple-500 to-cyan-400"></div>

    <div class="w-full px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 gap-4">
        
        <!-- Left: Brand Logo & Desktop/Mobile Sidebar Toggle -->
        <div class="flex items-center gap-3.5 shrink-0">
          <button @click="$emit('toggle-sidebar')" title="Toggle Sidebar Menu" class="p-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-white transition-all active:scale-95">
            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>

          <div class="flex items-center gap-3">
            <!-- Glowing Badge Logo -->
            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 via-purple-600 to-cyan-400 rounded-xl flex items-center justify-center font-black text-white text-base shadow-lg shadow-indigo-500/30 ring-2 ring-indigo-400/30 font-mono tracking-tighter shrink-0">
              WK
            </div>
            <div class="flex flex-col min-w-0 max-w-[170px] sm:max-w-none">
              <span class="font-black text-white text-xs sm:text-base tracking-tight leading-tight flex items-center gap-1.5 truncate">
                <span class="sm:hidden text-indigo-300">SMK MUHA 2 GENTENG</span>
                <span class="hidden sm:inline">SMKS MUHAMMADIYAH 2<span class="text-indigo-400"> GENTENG</span></span>
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse shrink-0"></span>
              </span>
              <span class="text-[9px] sm:text-[10px] text-indigo-400 font-bold tracking-wider uppercase truncate">PORTAL WALI KELAS</span>
            </div>
          </div>

          <span class="hidden md:inline-flex items-center gap-2 bg-indigo-500/20 text-indigo-300 text-xs font-black px-3.5 py-1 rounded-full border border-indigo-500/30 shadow-inner ml-1">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            WALI KELAS {{ kelasBinaan }}
          </span>

          <!-- Role Switcher Button for Dual-Role Teachers (Only rendered when user has BOTH Guru and Wali Kelas roles) -->
          <a
            v-if="hasMultipleTeacherRoles"
            href="/guru/dashboard"
            class="inline-flex items-center gap-1 sm:gap-2 px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/35 border border-cyan-500/40 text-cyan-300 hover:text-white font-extrabold text-[10px] sm:text-xs transition-all duration-200 shadow-md shadow-cyan-950/40 hover:scale-105 active:scale-95 ml-1.5"
            title="Beralih ke Portal Guru Mapel tanpa Logout"
          >
            <span class="text-xs sm:text-sm">🔄</span>
            <span class="hidden sm:inline">Switch Role:</span>
            <span>Guru Mapel ⚡</span>
          </a>
        </div>

        <!-- Center: Search Input Bar -->
        <div class="hidden md:flex items-center justify-center flex-1 max-w-md mx-4">
          <div class="relative w-full">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari siswa, ketuntasan tugas, atau rapor..."
              class="w-full pl-10 pr-9 py-2 bg-white/5 border border-white/10 rounded-xl text-xs text-white placeholder-gray-400 focus:border-indigo-400 focus:bg-white/10 focus:ring-2 focus:ring-indigo-400/20 outline-none transition-all"
            />
            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-[10px] font-mono text-gray-500 font-bold">⌘K</span>
          </div>
        </div>

        <!-- Right: Notifications & Profile Dropdown -->
        <div class="flex items-center gap-3 shrink-0">
          
          <!-- Notification Bell -->
          <div class="relative">
            <button @click="notifMenuOpen = !notifMenuOpen" class="relative p-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-white transition-all active:scale-95">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
              <span v-if="unreadNotifCount > 0" class="absolute -top-1 -right-1 w-5 h-5 bg-gradient-to-r from-indigo-500 to-cyan-500 text-white font-black text-[10px] rounded-full flex items-center justify-center border-2 border-slate-900 shadow-md animate-bounce">
                {{ unreadNotifCount }}
              </span>
            </button>

            <!-- Notifications Dropdown -->
            <div v-if="notifMenuOpen" class="absolute right-0 mt-3 w-[calc(100vw-2rem)] max-w-sm sm:w-96 bg-slate-800/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/10 overflow-hidden z-50 animate-in fade-in zoom-in-95">
              <div class="p-4 border-b border-white/10 flex items-center justify-between bg-slate-900/50">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-white text-sm">Notifikasi Pembina Kelas</span>
                  <span class="bg-indigo-500/20 text-indigo-300 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-indigo-500/30">{{ unreadNotifCount }} Baru</span>
                </div>
                <button @click="markAllNotifRead" class="text-[11px] text-indigo-400 hover:text-indigo-300 font-bold transition-colors">Tandai Dibaca</button>
              </div>

              <div class="divide-y divide-white/5 max-h-72 overflow-y-auto">
                <div v-for="n in notifications" :key="n.id" :class="['p-4 transition-colors hover:bg-white/5 flex gap-3', !n.read ? 'bg-indigo-500/10' : '']">
                  <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-sm shrink-0 border border-indigo-500/30">🔔</div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-0.5">
                      <span class="font-bold text-xs text-white truncate">{{ n.title }}</span>
                      <span class="text-[10px] text-gray-400 shrink-0 font-mono">{{ n.time }}</span>
                    </div>
                    <p class="text-xs text-gray-300 leading-relaxed line-clamp-2 font-medium">{{ n.text }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Profile Menu -->
          <div class="relative">
            <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-3 p-1.5 pl-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 transition-all active:scale-95">
              <div class="text-right hidden sm:block">
                <div class="text-xs font-black text-white leading-tight">{{ user?.name || 'Siti Aminah, M.Pd.' }}</div>
                <div class="text-[10px] font-extrabold text-indigo-400 tracking-wide">WALI KELAS {{ kelasBinaan }}</div>
              </div>
              
              <div class="p-[2px] bg-gradient-to-br from-indigo-400 via-purple-500 to-cyan-400 rounded-xl shadow-lg">
                <div class="w-8 h-8 bg-slate-900 rounded-[10px] flex items-center justify-center text-indigo-300 font-extrabold text-xs">
                  {{ userInitials || 'SA' }}
                </div>
              </div>
              <svg class="w-3.5 h-3.5 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <!-- Profile Dropdown Menu -->
            <div v-if="userMenuOpen" class="absolute right-0 mt-3 w-[calc(100vw-2rem)] max-w-xs sm:w-72 bg-slate-800/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/10 py-2 z-50 animate-in fade-in zoom-in-95">
              <div class="px-4 py-3 border-b border-white/10 flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-md shrink-0">
                  {{ userInitials || 'SA' }}
                </div>
                <div class="min-w-0">
                  <div class="text-sm font-extrabold text-white truncate">{{ user?.name || 'Siti Aminah, M.Pd.' }}</div>
                  <div class="text-xs text-gray-400 truncate font-medium">{{ user?.email || 'siti.walikelas@sekolah.sch.id' }}</div>
                  <span class="inline-block mt-1 bg-indigo-500/20 text-indigo-300 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-indigo-500/30">
                    Wali Kelas {{ kelasBinaan }}
                  </span>
                </div>
              </div>

              <div class="py-1 font-semibold">
                <button @click="navigateTo('dashboard')" class="w-full text-left px-4 py-2.5 text-xs text-gray-300 hover:text-white hover:bg-white/5 transition-colors flex items-center gap-2.5">
                  <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                  Dashboard Ringkasan
                </button>
                <button @click="navigateTo('tugas')" class="w-full text-left px-4 py-2.5 text-xs text-gray-300 hover:text-white hover:bg-white/5 transition-colors flex items-center gap-2.5">
                  <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                  Ketuntasan Tugas Siswa
                </button>
                <button @click="navigateTo('nilai')" class="w-full text-left px-4 py-2.5 text-xs text-gray-300 hover:text-white hover:bg-white/5 transition-colors flex items-center gap-2.5">
                  <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  Nilai Siswa Per Semester
                </button>
                
                <!-- Beralih Portal Link inside Profile Dropdown Menu for Mobile -->
                <a
                  v-if="hasMultipleTeacherRoles"
                  href="/guru/dashboard"
                  class="w-full text-left px-4 py-2.5 text-xs text-cyan-400 hover:text-white hover:bg-cyan-500/10 transition-colors flex items-center gap-2.5 border-t border-white/5 mt-1 pt-2.5 font-bold"
                >
                  <span class="text-sm">🔄</span>
                  Beralih ke Portal Guru Mapel
                </a>
              </div>

              <div class="border-t border-white/10 pt-1 mt-1">
                <button @click="handleLogout" class="w-full text-left px-4 py-2.5 text-xs font-bold text-red-400 hover:bg-red-500/10 transition-colors flex items-center gap-2.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                  Keluar Sesi
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </nav>
  </div>
</template>
