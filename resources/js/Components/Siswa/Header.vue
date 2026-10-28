<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import ToastNotification from '@/Components/Common/ToastNotification.vue';

const props = defineProps({
  user: Object,
  userInitials: String,
  unreadNotifCount: Number,
  notifications: Array,
  kelasSiswa: {
    type: String,
    default: 'X RPL 1'
  },
  activeSiswa: {
    type: Object,
    default: () => null
  }
});

const emit = defineEmits(['toggle-sidebar', 'logout', 'change-view', 'mark-read', 'switch-siswa']);

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

    <nav ref="headerRef" class="bg-slate-900/90 backdrop-blur-2xl border-b border-white/10 sticky top-0 z-50 shadow-2xl shadow-cyan-950/20">
    <!-- Top Accent Bar -->
    <div class="h-[3px] w-full bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-500"></div>

    <div class="w-full px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 gap-4">
        
        <!-- Left: Brand Logo & Desktop Sidebar Collapse Toggle -->
        <div class="flex items-center gap-3.5 shrink-0">
          <button @click="$emit('toggle-sidebar')" title="Sembunyikan / Tampilkan Sidebar" class="hidden lg:flex p-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-white transition-all">
            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>

          <div class="flex items-center gap-3">
            <!-- Glowing Badge Logo -->
            <div class="w-10 h-10 bg-gradient-to-br from-cyan-400 via-blue-500 to-indigo-600 rounded-xl flex items-center justify-center font-black text-white text-base shadow-lg shadow-cyan-500/30 ring-2 ring-cyan-400/30 font-mono tracking-tighter shrink-0">
              SIS
            </div>
            <div class="flex flex-col min-w-0 max-w-[170px] sm:max-w-none">
              <span class="font-black text-white text-xs sm:text-base tracking-tight leading-tight flex items-center gap-1.5 truncate">
                <span class="sm:hidden text-cyan-300">SMK MUHA 2 GENTENG</span>
                <span class="hidden sm:inline">SMKS MUHAMMADIYAH 2<span class="text-cyan-400"> GENTENG</span></span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse shrink-0"></span>
              </span>
              <span class="text-[9px] sm:text-[10px] text-cyan-400 font-bold tracking-wider uppercase truncate">PORTAL BELAJAR SISWA</span>
            </div>
          </div>

          <span class="hidden md:inline-flex items-center gap-2 bg-gradient-to-r from-cyan-500/15 to-blue-500/15 text-cyan-300 text-xs font-bold px-3.5 py-1 rounded-full border border-cyan-500/30 shadow-inner ml-1">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            KELAS {{ kelasSiswa }}
          </span>

          <!-- Active Student Badge -->
          <div
            v-if="activeSiswa"
            class="flex items-center gap-2 bg-gradient-to-r from-cyan-500/20 to-blue-500/20 border border-cyan-400/40 px-2.5 sm:px-3 py-1 rounded-xl shadow-inner ml-1"
          >
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
            <div class="flex flex-col text-left">
              <span class="text-xs font-black text-white truncate max-w-[120px] sm:max-w-[180px]">{{ activeSiswa.nama }}</span>
              <span class="text-[9px] font-mono text-cyan-300">NIS: {{ activeSiswa.nis || ('100' + activeSiswa.id) }}</span>
            </div>
            <button
              @click="$emit('switch-siswa')"
              class="ml-1 px-2 py-0.5 rounded-lg bg-cyan-500/20 hover:bg-cyan-500/40 text-cyan-200 border border-cyan-400/40 text-[10px] font-bold transition flex items-center gap-1 shrink-0"
              title="Ganti Siswa"
            >
              <span>Ganti</span>
              <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </button>
          </div>
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
              placeholder="Cari materi, tugas, atau nilai TP..."
              class="w-full pl-10 pr-9 py-2 bg-white/5 border border-white/10 rounded-xl text-xs text-white placeholder-gray-400 focus:border-cyan-400 focus:bg-white/10 focus:ring-2 focus:ring-cyan-400/20 outline-none transition-all"
            />
            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-[10px] font-mono text-gray-500">⌘K</span>
          </div>
        </div>

        <!-- Right: Notifications & Profile Dropdown -->
        <div class="flex items-center gap-3 shrink-0">
          
          <!-- Notification Bell -->
          <div class="relative">
            <button @click="notifMenuOpen = !notifMenuOpen" class="relative p-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-white transition-all">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
              <span v-if="unreadNotifCount > 0" class="absolute -top-1 -right-1 w-5 h-5 bg-gradient-to-r from-cyan-500 to-blue-500 text-white font-bold text-[10px] rounded-full flex items-center justify-center border-2 border-slate-900 shadow-md animate-bounce">
                {{ unreadNotifCount }}
              </span>
            </button>

            <!-- Notifications Dropdown -->
            <div v-if="notifMenuOpen" class="absolute right-0 mt-3 w-[calc(100vw-2rem)] max-w-sm sm:w-96 bg-slate-800/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/10 overflow-hidden z-50">
              <div class="p-4 border-b border-white/10 flex items-center justify-between bg-slate-900/50">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-white text-sm">Notifikasi Pembelajaran</span>
                  <span class="bg-cyan-500/20 text-cyan-300 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ unreadNotifCount }} Baru</span>
                </div>
                <button @click="markAllNotifRead" class="text-[11px] text-cyan-400 hover:text-cyan-300 font-medium transition-colors">Tandai Dibaca</button>
              </div>

              <div class="divide-y divide-white/5 max-h-72 overflow-y-auto">
                <div v-for="n in notifications" :key="n.id" :class="['p-4 transition-colors hover:bg-white/5 flex gap-3', !n.read ? 'bg-cyan-500/5' : '']">
                  <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-sm shrink-0">🔔</div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-0.5">
                      <span class="font-semibold text-xs text-white truncate">{{ n.title }}</span>
                      <span class="text-[10px] text-gray-400 shrink-0">{{ n.time }}</span>
                    </div>
                    <p class="text-xs text-gray-300 leading-relaxed line-clamp-2">{{ n.text }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Profile Menu -->
          <div class="relative">
            <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-3 p-1.5 pl-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 transition-all">
              <div class="text-right hidden sm:block">
                <div class="text-xs font-extrabold text-white leading-tight">{{ user?.name || 'Siswa' }}</div>
                <div class="text-[10px] font-bold text-cyan-400 tracking-wide">SISWA {{ kelasSiswa }}</div>
              </div>
              
              <!-- Avatar with ring -->
              <div class="p-[2px] bg-gradient-to-br from-cyan-400 via-blue-400 to-indigo-500 rounded-xl shadow-lg">
                <div class="w-8 h-8 bg-slate-900 rounded-[10px] flex items-center justify-center text-cyan-300 font-bold text-xs">
                  {{ userInitials || 'SW' }}
                </div>
              </div>
              <svg class="w-3.5 h-3.5 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <!-- Profile Dropdown Menu (Murni Info Akun & Sesi) -->
            <div v-if="userMenuOpen" class="absolute right-0 mt-3 w-72 max-w-[calc(100vw-2rem)] bg-slate-800/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/10 p-3 z-50">
              <div class="px-2 py-2.5 border-b border-white/10 flex items-center gap-3">
                <div class="w-11 h-11 bg-gradient-to-br from-cyan-400 to-blue-600 rounded-xl flex items-center justify-center text-white font-bold text-sm shadow-md shrink-0">
                  {{ userInitials || 'SW' }}
                </div>
                <div class="min-w-0">
                  <div class="text-sm font-bold text-white truncate">{{ user?.name || 'Siswa' }}</div>
                  <div class="text-xs text-gray-400 truncate">{{ user?.email || '-' }}</div>
                  <span class="inline-block mt-1 bg-cyan-500/20 text-cyan-300 text-[10px] font-semibold px-2 py-0.5 rounded-md border border-cyan-500/30">
                    Siswa {{ kelasSiswa }}
                  </span>
                </div>
              </div>

              <!-- Ringkasan Info Akun -->
              <div class="p-2.5 text-xs text-gray-300 space-y-1.5 bg-white/5 rounded-xl my-2.5 border border-white/5">
                <div class="flex items-center justify-between text-[11px]">
                  <span class="text-gray-400">Ruang Kelas</span>
                  <span class="font-bold text-cyan-300 font-mono">{{ kelasSiswa }}</span>
                </div>
                <div class="flex items-center justify-between text-[11px]">
                  <span class="text-gray-400">Status Sesi</span>
                  <span class="font-bold text-emerald-400 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Aktif Terhubung
                  </span>
                </div>
              </div>

              <!-- Switch Student Identity Button -->
              <div v-if="activeSiswa" class="pb-2">
                <button
                  @click="userMenuOpen = false; $emit('switch-siswa')"
                  class="w-full text-center px-4 py-2 min-h-[40px] rounded-xl text-xs font-bold text-cyan-300 hover:text-white bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 active:scale-95 transition-all flex items-center justify-center gap-2"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                  Ganti Identitas Siswa
                </button>
              </div>

              <!-- Tombol Keluar Sesi -->
              <div class="pt-1">
                <button
                  @click="handleLogout"
                  class="w-full text-center px-4 py-2.5 min-h-[44px] rounded-xl text-xs font-bold text-rose-300 hover:text-white bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 active:scale-95 transition-all flex items-center justify-center gap-2"
                >
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
