<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  mapelList: { type: Array, default: () => [] },
  kelompokMapelList: { type: Array, default: () => [] }
});

const emit = defineEmits([
  'save-mapel', 'delete-mapel',
  'save-kelompok', 'delete-kelompok'
]);

const activeMapelTab = ref('daftar_mapel');

// State for Mata Pelajaran Tab
const searchMapel = ref('');
const showMapelModal = ref(false);
const editingMapel = ref(null);
const mapelForm = ref({
  id: null,
  nama: '',
  kelompok: 'Mata Pelajaran Umum',
  deskripsi: ''
});

// State for Kelompok Mapel Tab
const searchKelompok = ref('');
const showKelompokModal = ref(false);
const editingKelompok = ref(null);
const kelompokForm = ref({
  id: null,
  nama: '',
  kode: '',
  deskripsi: ''
});

const activeKelompokOptions = computed(() => {
  if (props.kelompokMapelList && props.kelompokMapelList.length) {
    return props.kelompokMapelList.map(k => k.nama);
  }
  return [];
});

// Filtered Mapel List
const filteredMapelList = computed(() => {
  let list = props.mapelList || [];
  if (searchMapel.value.trim()) {
    const q = searchMapel.value.toLowerCase();
    list = list.filter(m =>
      m.nama.toLowerCase().includes(q) ||
      (m.kelompok && m.kelompok.toLowerCase().includes(q))
    );
  }
  return list;
});

// Filtered Kelompok List
const filteredKelompokList = computed(() => {
  let list = props.kelompokMapelList || [];

  if (searchKelompok.value.trim()) {
    const q = searchKelompok.value.toLowerCase();
    list = list.filter(k =>
      k.nama.toLowerCase().includes(q) ||
      (k.kode && k.kode.toLowerCase().includes(q))
    );
  }
  return list;
});

// Mapel Form Handlers
function openAddMapelModal() {
  editingMapel.value = null;
  mapelForm.value = {
    id: null,
    nama: '',
    kelompok: activeKelompokOptions.value.length > 0 ? activeKelompokOptions.value[0] : '',
    deskripsi: ''
  };
  showMapelModal.value = true;
}

function openEditMapelModal(m) {
  editingMapel.value = m;
  mapelForm.value = { ...m };
  showMapelModal.value = true;
}

function handleSaveMapel() {
  if (!mapelForm.value.nama) return;
  emit('save-mapel', { ...mapelForm.value });
  showMapelModal.value = false;
}

function handleDeleteMapel(id) {
  emit('delete-mapel', id);
}

// Kelompok Form Handlers
function openAddKelompokModal() {
  editingKelompok.value = null;
  kelompokForm.value = { id: null, nama: '', kode: '', deskripsi: '' };
  showKelompokModal.value = true;
}

function openEditKelompokModal(k) {
  editingKelompok.value = k;
  kelompokForm.value = { ...k };
  showKelompokModal.value = true;
}

function handleSaveKelompok() {
  if (!kelompokForm.value.nama) return;
  emit('save-kelompok', { ...kelompokForm.value });
  showKelompokModal.value = false;
}

function handleDeleteKelompok(id) {
  emit('delete-kelompok', id);
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Master Mata Pelajaran &amp; Kelompok Mapel</span>
          <span class="text-cyan-400 text-base">📚</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">
          Pengaturan terpusat daftar mata pelajaran sekolah dan manajemen kategori kelompok mapel (Sub-Menu Control Center)
        </p>
      </div>

      <!-- Action Button based on Sub-Tab -->
      <button
        v-if="activeMapelTab === 'daftar_mapel'"
        @click="openAddMapelModal"
        class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-teal-500 to-indigo-600 text-white px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-105 active:scale-95 transition-all duration-200 shrink-0 min-h-[44px]"
      >
        <span class="text-base">+</span>
        <span>Tambah Mapel Baru</span>
      </button>

      <button
        v-else-if="activeMapelTab === 'kelompok_mapel'"
        @click="openAddKelompokModal"
        class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-purple-500 via-indigo-600 to-purple-600 text-white px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 hover:scale-105 active:scale-95 transition-all duration-200 shrink-0 min-h-[44px]"
      >
        <span class="text-base">+</span>
        <span>Tambah Kelompok Mapel</span>
      </button>
    </div>

    <!-- Sub Menu Navigation Tabs Bar -->
    <div class="flex items-center gap-2 border-b border-white/10 pb-3 overflow-x-auto custom-scrollbar">
      <button
        @click="activeMapelTab = 'daftar_mapel'"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 shrink-0 min-h-[44px]',
          activeMapelTab === 'daftar_mapel'
            ? 'bg-gradient-to-r from-cyan-500 to-indigo-600 text-white shadow-lg shadow-cyan-500/25 border border-cyan-400/40'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        <span>📚</span>
        <span>Master Data Mata Pelajaran</span>
      </button>

      <button
        @click="activeMapelTab = 'kelompok_mapel'"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 shrink-0 min-h-[44px]',
          activeMapelTab === 'kelompok_mapel'
            ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/25 border border-purple-500/40'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        <span>🏷️</span>
        <span>Manajemen Kelompok Mapel</span>
      </button>
    </div>

    <!-- SUB-TAB 1: DAFTAR MATA PELAJARAN -->
    <div v-if="activeMapelTab === 'daftar_mapel'" class="space-y-4">
      
      <!-- Search Bar -->
      <div class="max-w-md relative">
        <input
          v-model="searchMapel"
          type="text"
          placeholder="Cari nama mata pelajaran atau kelompok..."
          class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 pl-10 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-cyan-400 outline-none min-h-[44px]"
        />
        <svg class="w-4 h-4 text-gray-500 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>

      <!-- Datatable & Mobile Cards Container -->
      <div class="bg-slate-900/80 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl">
        <!-- Desktop Table View -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left text-xs sm:text-sm text-gray-300">
            <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
              <tr>
                <th class="px-6 py-4 w-16 text-center">No.</th>
                <th class="px-6 py-4">Nama Mata Pelajaran</th>
                <th class="px-6 py-4">Kelompok Mapel</th>
                <th class="px-6 py-4">Deskripsi Ringkas</th>
                <th class="px-6 py-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 font-medium">
              <tr v-for="(m, idx) in filteredMapelList" :key="m.id" class="hover:bg-white/5 transition-colors duration-150">
                <td class="px-6 py-4 text-center font-mono text-xs text-cyan-400 font-bold">
                  {{ idx + 1 }}
                </td>
                <td class="px-6 py-4 font-black text-white text-base">
                  {{ m.nama }}
                </td>
                <td class="px-6 py-4">
                  <span class="bg-indigo-500/20 text-indigo-300 text-xs font-bold px-3 py-1 rounded-full border border-indigo-500/30">
                    🏷️ {{ m.kelompok || 'Mata Pelajaran Umum' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-xs text-gray-400 max-w-xs leading-relaxed">
                  {{ m.deskripsi || '-' }}
                </td>
                <td class="px-6 py-4 text-right space-x-2">
                  <button @click="openEditMapelModal(m)" class="text-cyan-300 hover:text-white font-bold text-xs bg-cyan-500/15 hover:bg-cyan-500/30 px-3.5 py-1.5 rounded-xl border border-cyan-500/30 active:scale-95 transition-all">✏️ Edit</button>
                  <button @click="handleDeleteMapel(m.id)" class="text-red-400 hover:text-red-300 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 px-3.5 py-1.5 rounded-xl border border-red-500/30 active:scale-95 transition-all">🗑️ Hapus</button>
                </td>
              </tr>
              <tr v-if="filteredMapelList.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-gray-400 font-medium">
                  Belum ada data mata pelajaran yang didaftarkan.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="block md:hidden divide-y divide-white/5">
          <div
            v-for="m in filteredMapelList"
            :key="'mob-mapel-' + m.id"
            class="p-4 space-y-3 hover:bg-white/5 transition-colors"
          >
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-black text-white text-base">{{ m.nama }}</h3>
              <span class="bg-indigo-500/20 text-indigo-300 text-[10px] font-bold px-2.5 py-1 rounded-full border border-indigo-500/30 shrink-0">
                🏷️ {{ m.kelompok || 'Mata Pelajaran Umum' }}
              </span>
            </div>

            <p class="text-xs text-gray-400 bg-slate-950/40 p-2.5 rounded-xl border border-white/5 leading-relaxed">
              {{ m.deskripsi || 'Tidak ada deskripsi' }}
            </p>

            <div class="flex items-center gap-2 pt-2 border-t border-white/5">
              <button @click="openEditMapelModal(m)" class="flex-1 py-2 text-center text-cyan-300 font-bold text-xs bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 active:scale-95 transition-all">
                ✏️ Edit
              </button>
              <button @click="handleDeleteMapel(m.id)" class="flex-1 py-2 text-center text-red-400 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 active:scale-95 transition-all">
                🗑️ Hapus
              </button>
            </div>
          </div>

          <div v-if="filteredMapelList.length === 0" class="p-8 text-center text-gray-400 font-medium">
            Belum ada data mata pelajaran yang didaftarkan.
          </div>
        </div>
      </div>
    </div>

    <!-- SUB-TAB 2: MANAJEMEN KELOMPOK MAPEL -->
    <div v-else-if="activeMapelTab === 'kelompok_mapel'" class="space-y-4">
      
      <!-- Search Bar Kelompok -->
      <div class="max-w-md relative">
        <input
          v-model="searchKelompok"
          type="text"
          placeholder="Cari kelompok mapel..."
          class="w-full bg-slate-900 border border-white/10 rounded-2xl px-4 py-2.5 pl-10 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-purple-400 outline-none min-h-[44px]"
        />
        <svg class="w-4 h-4 text-gray-500 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>

      <!-- Datatable & Mobile Cards Container Kelompok -->
      <div class="bg-slate-900/80 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl">
        <!-- Desktop Table View -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left text-xs sm:text-sm text-gray-300">
            <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
              <tr>
                <th class="px-6 py-4 w-16 text-center">No.</th>
                <th class="px-6 py-4">Kode / Singkatan</th>
                <th class="px-6 py-4">Nama Kelompok Mapel</th>
                <th class="px-6 py-4">Deskripsi Kelompok</th>
                <th class="px-6 py-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 font-medium">
              <tr v-for="(k, idx) in filteredKelompokList" :key="k.id" class="hover:bg-white/5 transition-colors duration-150">
                <td class="px-6 py-4 text-center font-mono text-xs text-purple-400 font-bold">
                  {{ idx + 1 }}
                </td>
                <td class="px-6 py-4 font-mono font-black text-purple-300">
                  {{ k.kode || '-' }}
                </td>
                <td class="px-6 py-4 font-black text-white text-base">
                  {{ k.nama }}
                </td>
                <td class="px-6 py-4 text-xs text-gray-400 max-w-xs leading-relaxed">
                  {{ k.deskripsi || '-' }}
                </td>
                <td class="px-6 py-4 text-right space-x-2">
                  <button @click="openEditKelompokModal(k)" class="text-purple-300 hover:text-white font-bold text-xs bg-purple-500/15 hover:bg-purple-500/30 px-3.5 py-1.5 rounded-xl border border-purple-500/30 active:scale-95 transition-all">✏️ Edit</button>
                  <button @click="handleDeleteKelompok(k.id)" class="text-red-400 hover:text-red-300 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 px-3.5 py-1.5 rounded-xl border border-red-500/30 active:scale-95 transition-all">🗑️ Hapus</button>
                </td>
              </tr>
              <tr v-if="filteredKelompokList.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-gray-400 font-medium">
                  Belum ada kelompok mapel terdaftar.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="block md:hidden divide-y divide-white/5">
          <div
            v-for="k in filteredKelompokList"
            :key="'mob-kel-' + k.id"
            class="p-4 space-y-3 hover:bg-white/5 transition-colors"
          >
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-black text-white text-base">{{ k.nama }}</h3>
              <span v-if="k.kode" class="bg-purple-500/20 text-purple-300 font-mono text-[10px] font-bold px-2.5 py-1 rounded-full border border-purple-500/30 shrink-0">
                {{ k.kode }}
              </span>
            </div>

            <p class="text-xs text-gray-400 bg-slate-950/40 p-2.5 rounded-xl border border-white/5 leading-relaxed">
              {{ k.deskripsi || 'Tidak ada deskripsi' }}
            </p>

            <div class="flex items-center gap-2 pt-2 border-t border-white/5">
              <button @click="openEditKelompokModal(k)" class="flex-1 py-2 text-center text-purple-300 font-bold text-xs bg-purple-500/15 hover:bg-purple-500/30 rounded-xl border border-purple-500/30 active:scale-95 transition-all">
                ✏️ Edit
              </button>
              <button @click="handleDeleteKelompok(k.id)" class="flex-1 py-2 text-center text-red-400 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 active:scale-95 transition-all">
                🗑️ Hapus
              </button>
            </div>
          </div>

          <div v-if="filteredKelompokList.length === 0" class="p-8 text-center text-gray-400 font-medium">
            Belum ada kelompok mapel terdaftar.
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL FORM CRUD MAPEL -->
    <div v-if="showMapelModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl p-6 sm:p-8 max-w-md w-full max-h-[90vh] sm:max-h-[85vh] overflow-y-auto shadow-2xl space-y-6 animate-in slide-in-from-bottom duration-200 sm:animate-in sm:fade-in sm:zoom-in-95 pb-24 sm:pb-6">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">
            {{ editingMapel ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran Baru' }}
          </h3>
          <button @click="showMapelModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSaveMapel" class="space-y-4 text-xs sm:text-sm">
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nama Lengkap Mata Pelajaran *</label>
            <input
              v-model="mapelForm.nama"
              type="text"
              placeholder="Contoh: Bahasa Indonesia, Pemrograman Web"
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white font-bold outline-none focus:border-cyan-400 text-sm min-h-[44px]"
              required
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Pilih Kelompok Mapel *</label>
            <select
              v-model="mapelForm.kelompok"
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white font-semibold outline-none focus:border-cyan-400 min-h-[44px]"
              required
            >
              <option v-for="opt in activeKelompokOptions" :key="opt" :value="opt">
                {{ opt }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Deskripsi / Catatan <span class="text-gray-400 text-[10px] font-normal lowercase">(Opsional)</span></label>
            <textarea
              v-model="mapelForm.deskripsi"
              rows="3"
              placeholder="Ringkasan kompetensi mata pelajaran..."
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-cyan-400 text-xs"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button
              type="button"
              @click="showMapelModal = false"
              class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl font-bold text-xs min-h-[44px]"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-5 py-3 bg-gradient-to-r from-cyan-500 to-indigo-600 text-white rounded-2xl font-extrabold text-xs shadow-lg shadow-cyan-500/25 hover:scale-105 active:scale-95 transition-all min-h-[44px]"
            >
              Simpan Mata Pelajaran
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL FORM CRUD KELOMPOK MAPEL -->
    <div v-if="showKelompokModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl p-6 sm:p-8 max-w-md w-full max-h-[90vh] sm:max-h-[85vh] overflow-y-auto shadow-2xl space-y-6 animate-in slide-in-from-bottom duration-200 sm:animate-in sm:fade-in sm:zoom-in-95 pb-24 sm:pb-6">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">
            {{ editingKelompok ? 'Edit Kelompok Mapel' : 'Tambah Kelompok Mapel Baru' }}
          </h3>
          <button @click="showKelompokModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSaveKelompok" class="space-y-4 text-xs sm:text-sm">
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nama Kelompok Mapel *</label>
            <input
              v-model="kelompokForm.nama"
              type="text"
              placeholder="Contoh: Mata Pelajaran Umum, Kejuruan, P5"
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white font-bold outline-none focus:border-purple-400 text-sm min-h-[44px]"
              required
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Kode / Singkatan <span class="text-gray-400 text-[10px] font-normal lowercase">(Opsional)</span></label>
            <input
              v-model="kelompokForm.kode"
              type="text"
              placeholder="Contoh: UMUM, KJR, MULOK, P5"
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white font-mono uppercase outline-none focus:border-purple-400 min-h-[44px]"
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Deskripsi Kelompok <span class="text-gray-400 text-[10px] font-normal lowercase">(Opsional)</span></label>
            <textarea
              v-model="kelompokForm.deskripsi"
              rows="3"
              placeholder="Fokus atau keterangan kelompok mapel ini..."
              class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-2xl text-white outline-none focus:border-purple-400 text-xs"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button
              type="button"
              @click="showKelompokModal = false"
              class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl font-bold text-xs min-h-[44px]"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-5 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-2xl font-extrabold text-xs shadow-lg shadow-purple-500/25 hover:scale-105 active:scale-95 transition-all min-h-[44px]"
            >
              Simpan Kelompok Mapel
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
