<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import ToastNotification from '@/Components/Common/ToastNotification.vue';

const props = defineProps({
  user: Object,
  userInitials: String,
  unreadNotifCount: Number,
  notifications: Array
});

const emit = defineEmits(['toggle-sidebar', 'logout', 'change-view', 'mark-read']);

const searchQuery = ref('');
const userMenuOpen = ref(false);
const notifMenuOpen = ref(false);
const headerRef = ref(null);

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

    <nav ref="headerRef" class="bg-slate-900/95 backdrop-blur-2xl border-b border-white/10 sticky top-0 z-50 shadow-2xl shadow-purple-950/20">
    <!-- Radiant Top Gradient Border Strip -->
    <div class="h-[3px] w-full bg-gradient-to-r from-purple-500 via-pink-500 to-cyan-400 animate-pulse"></div>

    <div class="w-full px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 gap-4">
        
        <!-- Left: Sidebar Toggle Button & Glowing School Brand Logo -->
        <div class="flex items-center gap-3.5 shrink-0">
          <button
            @click="$emit('toggle-sidebar')"
            title="Toggle Sidebar"
            class="p-2.5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-white transition-all duration-200 active:scale-95 group focus:outline-none focus:ring-2 focus:ring-purple-500/40"
          >
            <svg class="w-5 h-5 text-purple-400 group-hover:rotate-180 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>

          <div class="flex items-center gap-3 cursor-pointer" @click="navigateTo('dashboard')">
            <!-- Glowing Badge Logo -->
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 via-indigo-500 to-cyan-400 rounded-2xl flex items-center justify-center font-black text-white text-sm shadow-lg shadow-purple-500/40 ring-2 ring-purple-400/30 font-mono tracking-tighter shrink-0 hover:scale-105 transition-transform duration-200">
              ADM
            </div>
            <div class="flex flex-col min-w-0 max-w-[170px] sm:max-w-none">
              <span class="font-black text-white text-xs sm:text-base tracking-tight leading-tight flex items-center gap-1.5 truncate">
                <span class="sm:hidden text-purple-300">SMK MUHA 2 GENTENG</span>
                <span class="hidden sm:inline">SMKS MUHAMMADIYAH 2<span class="text-purple-400"> GENTENG</span></span>
                <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-emerald-400 animate-ping shrink-0"></span>
              </span>
              <span class="text-[9px] sm:text-[10px] text-purple-400 font-bold tracking-wider uppercase truncate">PORTAL ADMIN SEKOLAH</span>
            </div>
          </div>

          <span class="hidden md:inline-flex items-center gap-2 bg-gradient-to-r from-purple-500/15 via-indigo-500/15 to-purple-500/15 text-purple-300 text-xs font-extrabold px-3.5 py-1 rounded-full border border-purple-500/30 shadow-inner ml-2">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            PANEL ADMINISTRATOR • TA {{ $page.props.activeTahunAjaran?.kode || '2025/2026' }}
          </span>
        </div>

        <!-- Center: Search Input Bar with Shortcut Indicator -->
        <div class="hidden md:flex items-center justify-center flex-1 max-w-md mx-4">
          <div class="relative w-full">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari data guru, wali kelas, atau akun user..."
              class="w-full pl-10 pr-9 py-2 bg-white/5 border border-white/10 rounded-2xl text-xs text-white placeholder-gray-400 focus:border-purple-400 focus:bg-white/10 focus:ring-2 focus:ring-purple-400/20 outline-none transition-all duration-200"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs text-gray-400 hover:text-white"
            >
              ✕
            </button>
            <span v-else class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[10px] font-mono font-bold text-gray-500 pointer-events-none">⌘K</span>
          </div>
        </div>

        <!-- Right: System Notifications & Admin Profile Menu -->
        <div class="flex items-center gap-3 shrink-0">
          
          <!-- Notifications Bell -->
          <div class="relative">
            <button
              @click="notifMenuOpen = !notifMenuOpen; userMenuOpen = false"
              class="relative p-2.5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-white transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-purple-500/40"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
              </svg>
              <span
                v-if="unreadNotifCount > 0"
                class="absolute -top-1 -right-1 w-5 h-5 bg-gradient-to-r from-purple-500 to-pink-500 text-white font-bold text-[10px] rounded-full flex items-center justify-center border-2 border-slate-900 shadow-md animate-bounce"
              >
                {{ unreadNotifCount }}
              </span>
            </button>

            <!-- Notifications Dropdown Menu -->
            <div
              v-if="notifMenuOpen"
              class="absolute right-0 mt-3 w-[calc(100vw-2rem)] max-w-sm sm:w-96 bg-slate-900/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white/10 overflow-hidden z-50 transform transition-all duration-200 animate-in fade-in slide-in-from-top-2"
            >
              <div class="p-4 border-b border-white/10 flex items-center justify-between bg-slate-950/60">
                <div class="flex items-center gap-2">
                  <span class="font-extrabold text-white text-sm">Notifikasi Sistem</span>
                  <span class="bg-purple-500/20 text-purple-300 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-purple-500/30">
                    {{ unreadNotifCount }} Baru
                  </span>
                </div>
                <button
                  @click="markAllNotifRead"
                  class="text-[11px] text-purple-400 hover:text-purple-300 font-bold transition-colors"
                >
                  Tandai Dibaca
                </button>
              </div>

              <div class="divide-y divide-white/5 max-h-80 overflow-y-auto">
                <div
                  v-for="n in notifications"
                  :key="n.id"
                  :class="['p-4 transition-colors hover:bg-white/5 flex gap-3', !n.read ? 'bg-purple-500/10' : '']"
                >
                  <div class="w-9 h-9 rounded-xl bg-purple-500/20 text-purple-300 flex items-center justify-center text-base shrink-0 border border-purple-500/30 shadow-md">
                    🔔
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                      <span class="font-extrabold text-xs text-white truncate">{{ n.title }}</span>
                      <span class="text-[10px] text-gray-400 shrink-0 font-mono">{{ n.time }}</span>
                    </div>
                    <p class="text-xs text-gray-300 leading-relaxed line-clamp-2">{{ n.text }}</p>
                  </div>
                </div>
                <div v-if="!notifications || notifications.length === 0" class="p-6 text-center text-gray-400 text-xs">
                  Tidak ada notifikasi baru.
                </div>
              </div>
            </div>
          </div>

          <!-- Admin Profile Dropdown Menu -->
          <div class="relative">
            <button
              @click="userMenuOpen = !userMenuOpen; notifMenuOpen = false"
              class="flex items-center gap-3 p-1.5 pl-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-purple-500/40"
            >
              <div class="text-right hidden sm:block">
                <div class="text-xs font-black text-white leading-tight">{{ user?.name || 'Super Admin' }}</div>
                <div class="text-[10px] font-bold text-purple-400 tracking-wider">SYSTEM ADMINISTRATOR</div>
              </div>
              
              <!-- Avatar with Gradient Ring -->
              <div class="p-[2px] bg-gradient-to-br from-purple-400 via-pink-400 to-cyan-400 rounded-2xl shadow-lg hover:shadow-purple-500/30 transition-all duration-200">
                <div class="w-8 h-8 bg-slate-900 rounded-[14px] flex items-center justify-center text-purple-300 font-extrabold text-xs">
                  {{ userInitials || 'AD' }}
                </div>
              </div>
              <svg class="w-3.5 h-3.5 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <!-- Dropdown Content -->
            <div
              v-if="userMenuOpen"
              class="absolute right-0 mt-3 w-[calc(100vw-2rem)] max-w-xs sm:w-72 bg-slate-900/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white/10 py-2 z-50 transform transition-all duration-200 animate-in fade-in slide-in-from-top-2"
            >
              <div class="px-4 py-3 border-b border-white/10 flex items-center gap-3 bg-slate-950/40">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 via-indigo-500 to-cyan-500 rounded-2xl flex items-center justify-center text-white font-extrabold text-sm shadow-md shrink-0">
                  {{ userInitials || 'AD' }}
                </div>
                <div class="min-w-0">
                  <div class="text-sm font-extrabold text-white truncate">{{ user?.name || 'Super Administrator' }}</div>
                  <div class="text-xs text-gray-400 truncate font-mono">{{ user?.email || 'admin@sekolah.sch.id' }}</div>
                  <span class="inline-block mt-1 bg-purple-500/20 text-purple-300 text-[10px] font-extrabold px-2 py-0.5 rounded-full border border-purple-500/30">
                    Akses Penuh — Root Admin
                  </span>
                </div>
              </div>

              <div class="py-1">
                <button
                  @click="navigateTo('dashboard')"
                  class="w-full text-left px-4 py-2.5 text-xs font-semibold text-gray-300 hover:text-white hover:bg-white/5 transition-colors flex items-center gap-2.5"
                >
                  <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 01-1-1v-4a1 1 0 01-1-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                  </svg>
                  Dashboard Overview
                </button>

                <button
                  @click="navigateTo('guru')"
                  class="w-full text-left px-4 py-2.5 text-xs font-semibold text-gray-300 hover:text-white hover:bg-white/5 transition-colors flex items-center gap-2.5"
                >
                  <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                  </svg>
                  Input & Data Guru
                </button>

                <button
                  @click="navigateTo('users')"
                  class="w-full text-left px-4 py-2.5 text-xs font-semibold text-gray-300 hover:text-white hover:bg-white/5 transition-colors flex items-center gap-2.5"
                >
                  <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                  </svg>
                  Kelola Username & Password
                </button>
              </div>

              <div class="border-t border-white/10 pt-1 mt-1">
                <button
                  @click="handleLogout"
                  class="w-full text-left px-4 py-2.5 text-xs font-bold text-red-400 hover:bg-red-500/10 transition-colors flex items-center gap-2.5"
                >
                  <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                  </svg>
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
