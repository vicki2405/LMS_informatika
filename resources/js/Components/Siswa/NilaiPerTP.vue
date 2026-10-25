<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  tpList: { type: Array, default: () => [] },
  nilaiList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  kelasSiswa: { type: String, default: 'Kelas' },
  nilaiTPList: { type: Array, default: () => [] },
});

// 1. Academic Period Filter (Semester Ganjil / Genap)
function getInitialSemester() {
  const currentMonth = new Date().getMonth() + 1;
  return currentMonth >= 1 && currentMonth <= 6 ? 'Genap' : 'Ganjil';
}

const selectedSemester = ref(getInitialSemester());
const selectedMapelFilter = ref('Semua');
const searchQuery = ref('');

// List of Unique Subjects available from Master TP or fallback
const mapelList = computed(() => {
  const set = new Set();
  (props.tpList || []).forEach(t => {
    if (t.mapel && t.mapel !== 'Umum') set.add(t.mapel);
  });
  if (set.size === 0) {
    (props.nilaiTPList || []).forEach(t => {
      if (t.mapel) set.add(t.mapel);
    });
  }
  return [...set].sort();
});

// Auto select first mapel if 'Semua' and mapels exist
const effectiveMapelFilter = computed(() => {
  if (selectedMapelFilter.value === 'Semua' && mapelList.value.length > 0) {
    return mapelList.value[0];
  }
  return selectedMapelFilter.value;
});

// Active TP list filtered by semester and selected subject
const activeTpList = computed(() => {
  let list = props.tpList || [];

  if (list.length === 0 && props.nilaiTPList && props.nilaiTPList.length > 0) {
    // Fallback adapter if tpList is empty
    return props.nilaiTPList.map(n => ({
      id: n.id,
      kode: n.kodeTP,
      judul: n.deskripsi,
      mapel: n.mapel,
      kktp: 75,
      semester: selectedSemester.value,
    }));
  }

  // Filter by Semester
  list = list.filter(t => (t.semester || 'Ganjil') === selectedSemester.value);

  // Filter by Subject
  if (effectiveMapelFilter.value !== 'Semua') {
    list = list.filter(t => t.mapel?.toLowerCase() === effectiveMapelFilter.value.toLowerCase());
  }

  return list.sort((a, b) => (a.kode || '').localeCompare(b.kode || '', undefined, { numeric: true }));
});

// Fast Lookup Map for Student Scores (siswaId_tpId)
const nilaiMap = computed(() => {
  const map = new Map();
  const list = Array.isArray(props.nilaiList) ? props.nilaiList : [];
  for (let i = 0; i < list.length; i++) {
    const n = list[i];
    if (!n) continue;
    const sId = n.siswaId || n.siswa_id;
    const tId = n.tpId || n.tp_id;
    if (sId && tId) {
      map.set(`${sId}_${tId}`, n);
    }
  }

  // Fallback to nilaiTPList if props.nilaiList is empty
  if (map.size === 0 && props.nilaiTPList && props.nilaiTPList.length > 0) {
    props.nilaiTPList.forEach(n => {
      if (props.siswaList && props.siswaList.length > 0) {
        map.set(`${props.siswaList[0].id}_${n.id}`, {
          nilai: n.nilai,
          catatan: n.catatanGuru,
        });
      }
    });
  }

  return map;
});

function getScore(siswaId, tpId) {
  const rec = nilaiMap.value.get(`${siswaId}_${tpId}`);
  return rec && rec.nilai !== null && rec.nilai !== undefined && rec.nilai !== '' ? Number(rec.nilai) : null;
}

// Student Stats Calculator per Student (Standar Kurikulum Merdeka persis seperti Guru)
function getStudentStats(siswaId) {
  const tps = activeTpList.value;
  const totalTps = tps.length;
  if (totalTps === 0) {
    return { sum: 0, filledCount: 0, totalTps: 0, finalScore: 0, isComplete: false };
  }

  let sum = 0;
  let filledCount = 0;
  for (let i = 0; i < totalTps; i++) {
    const score = getScore(siswaId, tps[i].id);
    if (score !== null && !isNaN(score)) {
      sum += score;
      filledCount++;
    }
  }

  const finalScore = totalTps > 0 ? Math.round((sum / totalTps) * 10) / 10 : 0;
  const isComplete = totalTps > 0 && filledCount === totalTps;

  return { sum, filledCount, totalTps, finalScore, isComplete };
}

// Standar Predikat Capaian Kurikulum Merdeka (Persis dengan Dashboard Guru)
function getPredikat(finalScore, isComplete) {
  if (!isComplete) {
    return { label: 'Belum Lengkap', color: 'bg-amber-500/20 text-amber-300 border-amber-500/30' };
  }
  const score = Number(finalScore);
  if (score >= 90) return { label: 'Sangat Baik (A)', color: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' };
  if (score >= 80) return { label: 'Baik (B)', color: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' };
  if (score >= 75) return { label: 'Cukup (C)', color: 'bg-blue-500/20 text-blue-300 border-blue-500/30' };
  return { label: 'Perlu Bimbingan (D)', color: 'bg-rose-500/20 text-rose-300 border-rose-500/30' };
}

// Filtered Student List for the Active Class
const filteredSiswa = computed(() => {
  let list = props.siswaList || [];
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(s => s.nama.toLowerCase().includes(q) || (s.nis && String(s.nis).includes(q)));
  }
  return list;
});
</script>

<template>
  <div class="space-y-5 animate-in fade-in duration-200">
    <!-- Top Header: Sesuai Format Ledger Guru -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2">
            <span>Matriks Capaian TP</span>
            <span class="text-cyan-400 text-sm">📊</span>
          </h1>
          <span
            class="text-xs font-black px-3 py-1 rounded-full border transition-all"
            :class="selectedSemester === 'Genap' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm shadow-amber-500/20' : 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'"
          >
            Semester {{ selectedSemester }}
          </span>
        </div>
        <p class="text-gray-400 text-xs font-medium">Rekapitulasi nilai capaian Tujuan Pembelajaran (TP) — Kelas {{ kelasSiswa }}</p>
      </div>

      <!-- Quick Semester Toggle -->
      <div class="flex items-center gap-1.5 bg-slate-800/80 p-1 rounded-2xl border border-white/10 shrink-0">
        <button
          type="button"
          @click="selectedSemester = 'Ganjil'"
          :class="[
            'px-3.5 py-1.5 rounded-xl text-xs font-black transition-all',
            selectedSemester === 'Ganjil' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-gray-400 hover:text-white'
          ]"
        >
          Ganjil
        </button>
        <button
          type="button"
          @click="selectedSemester = 'Genap'"
          :class="[
            'px-3.5 py-1.5 rounded-xl text-xs font-black transition-all',
            selectedSemester === 'Genap' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-gray-400 hover:text-white'
          ]"
        >
          Genap
        </button>
      </div>
    </div>

    <!-- Filter Bar: Mata Pelajaran & Search Siswa -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-2xl p-3 sm:p-4 border border-white/10 shadow-lg">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Pilihan Mata Pelajaran -->
        <div class="flex items-center gap-2 flex-1">
          <label class="text-xs font-bold text-gray-400 uppercase tracking-wider shrink-0 hidden sm:inline">Mata Pelajaran:</label>
          <select
            v-model="selectedMapelFilter"
            class="w-full sm:w-64 min-h-[42px] bg-slate-800 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white font-bold outline-none transition"
          >
            <option value="Semua">Semua Mata Pelajaran</option>
            <option v-for="m in mapelList" :key="m" :value="m">{{ m }}</option>
          </select>
        </div>

        <!-- Search Siswa -->
        <div class="w-full sm:w-64 relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama atau NIS siswa..."
            class="w-full min-h-[42px] bg-slate-800 border border-white/15 focus:border-cyan-400 rounded-xl pl-8 pr-3 py-2 text-xs text-white placeholder-gray-400 outline-none transition"
          />
          <span class="absolute left-2.5 top-3 text-gray-400 text-xs">🔍</span>
        </div>
      </div>
    </div>

    <!-- Container Tabel & Mobile Cards -->
    <div class="bg-slate-900/90 rounded-2xl border border-white/10 overflow-hidden shadow-2xl">
      
      <!-- Banner Sub-Header -->
      <div class="px-5 py-3.5 border-b border-white/10 bg-slate-950/70 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-xs sm:text-sm font-black text-white">
            📊 Matriks Ledger Penilaian — {{ effectiveMapelFilter }} (Semester {{ selectedSemester }})
          </span>
        </div>
        <span class="text-[11px] font-bold text-cyan-400 font-mono">
          {{ activeTpList.length }} TP Terdaftar
        </span>
      </div>

      <!-- DESKTOP TABLE VIEW (PERSIS SEPERTI DI GURU) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300">
          <thead class="bg-slate-950/80 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10 select-none">
            <tr>
              <th class="px-4 py-3.5 text-center w-12 border-r border-white/5">No</th>
              <th class="px-6 py-3.5 w-60 border-r border-white/5 sticky left-0 z-20 bg-slate-950">Nama Siswa</th>
              
              <!-- Dynamic TP Columns (Sesuai Master TP) -->
              <th
                v-for="tp in activeTpList"
                :key="tp.id"
                class="px-3 py-3.5 text-center min-w-[90px] border-r border-white/5"
                :title="`${tp.kode}: ${tp.judul} (KKTP: ${tp.kktp || 75})`"
              >
                <div class="text-cyan-300 font-black text-xs cursor-help">
                  {{ tp.kode }}
                </div>
                <div class="text-[10px] text-gray-500 font-mono mt-0.5 normal-case font-medium">
                  KKTP {{ tp.kktp || 75 }}
                </div>
              </th>

              <!-- Nilai Akhir Column -->
              <th class="px-5 py-3.5 text-center w-32 border-r border-white/5">
                <div>Nilai Akhir</div>
                <div class="text-[9px] text-cyan-400 font-semibold normal-case">Target {{ activeTpList.length }} TP</div>
              </th>

              <!-- Predikat Capaian Column -->
              <th class="px-5 py-3.5 text-center w-40">
                <div>Predikat Capaian</div>
                <div class="text-[9px] text-gray-400 font-semibold normal-case">Standar KKTP &ge; 75</div>
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-white/5 font-medium">
            <tr
              v-for="(siswa, index) in filteredSiswa"
              :key="siswa.id"
              class="group hover:bg-white/5 transition-colors"
            >
              <!-- 1. Nomor -->
              <td class="px-4 py-3 text-center font-mono text-gray-400 text-xs border-r border-white/5">
                {{ index + 1 }}
              </td>

              <!-- 2. Nama Siswa -->
              <td class="px-6 py-3 border-r border-white/5 sticky left-0 z-10 bg-slate-900 group-hover:bg-slate-800 transition-colors shadow-sm">
                <div class="font-black text-white text-xs truncate">{{ siswa.nama }}</div>
                <div class="text-[10px] text-cyan-400 font-mono">NIS: {{ siswa.nis || '-' }}</div>
              </td>

              <!-- 3. Dynamic TP Score Cells -->
              <td
                v-for="tp in activeTpList"
                :key="tp.id"
                class="px-3 py-3 text-center border-r border-white/5 font-mono font-bold text-xs"
              >
                <span
                  v-if="getScore(siswa.id, tp.id) !== null"
                  :class="getScore(siswa.id, tp.id) >= (tp.kktp || 75) ? 'text-emerald-300 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20' : 'text-rose-300 bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20'"
                >
                  {{ getScore(siswa.id, tp.id) }}
                </span>
                <span v-else class="text-gray-500">-</span>
              </td>

              <!-- 4. Nilai Akhir -->
              <td class="px-5 py-3 text-center border-r border-white/5 font-mono">
                <span
                  class="font-black text-sm"
                  :class="getStudentStats(siswa.id).finalScore >= 75 ? 'text-cyan-300' : 'text-rose-400'"
                >
                  {{ getStudentStats(siswa.id).finalScore }}
                </span>
              </td>

              <!-- 5. Predikat Capaian -->
              <td class="px-5 py-3 text-center">
                <span
                  :class="[
                    'text-[11px] font-extrabold px-3 py-0.5 rounded-full border shadow-sm inline-block',
                    getPredikat(getStudentStats(siswa.id).finalScore, getStudentStats(siswa.id).isComplete).color
                  ]"
                >
                  {{ getPredikat(getStudentStats(siswa.id).finalScore, getStudentStats(siswa.id).isComplete).label }}
                </span>
              </td>
            </tr>

            <!-- Empty Row State -->
            <tr v-if="filteredSiswa.length === 0">
              <td :colspan="activeTpList.length + 4" class="px-6 py-12 text-center text-gray-400 text-xs">
                Tidak ada data siswa yang cocok dengan kriteria pencarian.
              </td>
            </tr>

            <tr v-else-if="activeTpList.length === 0">
              <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-xs">
                Belum ada Tujuan Pembelajaran (TP) yang terdaftar untuk {{ effectiveMapelFilter }} Semester {{ selectedSemester }}.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- MOBILE SMARTPHONE VIEW (CARD LIST PERSIS SEPERTI DI GURU) -->
      <div class="block md:hidden p-4 space-y-3">
        <div
          v-for="(siswa, index) in filteredSiswa"
          :key="'mobile-' + siswa.id"
          class="bg-slate-950/60 rounded-2xl p-4 border border-white/10 space-y-3 shadow-md"
        >
          <!-- Header Siswa -->
          <div class="flex items-center justify-between">
            <span class="font-black text-xs text-white">{{ index + 1 }}. {{ siswa.nama }}</span>
            <span class="font-mono text-[11px] text-cyan-400 font-bold">NIS: {{ siswa.nis || '-' }}</span>
          </div>

          <!-- Grid Nilai per TP -->
          <div v-if="activeTpList.length > 0" class="grid grid-cols-2 gap-2 pt-2 border-t border-white/5">
            <div
              v-for="tp in activeTpList"
              :key="'m-tp-' + tp.id"
              class="bg-slate-900/90 p-2.5 rounded-xl border border-white/10 flex items-center justify-between"
            >
              <div class="min-w-0 pr-1">
                <span class="text-xs font-black text-cyan-300 block truncate">{{ tp.kode }}</span>
                <span class="text-[9px] text-gray-400 block">KKTP {{ tp.kktp || 75 }}</span>
              </div>
              <span
                v-if="getScore(siswa.id, tp.id) !== null"
                :class="getScore(siswa.id, tp.id) >= (tp.kktp || 75) ? 'text-emerald-300 font-black text-xs bg-emerald-500/15 px-2 py-0.5 rounded' : 'text-rose-300 font-black text-xs bg-rose-500/15 px-2 py-0.5 rounded'"
              >
                {{ getScore(siswa.id, tp.id) }}
              </span>
              <span v-else class="text-gray-500 text-xs font-mono">-</span>
            </div>
          </div>

          <div v-else class="py-4 text-center text-xs text-gray-400">
            Belum ada TP untuk Semester {{ selectedSemester }}.
          </div>

          <!-- Footer Nilai Akhir & Predikat -->
          <div class="flex items-center justify-between pt-2 border-t border-white/10 flex-wrap gap-2">
            <div class="text-xs font-extrabold text-white flex items-center gap-1.5">
              <span>Nilai Akhir:</span>
              <span
                class="font-black text-sm"
                :class="getStudentStats(siswa.id).finalScore >= 75 ? 'text-cyan-300' : 'text-rose-400'"
              >
                {{ getStudentStats(siswa.id).finalScore }}
              </span>
            </div>
            <span
              :class="[
                'text-[10px] font-black px-2.5 py-0.5 rounded-full border',
                getPredikat(getStudentStats(siswa.id).finalScore, getStudentStats(siswa.id).isComplete).color
              ]"
            >
              {{ getPredikat(getStudentStats(siswa.id).finalScore, getStudentStats(siswa.id).isComplete).label }}
            </span>
          </div>
        </div>

        <div v-if="filteredSiswa.length === 0" class="py-8 text-center text-gray-400 text-xs">
          Tidak ada data siswa yang cocok dengan pencarian.
        </div>
      </div>

    </div>
  </div>
</template>
