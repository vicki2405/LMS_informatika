<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  jadwalList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] },
  teacherSubject: { type: String, default: 'Informatika' },
  mapelList: { type: Array, default: () => [] }
});

const emit = defineEmits(['save-jadwal', 'delete-jadwal', 'change-view']);

const activeDayTab = ref('Senin');
const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

// Jam ke list (urutan saja, waktu fleksibel)
const jamList = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

// Filter jadwal for active day
const filteredJadwalByDay = computed(() => {
  return jamList.map(jamKe => {
    const existing = props.jadwalList.find(item => item.hari === activeDayTab.value && item.jamKe === jamKe);
    return {
      jamKe,
      jadwal: existing || null
    };
  });
});

// Modal
const showJadwalModal = ref(false);
const editingJadwal = ref(null);
const selectedTingkat = ref('X');
const jadwalForm = ref({
  hari: 'Senin',
  jamKe: 1,
  waktu: '',
  kelas: '',
  mataPelajaran: ''
});

const filteredKelasByTingkat = computed(() => {
  return (props.kelasList || []).filter(k => (k.tingkat || 'X') === selectedTingkat.value);
});

function onTingkatChange() {
  const available = filteredKelasByTingkat.value;
  jadwalForm.value.kelas = available.length > 0 ? (available[0].namaKelas || available[0]) : '';
}

function openAddJadwal(jamKe) {
  editingJadwal.value = null;
  let defaultKelas = '';
  const firstKelasObj = props.kelasList?.[0];
  if (firstKelasObj) {
    selectedTingkat.value = firstKelasObj.tingkat || 'X';
    defaultKelas = firstKelasObj.namaKelas || firstKelasObj;
  }
  jadwalForm.value = {
    hari: activeDayTab.value,
    jamKe: jamKe || 1,
    waktu: '',
    kelas: defaultKelas,
    mataPelajaran: teacherAssignedMapels.value[0] || ''
  };
  showJadwalModal.value = true;
}

function openEditJadwal(j) {
  editingJadwal.value = j;
  const className = j.kelas || '';
  if (className.toUpperCase().startsWith('XII')) selectedTingkat.value = 'XII';
  else if (className.toUpperCase().startsWith('XI')) selectedTingkat.value = 'XI';
  else selectedTingkat.value = 'X';

  jadwalForm.value = {
    hari: j.hari || activeDayTab.value,
    jamKe: j.jamKe || 1,
    waktu: j.waktu || '',
    kelas: className,
    mataPelajaran: j.mataPelajaran || teacherAssignedMapels.value[0] || ''
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
  return list.length > 0 ? list : (props.mapelList || []).map(m => m.nama);
});
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Jadwal Mengajar</span>
          <span class="text-cyan-400 text-base">📅</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">Waktu pelajaran fleksibel sesuai kebutuhan</p>
      </div>
    </div>

    <!-- Day Tabs -->
    <div class="flex items-center gap-2 border-b border-white/10 pb-3 overflow-x-auto custom-scrollbar">
      <button
        v-for="d in days"
        :key="d"
        @click="activeDayTab = d"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 whitespace-nowrap min-h-[44px]',
          activeDayTab === d
            ? 'bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 shadow-lg shadow-cyan-500/25 border border-cyan-400'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        📅 {{ d }}
      </button>
    </div>

    <!-- Matriks Table -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      <div class="p-4 sm:p-5 border-b border-white/10 flex items-center justify-between">
        <h2 class="text-base font-black text-white flex items-center gap-2">
          📋 Matriks Jam — Hari {{ activeDayTab }}
        </h2>
      </div>

      <!-- Desktop Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300 border-collapse min-w-[600px]">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="py-4 px-4 w-[8%] text-center">Jam</th>
              <th class="py-4 px-6 w-[20%]">Waktu</th>
              <th class="py-4 px-6 w-[22%]">Kelas</th>
              <th class="py-4 px-6 w-[25%]">Mata Pelajaran</th>
              <th class="py-4 px-6 text-right w-[25%]">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr
              v-for="item in filteredJadwalByDay"
              :key="item.jamKe"
              :class="['transition-colors duration-150', item.jadwal ? 'bg-slate-800/40 hover:bg-slate-800/80' : 'hover:bg-white/5']"
            >
              <!-- Jam Ke -->
              <td class="py-4 px-4 text-center">
                <span class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-300 font-black text-xs flex items-center justify-center border border-cyan-500/30 font-mono mx-auto">
                  {{ item.jamKe }}
                </span>
              </td>

              <!-- Waktu -->
              <td class="py-4 px-6 font-mono font-bold text-gray-300 whitespace-nowrap">
                <template v-if="item.jadwal">
                  {{ item.jadwal.waktu || '—' }}
                </template>
                <template v-else>
                  <span class="text-gray-600">—</span>
                </template>
              </td>

              <!-- Kelas -->
              <td class="py-4 px-6 whitespace-nowrap">
                <template v-if="item.jadwal">
                  <span class="px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                    🏫 {{ item.jadwal.kelas }}
                  </span>
                </template>
                <template v-else>
                  <span class="text-gray-500 italic text-xs">Kosong</span>
                </template>
              </td>

              <!-- Mata Pelajaran -->
              <td class="py-4 px-6 truncate">
                <template v-if="item.jadwal">
                  <span class="font-extrabold text-white text-sm">{{ item.jadwal.mataPelajaran }}</span>
                </template>
                <template v-else>
                  <span class="text-gray-600">—</span>
                </template>
              </td>

              <!-- Aksi -->
              <td class="py-4 px-6 text-right whitespace-nowrap">
                <template v-if="item.jadwal">
                  <div class="flex items-center justify-end gap-2">
                    <button @click="openEditJadwal(item.jadwal)" class="px-3.5 py-1.5 text-xs font-bold text-cyan-300 bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 transition-all active:scale-95 min-h-[36px]">✏️ Edit</button>
                    <button @click="$emit('delete-jadwal', item.jadwal.id)" class="px-3.5 py-1.5 text-xs font-bold text-red-400 bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 transition-all active:scale-95 min-h-[36px]">🗑️ Hapus</button>
                  </div>
                </template>
                <template v-else>
                  <button @click="openAddJadwal(item.jamKe)" class="px-4 py-1.5 text-xs font-bold text-emerald-300 bg-emerald-500/15 hover:bg-emerald-500/30 rounded-xl border border-emerald-500/30 transition-all active:scale-95 min-h-[36px]">+ Isi</button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Smartphone Card-List View (md:hidden) -->
      <div class="md:hidden divide-y divide-white/5 p-3 space-y-2.5">
        <div
          v-for="item in filteredJadwalByDay"
          :key="'mob-' + item.jamKe"
          :class="[
            'p-4 rounded-2xl border transition-all',
            item.jadwal ? 'bg-slate-800/60 border-cyan-500/30 shadow-md' : 'bg-white/[0.02] border-white/5'
          ]"
        >
          <div class="flex items-start justify-between gap-3 mb-2">
            <div class="flex items-center gap-2">
              <span class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-300 font-mono font-black text-xs flex items-center justify-center border border-cyan-500/30 shrink-0">
                {{ item.jamKe }}
              </span>
              <div>
                <div class="text-xs font-mono text-gray-400 font-bold">
                  {{ item.jadwal?.waktu || 'Waktu Fleksibel' }}
                </div>
                <div v-if="item.jadwal" class="text-sm font-bold text-white mt-0.5">
                  {{ item.jadwal.mataPelajaran }}
                </div>
                <div v-else class="text-xs text-gray-500 italic mt-0.5">
                  Slot Belum Terisi
                </div>
              </div>
            </div>

            <span
              v-if="item.jadwal"
              class="px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 shrink-0"
            >
              🏫 {{ item.jadwal.kelas }}
            </span>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/5">
            <template v-if="item.jadwal">
              <button
                @click="openEditJadwal(item.jadwal)"
                class="px-3.5 py-2 text-xs font-bold text-cyan-300 bg-cyan-500/15 rounded-xl border border-cyan-500/30 active:scale-95 transition-all min-h-[36px]"
              >
                ✏️ Edit
              </button>
              <button
                @click="$emit('delete-jadwal', item.jadwal.id)"
                class="px-3.5 py-2 text-xs font-bold text-red-400 bg-red-500/15 rounded-xl border border-red-500/30 active:scale-95 transition-all min-h-[36px]"
              >
                🗑️ Hapus
              </button>
            </template>
            <template v-else>
              <button
                @click="openAddJadwal(item.jamKe)"
                class="w-full py-2 text-xs font-bold text-emerald-300 bg-emerald-500/15 rounded-xl border border-emerald-500/30 active:scale-95 transition-all flex items-center justify-center gap-1.5 min-h-[40px]"
              >
                <span>+</span>
                <span>Jadwalkan Jam Ke-{{ item.jamKe }}</span>
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL FORM -->
    <div v-if="showJadwalModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto space-y-5 animate-in slide-in-from-bottom duration-200 sm:animate-in sm:fade-in sm:zoom-in-95 pb-24 sm:pb-6">
        <div class="w-12 h-1 bg-white/20 rounded-full mx-auto sm:hidden"></div>
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">{{ editingJadwal ? 'Edit Jadwal' : 'Tambah Jadwal' }}</h3>
          <button @click="showJadwalModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSaveJadwal" class="space-y-4 text-xs sm:text-sm">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Hari *</label>
              <select v-model="jadwalForm.hari" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none min-h-[44px]" required>
                <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Jam Ke- *</label>
              <select v-model.number="jadwalForm.jamKe" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none min-h-[44px]" required>
                <option v-for="j in jamList" :key="j" :value="j">Jam {{ j }}</option>
              </select>
            </div>
          </div>

          <!-- Input Waktu Bebas -->
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Waktu Pelajaran (Opsional)</label>
            <input v-model="jadwalForm.waktu" type="text" placeholder="Contoh: 08.00 - 09.30, atau Bebas" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none min-h-[44px]" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Tingkat *</label>
              <select v-model="selectedTingkat" @change="onTingkatChange" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none min-h-[44px]" required>
                <option value="X">X</option>
                <option value="XI">XI</option>
                <option value="XII">XII</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Kelas *</label>
              <select v-model="jadwalForm.kelas" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm font-semibold min-h-[44px]" required>
                <option value="" disabled>-- Pilih Kelas --</option>
                <option v-for="k in filteredKelasByTingkat" :key="k.id || k.namaKelas" :value="k.namaKelas || k">
                  {{ k.namaKelas || k }} {{ k.jurusan ? '(' + k.jurusan + ')' : '' }}
                </option>
              </select>
            </div>
          </div>

          <div v-if="filteredKelasByTingkat.length === 0" class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-2xl text-xs text-amber-300">
            ⚠️ Belum ada kelas Tingkat {{ selectedTingkat }}
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Mata Pelajaran *</label>
            <select v-model="jadwalForm.mataPelajaran" class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-cyan-400 outline-none min-h-[44px]" required>
              <option value="" disabled>-- Pilih Mapel --</option>
              <option v-for="name in teacherAssignedMapels" :key="name" :value="name">{{ name }}</option>
            </select>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button type="button" @click="showJadwalModal = false" class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl text-xs font-bold min-h-[44px]">Batal</button>
            <button type="submit" class="px-5 py-3 bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 rounded-2xl text-xs font-black shadow-lg hover:shadow-cyan-500/25 active:scale-95 transition-all min-h-[44px]">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
