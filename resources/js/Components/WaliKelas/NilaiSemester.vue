<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  rekapNilaiSemester: { type: Array, default: () => [] },
  kelasBinaan:        { type: String, default: 'X RPL 1' },
  tpList:             { type: Array, default: () => [] },
  mapelList:          { type: Array, default: () => [] },
});

const searchNilai      = ref('');
const selectedSemester = ref('Ganjil');
const selectedMapel    = ref('');

// ─── Daftar mapel untuk dropdown ─────────────────────────────────────────────
const mapelOptions = computed(() => {
  // Prioritaskan data dari prop (database), fallback ke derive dari tpList
  if (props.mapelList && props.mapelList.length > 0) {
    return props.mapelList;
  }
  const set = new Set(props.tpList.map(t => t.mapel).filter(Boolean));
  return [...set].sort();
});

// Set default selectedMapel ke mapel pertama saat mapelOptions tersedia
const initMapel = computed(() => mapelOptions.value[0] ?? '');

// ─── tpHeaders dinamis berdasarkan mapel + semester yang dipilih ─────────────
const tpHeaders = computed(() => {
  const mapel = selectedMapel.value || initMapel.value;
  return props.tpList.filter(tp =>
    tp.mapel === mapel &&
    tp.semester === selectedSemester.value
  );
});

// ─── Helper badge predikat ────────────────────────────────────────────────────
function getPredikatBadge(score) {
  if (score === null || score === undefined || score === 0) {
    return { label: 'Belum Dinilai', style: 'bg-slate-800 text-gray-400 border-gray-700' };
  }
  if (score >= 90) return { label: 'A (Sangat Baik)', style: 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40' };
  if (score >= 80) return { label: 'B (Baik)', style: 'bg-blue-500/20 text-blue-300 border-blue-500/40' };
  if (score >= 75) return { label: 'C (Cukup)', style: 'bg-amber-500/20 text-amber-300 border-amber-500/40' };
  return { label: 'D (Perlu Bimbingan)', style: 'bg-red-500/20 text-red-300 border-red-500/40' };
}

// ─── Pre-computed & memoized data list for high performance rendering ────────
const processedNilaiList = computed(() => {
  const kodes = tpHeaders.value.map(tp => tp.kode);
  const q = searchNilai.value.trim().toLowerCase();

  return props.rekapNilaiSemester
    .filter(n => {
      if (!q) return true;
      return (
        n.namaSiswa?.toLowerCase().includes(q) ||
        String(n.nis).includes(q)
      );
    })
    .map(siswaItem => {
      const vals = kodes
        .map(k => siswaItem.scores?.[k])
        .filter(v => v !== undefined && v !== null);

      const avg = vals.length === 0 ? null : Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 10) / 10;
      const predikatObj = getPredikatBadge(avg);

      return {
        ...siswaItem,
        mapelAvg: avg,
        predikatObj,
      };
    });
});
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">

    <!-- ── Top Action Header ──────────────────────────────────────────────── -->
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Data Ledger Nilai TP Siswa</span>
          <span class="text-indigo-400 text-base">📝</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">
          Matriks nilai Tujuan Pembelajaran (TP) — filter per Mapel &amp; Semester
        </p>
      </div>

      <!-- Filter Controls -->
      <div class="flex flex-wrap items-center gap-3 shrink-0">

        <!-- Semester Toggle -->
        <div class="flex items-center gap-1 bg-slate-900/80 p-1.5 rounded-2xl border border-white/10 shadow-lg">
          <button
            @click="selectedSemester = 'Ganjil'"
            :class="['px-4 py-2 rounded-xl font-extrabold text-xs transition-all duration-200 min-h-[36px]',
              selectedSemester === 'Ganjil'
                ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md'
                : 'text-gray-400 hover:text-white']"
          >
            Semester 1 (Ganjil)
          </button>
          <button
            @click="selectedSemester = 'Genap'"
            :class="['px-4 py-2 rounded-xl font-extrabold text-xs transition-all duration-200 min-h-[36px]',
              selectedSemester === 'Genap'
                ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md'
                : 'text-gray-400 hover:text-white']"
          >
            Semester 2 (Genap)
          </button>
        </div>

        <!-- Mapel Dropdown -->
        <div class="relative">
          <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 pl-1">📚 Mata Pelajaran</label>
          <div class="relative">
            <select
              v-model="selectedMapel"
              class="appearance-none bg-slate-800 border border-white/15 rounded-2xl pl-4 pr-10 py-2.5 text-xs text-white font-bold focus:border-indigo-400 outline-none min-h-[42px] min-w-[180px] cursor-pointer shadow-lg transition-colors hover:border-indigo-400/50"
            >
              <option v-if="mapelOptions.length === 0" value="" disabled>
                Belum ada data mapel
              </option>
              <option
                v-for="mapel in mapelOptions"
                :key="mapel"
                :value="mapel"
              >
                {{ mapel }}
              </option>
            </select>
            <!-- Arrow icon -->
            <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
              </svg>
            </span>
          </div>
        </div>

      </div>
    </div>

    <!-- Info badge mapel & TP yang tampil -->
    <div class="flex flex-wrap items-center gap-2 text-xs">
      <span class="px-3 py-1 bg-indigo-500/15 border border-indigo-500/30 rounded-full text-indigo-300 font-bold">
        📚 {{ selectedMapel || initMapel || '—' }}
      </span>
      <span class="px-3 py-1 bg-slate-800 border border-white/10 rounded-full text-gray-400 font-semibold">
        {{ selectedSemester === 'Ganjil' ? 'Semester 1 (Ganjil)' : 'Semester 2 (Genap)' }}
      </span>
      <span class="px-3 py-1 bg-cyan-500/10 border border-cyan-500/20 rounded-full text-cyan-300 font-semibold">
        {{ tpHeaders.length }} TP Ditemukan
      </span>
    </div>

    <!-- Search Bar -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl max-w-md">
      <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">🔍 Cari Nama Siswa / NIS</label>
      <input
        v-model="searchNilai"
        type="text"
        placeholder="Cari Nama Siswa atau NIS..."
        class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-indigo-400 outline-none min-h-[44px]"
      />
    </div>

    <!-- ── Tabel Matriks Ledger Nilai TP ──────────────────────────────────── -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">

      <!-- Tidak ada TP untuk kombinasi mapel + semester ini -->
      <div v-if="tpHeaders.length === 0" class="py-14 text-center space-y-2">
        <div class="text-4xl">📭</div>
        <p class="text-gray-400 font-semibold text-sm">
          Belum ada Tujuan Pembelajaran untuk
          <span class="text-indigo-300 font-bold">{{ selectedMapel || initMapel }}</span>
          — {{ selectedSemester === 'Ganjil' ? 'Semester 1 (Ganjil)' : 'Semester 2 (Genap)' }}
        </p>
      </div>

      <template v-else>
        <!-- Desktop Table View -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left text-xs sm:text-sm text-gray-300 border-collapse table-fixed min-w-[750px]">
            <!-- Headers -->
            <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
              <tr>
                <th class="py-4 px-4 w-[5%] text-center border-r border-white/5">NO</th>
                <th class="py-4 px-6 w-[22%] border-r border-white/5">NAMA SISWA</th>
                <th
                  v-for="tp in tpHeaders"
                  :key="tp.id"
                  class="py-3 px-4 text-center border-r border-white/5"
                  :style="{ width: (50 / tpHeaders.length) + '%' }"
                >
                  <div class="flex items-center justify-center gap-1 font-black text-cyan-300 text-xs">
                    <span>{{ tp.kode }}</span>
                    <span :class="['w-1.5 h-1.5 rounded-full', tp.jenis === 'Sumatif' ? 'bg-amber-400' : 'bg-cyan-400']"></span>
                  </div>
                  <div class="text-[9px] text-gray-400 font-normal uppercase truncate max-w-[120px] mx-auto mt-0.5" :title="tp.judul">
                    {{ tp.judul }}
                  </div>
                  <div class="text-[9px] mt-0.5" :class="tp.jenis === 'Sumatif' ? 'text-amber-400/70' : 'text-cyan-500/60'">
                    {{ tp.jenis }}
                  </div>
                </th>
                <th class="py-4 px-4 text-center border-r border-white/5 w-[10%]">RATA-RATA</th>
                <th class="py-4 px-6 text-center w-[18%]">PREDIKAT</th>
              </tr>
            </thead>

            <!-- Rows -->
            <tbody class="divide-y divide-white/5 font-medium">
              <tr
                v-for="(siswaItem, index) in processedNilaiList"
                :key="siswaItem.id"
                class="hover:bg-white/5 transition-colors"
              >
                <td class="py-4 px-4 text-center font-mono text-xs text-gray-400 border-r border-white/5 align-middle">
                  {{ index + 1 }}
                </td>
                <td class="py-4 px-6 border-r border-white/5 font-extrabold text-white align-middle truncate" :title="siswaItem.namaSiswa">
                  {{ siswaItem.namaSiswa }}
                  <div class="text-[11px] font-mono text-gray-400 font-normal">NIS: {{ siswaItem.nis }}</div>
                </td>
                <td
                  v-for="tp in tpHeaders"
                  :key="tp.id"
                  class="py-4 px-3 text-center border-r border-white/5 align-middle"
                >
                  <div
                    v-if="siswaItem.scores?.[tp.kode] !== undefined"
                    class="inline-flex items-center justify-center px-3 py-1.5 rounded-xl bg-slate-800/80 border border-white/15 text-white font-mono font-black text-xs shadow-inner"
                  >
                    {{ siswaItem.scores[tp.kode] }}
                  </div>
                  <div v-else class="inline-flex items-center justify-center px-3 py-1 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 font-mono text-[11px] font-semibold">
                    Belum
                  </div>
                </td>
                <td class="py-4 px-4 text-center font-mono font-black text-sm border-r border-white/5 align-middle"
                    :class="siswaItem.mapelAvg !== null ? 'text-cyan-300' : 'text-gray-500'">
                  {{ siswaItem.mapelAvg ?? '—' }}
                </td>
                <td class="py-4 px-6 text-center align-middle whitespace-nowrap">
                  <span
                    :class="[
                      'px-3.5 py-1 rounded-full text-xs font-black border inline-block shadow-md',
                      siswaItem.predikatObj.style
                    ]"
                  >
                    {{ siswaItem.predikatObj.label }}
                  </span>
                </td>
              </tr>

              <tr v-if="processedNilaiList.length === 0">
                <td :colspan="3 + tpHeaders.length" class="py-12 text-center text-gray-400 font-medium">
                  Belum ada data nilai untuk siswa di kelas ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="block md:hidden divide-y divide-white/5">
          <div
            v-for="(siswaItem, index) in processedNilaiList"
            :key="'mob-ns-' + siswaItem.id"
            class="p-4 space-y-3 hover:bg-white/5 transition-colors"
          >
            <div class="flex items-center justify-between gap-2">
              <div>
                <h3 class="font-extrabold text-white text-sm">{{ index + 1 }}. {{ siswaItem.namaSiswa }}</h3>
                <p class="font-mono text-[11px] text-gray-400 font-normal">NIS: {{ siswaItem.nis }}</p>
              </div>
              <span
                :class="[
                  'px-3 py-0.5 rounded-full text-[10px] font-black border shadow-md shrink-0',
                  siswaItem.predikatObj.style
                ]"
              >
                {{ siswaItem.predikatObj.label }}
              </span>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs pt-1">
              <div
                v-for="tp in tpHeaders"
                :key="'mob-tp-' + tp.id"
                class="bg-slate-950/40 p-2.5 rounded-xl border border-white/5 flex items-center justify-between"
              >
                <div>
                  <span class="text-cyan-300 font-bold text-[11px]">{{ tp.kode }}</span>
                  <div class="text-[9px] text-gray-500 truncate max-w-[80px]" :title="tp.judul">{{ tp.judul }}</div>
                </div>
                <span v-if="siswaItem.scores?.[tp.kode] !== undefined" class="font-mono font-black text-white text-xs">
                  {{ siswaItem.scores[tp.kode] }}
                </span>
                <span v-else class="text-amber-300 text-[10px] font-semibold">Belum</span>
              </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-white/5">
              <span class="text-xs font-bold text-gray-400">Rata-rata {{ selectedMapel || initMapel }}:</span>
              <span class="font-mono font-black text-sm text-cyan-300">{{ siswaItem.mapelAvg ?? '—' }}</span>
            </div>
          </div>

          <div v-if="processedNilaiList.length === 0" class="p-8 text-center text-gray-400 font-medium">
            Belum ada data nilai untuk siswa di kelas ini.
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
