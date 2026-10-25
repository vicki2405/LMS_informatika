<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  tpList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] },
  nilaiList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  kelasSiswa: { type: String, default: 'Kelas' },
  nilaiTPList: { type: Array, default: () => [] },
  siswaObj: { type: Object, default: () => ({}) },
});

// 1. Academic Period Filter (Semester Ganjil / Genap)
function getInitialSemester() {
  const currentMonth = new Date().getMonth() + 1;
  return currentMonth >= 1 && currentMonth <= 6 ? 'Genap' : 'Ganjil';
}

const selectedSemester = ref(getInitialSemester());

// 2. Active Subjects List (Strictly synced with Jadwal / Guru aktif di kelas)
const availableMapels = computed(() => {
  if (Array.isArray(props.mapelList) && props.mapelList.length > 0) {
    return [...props.mapelList].sort();
  }
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

// Subject selection state
const selectedMapelFilter = ref('Semua');

// Automatically configure subject filter based on number of active subjects
watch(availableMapels, (newMapels) => {
  if (newMapels.length === 1) {
    // If only 1 teacher / subject is active for this class, strictly lock to it
    selectedMapelFilter.value = newMapels[0];
  } else if (newMapels.length > 1) {
    // If multiple teachers teach different subjects in this class, allow filtering
    if (!newMapels.includes(selectedMapelFilter.value) && selectedMapelFilter.value !== 'Semua') {
      selectedMapelFilter.value = 'Semua';
    }
  } else {
    selectedMapelFilter.value = 'Semua';
  }
}, { immediate: true });

const effectiveMapelFilter = computed(() => {
  if (availableMapels.value.length === 1) {
    return availableMapels.value[0];
  }
  return selectedMapelFilter.value;
});

// 3. Shared Class Account Student Identity Management (Strictly synced from Tab Materi & CBT)
const activeSiswaId = ref(null);

function syncActiveSiswaFromStorage() {
  try {
    const savedId = localStorage.getItem('siswacloud_active_siswa_id');
    if (savedId && props.siswaList.some(s => String(s.id) === String(savedId))) {
      activeSiswaId.value = Number(savedId);
      return;
    }
  } catch (e) {}

  if (props.siswaObj?.id && props.siswaList.some(s => String(s.id) === String(props.siswaObj.id))) {
    activeSiswaId.value = Number(props.siswaObj.id);
  } else if (props.siswaList && props.siswaList.length > 0) {
    activeSiswaId.value = Number(props.siswaList[0].id);
  }
}

onMounted(() => {
  syncActiveSiswaFromStorage();

  // Listen for storage changes from other tabs (e.g. if student selects name in tab materi)
  if (typeof window !== 'undefined') {
    window.addEventListener('storage', handleStorageEvent);
    window.addEventListener('focus', syncActiveSiswaFromStorage);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('storage', handleStorageEvent);
    window.removeEventListener('focus', syncActiveSiswaFromStorage);
  }
});

function handleStorageEvent(e) {
  if (e.key === 'siswacloud_active_siswa_id') {
    syncActiveSiswaFromStorage();
  }
}

watch(() => props.siswaList, (newVal) => {
  if (newVal && newVal.length > 0 && !activeSiswaId.value) {
    syncActiveSiswaFromStorage();
  }
}, { immediate: true });

const activeSiswa = computed(() => {
  if (!activeSiswaId.value) return props.siswaObj || null;
  return props.siswaList.find(s => Number(s.id) === Number(activeSiswaId.value)) || props.siswaObj || null;
});

// 4. Active TP list filtered by semester and selected subject
const activeTpList = computed(() => {
  let list = props.tpList || [];

  if (list.length === 0 && props.nilaiTPList && props.nilaiTPList.length > 0) {
    return props.nilaiTPList.map(n => ({
      id: n.id,
      kode: n.kodeTP,
      judul: n.deskripsi,
      mapel: n.mapel,
      kktp: 76,
      semester: selectedSemester.value,
    }));
  }

  // Filter by Semester
  list = list.filter(t => (t.semester || 'Ganjil') === selectedSemester.value);

  // Filter by Subject (if not 'Semua')
  if (effectiveMapelFilter.value !== 'Semua') {
    list = list.filter(t => (t.mapel || '').trim().toLowerCase() === effectiveMapelFilter.value.trim().toLowerCase());
  }

  return list.sort((a, b) => (a.kode || '').localeCompare(b.kode || '', undefined, { numeric: true }));
});

// 5. Fast Lookup Map for Student Scores (siswaId_tpId)
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
      const targetId = activeSiswaId.value || props.siswaList?.[0]?.id;
      if (targetId) {
        map.set(`${targetId}_${n.id}`, {
          nilai: n.nilai,
          catatan: n.catatanGuru,
        });
      }
    });
  }

  return map;
});

function getScoreRecord(siswaId, tpId) {
  if (!siswaId) return null;
  return nilaiMap.value.get(`${siswaId}_${tpId}`) || null;
}

function getScore(siswaId, tpId) {
  const rec = getScoreRecord(siswaId, tpId);
  return rec && rec.nilai !== null && rec.nilai !== undefined && rec.nilai !== '' ? Number(rec.nilai) : null;
}

function getCatatan(siswaId, tpId) {
  const rec = getScoreRecord(siswaId, tpId);
  return rec?.catatan || null;
}

// Student Stats Calculator for Active Selected Student
const activeSiswaStats = computed(() => {
  const sId = activeSiswa.value?.id;
  const tps = activeTpList.value;
  const totalTps = tps.length;
  if (!sId || totalTps === 0) {
    return { sum: 0, filledCount: 0, totalTps: 0, finalScore: 0, isComplete: false, tuntasCount: 0 };
  }

  let sum = 0;
  let filledCount = 0;
  let tuntasCount = 0;

  for (let i = 0; i < totalTps; i++) {
    const score = getScore(sId, tps[i].id);
    const kktp = tps[i].kktp || 75;
    if (score !== null && !isNaN(score)) {
      sum += score;
      filledCount++;
      if (score >= kktp) {
        tuntasCount++;
      }
    }
  }

  // Pembagi adalah total target TP aktif semester ini
  const finalScore = totalTps > 0 ? Math.round((sum / totalTps) * 10) / 10 : 0;
  const isComplete = totalTps > 0 && filledCount === totalTps;

  return { sum, filledCount, totalTps, finalScore, isComplete, tuntasCount };
});

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
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-200">
    
    <!-- Top Header: Judul Menu & Periode Akademik -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2">
            <span>Rapor Capaian Nilai TP</span>
            <span class="text-cyan-400 text-sm">📊</span>
          </h1>
          <span
            class="text-xs font-black px-3 py-1 rounded-full border transition-all"
            :class="selectedSemester === 'Genap' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm shadow-amber-500/20' : 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'"
          >
            Semester {{ selectedSemester }}
          </span>
        </div>
        <p class="text-gray-400 text-xs font-medium">
          Rekapitulasi capaian nilai Tujuan Pembelajaran (TP) — Kelas {{ kelasSiswa }}
        </p>
      </div>

      <!-- Quick Controls: Semester Toggle -->
      <div class="flex items-center gap-1 bg-slate-800/80 p-1 rounded-2xl border border-white/10 shrink-0">
        <button
          type="button"
          @click="selectedSemester = 'Ganjil'"
          :class="[
            'px-3.5 py-1.5 rounded-xl text-xs font-black transition-all min-h-[36px]',
            selectedSemester === 'Ganjil' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-gray-400 hover:text-white'
          ]"
        >
          Ganjil
        </button>
        <button
          type="button"
          @click="selectedSemester = 'Genap'"
          :class="[
            'px-3.5 py-1.5 rounded-xl text-xs font-black transition-all min-h-[36px]',
            selectedSemester === 'Genap' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-gray-400 hover:text-white'
          ]"
        >
          Genap
        </button>
      </div>
    </div>

    <!-- Filter Bar: Form Mata Pelajaran & Info Identitas Siswa -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-2xl p-4 border border-white/10 shadow-lg">
      <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        
        <!-- Form Mata Pelajaran: Disesuaikan dengan Guru Aktif Terjadwal di Kelas -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 flex-1">
          <label class="text-xs font-bold text-gray-400 uppercase tracking-wider shrink-0">Mata Pelajaran:</label>
          
          <!-- SKENARIO 1: JIKA HANYA ADA 1 GURU AKTIF DENGAN 1 MAPEL (MISAL KKA) -->
          <div
            v-if="availableMapels.length === 1"
            class="flex items-center gap-2.5 bg-slate-800/90 border border-cyan-500/40 rounded-xl px-3.5 py-2 min-h-[42px] w-full sm:w-auto"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse"></span>
            <span class="text-xs font-black text-white tracking-wide">{{ availableMapels[0] }}</span>
            <span class="text-[10px] bg-cyan-500/20 text-cyan-300 font-bold px-2 py-0.5 rounded-md border border-cyan-500/30">
              Mata Pelajaran Terjadwal (1 Guru Aktif)
            </span>
          </div>

          <!-- SKENARIO 2: JIKA ADA BEBERAPA GURU AKTIF YANG MENGAJAR KELAS SAMA TAPI MAPEL BERBEDA (MISAL 5 GURU) -->
          <div v-else-if="availableMapels.length > 1" class="w-full sm:w-72">
            <select
              v-model="selectedMapelFilter"
              class="w-full min-h-[42px] bg-slate-800 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white font-bold outline-none transition cursor-pointer"
            >
              <option value="Semua">Semua Mata Pelajaran ({{ availableMapels.length }} Terjadwal)</option>
              <option v-for="m in availableMapels" :key="m" :value="m">{{ m }}</option>
            </select>
          </div>

          <!-- SKENARIO 3: BELUM ADA JADWAL MAPEL AKTIF -->
          <div v-else class="text-xs text-gray-400 italic py-2">
            Belum ada jadwal mata pelajaran aktif untuk kelas ini
          </div>
        </div>

        <!-- Identitas Siswa Aktif (Otomatis Tersinkron dari Tab Materi) -->
        <div class="flex items-center gap-2 shrink-0">
          <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800/80 border border-white/10 text-xs font-bold text-gray-300 min-h-[42px]">
            <span class="text-sm">👤</span>
            <span class="text-white font-black">{{ activeSiswa?.nama || 'Siswa' }}</span>
            <span class="text-[10px] text-gray-400 font-mono">({{ activeSiswa?.nis || '-' }})</span>
          </div>
        </div>

      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- RAPOR CAPAIAN PRIBADI SISWA (HANYA UNTUK SISWA YANG DIPILIH)              -->
    <!-- ========================================================================= -->
    <div class="space-y-6">
      
      <!-- Info Header Siswa & Rangkuman Nilai -->
      <div class="bg-gradient-to-br from-slate-900/90 via-slate-900/80 to-cyan-950/30 rounded-2xl p-5 border border-white/10 shadow-xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-white/10">
          <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 border border-cyan-500/30 flex items-center justify-center text-cyan-300 font-black text-xl shadow-inner shrink-0">
              {{ (activeSiswa?.nama || 'S').charAt(0) }}
            </div>
            <div>
              <h2 class="text-base sm:text-lg font-black text-white flex items-center gap-2 flex-wrap">
                <span>{{ activeSiswa?.nama || 'Siswa' }}</span>
                <span class="text-[10px] bg-slate-800 text-cyan-400 border border-cyan-500/30 font-mono px-2 py-0.5 rounded-full">
                  NIS: {{ activeSiswa?.nis || '-' }}
                </span>
              </h2>
              <p class="text-xs text-gray-400 mt-0.5">
                Kelas {{ kelasSiswa }} • Mata Pelajaran: <span class="text-cyan-300 font-bold">{{ effectiveMapelFilter }}</span> • Semester {{ selectedSemester }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <span
              :class="[
                'text-xs font-black px-3.5 py-1.5 rounded-xl border shadow-sm',
                getPredikat(activeSiswaStats.finalScore, activeSiswaStats.isComplete).color
              ]"
            >
              Predikat: {{ getPredikat(activeSiswaStats.finalScore, activeSiswaStats.isComplete).label }}
            </span>
          </div>
        </div>

        <!-- 4 KPI Stat Cards Pribadi Siswa -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-4">
          <!-- 1. Nilai Rata-rata TP -->
          <div class="bg-slate-950/60 p-3.5 rounded-xl border border-white/10">
            <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Nilai Rata-rata TP</span>
            <div class="flex items-baseline gap-2">
              <span
                class="text-2xl sm:text-3xl font-black font-mono tracking-tight"
                :class="activeSiswaStats.finalScore >= 75 ? 'text-cyan-300' : 'text-rose-400'"
              >
                {{ activeSiswaStats.finalScore }}
              </span>
              <span class="text-[11px] text-gray-400">/ 100</span>
            </div>
            <span class="text-[10px] text-gray-400 block mt-1">Standar KKTP &ge; 75</span>
          </div>

          <!-- 2. Ketuntasan TP -->
          <div class="bg-slate-950/60 p-3.5 rounded-xl border border-white/10">
            <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Ketuntasan TP</span>
            <div class="flex items-baseline gap-1.5">
              <span class="text-2xl sm:text-3xl font-black font-mono text-emerald-300">
                {{ activeSiswaStats.tuntasCount }}
              </span>
              <span class="text-sm font-bold text-gray-400 font-mono">/ {{ activeTpList.length }} TP</span>
            </div>
            <span class="text-[10px] text-emerald-400/80 block mt-1">Tuntas Melampaui KKTP</span>
          </div>

          <!-- 3. TP Terisi / Dinilai -->
          <div class="bg-slate-950/60 p-3.5 rounded-xl border border-white/10">
            <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Status Penilaian</span>
            <div class="flex items-baseline gap-1.5">
              <span class="text-2xl sm:text-3xl font-black font-mono text-cyan-400">
                {{ activeSiswaStats.filledCount }}
              </span>
              <span class="text-sm font-bold text-gray-400 font-mono">/ {{ activeTpList.length }} TP</span>
            </div>
            <span
              class="text-[10px] block mt-1 font-bold"
              :class="activeSiswaStats.isComplete ? 'text-emerald-400' : 'text-amber-400'"
            >
              {{ activeSiswaStats.isComplete ? 'Semua TP Dinilai' : 'Masih Berjalan' }}
            </span>
          </div>

          <!-- 4. Jumlah Target TP Aktif -->
          <div class="bg-slate-950/60 p-3.5 rounded-xl border border-white/10">
            <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Target Kurikulum</span>
            <div class="flex items-baseline gap-1.5">
              <span class="text-2xl sm:text-3xl font-black font-mono text-white">
                {{ activeTpList.length }}
              </span>
              <span class="text-xs text-gray-400 font-bold">Materi TP</span>
            </div>
            <span class="text-[10px] text-gray-400 block mt-1">Semester {{ selectedSemester }}</span>
          </div>
        </div>
      </div>

      <!-- Detail Capaian per TP (HANYA MILIK SISWA TERSEBUT) -->
      <div class="bg-slate-900/90 rounded-2xl border border-white/10 overflow-hidden shadow-2xl">
        <div class="px-5 py-4 border-b border-white/10 bg-slate-950/70 flex items-center justify-between">
          <div>
            <h3 class="text-sm font-black text-white flex items-center gap-2">
              <span>Rincian Nilai per Tujuan Pembelajaran</span>
              <span class="text-xs text-cyan-400 font-mono font-bold">({{ activeTpList.length }} TP)</span>
            </h3>
            <p class="text-[11px] text-gray-400 mt-0.5">
              Daftar capaian nilai formasi Tujuan Pembelajaran yang dinilai oleh guru pengampu
            </p>
          </div>
        </div>

        <!-- Desktop View (Tabel Rincian Nilai Siswa) -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="w-full text-left text-xs text-gray-300">
            <thead class="bg-slate-950/80 text-gray-400 uppercase text-[10px] font-extrabold tracking-wider border-b border-white/10">
              <tr>
                <th class="px-4 py-3 text-center w-12">No</th>
                <th class="px-4 py-3 w-32">Kode TP</th>
                <th class="px-6 py-3">Deskripsi Tujuan Pembelajaran</th>
                <th class="px-4 py-3 text-center w-24">KKTP</th>
                <th class="px-5 py-3 text-center w-28">Nilai Siswa</th>
                <th class="px-5 py-3 text-center w-32">Status</th>
                <th class="px-5 py-3 w-48">Catatan Guru</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 font-medium">
              <tr
                v-for="(tp, index) in activeTpList"
                :key="tp.id"
                class="hover:bg-white/5 transition-colors"
              >
                <!-- Nomor -->
                <td class="px-4 py-3.5 text-center font-mono text-gray-400">{{ index + 1 }}</td>

                <!-- Kode TP & Jenis -->
                <td class="px-4 py-3.5">
                  <span class="font-black text-cyan-300 block font-mono text-xs">{{ tp.kode }}</span>
                  <span class="text-[10px] text-gray-400">{{ tp.jenis || 'Formatif' }}</span>
                </td>

                <!-- Deskripsi TP -->
                <td class="px-6 py-3.5">
                  <div class="font-bold text-white text-xs">{{ tp.judul }}</div>
                  <div class="text-[10px] text-cyan-400/80 mt-0.5">Mata Pelajaran: {{ tp.mapel }}</div>
                </td>

                <!-- KKTP -->
                <td class="px-4 py-3.5 text-center font-mono font-bold text-gray-300">
                  {{ tp.kktp || 75 }}
                </td>

                <!-- Nilai Siswa -->
                <td class="px-5 py-3.5 text-center font-mono">
                  <span
                    v-if="getScore(activeSiswa?.id, tp.id) !== null"
                    :class="[
                      'text-sm font-black px-2.5 py-1 rounded-lg border font-mono inline-block',
                      getScore(activeSiswa?.id, tp.id) >= (tp.kktp || 75)
                        ? 'text-emerald-300 bg-emerald-500/15 border-emerald-500/30'
                        : 'text-rose-300 bg-rose-500/15 border-rose-500/30'
                    ]"
                  >
                    {{ getScore(activeSiswa?.id, tp.id) }}
                  </span>
                  <span v-else class="text-gray-500 font-mono text-xs italic">Belum Dinilai</span>
                </td>

                <!-- Status Ketuntasan -->
                <td class="px-5 py-3.5 text-center">
                  <span
                    v-if="getScore(activeSiswa?.id, tp.id) !== null"
                    :class="[
                      'text-[10px] font-black px-3 py-1 rounded-full border shadow-sm inline-block',
                      getScore(activeSiswa?.id, tp.id) >= (tp.kktp || 75)
                        ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                        : 'bg-rose-500/20 text-rose-300 border-rose-500/30'
                    ]"
                  >
                    {{ getScore(activeSiswa?.id, tp.id) >= (tp.kktp || 75) ? '✓ Tuntas' : '⚠ Belum Tuntas' }}
                  </span>
                  <span v-else class="text-gray-500 text-[10px] font-medium">-</span>
                </td>

                <!-- Catatan Guru -->
                <td class="px-5 py-3.5 text-xs text-gray-400 italic">
                  {{ getCatatan(activeSiswa?.id, tp.id) || '-' }}
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="activeTpList.length === 0">
                <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-xs">
                  Belum ada Tujuan Pembelajaran (TP) aktif untuk {{ effectiveMapelFilter }} Semester {{ selectedSemester }}.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile View (Card List Rincian TP) -->
        <div class="block sm:hidden p-4 space-y-3">
          <div
            v-for="(tp, index) in activeTpList"
            :key="'m-det-' + tp.id"
            class="bg-slate-950/60 rounded-2xl p-4 border border-white/10 space-y-3"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-black text-cyan-300 font-mono">{{ index + 1 }}. {{ tp.kode }}</span>
              <span
                v-if="getScore(activeSiswa?.id, tp.id) !== null"
                :class="[
                  'text-[10px] font-black px-2.5 py-0.5 rounded-full border',
                  getScore(activeSiswa?.id, tp.id) >= (tp.kktp || 75)
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                    : 'bg-rose-500/20 text-rose-300 border-rose-500/30'
                ]"
              >
                {{ getScore(activeSiswa?.id, tp.id) >= (tp.kktp || 75) ? 'Tuntas' : 'Belum Tuntas' }}
              </span>
              <span v-else class="text-gray-500 text-[10px]">Belum Dinilai</span>
            </div>

            <div class="text-xs font-bold text-white">{{ tp.judul }}</div>

            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-white/5 text-xs">
              <div class="bg-slate-900/80 p-2.5 rounded-xl">
                <span class="text-[10px] text-gray-400 block">KKTP</span>
                <span class="font-bold text-gray-200 font-mono">{{ tp.kktp || 75 }}</span>
              </div>
              <div class="bg-slate-900/80 p-2.5 rounded-xl">
                <span class="text-[10px] text-gray-400 block">Nilai Siswa</span>
                <span
                  v-if="getScore(activeSiswa?.id, tp.id) !== null"
                  :class="getScore(activeSiswa?.id, tp.id) >= (tp.kktp || 75) ? 'text-emerald-300 font-black font-mono' : 'text-rose-300 font-black font-mono'"
                >
                  {{ getScore(activeSiswa?.id, tp.id) }}
                </span>
                <span v-else class="text-gray-500 font-mono">-</span>
              </div>
            </div>

            <div v-if="getCatatan(activeSiswa?.id, tp.id)" class="text-[11px] text-gray-400 bg-slate-900/50 p-2.5 rounded-xl italic">
              Catatan: {{ getCatatan(activeSiswa?.id, tp.id) }}
            </div>
          </div>

          <div v-if="activeTpList.length === 0" class="py-8 text-center text-gray-400 text-xs">
            Belum ada TP untuk Semester {{ selectedSemester }}.
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
