<script setup>
defineProps({
  activeView: String,
  sidebarOpen: Boolean,
  kelasSiswa: {
    type: String,
    default: 'X RPL 1'
  }
});

const emit = defineEmits(['update:activeView', 'update:sidebarOpen']);

function selectView(view) {
  emit('update:activeView', view);
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    emit('update:sidebarOpen', false);
  }
}
</script>

<template>
  <div>
    <!-- Desktop Navigation Sidebar (Mobile menggunakan Bottom Navigation Bar) -->
    <aside
      :class="[
        'hidden lg:block sticky top-16 left-0 h-[calc(100vh-4rem)] w-72 bg-slate-900/95 backdrop-blur-2xl border-r border-white/10 z-40 transition-all duration-300 overflow-y-auto shrink-0 shadow-2xl',
        sidebarOpen ? 'translate-x-0 ml-0' : '-translate-x-full -ml-72'
      ]"
    >
      <div class="p-5 flex flex-col justify-between min-h-full">
        <div>
          <!-- Header Inside Sidebar -->
          <div class="mb-6 flex items-center justify-between pb-4 border-b border-white/10">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center text-lg font-black text-white shadow-lg shadow-cyan-500/25">
                🚀
              </div>
              <div class="min-w-0">
                <h2 class="text-sm font-black text-white truncate">Ruang Belajar</h2>
                <p class="text-[11px] text-cyan-400 font-bold truncate">Siswa Kelas {{ kelasSiswa }}</p>
              </div>
            </div>
            <button
              @click="$emit('update:sidebarOpen', false)"
              title="Tutup Menu"
              class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-colors"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Main Navigation Links -->
          <nav class="space-y-1.5">
            <div class="text-[10px] font-mono font-bold text-gray-400 uppercase tracking-wider px-3 mb-2">
              Menu Utama
            </div>

            <!-- 1. Dashboard Utama -->
            <button
              @click="selectView('dashboard')"
              :class="[
                'w-full flex items-center gap-3 px-3.5 py-3 rounded-2xl text-xs font-bold transition-all duration-200 active:scale-95 text-left',
                activeView === 'dashboard'
                  ? 'bg-gradient-to-r from-cyan-500/20 to-blue-500/20 text-cyan-300 border border-cyan-500/40 shadow-sm font-extrabold'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div class="w-8 h-8 rounded-xl flex items-center justify-center text-base" :class="activeView === 'dashboard' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-gray-400'">
                🏠
              </div>
              <span>Beranda Kelas</span>
            </button>

            <!-- 2. Materi Pembelajaran -->
            <button
              @click="selectView('materi')"
              :class="[
                'w-full flex items-center gap-3 px-3.5 py-3 rounded-2xl text-xs font-bold transition-all duration-200 active:scale-95 text-left',
                activeView === 'materi'
                  ? 'bg-gradient-to-r from-cyan-500/20 to-indigo-500/20 text-cyan-300 border border-cyan-500/40 shadow-sm font-extrabold'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div class="w-8 h-8 rounded-xl flex items-center justify-center text-base" :class="activeView === 'materi' ? 'bg-indigo-500/20 text-indigo-300' : 'bg-white/5 text-indigo-400'">
                📚
              </div>
              <span>Materi Pembelajaran</span>
            </button>

            <!-- 3. CBT Ujian Online -->
            <button
              @click="selectView('cbt')"
              :class="[
                'w-full flex items-center justify-between px-3.5 py-3 rounded-2xl text-xs font-bold transition-all duration-200 active:scale-95 text-left',
                activeView === 'cbt'
                  ? 'bg-gradient-to-r from-cyan-500/25 to-teal-500/25 text-cyan-300 border border-cyan-400/50 shadow-sm font-extrabold'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center text-base" :class="activeView === 'cbt' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-cyan-400'">
                  🎯
                </div>
                <span>CBT (Ujian Online)</span>
              </div>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                Aktif
              </span>
            </button>

            <!-- 4. Tugas Siswa -->
            <button
              @click="selectView('tugas')"
              :class="[
                'w-full flex items-center gap-3 px-3.5 py-3 rounded-2xl text-xs font-bold transition-all duration-200 active:scale-95 text-left',
                activeView === 'tugas'
                  ? 'bg-gradient-to-r from-emerald-500/20 to-teal-500/20 text-emerald-300 border border-emerald-500/40 shadow-sm font-extrabold'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div class="w-8 h-8 rounded-xl flex items-center justify-center text-base" :class="activeView === 'tugas' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-white/5 text-emerald-400'">
                📝
              </div>
              <span>Tugas & Portofolio</span>
            </button>

            <div class="text-[10px] font-mono font-bold text-gray-400 uppercase tracking-wider px-3 pt-4 mb-2">
              Akademik & Penilaian
            </div>

            <!-- 5. Nilai Per TP -->
            <button
              @click="selectView('nilai')"
              :class="[
                'w-full flex items-center gap-3 px-3.5 py-3 rounded-2xl text-xs font-bold transition-all duration-200 active:scale-95 text-left',
                activeView === 'nilai'
                  ? 'bg-gradient-to-r from-purple-500/20 to-pink-500/20 text-purple-300 border border-purple-500/40 shadow-sm font-extrabold'
                  : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent'
              ]"
            >
              <div class="w-8 h-8 rounded-xl flex items-center justify-center text-base" :class="activeView === 'nilai' ? 'bg-purple-500/20 text-purple-300' : 'bg-white/5 text-purple-400'">
                📊
              </div>
              <span>Nilai TP & Raport</span>
            </button>
          </nav>
        </div>

        <!-- Student Motivation Card -->
        <div class="mt-8 p-4 bg-cyan-500/10 rounded-2xl border border-cyan-500/20 shadow-sm">
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-sm">💡</span>
            <h4 class="text-xs font-bold text-cyan-300 uppercase tracking-wider">Tips Belajar</h4>
          </div>
          <p class="text-[11px] text-gray-300 leading-relaxed">
            Selesaikan modul materi presentasi dan ujian CBT tepat waktu agar capaian kompetensi tercatat tuntas di buku guru.
          </p>
        </div>
      </div>
    </aside>
  </div>
</template>
