<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  rekapTugasList: { type: Array, default: () => [] },
  rekapNilaiCatatanList: { type: Array, default: () => [] },
  kelasBinaan: { type: String, default: 'X RPL 1' }
});

const searchStudent = ref('');
const selectedSubject = ref('Semua');
const selectedStatus = ref('Semua');
const sourceFilter = ref('Semua');

// Modal Detail Progres State
const showDetailModal = ref(false);
const selectedStudentDetail = ref(null);

const combinedList = computed(() => {
  return [...(props.rekapTugasList || []), ...(props.rekapNilaiCatatanList || [])];
});

const sourceCounts = computed(() => {
  const list = combinedList.value;
  return {
    semua: list.length,
    tugas: list.filter(t => t.source === 'Tugas').length,
    nilai: list.filter(t => t.source === 'Nilai TP').length
  };
});

const subjects = computed(() => {
  const set = new Set(combinedList.value.map(t => t.mapel).filter(Boolean));
  return [...set].sort();
});

const filteredTugasList = computed(() => {
  let list = [...combinedList.value];

  if (sourceFilter.value !== 'Semua') {
    list = list.filter(t => t.source === sourceFilter.value);
  }

  if (selectedSubject.value !== 'Semua') {
    list = list.filter(t => t.mapel.toLowerCase() === selectedSubject.value.toLowerCase());
  }

  if (selectedStatus.value !== 'Semua') {
    list = list.filter(t => t.status.toLowerCase() === selectedStatus.value.toLowerCase());
  }

  if (searchStudent.value.trim()) {
    const q = searchStudent.value.toLowerCase();
    list = list.filter(t =>
      t.namaSiswa.toLowerCase().includes(q) ||
      t.nis.includes(q) ||
      t.judulTugas.toLowerCase().includes(q) ||
      t.guruPengajar.toLowerCase().includes(q)
    );
  }

  list.sort((a, b) => a.namaSiswa.localeCompare(b.namaSiswa));

  return list;
});

const rataRataKetuntasan = computed(() => {
  const list = combinedList.value;
  if (list.length === 0) return '0%';
  const tuntasCount = list.filter(t => t.status === 'Tuntas').length;
  return ((tuntasCount / list.length) * 100).toFixed(1) + '%';
});

function viewStudentTaskDetail(item) {
  selectedStudentDetail.value = item;
  showDetailModal.value = true;
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Ketuntasan Tugas Siswa</span>
          <span class="text-indigo-400 text-base">📋</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">Laporan rekap pengumpulkan dan ketuntasan tugas siswa {{ kelasBinaan }} dari setiap guru mata pelajaran</p>
      </div>

      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <span class="bg-slate-800/80 text-gray-300 text-xs font-bold px-4 py-2 rounded-2xl border border-white/10 shadow-md">
          📚 Tugas: {{ sourceCounts.tugas }} · 📊 Nilai TP: {{ sourceCounts.nilai }}
        </span>
        <span class="bg-emerald-500/20 text-emerald-300 text-xs font-black px-4 py-2 rounded-2xl border border-emerald-500/30 shadow-md">
          ✓ Rata-Rata Ketuntasan: {{ rataRataKetuntasan }}
        </span>
      </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">🔍 Cari Siswa / Tugas</label>
          <input
            v-model="searchStudent"
            type="text"
            placeholder="Cari Nama Siswa, NIS, atau Tugas..."
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-indigo-400 outline-none min-h-[44px]"
          />
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">🗂️ Sumber Data</label>
          <select v-model="sourceFilter" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-indigo-400 outline-none min-h-[44px]">
            <option value="Semua">Semua ({{ sourceCounts.semua }})</option>
            <option value="Tugas">Tugas / Pengumpulan ({{ sourceCounts.tugas }})</option>
            <option value="Nilai TP">Nilai TP &amp; Notif ({{ sourceCounts.nilai }})</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">📖 Mata Pelajaran</label>
          <select v-model="selectedSubject" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-indigo-400 outline-none min-h-[44px]">
            <option value="Semua">Semua Mata Pelajaran</option>
            <option v-for="s in subjects" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">🏷️ Status Ketuntasan</label>
          <select v-model="selectedStatus" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white font-semibold focus:border-indigo-400 outline-none min-h-[44px]">
            <option value="Semua">Semua Status Ketuntasan</option>
            <option value="Tuntas">Status: Tuntas</option>
            <option value="Belum Tuntas">Status: Belum Tuntas</option>
            <option value="Remedial">Status: Perlu Remedial</option>
            <option value="Belum Dinilai">Status: Belum Dinilai</option>
          </select>
        </div>

      </div>
    </div>

    <!-- Tabel Data Ketuntasan Tugas -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300 border-collapse table-fixed min-w-[1080px]">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="py-4 px-4 w-[4%] text-center">No</th>
              <th class="py-4 px-6 w-[18%]">Nama Siswa</th>
              <th class="py-4 px-6 w-[12%]">Mata Pelajaran</th>
              <th class="py-4 px-6 w-[12%]">Guru Pengajar</th>
              <th class="py-4 px-6 w-[18%]">Modul Tugas</th>
              <th class="py-4 px-4 w-[6%] text-center">Nilai</th>
              <th class="py-4 px-6 w-[10%] text-center">Status</th>
              <th class="py-4 px-6 w-[14%]">Catatan Guru</th>
              <th class="py-4 px-6 w-[8%] text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr v-for="(t, idx) in filteredTugasList" :key="t.id" class="hover:bg-white/5 transition-colors">
              <td class="py-4 px-4 text-center font-mono text-xs text-indigo-300 font-bold align-middle">{{ idx + 1 }}</td>
              
              <td class="py-4 px-6 font-extrabold text-white align-middle truncate" :title="t.namaSiswa">
                {{ t.namaSiswa }}
                <div class="text-[11px] font-mono text-gray-400 font-normal">NIS: {{ t.nis }}</div>
              </td>

              <td class="py-4 px-6 align-middle">
                <span class="bg-indigo-500/20 text-indigo-300 text-xs font-black px-3 py-1 rounded-full border border-indigo-500/30 inline-block">
                  {{ t.mapel }}
                </span>
              </td>

              <td class="py-4 px-6 text-xs text-gray-300 align-middle truncate" :title="t.guruPengajar">{{ t.guruPengajar }}</td>
              <td class="py-4 px-6 text-xs text-gray-300 align-middle truncate" :title="t.judulTugas">
                <template v-if="t.source === 'Nilai TP'">
                  <span class="text-cyan-300 font-black">{{ t.kodeTp }}</span>
                  <span class="text-gray-500">·</span>
                  <span class="text-gray-400">{{ t.semester }}</span> — {{ t.judulTugas }}
                </template>
                <template v-else>{{ t.judulTugas }}</template>
              </td>
              
              <td class="py-4 px-4 text-center font-mono font-black text-white text-base align-middle">
                {{ t.nilai || '-' }}
              </td>

              <td class="py-4 px-6 text-center align-middle">
                <span
                  :class="[
                    'px-3 py-1 rounded-full text-[11px] font-black border inline-block',
                    t.status === 'Tuntas' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                    t.status === 'Remedial' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' :
                    t.status === 'Belum Dinilai' ? 'bg-gray-500/20 text-gray-400 border-gray-500/30' :
                    'bg-red-500/20 text-red-300 border-red-500/30'
                  ]"
                >
                  {{ t.status }}
                </span>
              </td>

              <td class="py-4 px-6 text-xs text-gray-300 align-middle truncate" :title="t.catatan">
                <span v-if="t.catatan" class="text-cyan-200 font-semibold">💬 {{ t.catatan }}</span>
                <span v-else class="text-gray-500 font-normal">-</span>
              </td>

              <td class="py-4 px-6 text-right whitespace-nowrap align-middle">
                <button @click="viewStudentTaskDetail(t)" class="text-indigo-300 hover:text-white font-bold text-xs bg-indigo-500/15 hover:bg-indigo-500/30 px-3.5 py-1.5 rounded-xl border border-indigo-500/30 transition-all active:scale-95">
                  Detail
                </button>
              </td>
            </tr>

            <tr v-if="filteredTugasList.length === 0">
              <td colspan="9" class="py-12 text-center text-gray-400 text-xs font-medium">Tidak ada laporan ketuntasan tugas yang sesuai.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL DETAIL PROGRES TUGAS SISWA -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-white/10 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-5 transform transition-all animate-in fade-in zoom-in-95">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">Laporan Ketuntasan Tugas Siswa</h3>
          <span class="text-xs bg-indigo-500/20 text-indigo-300 font-mono font-black px-3.5 py-1 rounded-full border border-indigo-500/30">
            NIS: {{ selectedStudentDetail?.nis }}
          </span>
        </div>

        <div class="space-y-3 bg-slate-950/60 p-5 rounded-2xl border border-white/10 text-xs sm:text-sm">
          <div class="flex justify-between items-center"><span class="text-gray-400 font-medium">Nama Siswa:</span><span class="font-black text-white">{{ selectedStudentDetail?.namaSiswa }}</span></div>
          <div class="flex justify-between items-center"><span class="text-gray-400 font-medium">Mata Pelajaran:</span><span class="font-extrabold text-indigo-300">{{ selectedStudentDetail?.mapel }}</span></div>
          <div class="flex justify-between items-center"><span class="text-gray-400 font-medium">Guru Pengajar:</span><span class="text-gray-200 font-semibold">{{ selectedStudentDetail?.guruPengajar }}</span></div>
          <div class="flex justify-between items-center"><span class="text-gray-400 font-medium">Modul Tugas:</span><span class="text-gray-200 font-semibold">{{ selectedStudentDetail?.judulTugas }}</span></div>
          <div class="flex justify-between items-center"><span class="text-gray-400 font-medium">Tanggal Mengumpulkan:</span><span class="font-mono text-cyan-300 font-bold">{{ selectedStudentDetail?.tanggalKumpul || '24 Jul 2026' }}</span></div>
          <div class="flex justify-between items-center pt-2 border-t border-white/10"><span class="text-gray-400 font-medium">Nilai Tugas:</span><span class="font-mono font-black text-xl text-emerald-400">{{ selectedStudentDetail?.nilai ?? '—' }} / 100</span></div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400 font-medium">Status Ketuntasan:</span>
            <span
              :class="[
                'px-3 py-1 rounded-full text-xs font-black border',
                selectedStudentDetail?.status === 'Tuntas' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                selectedStudentDetail?.status === 'Remedial' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' :
                selectedStudentDetail?.status === 'Belum Dinilai' ? 'bg-gray-500/20 text-gray-400 border-gray-500/30' :
                'bg-red-500/20 text-red-300 border-red-500/30'
              ]"
            >
              {{ selectedStudentDetail?.status }}
            </span>
          </div>
          <div v-if="selectedStudentDetail?.catatan" class="pt-2 border-t border-white/10">
            <span class="text-gray-400 font-medium block mb-1">Catatan Guru:</span>
            <span class="text-cyan-200 font-semibold text-sm">💬 {{ selectedStudentDetail.catatan }}</span>
          </div>
        </div>

        <div class="flex items-center justify-end border-t border-white/10 pt-4">
          <button @click="showDetailModal = false" class="px-6 py-2.5 rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-xs font-black shadow-lg hover:opacity-90 active:scale-95 transition-all">
            Tutup
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
