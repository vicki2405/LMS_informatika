<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  kasusList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  kelasBinaan: { type: String, default: 'X RPL 1' }
});

const emit = defineEmits(['save-kasus', 'delete-kasus']);

const searchVal = ref('');
const filterKategori = ref('Semua');
const filterStatus = ref('Semua');

// Modals State
const showKasusModal = ref(false);
const editingKasus = ref(null);
const kasusForm = ref({
  id: null,
  siswaId: '',
  tanggal: new Date().toISOString().substring(0, 10),
  namaKasus: '',
  kategori: 'Ringan',
  poin: 5,
  tindakan: '',
  status: 'Dalam Pemantauan',
  catatan: ''
});

const sortedFilteredKasus = computed(() => {
  let list = [...(props.kasusList || [])];

  if (searchVal.value.trim()) {
    const q = searchVal.value.toLowerCase();
    list = list.filter(k =>
      k.namaSiswa.toLowerCase().includes(q) ||
      k.namaKasus.toLowerCase().includes(q) ||
      (k.tindakan && k.tindakan.toLowerCase().includes(q))
    );
  }

  if (filterKategori.value !== 'Semua') {
    list = list.filter(k => k.kategori === filterKategori.value);
  }

  if (filterStatus.value !== 'Semua') {
    list = list.filter(k => k.status === filterStatus.value);
  }

  return list;
});

function openAddModal() {
  editingKasus.value = null;
  kasusForm.value = {
    id: null,
    siswaId: props.siswaList[0]?.id || '',
    tanggal: new Date().toISOString().substring(0, 10),
    namaKasus: '',
    kategori: 'Ringan',
    poin: 5,
    tindakan: '',
    status: 'Dalam Pemantauan',
    catatan: ''
  };
  showKasusModal.value = true;
}

function openEditModal(k) {
  editingKasus.value = k;
  kasusForm.value = {
    id: k.id,
    siswaId: k.siswaId,
    tanggal: k.tanggal,
    namaKasus: k.namaKasus,
    kategori: k.kategori,
    poin: k.poin || 5,
    tindakan: k.tindakan || '',
    status: k.status,
    catatan: k.catatan || ''
  };
  showKasusModal.value = true;
}

function handleSave() {
  if (!kasusForm.value.siswaId || !kasusForm.value.namaKasus) return;
  emit('save-kasus', { ...kasusForm.value });
  showKasusModal.value = false;
}

function handleDelete(id) {
  emit('delete-kasus', id);
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/20 text-red-300 text-xs font-black border border-red-500/30 mb-2">
          <span>🚨</span> DATA DISIPLINER SISWA
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Data Kasus Kelas Binaan</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">Catat, monitor, dan tangani kasus kedisiplinan siswa kelas {{ kelasBinaan }} secara cepat</p>
      </div>

      <div class="flex flex-wrap items-center gap-3 shrink-0">
        <button
          @click="openAddModal"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-red-500 via-pink-600 to-red-600 text-white px-5 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-red-500/25 hover:shadow-red-500/40 hover:scale-105 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span>+</span>
          <span>Catat Kasus Baru</span>
        </button>
      </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        
        <div class="md:col-span-2">
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">🔍 Cari Kasus / Nama Siswa</label>
          <input
            v-model="searchVal"
            type="text"
            placeholder="Cari nama siswa, jenis kasus, atau tindakan..."
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-red-400 outline-none min-h-[44px]"
          />
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">⚠️ Kategori Kasus</label>
          <select
            v-model="filterKategori"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white focus:border-red-400 outline-none min-h-[44px]"
          >
            <option value="Semua">Semua Kategori</option>
            <option value="Ringan">Ringan</option>
            <option value="Sedang">Sedang</option>
            <option value="Berat">Berat</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">📌 Status Kasus</label>
          <select
            v-model="filterStatus"
            class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white focus:border-red-400 outline-none min-h-[44px]"
          >
            <option value="Semua">Semua Status</option>
            <option value="Dalam Pemantauan">Dalam Pemantauan</option>
            <option value="Selesai">Selesai</option>
          </select>
        </div>

      </div>
    </div>

    <!-- Data Table Roster & Mobile Cards -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      <!-- Desktop Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300 border-collapse table-fixed min-w-[900px]">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="py-4 px-4 w-[5%] text-center border-r border-white/5">No</th>
              <th class="py-4 px-5 w-[12%] border-r border-white/5">Tanggal</th>
              <th class="py-4 px-6 w-[18%] border-r border-white/5">Siswa</th>
              <th class="py-4 px-6 w-[18%] border-r border-white/5">Kasus</th>
              <th class="py-4 px-4 w-[10%] text-center border-r border-white/5">Tingkat</th>
              <th class="py-4 px-4 w-[8%] text-center border-r border-white/5">Poin</th>
              <th class="py-4 px-6 w-[17%] border-r border-white/5">Tindakan / Solusi</th>
              <th class="py-4 px-4 w-[12%] text-center border-r border-white/5">Status</th>
              <th class="py-4 px-5 w-[10%] text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr v-for="(k, idx) in sortedFilteredKasus" :key="k.id" class="hover:bg-white/5 transition-colors">
              <td class="py-4 px-4 text-center font-mono text-xs text-red-300 font-bold border-r border-white/5 align-middle">{{ idx + 1 }}</td>
              
              <td class="py-4 px-5 border-r border-white/5 align-middle truncate font-mono text-xs">{{ k.tanggal }}</td>
              
              <td class="py-4 px-6 border-r border-white/5 align-middle truncate">
                <div class="font-extrabold text-white text-sm truncate">{{ k.namaSiswa }}</div>
              </td>
              
              <td class="py-4 px-6 border-r border-white/5 align-middle">
                <div class="font-bold text-gray-200 text-xs line-clamp-2" :title="k.namaKasus">{{ k.namaKasus }}</div>
                <div v-if="k.catatan" class="text-[10px] text-gray-400 truncate mt-0.5" :title="k.catatan">Ket: {{ k.catatan }}</div>
              </td>

              <td class="py-4 px-4 text-center border-r border-white/5 align-middle">
                <span :class="[
                  'px-2.5 py-1 rounded-full font-black text-[10px] uppercase border',
                  k.kategori === 'Ringan' ? 'bg-blue-500/10 text-blue-300 border-blue-500/30' :
                  k.kategori === 'Sedang' ? 'bg-amber-500/10 text-amber-300 border-amber-500/30' :
                  'bg-red-500/15 text-red-300 border-red-500/30 font-black'
                ]">
                  {{ k.kategori }}
                </span>
              </td>

              <td class="py-4 px-4 text-center border-r border-white/5 align-middle">
                <span class="px-2 py-1 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 font-mono font-black text-xs">
                  {{ k.poin }} Pts
                </span>
              </td>

              <td class="py-4 px-6 border-r border-white/5 align-middle">
                <div class="text-xs text-gray-300 line-clamp-2" :title="k.tindakan">{{ k.tindakan || 'Belum dilakukan tindakan' }}</div>
              </td>

              <td class="py-4 px-4 text-center border-r border-white/5 align-middle">
                <span :class="[
                  'px-2.5 py-1 rounded-full font-black text-[10px] uppercase border',
                  k.status === 'Selesai' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30' : 'bg-red-500/10 text-red-300 border-red-500/30'
                ]">
                  {{ k.status }}
                </span>
              </td>

              <td class="py-4 px-5 text-right whitespace-nowrap align-middle">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    @click="openEditModal(k)"
                    class="px-2 py-1.5 text-xs font-bold text-cyan-300 bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 transition-all active:scale-95"
                    title="Edit Kasus"
                  >
                    ✏️
                  </button>
                  <button
                    @click="handleDelete(k.id)"
                    class="px-2 py-1.5 text-xs font-bold text-red-400 bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 transition-all active:scale-95"
                    title="Hapus Kasus"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
            
            <tr v-if="sortedFilteredKasus.length === 0">
              <td colspan="9" class="py-12 text-center text-gray-400 text-xs font-medium">Belum ada data kasus dicatat untuk kelas binaan.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card List View -->
      <div class="md:hidden divide-y divide-white/5">
        <div v-for="(k, idx) in sortedFilteredKasus" :key="k.id" class="p-5 space-y-4">
          <div class="flex items-center justify-between">
            <span class="font-mono text-xs text-red-300 font-bold">#{{ idx + 1 }}</span>
            <span class="font-mono text-[11px] text-gray-400">{{ k.tanggal }}</span>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-black text-gray-500 uppercase tracking-wider block">Siswa</span>
            <div class="font-extrabold text-white text-sm">{{ k.namaSiswa }}</div>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-black text-gray-500 uppercase tracking-wider block">Kasus / Pelanggaran</span>
            <div class="font-bold text-gray-200 text-xs leading-relaxed">{{ k.namaKasus }}</div>
            <div v-if="k.catatan" class="text-[10px] text-gray-400 mt-1">Ket: {{ k.catatan }}</div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <span class="text-[10px] font-black text-gray-500 uppercase tracking-wider block mb-1">Tingkat</span>
              <span :class="[
                'inline-block px-2.5 py-0.5 rounded-full font-black text-[9px] uppercase border',
                k.kategori === 'Ringan' ? 'bg-blue-500/10 text-blue-300 border-blue-500/30' :
                k.kategori === 'Sedang' ? 'bg-amber-500/10 text-amber-300 border-amber-500/30' :
                'bg-red-500/15 text-red-300 border-red-500/30 font-black'
              ]">
                {{ k.kategori }}
              </span>
            </div>
            <div>
              <span class="text-[10px] font-black text-gray-500 uppercase tracking-wider block mb-1">Poin</span>
              <span class="inline-block px-2.5 py-0.5 bg-red-500/10 border border-red-500/20 text-red-400 font-mono font-black text-[9px] rounded-lg">
                {{ k.poin }} Pts
              </span>
            </div>
            <div>
              <span class="text-[10px] font-black text-gray-500 uppercase tracking-wider block mb-1">Status</span>
              <span :class="[
                'inline-block px-2.5 py-0.5 rounded-full font-black text-[9px] uppercase border',
                k.status === 'Selesai' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30' : 'bg-red-500/10 text-red-300 border-red-500/30'
              ]">
                {{ k.status }}
              </span>
            </div>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-black text-gray-500 uppercase tracking-wider block">Tindakan / Solusi</span>
            <div class="text-xs text-gray-300 leading-relaxed">{{ k.tindakan || 'Belum dilakukan tindakan' }}</div>
          </div>

          <!-- Mobile Action Buttons -->
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/5">
            <button
              @click="openEditModal(k)"
              class="px-4 py-2 text-xs font-bold text-cyan-300 bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 transition-all active:scale-95 flex items-center gap-1 min-h-[36px]"
            >
              ✏️ Edit
            </button>
            <button
              @click="handleDelete(k.id)"
              class="px-3 py-2 text-xs font-bold text-red-400 bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 transition-all active:scale-95 flex items-center gap-1 min-h-[36px]"
            >
              🗑️ Hapus
            </button>
          </div>
        </div>

        <div v-if="sortedFilteredKasus.length === 0" class="py-12 text-center text-gray-400 text-xs font-medium">
          Belum ada data kasus dicatat untuk kelas binaan.
        </div>
      </div>
    </div>

    <!-- Modal Form Tambah / Edit Kasus -->
    <div v-if="showKasusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto transform transition-all animate-in fade-in zoom-in-95">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">
            {{ editingKasus ? 'Edit Catatan Kasus Siswa' : 'Catat Kasus Siswa Baru' }}
          </h3>
          <button @click="showKasusModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSave" class="space-y-4 text-xs sm:text-sm">
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Pilih Siswa *</label>
            <select
              v-model="kasusForm.siswaId"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-red-400 outline-none text-xs font-semibold min-h-[44px]"
              required
            >
              <option v-for="s in siswaList" :key="s.id" :value="s.id">{{ s.nama }}</option>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Tanggal Kejadian *</label>
            <input
              v-model="kasusForm.tanggal"
              type="date"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-red-400 outline-none min-h-[44px] font-mono"
              required
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nama Kasus / Pelanggaran *</label>
            <input
              v-model="kasusForm.namaKasus"
              type="text"
              placeholder="Contoh: Terlambat upacara, membuat gaduh"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-red-400 outline-none font-bold min-h-[44px]"
              required
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Kategori / Tingkatan Kasus</label>
            <select
              v-model="kasusForm.kategori"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-red-400 outline-none text-xs font-semibold min-h-[44px]"
            >
              <option value="Ringan">Ringan</option>
              <option value="Sedang">Sedang</option>
              <option value="Berat">Berat</option>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Point Pelanggaran *</label>
            <input
              v-model="kasusForm.poin"
              type="number"
              min="0"
              placeholder="Contoh: 5, 10, 20"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-red-400 outline-none font-mono min-h-[44px]"
              required
            />
            <p class="text-[11px] text-gray-400 mt-1.5 font-medium">Beban poin kedisiplinan yang diberikan untuk jenis kasus ini.</p>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Tindakan / Tindak Lanjut *</label>
            <textarea
              v-model="kasusForm.tindakan"
              rows="3"
              placeholder="Tindakan yang telah atau akan diambil..."
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-red-400 outline-none min-h-[80px]"
            ></textarea>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Status Penanganan</label>
            <select
              v-model="kasusForm.status"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-red-400 outline-none text-xs font-semibold min-h-[44px]"
            >
              <option value="Dalam Pemantauan">Dalam Pemantauan</option>
              <option value="Selesai">Selesai</option>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Catatan Tambahan (Opsional)</label>
            <textarea
              v-model="kasusForm.catatan"
              rows="2"
              placeholder="Catatan tambahan kejadian..."
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-red-400 outline-none min-h-[60px]"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button type="button" @click="showKasusModal = false" class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl text-xs font-bold min-h-[44px]">Batal</button>
            <button type="submit" class="px-5 py-3 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-2xl text-xs font-black shadow-lg hover:shadow-red-500/25 active:scale-95 transition-all min-h-[44px]">Simpan Kasus</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
