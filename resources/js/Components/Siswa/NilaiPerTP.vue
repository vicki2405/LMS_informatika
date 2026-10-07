<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  nilaiTPList: Array
});

const selectedSubjectFilter = ref('Semua');
const selectedStatusFilter = ref('Semua');

const subjects = computed(() => {
  const set = new Set((props.nilaiTPList || []).map(tp => tp.mapel).filter(Boolean));
  return [...set].sort();
});

const filteredTPList = computed(() => {
  let list = [...props.nilaiTPList];

  if (selectedSubjectFilter.value !== 'Semua') {
    list = list.filter(tp => tp.mapel.toLowerCase() === selectedSubjectFilter.value.toLowerCase());
  }

  if (selectedStatusFilter.value === 'Sudah') {
    list = list.filter(tp => tp.sudahDinilai);
  } else if (selectedStatusFilter.value === 'Belum') {
    list = list.filter(tp => !tp.sudahDinilai);
  }

  return list;
});

const totalTPSudahDinilai = computed(() => props.nilaiTPList.filter(tp => tp.sudahDinilai).length);
const totalTPBelumDinilai = computed(() => props.nilaiTPList.filter(tp => !tp.sudahDinilai).length);
const rataRataNilaiSudah = computed(() => {
  const scored = props.nilaiTPList.filter(tp => tp.sudahDinilai && tp.nilai !== null);
  if (scored.length === 0) return 0;
  const sum = scored.reduce((a, b) => a + b.nilai, 0);
  return (sum / scored.length).toFixed(1);
});
</script>

<template>
  <div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Daftar Nilai Per TP (Tujuan Pembelajaran)</h1>
        <p class="text-gray-400 mt-1">Transkrip lengkap pencapaian Tujuan Pembelajaran per mata pelajaran — terhubung dari Dashboard Guru</p>
      </div>

      <div class="flex items-center gap-2">
        <span class="bg-emerald-500/10 text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl border border-emerald-500/20">
          ✓ Rata-Rata TP: {{ rataRataNilaiSudah }}
        </span>
      </div>
    </div>

    <!-- Info Sync Banner with Guru Dashboard -->
    <div class="bg-gradient-to-r from-blue-900/30 via-indigo-900/20 to-slate-900 border border-blue-500/30 p-4 rounded-2xl mb-6 flex items-start gap-3 shadow-lg">
      <div class="text-xl">ℹ️</div>
      <div class="text-xs leading-relaxed">
        <span class="font-bold text-white block mb-0.5">Integrasi Data Penilaian Guru (/guru/dashboard)</span>
        <span class="text-gray-300">
          Data nilai per Tujuan Pembelajaran (TP) di bawah ini bersumber langsung dari menu <strong>Nilai Siswa</strong> pada Dashboard Guru. Siswa dapat dengan mudah mengidentifikasi TP mana yang sudah mendapatkan nilai dan TP mana yang <strong>belum dinilai</strong> oleh guru pengajar.
        </span>
      </div>
    </div>

    <!-- Alert Banner for Ungraded TPs if any -->
    <div v-if="totalTPBelumDinilai > 0" class="bg-amber-500/10 border border-amber-500/30 p-4 rounded-2xl mb-6 flex items-center justify-between shadow-lg">
      <div class="flex items-center gap-3">
        <span class="w-3 h-3 rounded-full bg-amber-400 animate-ping"></span>
        <div>
          <span class="font-bold text-amber-300 text-xs block">Perhatian: {{ totalTPBelumDinilai }} Tujuan Pembelajaran (TP) Belum Dinilai</span>
          <span class="text-[11px] text-gray-300">Ada {{ totalTPBelumDinilai }} TP yang masih dalam proses pengoreksian/penilaian oleh guru mata pelajaran.</span>
        </div>
      </div>
      <button @click="selectedStatusFilter = 'Belum'" class="px-3.5 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 font-bold text-xs border border-amber-500/30 transition-all shrink-0">
        Tampilkan TP Belum Dinilai →
      </button>
    </div>

    <!-- Stat Summary Cards -->
    <div class="grid grid-cols-3 gap-4 mb-8">
      <div class="bg-slate-900/80 backdrop-blur-xl p-5 rounded-2xl border border-white/10 shadow-lg">
        <div class="text-xs text-gray-400 font-semibold mb-1">Total TP Pembelajaran</div>
        <div class="text-2xl font-black text-white">{{ nilaiTPList.length }} <span class="text-xs font-normal text-gray-400">Tujuan</span></div>
      </div>

      <div class="bg-emerald-500/10 backdrop-blur-xl p-5 rounded-2xl border border-emerald-500/30 shadow-lg">
        <div class="text-xs text-emerald-300 font-semibold mb-1">🟢 Sudah Dinilai Guru</div>
        <div class="text-2xl font-black text-emerald-400">{{ totalTPSudahDinilai }} <span class="text-xs font-normal text-emerald-300">TP Tuntas</span></div>
      </div>

      <div class="bg-amber-500/10 backdrop-blur-xl p-5 rounded-2xl border border-amber-500/30 shadow-lg">
        <div class="text-xs text-amber-300 font-semibold mb-1">🟡 Belum Dinilai (Proses Guru)</div>
        <div class="text-2xl font-black text-amber-400">{{ totalTPBelumDinilai }} <span class="text-xs font-normal text-amber-300">TP Menunggu</span></div>
      </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="grid sm:grid-cols-2 gap-4 mb-6 max-w-xl">
      <div>
        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Filter Mata Pelajaran</label>
        <select v-model="selectedSubjectFilter" class="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:border-cyan-400 outline-none">
          <option value="Semua">Semua Mata Pelajaran</option>
          <option v-for="s in subjects" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Filter Status Penilaian</label>
        <select v-model="selectedStatusFilter" class="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:border-cyan-400 outline-none">
          <option value="Semua">Semua Status TP</option>
          <option value="Sudah">🟢 Sudah Dinilai</option>
          <option value="Belum">🟡 Belum Dinilai Guru</option>
        </select>
      </div>
    </div>

    <!-- Datatable & Mobile Cards Container -->
    <div class="bg-white/5 rounded-2xl border border-white/10 overflow-hidden shadow-xl">
      <!-- Desktop Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-300">
          <thead class="bg-white/5 text-gray-400 uppercase text-xs">
            <tr>
              <th class="px-6 py-4">Kode TP</th>
              <th class="px-6 py-4">Mata Pelajaran</th>
              <th class="px-6 py-4">Deskripsi Tujuan Pembelajaran (TP)</th>
              <th class="px-6 py-4 text-center">Status Penilaian</th>
              <th class="px-6 py-4 text-center">Nilai TP</th>
              <th class="px-6 py-4 text-center">Predikat</th>
              <th class="px-6 py-4">Catatan Evaluasi Guru</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            <tr v-for="tp in filteredTPList" :key="tp.id" :class="['transition-colors', !tp.sudahDinilai ? 'bg-amber-500/5 hover:bg-amber-500/10' : 'hover:bg-white/5']">
              <td class="px-6 py-4 font-mono text-xs text-cyan-300 font-bold">{{ tp.kodeTP }}</td>
              <td class="px-6 py-4">
                <span class="bg-cyan-500/20 text-cyan-300 text-xs font-semibold px-3 py-1 rounded-full border border-cyan-500/30">
                  {{ tp.mapel }}
                </span>
              </td>
              <td class="px-6 py-4 font-semibold text-white text-xs max-w-xs">{{ tp.deskripsi }}</td>
              
              <!-- Status Indicator: Sudah Dinilai vs Belum Dinilai -->
              <td class="px-6 py-4 text-center text-xs">
                <span v-if="tp.sudahDinilai" class="bg-emerald-500/20 text-emerald-300 font-bold px-3 py-1 rounded-full border border-emerald-500/30 inline-flex items-center gap-1">
                  🟢 Sudah Dinilai
                </span>
                <span v-else class="bg-amber-500/20 text-amber-300 font-bold px-3 py-1 rounded-full border border-amber-500/30 inline-flex items-center gap-1">
                  🟡 Belum Dinilai Guru
                </span>
              </td>

              <td class="px-6 py-4 text-center font-mono font-black text-base">
                <span v-if="tp.sudahDinilai" class="text-emerald-400 bg-emerald-500/10 px-3 py-1 rounded-lg border border-emerald-500/20">
                  {{ tp.nilai }}
                </span>
                <span v-else class="text-amber-400 bg-amber-500/10 px-3 py-1 rounded-lg border border-amber-500/20 text-xs font-bold">
                  Belum Ada Nilai
                </span>
              </td>

              <td class="px-6 py-4 text-center font-bold font-mono text-xs">
                <span v-if="tp.sudahDinilai" class="text-emerald-300">{{ tp.predikat }}</span>
                <span v-else class="text-gray-500">-</span>
              </td>

              <td class="px-6 py-4 text-xs">
                <span v-if="tp.sudahDinilai" class="text-gray-300 italic">
                  "{{ tp.catatanGuru || 'Sangat baik dalam menguasai TP ini.' }}"
                </span>
                <span v-else class="text-amber-300 font-medium text-[11px] flex items-center gap-1">
                  ⚠️ Menunggu penilaian dari guru {{ tp.mapel }}
                </span>
              </td>
            </tr>
            <tr v-if="filteredTPList.length === 0">
              <td colspan="7" class="px-6 py-8 text-center text-gray-400">Tidak ada daftar TP yang cocok dengan filter yang dipilih.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card List View (md:hidden) -->
      <div class="md:hidden p-4 divide-y divide-white/10">
        <div
          v-for="tp in filteredTPList"
          :key="'mobile-' + tp.id"
          class="py-4 first:pt-0 last:pb-0 space-y-2"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="font-mono text-xs text-cyan-300 font-bold bg-cyan-500/10 px-2.5 py-0.5 rounded-md border border-cyan-500/20">
              {{ tp.kodeTP }}
            </span>
            <span v-if="tp.sudahDinilai" class="bg-emerald-500/20 text-emerald-300 font-bold text-[10px] px-2.5 py-0.5 rounded-full border border-emerald-500/30">
              🟢 Sudah Dinilai
            </span>
            <span v-else class="bg-amber-500/20 text-amber-300 font-bold text-[10px] px-2.5 py-0.5 rounded-full border border-amber-500/30">
              🟡 Belum Dinilai Guru
            </span>
          </div>

          <div class="flex items-center justify-between gap-2">
            <span class="bg-cyan-500/20 text-cyan-300 text-xs font-semibold px-2.5 py-0.5 rounded-full border border-cyan-500/30">
              {{ tp.mapel }}
            </span>
            <div v-if="tp.sudahDinilai" class="flex items-center gap-1.5">
              <span class="text-xs text-gray-400">Nilai:</span>
              <span class="text-emerald-400 font-mono font-black text-sm bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                {{ tp.nilai }}
              </span>
              <span class="text-emerald-300 font-mono text-xs font-bold">({{ tp.predikat }})</span>
            </div>
            <span v-else class="text-amber-400 text-xs font-bold bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
              Belum Ada Nilai
            </span>
          </div>

          <p class="text-xs text-white font-medium leading-relaxed">{{ tp.deskripsi }}</p>

          <div class="text-[11px] pt-1 border-t border-white/5">
            <span v-if="tp.sudahDinilai" class="text-gray-300 italic">
              💬 "{{ tp.catatanGuru || 'Sangat baik dalam menguasai TP ini.' }}"
            </span>
            <span v-else class="text-amber-300 flex items-center gap-1">
              ⚠️ Menunggu penilaian dari guru {{ tp.mapel }}
            </span>
          </div>
        </div>

        <div v-if="filteredTPList.length === 0" class="py-8 text-center text-gray-400 text-xs">
          Tidak ada daftar TP yang cocok dengan filter yang dipilih.
        </div>
      </div>
    </div>
  </div>
</template>
