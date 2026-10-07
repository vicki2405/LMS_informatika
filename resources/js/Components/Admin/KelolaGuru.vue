<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  guruList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] }
});

const emit = defineEmits(['save-guru', 'delete-guru']);

const searchGuru = ref('');
const showGuruModal = ref(false);
const editingGuru = ref(null);
const selectedMapels = ref([]);

const guruForm = ref({
  id: null,
  nip: '',
  nama: '',
  telepon: '',
  mapel_utama: ''
});

const filteredGuruList = computed(() => {
  let list = props.guruList || [];
  if (searchGuru.value.trim()) {
    const q = searchGuru.value.toLowerCase();
    list = list.filter(g =>
      g.nama.toLowerCase().includes(q) ||
      (g.nip && g.nip.toLowerCase().includes(q)) ||
      (g.mapelUtama && g.mapelUtama.toLowerCase().includes(q))
    );
  }
  return list;
});

function openAddGuruModal() {
  editingGuru.value = null;
  selectedMapels.value = [];
  guruForm.value = {
    id: null,
    nip: '',
    nama: '',
    telepon: '',
    mapel_utama: ''
  };
  showGuruModal.value = true;
}

function openEditGuruModal(g) {
  editingGuru.value = g;
  const mapelsStr = g.mapelUtama || '';
  selectedMapels.value = mapelsStr.split(',').map(s => s.trim()).filter(s => s.length > 0);
  
  guruForm.value = {
    id: g.id,
    nip: g.nip || '',
    nama: g.nama || '',
    telepon: g.telepon || '',
    mapel_utama: g.mapelUtama || ''
  };
  showGuruModal.value = true;
}

function handleSaveGuru() {
  if (!guruForm.value.nama) return;
  
  // Join the selected subjects array into a comma-separated string
  guruForm.value.mapel_utama = selectedMapels.value.join(', ');
  
  emit('save-guru', { ...guruForm.value });
  showGuruModal.value = false;
}

function handleDeleteGuru(id) {
  emit('delete-guru', id);
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Master Data Guru</span>
          <span class="text-purple-400 text-base">👨‍🏫</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">
          Pengaturan terpusat data profil &amp; biodata Master Guru sekolah
        </p>
      </div>

      <button
        @click="openAddGuruModal"
        class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-purple-500 via-indigo-600 to-purple-600 text-white px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 hover:scale-105 active:scale-95 transition-all duration-200 shrink-0 min-h-[44px]"
      >
        <span class="text-base">+</span>
        <span>Tambah Guru Baru</span>
      </button>
    </div>

    <!-- Search Bar -->
    <div class="max-w-md relative">
      <input
        v-model="searchGuru"
        type="text"
        placeholder="Cari NIP, nama guru, atau mata pelajaran..."
        class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 pl-10 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-purple-400 outline-none min-h-[44px]"
      />
      <svg class="w-4 h-4 text-gray-500 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
    </div>

    <!-- Table -->
    <div class="bg-slate-900/80 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl">
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="px-6 py-4 w-16 text-center">No.</th>
              <th class="px-6 py-4">NIP / ID</th>
              <th class="px-6 py-4">Nama Lengkap Guru</th>
              <th class="px-6 py-4">Mata Pelajaran Utama</th>
              <th class="px-6 py-4">No. Telepon</th>
              <th class="px-6 py-4">Status Akun Login</th>
              <th class="px-6 py-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr v-for="(g, idx) in filteredGuruList" :key="g.id" class="hover:bg-white/5 transition-colors duration-150">
              <td class="px-6 py-4 text-center font-mono text-xs text-purple-400 font-bold">
                {{ idx + 1 }}
              </td>
              <td class="px-6 py-4 font-mono text-xs text-gray-300">
                {{ g.nip || '-' }}
              </td>
              <td class="px-6 py-4 font-black text-white text-base">
                {{ g.nama }}
              </td>
              <td class="px-6 py-4">
                <span class="bg-purple-500/20 text-purple-300 text-xs font-bold px-3 py-1 rounded-full border border-purple-500/30">
                  📚 {{ g.mapelUtama || 'Umum' }}
                </span>
              </td>
              <td class="px-6 py-4 text-xs text-gray-400">
                {{ g.telepon || '-' }}
              </td>
              <td class="px-6 py-4">
                <span v-if="g.hasAccount" class="bg-emerald-500/20 text-emerald-300 text-xs font-bold px-3 py-1 rounded-full border border-emerald-500/30">
                  ✓ Memiliki Akun
                </span>
                <span v-else class="bg-amber-500/20 text-amber-300 text-xs font-bold px-3 py-1 rounded-full border border-amber-500/30">
                  Belum Ada Akun
                </span>
              </td>
              <td class="px-6 py-4 text-right space-x-2">
                <button @click="openEditGuruModal(g)" class="text-purple-300 hover:text-white font-bold text-xs bg-purple-500/15 hover:bg-purple-500/30 px-3.5 py-1.5 rounded-xl border border-purple-500/30 active:scale-95 transition-all">✏️ Edit</button>
                <button @click="handleDeleteGuru(g.id)" class="text-red-400 hover:text-red-300 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 px-3.5 py-1.5 rounded-xl border border-red-500/30 active:scale-95 transition-all">🗑️ Hapus</button>
              </td>
            </tr>
            <tr v-if="filteredGuruList.length === 0">
              <td colspan="7" class="px-6 py-12 text-center text-gray-400 font-medium">
                Belum ada data guru yang terdaftar.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form Master Guru -->
    <div v-if="showGuruModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl max-w-lg w-full max-h-[90vh] sm:max-h-[85vh] overflow-y-auto p-6 shadow-2xl space-y-5 animate-in slide-in-from-bottom duration-200 sm:animate-in sm:zoom-in-95 pb-24 sm:pb-6">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="text-lg font-black text-white tracking-tight">
            {{ editingGuru ? 'Edit Master Data Guru' : 'Tambah Master Data Guru Baru' }}
          </h3>
          <button @click="showGuruModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSaveGuru" class="space-y-4">
          <div>
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">NIP (Nomor Induk Pegawai)</label>
            <input v-model="guruForm.nip" type="text" placeholder="Masukkan NIP..." class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
          </div>

          <div>
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">Nama Lengkap Guru <span class="text-red-400">*</span></label>
            <input v-model="guruForm.nama" required type="text" placeholder="Contoh: Drs. Budi Santoso, M.Pd." class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
          </div>

          <div>
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">No. Telepon / WhatsApp</label>
            <input v-model="guruForm.telepon" type="text" placeholder="0812xxxx..." class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
          </div>

          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider">Mata Pelajaran yang Diampu *</label>
              <span class="text-xs text-purple-400 font-extrabold font-mono">{{ selectedMapels.length }} Terpilih</span>
            </div>
            <div class="max-h-44 overflow-y-auto p-3.5 bg-slate-950 border border-white/10 rounded-2xl custom-scrollbar grid grid-cols-2 gap-2.5">
              <label v-for="m in mapelList" :key="m.id" class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-white/5 cursor-pointer transition-all border border-transparent hover:border-white/5">
                <input
                  type="checkbox"
                  :value="m.nama"
                  v-model="selectedMapels"
                  class="w-4 h-4 rounded text-purple-500 bg-slate-900 border-white/10 focus:ring-purple-500 focus:ring-offset-slate-950 cursor-pointer"
                />
                <span class="text-xs font-bold text-white truncate" :title="m.nama">{{ m.nama }}</span>
              </label>
              <div v-if="!mapelList || mapelList.length === 0" class="col-span-2 text-center text-xs text-gray-500 py-6 font-medium">
                Tidak ada data mata pelajaran.
              </div>
            </div>
            <p class="text-[10px] text-gray-500 font-medium">Anda dapat memilih satu atau beberapa mata pelajaran sekaligus untuk guru ini.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
            <button type="button" @click="showGuruModal = false" class="px-5 py-2.5 rounded-2xl text-xs font-extrabold text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-all">Batal</button>
            <button type="submit" class="px-5 py-2.5 rounded-2xl text-xs font-extrabold text-white bg-gradient-to-r from-purple-500 to-indigo-600 shadow-lg shadow-purple-500/25 hover:scale-105 active:scale-95 transition-all">Simpan Guru</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
