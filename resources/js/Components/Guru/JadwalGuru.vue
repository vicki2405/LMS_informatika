<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  jadwalList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  masterKelasList: { type: Array, default: () => [] },
  teacherSubject: { type: String, default: 'Informatika' },
  mapelList: { type: Array, default: () => [] }
});

const emit = defineEmits(['save-jadwal', 'delete-jadwal', 'change-view', 'go-to-absensi', 'go-to-jurnal']);

const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const jamList = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

// Otomatis deteksi hari ini: jika hari sekolah (Senin-Sabtu), langsung tampilkan hari ini
const dayMap = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const todayName = dayMap[new Date().getDay()];
const activeDayTab = ref(days.includes(todayName) ? todayName : 'Senin');

// Filter jadwal sesuai hari yang aktif
const filteredJadwalByDay = computed(() => {
  return jamList.map(jamKe => {
    const existing = (props.jadwalList || []).find(
      item => item.hari === activeDayTab.value && Number(item.jamKe || item.jam_ke) === jamKe
    );
    return {
      jamKe,
      jadwal: existing || null
    };
  });
});

// Hitung total jam terisi pada hari yang aktif
const activeDayFilledCount = computed(() => {
  return filteredJadwalByDay.value.filter(i => i.jadwal !== null).length;
});

// Pool master kelas agar guru baru tidak terkunci
const allAvailableClasses = computed(() => {
  if (props.masterKelasList && props.masterKelasList.length > 0) {
    return props.masterKelasList;
  }
  return props.kelasList || [];
});

// Modal state
const showJadwalModal = ref(false);
const editingJadwal = ref(null);
const selectedTingkat = ref('X');
const jadwalForm = ref({
  hari: 'Senin',
  jamKe: 1,
  waktu: '',
  kelas: '',
  mataPelajaran: '',
  ruangan: 'Ruang Kelas',
  keterangan: ''
});

const filteredKelasByTingkat = computed(() => {
  return allAvailableClasses.value
    .filter(k => (k.tingkat || 'X') === selectedTingkat.value)
    .sort((a, b) => (a.namaKelas || a.nama || a).localeCompare(b.namaKelas || b.nama || b, undefined, { numeric: true, sensitivity: 'base' }));
});

function onTingkatChange() {
  const available = filteredKelasByTingkat.value;
  jadwalForm.value.kelas = available.length > 0 ? (available[0].namaKelas || available[0].nama || available[0]) : '';
}

function openAddJadwal(jamKe = null) {
  editingJadwal.value = null;
  
  const firstKelasObj = allAvailableClasses.value?.[0];
  if (firstKelasObj) {
    selectedTingkat.value = firstKelasObj.tingkat || 'X';
  }
  
  const available = filteredKelasByTingkat.value;
  const defaultKelas = available.length > 0 
    ? (available[0].namaKelas || available[0].nama || available[0]) 
    : (firstKelasObj?.namaKelas || '');

  jadwalForm.value = {
    hari: activeDayTab.value,
    jamKe: jamKe || 1,
    waktu: '',
    kelas: defaultKelas,
    mataPelajaran: teacherAssignedMapels.value[0] || (props.teacherSubject || 'Informatika'),
    ruangan: 'Ruang Kelas',
    keterangan: ''
  };
  showJadwalModal.value = true;
}

function openEditJadwal(j) {
  editingJadwal.value = j;
  const className = j.kelas || j.namaKelas || '';
  
  if (className.toUpperCase().startsWith('XII')) selectedTingkat.value = 'XII';
  else if (className.toUpperCase().startsWith('XI')) selectedTingkat.value = 'XI';
  else selectedTingkat.value = 'X';

  jadwalForm.value = {
    hari: j.hari || activeDayTab.value,
    jamKe: Number(j.jamKe || j.jam_ke || 1),
    waktu: j.waktu || '',
    kelas: className,
    mataPelajaran: j.mataPelajaran || j.mata_pelajaran || teacherAssignedMapels.value[0] || '',
    ruangan: j.ruangan || 'Ruang Kelas',
    keterangan: j.keterangan && j.keterangan !== '-' ? j.keterangan : ''
  };
  showJadwalModal.value = true;
}

function handleSaveJadwal() {
  if (!jadwalForm.value.hari || !jadwalForm.value.kelas) return;
  emit('save-jadwal', { editing: editingJadwal.value, data: { ...jadwalForm.value } });
  showJadwalModal.value = false;
}

const teacherAssignedMapels = computed(() => {
  const subjectStr = props.teacherSubject || '';
  const list = subjectStr.split(',').map(s => s.trim()).filter(s => s.length > 0);
  if (list.length > 0) return list;
  return (props.mapelList || []).map(m => m.nama || m);
});

// Presets untuk modal
const quickTimePresets = ['07:15 - 08:45', '08:45 - 10:15', '10:30 - 12:00', '12:45 - 14:15'];
</script>

<template>
  <div class="space-y-5 animate-in fade-in duration-200">

    <!-- Header Bersih & Ringkas -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Jadwal Mengajar</span>
          <span class="text-cyan-400 text-lg">📅</span>
        </h1>
        <p class="text-xs sm:text-sm text-gray-400 font-medium mt-0.5">
          Menampilkan jadwal tatap muka pelajaran mingguan
        </p>
      </div>

      <button
        @click="openAddJadwal()"
        class="w-full sm:w-auto px-5 py-2.5 rounded-2xl bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-cyan-500/20 hover:shadow-cyan-500/30 active:scale-95 transition-all flex items-center justify-center gap-2 min-h-[42px]"
      >
        <span class="text-base font-bold">+</span>
        <span>Tambah Jadwal</span>
      </button>
    </div>

    <!-- Tab Hari: Otomatis aktif di Hari Ini -->
    <div class="flex items-center gap-2 border-b border-white/10 pb-3 overflow-x-auto no-scrollbar sm:custom-scrollbar">
      <button
        v-for="d in days"
        :key="d"
        @click="activeDayTab = d"
        :class="[
          'px-4 sm:px-5 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all duration-150 flex items-center gap-2 shrink-0 border min-h-[42px]',
          activeDayTab === d
            ? 'bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 font-black shadow-lg shadow-cyan-500/20 border-cyan-400'
            : 'bg-slate-900/60 hover:bg-slate-800 text-gray-400 hover:text-white border-white/5'
        ]"
      >
        <span>{{ d }}</span>
        <!-- Penanda Hari Ini jika sesuai -->
        <span
          v-if="d === todayName"
          :class="[
            'px-1.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase',
            activeDayTab === d ? 'bg-slate-950 text-cyan-300' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'
          ]"
        >
          Hari Ini
        </span>
      </button>
    </div>

    <!-- TABEL UTAMA JADWAL (Satu Tampilan Tunggal & Tidak Ramai) -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      <!-- Header Tabel -->
      <div class="p-4 sm:p-5 border-b border-white/10 flex items-center justify-between bg-slate-950/50">
        <div class="flex items-center gap-2.5">
          <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
          <h2 class="text-sm sm:text-base font-black text-white">
            Jadwal Hari {{ activeDayTab }}
          </h2>
          <span v-if="activeDayTab === todayName" class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 uppercase">
            Hari Ini
          </span>
        </div>
        <span class="text-xs font-mono font-bold text-gray-400">
          {{ activeDayFilledCount }} Jam Terisi
        </span>
      </div>

      <!-- Tampilan Desktop: Tabel Bersih & Rapi -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300 border-collapse min-w-[650px]">
          <thead class="bg-slate-950/70 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="py-4 px-4 w-[8%] text-center">Jam</th>
              <th class="py-4 px-5 w-[16%]">Waktu</th>
              <th class="py-4 px-5 w-[18%]">Kelas</th>
              <th class="py-4 px-5 w-[28%]">Mata Pelajaran</th>
              <th class="py-4 px-5 text-right w-[30%]">Aksi Cepat</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr
              v-for="item in filteredJadwalByDay"
              :key="'jam-' + item.jamKe"
              :class="item.jadwal ? 'bg-slate-800/30 hover:bg-slate-800/60' : 'hover:bg-white/[0.02]'"
            >
              <!-- Jam Ke -->
              <td class="py-3.5 px-4 text-center">
                <span
                  :class="[
                    'w-8 h-8 rounded-xl font-mono font-black text-xs flex items-center justify-center mx-auto border',
                    item.jadwal
                      ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'
                      : 'bg-white/5 text-gray-500 border-white/5'
                  ]"
                >
                  {{ item.jamKe }}
                </span>
              </td>

              <!-- Waktu -->
              <td class="py-3.5 px-5 font-mono whitespace-nowrap">
                <span v-if="item.jadwal" class="font-bold text-gray-200">
                  {{ item.jadwal.waktu || 'Waktu Fleksibel' }}
                </span>
                <span v-else class="text-gray-600 italic text-xs">—</span>
              </td>

              <!-- Kelas -->
              <td class="py-3.5 px-5 whitespace-nowrap">
                <span v-if="item.jadwal" class="px-3 py-1 rounded-xl text-xs font-black bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 inline-flex items-center gap-1">
                  🏫 {{ item.jadwal.kelas || item.jadwal.namaKelas }}
                </span>
                <span v-else class="text-gray-500 italic text-xs">Kosong</span>
              </td>

              <!-- Mata Pelajaran -->
              <td class="py-3.5 px-5">
                <div v-if="item.jadwal">
                  <div class="font-bold text-white text-sm">
                    {{ item.jadwal.mataPelajaran || item.jadwal.mata_pelajaran }}
                  </div>
                  <div v-if="item.jadwal.keterangan && item.jadwal.keterangan !== '-'" class="text-[11px] text-gray-400">
                    {{ item.jadwal.keterangan }}
                  </div>
                </div>
                <span v-else class="text-gray-600 text-xs">—</span>
              </td>


              <!-- Aksi -->
              <td class="py-3.5 px-5 text-right whitespace-nowrap">
                <div v-if="item.jadwal" class="flex items-center justify-end gap-1.5">
                  <button
                    @click="$emit('go-to-absensi', { kelas: item.jadwal.kelas || item.jadwal.namaKelas, tanggal: getTodayString() })"
                    class="px-2.5 py-1.5 text-xs font-bold text-emerald-300 bg-emerald-500/15 hover:bg-emerald-500/25 rounded-lg border border-emerald-500/30 transition-all active:scale-95 inline-flex items-center gap-1 shadow-sm"
                    title="Buka Input Absensi Kelas Ini"
                  >
                    📋 Presensi
                  </button>
                  <button
                    @click="$emit('go-to-jurnal', { ...item.jadwal })"
                    class="px-2.5 py-1.5 text-xs font-bold text-indigo-300 bg-indigo-500/15 hover:bg-indigo-500/25 rounded-lg border border-indigo-500/30 transition-all active:scale-95 inline-flex items-center gap-1 shadow-sm"
                    title="Buka Jurnal Mengajar Kelas Ini"
                  >
                    📝 Jurnal
                  </button>
                  <button
                    @click="openEditJadwal(item.jadwal)"
                    class="px-2 py-1.5 text-xs font-bold text-cyan-300 bg-cyan-500/10 hover:bg-cyan-500/20 rounded-lg border border-cyan-500/30 transition-all active:scale-95"
                    title="Edit Jadwal"
                  >
                    ✏️
                  </button>
                  <button
                    @click="$emit('delete-jadwal', item.jadwal.id)"
                    class="px-2 py-1.5 text-xs font-bold text-red-400 bg-red-500/10 hover:bg-red-500/20 rounded-lg border border-red-500/30 transition-all active:scale-95"
                    title="Hapus Jadwal"
                  >
                    🗑️
                  </button>
                </div>
                <button
                  v-else
                  @click="openAddJadwal(item.jamKe)"
                  class="px-3 py-1.5 text-xs font-bold text-emerald-400 bg-emerald-500/10 hover:bg-emerald-500/20 rounded-lg border border-emerald-500/20 transition-all active:scale-95"
                >
                  + Isi
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tampilan Mobile Smartphone (md:hidden): Card List Bersih -->
      <div class="md:hidden p-3 space-y-2.5">
        <div
          v-for="item in filteredJadwalByDay"
          :key="'mob-' + item.jamKe"
          :class="[
            'p-3.5 rounded-2xl border transition-all',
            item.jadwal ? 'bg-slate-900/90 border-cyan-500/30 shadow-md' : 'bg-slate-950/30 border-dashed border-white/5'
          ]"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5">
              <span
                :class="[
                  'w-8 h-8 rounded-xl font-mono font-black text-xs flex items-center justify-center shrink-0 border',
                  item.jadwal ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' : 'bg-white/5 text-gray-500 border-white/5'
                ]"
              >
                {{ item.jamKe }}
              </span>
              <div>
                <div class="text-[11px] font-mono font-bold text-gray-400">
                  {{ item.jadwal?.waktu || 'Jam Ke-' + item.jamKe }}
                </div>
                <div v-if="item.jadwal" class="text-sm font-bold text-white mt-0.5">
                  {{ item.jadwal.mataPelajaran || item.jadwal.mata_pelajaran }}
                </div>
                <div v-else class="text-xs text-gray-500 italic mt-0.5">
                  Slot Belum Terisi
                </div>
              </div>
            </div>

            <span
              v-if="item.jadwal"
              class="px-2.5 py-1 rounded-xl text-xs font-black bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shrink-0"
            >
              🏫 {{ item.jadwal.kelas || item.jadwal.namaKelas }}
            </span>
          </div>

          <div v-if="item.jadwal" class="flex items-center gap-2 text-xs text-gray-300 pt-2 border-t border-white/5 mt-2">
            <span v-if="item.jadwal.keterangan && item.jadwal.keterangan !== '-'" class="px-2 py-0.5 rounded-lg bg-white/5 border border-white/5 text-gray-400">
              📝 {{ item.jadwal.keterangan }}
            </span>
          </div>

          <!-- Aksi di Mobile -->
          <div class="pt-2 border-t border-white/5 mt-2">
            <div v-if="item.jadwal" class="grid grid-cols-2 gap-2">
              <button
                @click="openEditJadwal(item.jadwal)"
                class="py-2 text-xs font-bold text-cyan-300 bg-cyan-500/15 rounded-xl border border-cyan-500/30 flex items-center justify-center gap-1 min-h-[38px]"
              >
                ✏️ Edit
              </button>
              <button
                @click="$emit('delete-jadwal', item.jadwal.id)"
                class="py-2 text-xs font-bold text-red-400 bg-red-500/15 rounded-xl border border-red-500/30 flex items-center justify-center gap-1 min-h-[38px]"
              >
                🗑️ Hapus
              </button>
            </div>
            <button
              v-else
              @click="openAddJadwal(item.jamKe)"
              class="w-full py-2 text-xs font-bold text-emerald-400 bg-emerald-500/10 rounded-xl border border-emerald-500/20 flex items-center justify-center gap-1 min-h-[38px]"
            >
              + Isi Jam Ke-{{ item.jamKe }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL FORM INPUT -->
    <div
      v-if="showJadwalModal"
      class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 overflow-y-auto animate-in fade-in duration-150"
    >
      <div
        class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl p-5 sm:p-7 max-w-lg w-full shadow-2xl max-h-[92vh] overflow-y-auto space-y-4 animate-in slide-in-from-bottom duration-200 sm:zoom-in-95 pb-24 sm:pb-7"
      >
        <div class="w-12 h-1.5 bg-white/20 rounded-full mx-auto sm:hidden mb-1"></div>

        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="text-base sm:text-lg font-black text-white">
            {{ editingJadwal ? 'Edit Jadwal Mengajar' : 'Tambah Jadwal Mengajar' }}
          </h3>
          <button
            @click="showJadwalModal = false"
            class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white font-bold flex items-center justify-center"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="handleSaveJadwal" class="space-y-3.5 text-xs sm:text-sm">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1">Hari *</label>
              <select
                v-model="jadwalForm.hari"
                class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-white focus:border-cyan-400 outline-none font-semibold min-h-[42px]"
                required
              >
                <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1">Jam Ke- *</label>
              <select
                v-model.number="jadwalForm.jamKe"
                class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-white focus:border-cyan-400 outline-none font-mono font-bold min-h-[42px]"
                required
              >
                <option v-for="j in jamList" :key="j" :value="j">Jam Ke-{{ j }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1">Waktu Pelajaran (Opsional)</label>
            <input
              v-model="jadwalForm.waktu"
              type="text"
              placeholder="Contoh: 07:15 - 08:45"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-white focus:border-cyan-400 outline-none font-mono min-h-[42px]"
            />
            <div class="flex flex-wrap items-center gap-1.5 mt-2">
              <button
                type="button"
                v-for="preset in quickTimePresets"
                :key="preset"
                @click="jadwalForm.waktu = preset"
                class="px-2 py-0.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/5 text-[10px] font-mono text-cyan-300"
              >
                {{ preset }}
              </button>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-gray-300 font-bold uppercase text-[11px]">Tingkat & Kelas *</label>
            <div class="grid grid-cols-3 gap-2">
              <button
                type="button"
                v-for="t in ['X', 'XI', 'XII']"
                :key="t"
                @click="selectedTingkat = t; onTingkatChange();"
                :class="[
                  'py-2 rounded-xl text-xs font-black transition-all border min-h-[38px]',
                  selectedTingkat === t
                    ? 'bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 border-cyan-400'
                    : 'bg-slate-800 text-gray-400 border-white/5'
                ]"
              >
                Kelas {{ t }}
              </button>
            </div>

            <select
              v-model="jadwalForm.kelas"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-white focus:border-cyan-400 outline-none text-sm font-semibold min-h-[42px] mt-1"
              required
            >
              <option value="" disabled>-- Pilih Kelas Tujuan --</option>
              <option
                v-for="k in filteredKelasByTingkat"
                :key="k.id || k.namaKelas || k"
                :value="k.namaKelas || k.nama || k"
              >
                {{ k.namaKelas || k.nama || k }} {{ k.jurusan ? '— ' + (k.jurusan.nama || k.jurusan) : '' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1">Mata Pelajaran *</label>
            <select
              v-model="jadwalForm.mataPelajaran"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-white focus:border-cyan-400 outline-none text-sm font-semibold min-h-[42px]"
              required
            >
              <option value="" disabled>-- Pilih Mata Pelajaran --</option>
              <option v-for="name in teacherAssignedMapels" :key="name" :value="name">
                {{ name }}
              </option>
            </select>
          </div>



          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1">Keterangan (Opsional)</label>
            <input
              v-model="jadwalForm.keterangan"
              type="text"
              placeholder="Contoh: Teori, Praktik"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-3.5 py-2.5 text-white focus:border-cyan-400 outline-none text-xs sm:text-sm min-h-[42px]"
            />
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
            <button
              type="button"
              @click="showJadwalModal = false"
              class="w-1/2 sm:w-auto px-5 py-2.5 bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white rounded-2xl text-xs font-bold min-h-[42px]"
            >
              Batal
            </button>
            <button
              type="submit"
              class="w-1/2 sm:w-auto px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 font-black rounded-2xl text-xs sm:text-sm shadow-lg shadow-cyan-500/25 active:scale-95 transition-all min-h-[42px]"
            >
              {{ editingJadwal ? 'Simpan Perubahan' : 'Tambah Jadwal' }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
