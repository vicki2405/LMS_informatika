<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  siswaObj: { type: Object, default: () => ({ nama: 'Siswa', kelas: 'X TKR 1' }) },
  jadwalList: { type: Array, default: () => [] }
});

const hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
const activeHari = ref('Senin');

onMounted(() => {
  const currentDayIndex = new Date().getDay();
  const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const todayName = dayNames[currentDayIndex];
  if (hariList.includes(todayName)) {
    activeHari.value = todayName;
  }
});

const filteredJadwal = computed(() => {
  const list = props.jadwalList || [];
  return list.filter(j => j.hari === activeHari.value);
});

// Color palettes for subject cards to make mobile view visually stunning
const subjectColors = [
  { bg: 'from-cyan-500/20 via-teal-500/15 to-slate-900', border: 'border-cyan-500/30', badge: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40' },
  { bg: 'from-indigo-500/20 via-purple-500/15 to-slate-900', border: 'border-indigo-500/30', badge: 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40' },
  { bg: 'from-emerald-500/20 via-teal-500/15 to-slate-900', border: 'border-emerald-500/30', badge: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' },
  { bg: 'from-amber-500/20 via-orange-500/15 to-slate-900', border: 'border-amber-500/30', badge: 'bg-amber-500/20 text-amber-300 border-amber-500/40' }
];
</script>

<template>
  <div class="space-y-5 animate-in fade-in duration-300 max-w-4xl mx-auto pb-10">
    
    <!-- Top Mobile Header Banner -->
    <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-5 sm:p-6 rounded-3xl border border-white/10 shadow-2xl relative overflow-hidden">
      <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-cyan-500/10 rounded-full blur-3xl"></div>
      
      <div class="flex items-center justify-between gap-3 relative z-10">
        <div>
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-cyan-500/20 text-cyan-300 text-[11px] font-black border border-cyan-500/30 mb-2">
            <span>📅</span> TIMETABLE HARI INI
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
            Jadwal Pelajaran Kelas
          </h1>
          <p class="text-xs text-gray-400 font-medium mt-0.5">
            Rencana belajar resmi <span class="text-cyan-300 font-bold">{{ siswaObj.kelas }}</span>
          </p>
        </div>

        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-400 via-teal-500 to-indigo-600 flex items-center justify-center text-slate-950 font-black text-lg shadow-lg shadow-cyan-500/30 shrink-0">
          📆
        </div>
      </div>
    </div>

    <!-- Mobile-First Horizontal Touch Swipeable Day Selector -->
    <div class="sticky top-16 z-30 bg-slate-950/80 backdrop-blur-xl py-2 -mx-4 px-4 sm:mx-0 sm:px-0">
      <div class="flex items-center gap-2 overflow-x-auto scrollbar-none pb-1">
        <button
          v-for="hari in hariList"
          :key="hari"
          @click="activeHari = hari"
          :class="[
            'px-5 py-2.5 rounded-2xl font-extrabold text-xs transition-all duration-200 shrink-0 active:scale-95 border flex items-center gap-2',
            activeHari === hari
              ? 'bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 border-cyan-400 shadow-lg shadow-cyan-500/30 scale-105'
              : 'bg-slate-900/80 text-gray-400 border-white/10 hover:text-white hover:bg-white/5'
          ]"
        >
          <span>{{ hari === activeHari ? '📍' : '🗓️' }}</span>
          <span>{{ hari }}</span>
        </button>
      </div>
    </div>

    <!-- Timeline List View for Mobile & Tablet -->
    <div class="space-y-3.5">
      <div
        v-for="(j, idx) in filteredJadwal"
        :key="j.id"
        :class="[
          'bg-gradient-to-r p-4 sm:p-5 rounded-3xl border shadow-xl transition-all duration-200 hover:scale-[1.01]',
          subjectColors[idx % subjectColors.length].bg,
          subjectColors[idx % subjectColors.length].border
        ]"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          
          <!-- Left Info -->
          <div class="space-y-1.5 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-2.5 py-0.5 rounded-lg bg-white/10 text-white font-mono font-extrabold text-[10px] uppercase">
                {{ j.jamKe || j.jam_ke }}
              </span>
              <span class="text-cyan-400 text-xs font-mono font-extrabold">
                ⏰ {{ j.waktu }}
              </span>
            </div>

            <h3 class="text-base sm:text-lg font-black text-white leading-snug">
              {{ j.mapel }}
            </h3>

            <div class="flex items-center gap-3 text-xs font-semibold text-gray-300 pt-0.5">
              <span class="flex items-center gap-1">
                👨‍🏫 <span class="text-gray-200">{{ j.guru }}</span>
              </span>
            </div>
          </div>

          <!-- Right Room Badge -->
          <div class="shrink-0 self-start sm:self-center">
            <span :class="['px-3.5 py-1.5 rounded-2xl text-xs font-black border flex items-center gap-1.5 shadow-md', subjectColors[idx % subjectColors.length].badge]">
              <span>📍</span>
              <span>{{ j.ruangan }}</span>
            </span>
          </div>

        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredJadwal.length === 0" class="bg-slate-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-8 text-center space-y-3">
        <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center text-3xl mx-auto text-cyan-400">
          ☕
        </div>
        <h3 class="text-base font-extrabold text-white">Tidak Ada Jadwal Pelajaran {{ activeHari }}</h3>
        <p class="text-xs text-gray-400 max-w-sm mx-auto">
          Tidak ada jam tatap muka untuk kelas {{ siswaObj.kelas }} pada hari {{ activeHari }}. Gunakan waktu untuk belajar mandiri atau diskusi TP!
        </p>
      </div>
    </div>
  </div>
</template>
