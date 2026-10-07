<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  kelasBinaan: { type: String, default: 'X TKR 1' },
  jadwalList: { type: Array, default: () => [] },
  guruList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] }
});

const emit = defineEmits(['save-jadwal', 'delete-jadwal']);

const selectedHari = ref('Senin');
const searchMapel = ref('');
const showJadwalModal = ref(false);
const editingJadwal = ref(null);

const hariOptions = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

const jadwalForm = ref({
  id: null,
  hari: 'Senin',
  jam_ke: 'Jam Ke-1',
  waktu: '07.00 - 08.30 WIB',
  mata_pelajaran: '',
  guru_id: '',
  ruangan: 'Lab Komputer 1'
});

const filteredJadwalList = computed(() => {
  let list = props.jadwalList || [];

  if (selectedHari.value !== 'Semua') {
    list = list.filter(j => j.hari === selectedHari.value);
  }

  if (searchMapel.value.trim()) {
    const q = searchMapel.value.toLowerCase();
    list = list.filter(j =>
      j.mapel.toLowerCase().includes(q) ||
      (j.guru && j.guru.toLowerCase().includes(q)) ||
      (j.ruangan && j.ruangan.toLowerCase().includes(q))
    );
  }

  return list;
});

function openAddModal() {
  editingJadwal.value = null;
  jadwalForm.value = {
    id: null,
    hari: selectedHari.value !== 'Semua' ? selectedHari.value : 'Senin',
    jam_ke: `Jam Ke-${props.jadwalList.length + 1}`,
    waktu: '07.30 - 09.00 WIB',
    mata_pelajaran: '',
    guru_id: props.guruList[0]?.id || '',
    ruangan: 'Ruang Teori'
  };
  showJadwalModal.value = true;
}

function openEditModal(j) {
  editingJadwal.value = j;
  jadwalForm.value = {
    id: j.id,
    hari: j.hari,
    jam_ke: j.jamKe || j.jam_ke,
    waktu: j.waktu,
    mata_pelajaran: j.mapel,
    guru_id: j.guruId || '',
    ruangan: j.ruangan
  };
  showJadwalModal.value = true;
}

function handleSave() {
  if (!jadwalForm.value.mata_pelajaran || !jadwalForm.value.hari) return;
  emit('save-jadwal', { ...jadwalForm.value });
  showJadwalModal.value = false;
}

function handleDelete(id) {
  emit('delete-jadwal', id);
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    <!-- Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 backdrop-blur-xl p-6 rounded-3xl border border-white/10 shadow-2xl">
      <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-black border border-indigo-500/30 mb-2">
          <span>📅</span> WALIKELAS MANAGE TIMETABLE
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Jadwal Pelajaran {{ kelasBinaan }}</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">Kelola jadwal harian kelas binaan yang tersinkronisasi otomatis ke tampilan siswa</p>
      </div>

      <button
        @click="openAddModal"
        class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-500 via-purple-600 to-indigo-600 text-white px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:scale-105 active:scale-95 transition-all duration-200 shrink-0 min-h-[44px]"
      >
        <span class="text-base">+</span>
        <span>Tambah Sesi Jadwal</span>
      </button>
    </div>

    <!-- Filter Control Bar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
      <!-- Day Selector Pills -->
      <div class="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0 scrollbar-none">
        <button
          v-for="h in ['Semua', ...hariOptions]"
          :key="h"
          @click="selectedHari = h"
          :class="[
            'px-4 py-2.5 rounded-2xl text-xs font-black transition-all whitespace-nowrap active:scale-95 border',
            selectedHari === h
              ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white border-indigo-400/50 shadow-lg shadow-indigo-500/25'
              : 'bg-slate-900/60 text-gray-400 border-white/10 hover:text-white hover:bg-white/5'
          ]"
        >
          {{ h }}
        </button>
      </div>

      <!-- Search Input -->
      <div class="relative min-w-[240px]">
        <input
          v-model="searchMapel"
          type="text"
          placeholder="Cari mapel, guru, ruangan..."
          class="w-full bg-slate-900/60 border border-white/10 rounded-2xl px-4 py-2.5 pl-10 text-xs text-white placeholder-gray-500 focus:border-indigo-400 outline-none"
        />
        <svg class="w-4 h-4 text-gray-500 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>
    </div>

    <!-- Timetable Datatable & Mobile Cards Container -->
    <div class="bg-slate-900/80 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl">
      <!-- Desktop Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[650px]">
          <thead>
            <tr class="border-b border-white/10 bg-slate-950/60 text-[11px] font-black uppercase tracking-wider text-indigo-300">
              <th class="py-4 px-6">Hari & Sesi</th>
              <th class="py-4 px-6">Waktu / Jam</th>
              <th class="py-4 px-6">Mata Pelajaran</th>
              <th class="py-4 px-6">Guru Pengajar</th>
              <th class="py-4 px-6">Ruangan / Lab</th>
              <th class="py-4 px-6 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 text-xs font-semibold">
            <tr v-for="j in filteredJadwalList" :key="j.id" class="hover:bg-white/5 transition-colors">
              <td class="py-4 px-6">
                <div class="flex items-center gap-2">
                  <span class="px-2.5 py-1 rounded-xl bg-indigo-500/20 text-indigo-300 font-extrabold text-[10px] border border-indigo-500/30">
                    {{ j.hari }}
                  </span>
                  <span class="text-white font-extrabold">{{ j.jamKe || j.jam_ke }}</span>
                </div>
              </td>
              <td class="py-4 px-6 text-gray-300 font-mono text-[11px]">
                {{ j.waktu }}
              </td>
              <td class="py-4 px-6 font-extrabold text-white">
                {{ j.mapel }}
              </td>
              <td class="py-4 px-6 text-indigo-300 font-bold">
                👨‍🏫 {{ j.guru }}
              </td>
              <td class="py-4 px-6">
                <span class="px-3 py-1 rounded-xl bg-emerald-500/15 text-emerald-300 text-[10px] font-extrabold border border-emerald-500/30">
                  📍 {{ j.ruangan }}
                </span>
              </td>
              <td class="py-4 px-6 text-right space-x-2">
                <button
                  @click="openEditModal(j)"
                  class="px-3 py-1.5 rounded-xl bg-cyan-500/20 text-cyan-300 hover:bg-cyan-500/30 border border-cyan-500/30 text-[11px] font-bold transition-all"
                >
                  Edit
                </button>
                <button
                  @click="handleDelete(j.id)"
                  class="px-3 py-1.5 rounded-xl bg-rose-500/20 text-rose-300 hover:bg-rose-500/30 border border-rose-500/30 text-[11px] font-bold transition-all"
                >
                  Hapus
                </button>
              </td>
            </tr>

            <tr v-if="filteredJadwalList.length === 0">
              <td colspan="6" class="py-12 text-center text-gray-500">
                <div class="text-3xl mb-2">📅</div>
                <div class="font-bold text-sm text-gray-400">Belum ada jadwal pelajaran terdaftar</div>
                <div class="text-xs text-gray-600 mt-1">Klik "+ Tambah Sesi Jadwal" untuk mengisi jadwal kelas binaan ini</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Cards View -->
      <div class="block md:hidden divide-y divide-white/5">
        <div
          v-for="j in filteredJadwalList"
          :key="'mob-j-' + j.id"
          class="p-4 space-y-3 hover:bg-white/5 transition-colors"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-xl bg-indigo-500/20 text-indigo-300 font-black text-[10px] border border-indigo-500/30">
                {{ j.hari }}
              </span>
              <span class="text-white font-extrabold text-xs">{{ j.jamKe || j.jam_ke }}</span>
            </div>
            <span class="px-2.5 py-0.5 rounded-xl bg-emerald-500/15 text-emerald-300 text-[10px] font-extrabold border border-emerald-500/30 shrink-0">
              📍 {{ j.ruangan }}
            </span>
          </div>

          <div>
            <h3 class="font-extrabold text-white text-sm">{{ j.mapel }}</h3>
            <p class="text-indigo-300 font-bold text-xs mt-0.5">👨‍🏫 {{ j.guru }}</p>
            <p class="text-gray-400 font-mono text-[11px] mt-1">⏰ Waktu: {{ j.waktu }}</p>
          </div>

          <div class="flex items-center gap-2 pt-2 border-t border-white/5">
            <button
              @click="openEditModal(j)"
              class="flex-1 py-2 text-center text-cyan-300 font-bold text-xs bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 active:scale-95 transition-all"
            >
              ✏️ Edit
            </button>
            <button
              @click="handleDelete(j.id)"
              class="flex-1 py-2 text-center text-rose-400 font-bold text-xs bg-rose-500/15 hover:bg-rose-500/30 rounded-xl border border-rose-500/30 active:scale-95 transition-all"
            >
              🗑️ Hapus
            </button>
          </div>
        </div>

        <div v-if="filteredJadwalList.length === 0" class="p-8 text-center text-gray-500">
          <div class="text-3xl mb-2">📅</div>
          <div class="font-bold text-sm text-gray-400">Belum ada jadwal pelajaran terdaftar</div>
        </div>
      </div>
    </div>

    <!-- MODAL FORM CRUD JADWAL -->
    <div v-if="showJadwalModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-6 transform transition-all animate-in fade-in zoom-in-95">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">
            {{ editingJadwal ? 'Edit Sesi Jadwal' : 'Tambah Sesi Jadwal Baru' }}
          </h3>
          <button @click="showJadwalModal = false" class="text-gray-400 hover:text-white font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSave" class="space-y-4 text-xs sm:text-sm">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Hari</label>
              <select
                v-model="jadwalForm.hari"
                class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white font-bold focus:border-indigo-400 outline-none"
              >
                <option v-for="h in hariOptions" :key="h" :value="h">{{ h }}</option>
              </select>
            </div>

            <div>
              <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Jam Ke-</label>
              <input
                v-model="jadwalForm.jam_ke"
                type="text"
                placeholder="Jam Ke-1"
                class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-indigo-400 outline-none"
                required
              />
            </div>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Rentang Waktu (Opsional)</label>
            <input
              v-model="jadwalForm.waktu"
              type="text"
              placeholder="07.00 - 08.30 WIB"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white font-mono focus:border-indigo-400 outline-none"
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Mata Pelajaran *</label>
            <select
              v-if="mapelList && mapelList.length > 0"
              v-model="jadwalForm.mata_pelajaran"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white font-bold focus:border-indigo-400 outline-none min-h-[44px]"
              required
            >
              <option value="" disabled>-- Pilih Mata Pelajaran --</option>
              <option v-for="mapel in mapelList" :key="mapel" :value="mapel">
                {{ mapel }}
              </option>
            </select>
            <input
              v-else
              v-model="jadwalForm.mata_pelajaran"
              type="text"
              placeholder="Contoh: Pemrograman Web / Informatika"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white font-bold focus:border-indigo-400 outline-none min-h-[44px]"
              required
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Guru Pengajar (Opsional)</label>
            <select
              v-model="jadwalForm.guru_id"
              class="w-full bg-slate-800 border border-indigo-500/40 rounded-2xl px-4 py-3 text-indigo-300 font-bold focus:border-indigo-400 outline-none"
            >
              <option value="">-- Pilih Guru Pengajar --</option>
              <option v-for="g in guruList" :key="g.id" :value="g.id">
                {{ g.nama }} ({{ g.mapelUtama }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Ruangan / Lab</label>
            <input
              v-model="jadwalForm.ruangan"
              type="text"
              placeholder="Contoh: Lab Komputer 1 / Bengkel TKR"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-indigo-400 outline-none"
            />
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-white/10">
            <button
              type="button"
              @click="showJadwalModal = false"
              class="px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 text-gray-300 font-bold"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-6 py-3 rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-extrabold shadow-lg shadow-indigo-500/30 hover:scale-105 active:scale-95 transition-all"
            >
              Simpan Jadwal
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
