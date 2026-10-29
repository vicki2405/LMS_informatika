<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  siswaList: { type: Array, default: () => [] },
  kelasBinaan: { type: String, default: 'X RPL 1' }
});

const emit = defineEmits([
  'save-siswa', 'delete-siswa', 'import-siswa'
]);

const searchSiswa = ref('');

// Filter & Sort Alphabetically A-Z
const sortedFilteredSiswa = computed(() => {
  let list = [...(props.siswaList || [])];

  if (searchSiswa.value.trim()) {
    const q = searchSiswa.value.toLowerCase();
    list = list.filter(s =>
      s.nama.toLowerCase().includes(q) ||
      (s.nis && s.nis.includes(q)) ||
      (s.namaOrangTua && s.namaOrangTua.toLowerCase().includes(q))
    );
  }

  return list.sort((a, b) => a.nama.localeCompare(b.nama));
});

// Modals State
const showSiswaModal = ref(false);
const editingSiswa = ref(null);
const siswaForm = ref({
  id: null,
  nis: '',
  nama: '',
  jenisKelamin: 'Laki-laki',
  teleponSiswa: '',
  namaOrangTua: '',
  teleponOrtu: '',
  linkMaps: ''
});

const showImportModal = ref(false);
const importFile = ref(null);

function openAddSiswaModal() {
  editingSiswa.value = null;
  siswaForm.value = {
    id: null,
    nis: String(1000 + (props.siswaList?.length || 0) + 1),
    nama: '',
    jenisKelamin: 'Laki-laki',
    teleponSiswa: '',
    namaOrangTua: '',
    teleponOrtu: '',
    linkMaps: ''
  };
  showSiswaModal.value = true;
}

function openEditSiswa(s) {
  editingSiswa.value = s;
  siswaForm.value = {
    id: s.id || null,
    nis: s.nis || '',
    nama: s.nama || '',
    jenisKelamin: s.jenisKelamin || 'Laki-laki',
    teleponSiswa: s.teleponSiswa || s.telepon || '',
    namaOrangTua: s.namaOrangTua || '',
    teleponOrtu: s.teleponOrtu || s.telepon || '',
    linkMaps: s.linkMaps || ''
  };
  showSiswaModal.value = true;
}

function handleSaveSiswa() {
  if (!siswaForm.value.nama) return;
  emit('save-siswa', {
    editing: editingSiswa.value,
    data: {
      ...siswaForm.value,
      kelas: props.kelasBinaan
    }
  });
  showSiswaModal.value = false;
}

function handleDeleteSiswa(id, nama) {
  emit('delete-siswa', id);
}

// Download CSV Template & Process Import
function downloadCsvTemplate() {
  const csvContent = "data:text/csv;charset=utf-8," 
    + "NIS,Nama Lengkap,Jenis Kelamin,No WA Siswa,Nama Orang Tua,No WA Ortu,Link Google Maps Rumah\n"
    + "1001,Ahmad Rizky,Laki-laki,081234567890,Rahmat Hidayat,081299887766,https://maps.google.com/?q=-6.200000,106.816666\n"
    + "1002,Dewi Lestari,Perempuan,082345678901,Surya Lestari,082388776655,https://maps.google.com/?q=-6.210000,106.820000\n";
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `template_siswa_kelas_${props.kelasBinaan.replace(/\s+/g, '_')}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function handleFileUpload(event) {
  importFile.value = event.target.files[0];
}

function processImport() {
  if (!importFile.value) {
    alert('Pilih berkas file CSV terlebih dahulu!');
    return;
  }
  const reader = new FileReader();
  reader.onload = (e) => {
    const text = e.target.result;
    const lines = text.split('\n');
    const importedSiswa = [];
    lines.forEach((line, index) => {
      if (index === 0 || !line.trim()) return;
      const [nis, nama, jenisKelamin, teleponSiswa, namaOrangTua, teleponOrtu, linkMaps] = line.split(',').map(item => item?.trim());
      if (nama) {
        importedSiswa.push({
          id: Date.now() + index,
          nis: nis || String(1000 + (props.siswaList?.length || 0) + index + 1),
          nama,
          jenisKelamin: jenisKelamin || 'Laki-laki',
          teleponSiswa: teleponSiswa || '081234567890',
          namaOrangTua: namaOrangTua || 'Bapak/Ibu ' + nama.split(' ')[0],
          teleponOrtu: teleponOrtu || '081234567890',
          linkMaps: linkMaps || '',
          kelas: props.kelasBinaan
        });
      }
    });
    emit('import-siswa', importedSiswa);
    alert(`Berhasil mengimpor ${importedSiswa.length} data siswa baru ke kelas ${props.kelasBinaan}!`);
    showImportModal.value = false;
    importFile.value = null;
  };
  reader.readAsText(importFile.value);
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Data Siswa Kelas Binaan</span>
          <span class="text-indigo-400 text-base">👨‍🎓</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">Kelola biodata siswa kelas {{ kelasBinaan }}, kontak WhatsApp, dan lokasi Google Maps rumah</p>
      </div>

      <div class="flex flex-wrap items-center gap-3 shrink-0">
        <button
          @click="showImportModal = true"
          class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg active:scale-95 transition-all min-h-[44px]"
        >
          <span>📁</span>
          <span>Import Excel / CSV</span>
        </button>

        <button
          @click="openAddSiswaModal"
          class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-500 via-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:scale-105 active:scale-95 transition-all duration-200 min-h-[44px]"
        >
          <span>+</span>
          <span>Tambah Siswa Baru</span>
        </button>
      </div>
    </div>

    <!-- Search Bar & Summary Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="md:col-span-2 bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-white/10 shadow-xl">
        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">🔍 Cari Nama Siswa / NIS / Ortu</label>
        <input
          v-model="searchSiswa"
          type="text"
          placeholder="Cari Nama Siswa, NIS, atau Nama Orang Tua..."
          class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-2.5 text-xs sm:text-sm text-white placeholder-gray-400 focus:border-indigo-400 outline-none min-h-[44px]"
        />
      </div>

      <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl p-5 border border-indigo-500/30 shadow-xl flex items-center justify-between">
        <div>
          <span class="text-xs font-semibold text-gray-400 block">Total Siswa Binaan</span>
          <span class="text-2xl font-black text-white tracking-tight">{{ sortedFilteredSiswa.length }} Siswa</span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-mono font-black text-base border border-indigo-500/30">
          {{ sortedFilteredSiswa.length }}
        </div>
      </div>
    </div>

    <!-- Tabel Roster Siswa Kelas Binaan & Mobile Cards -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      <!-- Desktop Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300 border-collapse table-fixed min-w-[900px]">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="py-4 px-4 w-[6%] text-center border-r border-white/5">No</th>
              <th class="py-4 px-6 w-[22%] border-r border-white/5">Nama Siswa</th>
              <th class="py-4 px-4 w-[8%] text-center border-r border-white/5">L/P</th>
              <th class="py-4 px-5 w-[16%] border-r border-white/5">No. WA Siswa</th>
              <th class="py-4 px-5 w-[16%] border-r border-white/5">Orang Tua / Wali</th>
              <th class="py-4 px-5 w-[16%] border-r border-white/5">No. WA Ortu</th>
              <th class="py-4 px-4 w-[10%] text-center border-r border-white/5">Maps</th>
              <th class="py-4 px-6 w-[12%] text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr v-for="(s, idx) in sortedFilteredSiswa" :key="s.id" class="hover:bg-white/5 transition-colors">
              <td class="py-4 px-4 text-center font-mono text-xs text-indigo-300 font-bold border-r border-white/5 align-middle">{{ idx + 1 }}</td>
              
              <td class="py-4 px-6 border-r border-white/5 align-middle truncate" :title="s.nama">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-black text-xs flex items-center justify-center shrink-0 shadow-md">
                    {{ s.nama.substring(0, 2).toUpperCase() }}
                  </div>
                  <div class="truncate">
                    <div class="font-extrabold text-white text-sm truncate">{{ s.nama }}</div>
                    <div class="text-[11px] font-mono text-gray-400 font-normal">NIS: {{ s.nis || '100' + (idx+1) }}</div>
                  </div>
                </div>
              </td>

              <td class="py-4 px-4 text-center border-r border-white/5 align-middle">
                <span :class="['px-2.5 py-1 rounded-full font-black text-[10px]', s.jenisKelamin === 'Laki-laki' ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30' : 'bg-pink-500/20 text-pink-300 border border-pink-500/30']">
                  {{ s.jenisKelamin === 'Laki-laki' ? 'L' : 'P' }}
                </span>
              </td>

              <!-- No WA Siswa -->
              <td class="py-4 px-5 border-r border-white/5 font-mono text-xs align-middle truncate">
                <a
                  v-if="s.teleponSiswa || s.telepon"
                  :href="'https://wa.me/' + (s.teleponSiswa || s.telepon).replace(/[^0-9]/g, '')"
                  target="_blank"
                  class="text-cyan-300 hover:text-white hover:underline flex items-center gap-1 font-bold truncate"
                  title="Chat WhatsApp Siswa"
                >
                  📱 {{ s.teleponSiswa || s.telepon }}
                </a>
                <span v-else class="text-gray-600">—</span>
              </td>

              <!-- Nama Ortu -->
              <td class="py-4 px-5 border-r border-white/5 text-xs text-gray-300 font-semibold align-middle truncate" :title="s.namaOrangTua">
                {{ s.namaOrangTua || 'Bapak/Ibu ' + s.nama.split(' ')[0] }}
              </td>

              <!-- No WA Ortu -->
              <td class="py-4 px-5 border-r border-white/5 font-mono text-xs align-middle truncate">
                <a
                  v-if="s.teleponOrtu || s.telepon"
                  :href="'https://wa.me/' + (s.teleponOrtu || s.telepon).replace(/[^0-9]/g, '')"
                  target="_blank"
                  class="text-emerald-300 hover:text-white hover:underline flex items-center gap-1 font-bold truncate"
                  title="Chat WhatsApp Ortu"
                >
                  💬 {{ s.teleponOrtu || s.telepon }}
                </a>
                <span v-else class="text-gray-600">—</span>
              </td>

              <!-- Link Google Maps Lokasi Rumah -->
              <td class="py-4 px-4 text-center border-r border-white/5 align-middle">
                <a
                  v-if="s.linkMaps"
                  :href="s.linkMaps"
                  target="_blank"
                  class="inline-flex items-center gap-1 px-3 py-1 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs font-bold rounded-xl border border-rose-500/30 transition-all active:scale-95"
                >
                  📍 Maps
                </a>
                <span v-else class="text-gray-600 text-xs">—</span>
              </td>

              <!-- Actions Column -->
              <td class="py-4 px-6 text-right whitespace-nowrap align-middle">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    @click="openEditSiswa(s)"
                    class="px-2.5 py-1.5 text-xs font-bold text-cyan-300 bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 transition-all active:scale-95"
                    title="Edit Data Siswa"
                  >
                    ✏️ Edit
                  </button>

                  <button
                    @click="handleDeleteSiswa(s.id, s.nama)"
                    class="px-2 py-1.5 text-xs font-bold text-red-400 bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 transition-all active:scale-95"
                    title="Hapus Siswa"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="sortedFilteredSiswa.length === 0">
              <td colspan="8" class="py-12 text-center text-gray-400 text-xs font-medium">Tidak ada data siswa yang cocok.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card List View (md:hidden) -->
      <div class="md:hidden divide-y divide-white/10">
        <div
          v-for="(s, idx) in sortedFilteredSiswa"
          :key="'mob-siswa-' + s.id"
          class="p-4 space-y-3.5 hover:bg-white/[0.02] transition-colors"
        >
          <!-- Header: No, Nama, Gender, NIS -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-black text-sm flex items-center justify-center shrink-0 shadow-md">
                {{ s.nama.substring(0, 2).toUpperCase() }}
              </div>
              <div class="min-w-0">
                <div class="font-extrabold text-white text-sm truncate">{{ s.nama }}</div>
                <div class="flex items-center gap-2 text-[11px] text-gray-400 font-mono mt-0.5">
                  <span>#{{ idx + 1 }}</span>
                  <span>•</span>
                  <span>NIS: {{ s.nis || '100' + (idx + 1) }}</span>
                </div>
              </div>
            </div>

            <span
              :class="[
                'px-2.5 py-1 rounded-full font-black text-[10px] shrink-0',
                s.jenisKelamin === 'Laki-laki'
                  ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                  : 'bg-pink-500/20 text-pink-300 border border-pink-500/30'
              ]"
            >
              {{ s.jenisKelamin === 'Laki-laki' ? 'Laki-laki (L)' : 'Perempuan (P)' }}
            </span>
          </div>

          <!-- Kontak Details Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <!-- WA Siswa -->
            <div class="bg-slate-950/60 rounded-xl p-2.5 border border-white/5 flex items-center justify-between gap-2">
              <span class="text-gray-400 text-[11px] font-bold">WA Siswa:</span>
              <a
                v-if="s.teleponSiswa || s.telepon"
                :href="'https://wa.me/' + (s.teleponSiswa || s.telepon).replace(/[^0-9]/g, '')"
                target="_blank"
                class="text-cyan-300 hover:text-white font-mono font-bold text-xs truncate flex items-center gap-1"
                title="Hubungi Siswa"
              >
                📱 {{ s.teleponSiswa || s.telepon }}
              </a>
              <span v-else class="text-gray-600 font-mono">-</span>
            </div>

            <!-- WA Ortu -->
            <div class="bg-slate-950/60 rounded-xl p-2.5 border border-white/5 flex items-center justify-between gap-2">
              <span class="text-gray-400 text-[11px] font-bold truncate">Ortu: {{ s.namaOrangTua ? s.namaOrangTua.split(' ')[0] : 'Wali' }}</span>
              <a
                v-if="s.teleponOrtu || s.telepon"
                :href="'https://wa.me/' + (s.teleponOrtu || s.telepon).replace(/[^0-9]/g, '')"
                target="_blank"
                class="text-emerald-300 hover:text-white font-mono font-bold text-xs truncate flex items-center gap-1"
                title="Hubungi Orang Tua"
              >
                💬 {{ s.teleponOrtu || s.telepon }}
              </a>
              <span v-else class="text-gray-600 font-mono">-</span>
            </div>
          </div>

          <!-- Bottom Action Buttons: Maps, Edit, Hapus -->
          <div class="flex items-center justify-between gap-2 pt-1 border-t border-white/5">
            <div>
              <a
                v-if="s.linkMaps"
                :href="s.linkMaps"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs font-bold rounded-xl border border-rose-500/30 min-h-[38px] transition-all active:scale-95"
              >
                📍 Lokasi Rumah
              </a>
              <span v-else class="text-gray-600 text-xs italic">Lokasi belum diset</span>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="openEditSiswa(s)"
                class="px-3 py-2 text-xs font-bold text-cyan-300 bg-cyan-500/15 hover:bg-cyan-500/30 rounded-xl border border-cyan-500/30 min-h-[38px] transition-all active:scale-95 flex items-center gap-1"
                title="Edit Data Siswa"
              >
                ✏️ Edit
              </button>
              <button
                @click="handleDeleteSiswa(s.id, s.nama)"
                class="px-3 py-2 text-xs font-bold text-red-400 bg-red-500/15 hover:bg-red-500/30 rounded-xl border border-red-500/30 min-h-[38px] transition-all active:scale-95 flex items-center gap-1"
                title="Hapus Siswa"
              >
                🗑️ Hapus
              </button>
            </div>
          </div>
        </div>

        <div v-if="sortedFilteredSiswa.length === 0" class="py-12 text-center text-gray-400 text-xs font-medium">
          Tidak ada data siswa yang cocok.
        </div>
      </div>
    </div>

    <!-- Modal Form Tambah / Edit Siswa -->
    <div v-if="showSiswaModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto">
      <div class="bg-slate-900 border border-white/10 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto transform transition-all animate-in fade-in zoom-in-95">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">
            {{ editingSiswa ? 'Edit Biodata Siswa' : 'Tambah Siswa Baru (' + kelasBinaan + ')' }}
          </h3>
          <button @click="showSiswaModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSaveSiswa" class="space-y-4 text-xs sm:text-sm">
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">NIS / NISN *</label>
            <input
              v-model="siswaForm.nis"
              type="text"
              placeholder="Contoh: 1001"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-indigo-400 outline-none font-mono min-h-[44px]"
              required
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nama Lengkap Siswa *</label>
            <input
              v-model="siswaForm.nama"
              type="text"
              placeholder="Nama Lengkap Siswa"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-indigo-400 outline-none font-bold text-sm min-h-[44px]"
              required
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Jenis Kelamin</label>
            <select
              v-model="siswaForm.jenisKelamin"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-indigo-400 outline-none text-xs font-semibold min-h-[44px]"
            >
              <option value="Laki-laki">Laki-laki</option>
              <option value="Perempuan">Perempuan</option>
            </select>
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">No. WhatsApp Siswa 📱</label>
            <input
              v-model="siswaForm.teleponSiswa"
              type="text"
              placeholder="Contoh: 081234567890"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white font-mono focus:border-indigo-400 outline-none min-h-[44px]"
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Nama Orang Tua / Wali</label>
            <input
              v-model="siswaForm.namaOrangTua"
              type="text"
              placeholder="Contoh: Rahmat Hidayat"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white focus:border-indigo-400 outline-none min-h-[44px]"
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">No. WhatsApp Orang Tua / Wali 💬</label>
            <input
              v-model="siswaForm.teleponOrtu"
              type="text"
              placeholder="Contoh: 081299887766"
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white font-mono focus:border-indigo-400 outline-none min-h-[44px]"
            />
          </div>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Link Google Maps Rumah Siswa 📍</label>
            <input
              v-model="siswaForm.linkMaps"
              type="url"
              placeholder="https://maps.google.com/?q=..."
              class="w-full bg-slate-800 border border-white/10 rounded-2xl px-4 py-3 text-white font-mono text-xs focus:border-indigo-400 outline-none min-h-[44px]"
            />
            <p class="text-[11px] text-gray-400 mt-1.5 font-medium">Salin link lokasi tempat tinggal siswa dari Google Maps untuk keperluan home visit.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button type="button" @click="showSiswaModal = false" class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl text-xs font-bold min-h-[44px]">Batal</button>
            <button type="submit" class="px-5 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-2xl text-xs font-black shadow-lg hover:shadow-indigo-500/25 active:scale-95 transition-all min-h-[44px]">Simpan Data Siswa</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Import Excel / CSV -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
      <div class="bg-slate-900 border border-white/10 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5 transform transition-all animate-in fade-in zoom-in-95">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <h3 class="text-lg font-black text-white">Import Data Siswa ({{ kelasBinaan }})</h3>
          <button @click="showImportModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <div class="space-y-4 text-xs sm:text-sm">
          <p class="text-gray-300 font-medium">Unggah berkas CSV sesuai format kolom template:</p>
          
          <button @click="downloadCsvTemplate" class="w-full py-3 bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-300 font-bold rounded-2xl border border-indigo-500/30 flex items-center justify-center gap-2 transition-all">
            📥 Unduh Template CSV Siswa
          </button>

          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Pilih File CSV</label>
            <input type="file" accept=".csv" @change="handleFileUpload" class="w-full text-xs text-gray-300 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white font-bold cursor-pointer" />
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
          <button @click="showImportModal = false" class="px-5 py-3 bg-white/5 hover:bg-white/10 text-gray-400 rounded-2xl text-xs font-bold min-h-[44px]">Batal</button>
          <button @click="processImport" class="px-5 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-2xl text-xs font-black shadow-lg active:scale-95 transition-all min-h-[44px]">Proses Import</button>
        </div>
      </div>
    </div>

  </div>
</template>
