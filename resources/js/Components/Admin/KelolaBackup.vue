<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const importForm = useForm({
  backup_file: null
});

const fileInputRef = ref(null);
const selectedFileName = ref('');

const fileStats = ref({
  name: '',
  size: '',
  siswaCount: 0,
  guruCount: 0,
  kelasCount: 0,
  jadwalCount: 0,
  cbtCount: 0,
  isInspected: false,
  isEmpty: false
});

function onFileChange(event) {
  const file = event.target.files[0];
  if (!file) return;

  importForm.backup_file = file;
  selectedFileName.value = file.name;

  const sizeMb = file.size / (1024 * 1024);
  const formattedSize = sizeMb >= 1
    ? `${sizeMb.toFixed(2)} MB`
    : `${(file.size / 1024).toFixed(1)} KB`;

  fileStats.value = {
    name: file.name,
    size: formattedSize,
    siswaCount: 0,
    guruCount: 0,
    kelasCount: 0,
    jadwalCount: 0,
    cbtCount: 0,
    isInspected: false,
    isEmpty: false
  };

  // Inspect JSON structure client-side
  const reader = new FileReader();
  reader.onload = (e) => {
    try {
      const parsed = JSON.parse(e.target.result);
      const siswa = Array.isArray(parsed.siswas) ? parsed.siswas.length : 0;
      const guru = Array.isArray(parsed.gurus) ? parsed.gurus.length : 0;
      const kelas = Array.isArray(parsed.kelases) ? parsed.kelases.length : 0;
      const jadwal = Array.isArray(parsed.jadwals) ? parsed.jadwals.length : 0;
      const cbt = (Array.isArray(parsed.cbt_soals) ? parsed.cbt_soals.length : 0) +
                  (Array.isArray(parsed.cbt_bank_soals) ? parsed.cbt_bank_soals.length : 0);

      fileStats.value.siswaCount = siswa;
      fileStats.value.guruCount = guru;
      fileStats.value.kelasCount = kelas;
      fileStats.value.jadwalCount = jadwal;
      fileStats.value.cbtCount = cbt;
      fileStats.value.isInspected = true;
      fileStats.value.isEmpty = (siswa === 0 && guru === 0 && kelas === 0);
    } catch (err) {
      fileStats.value.isInspected = true;
      fileStats.value.isEmpty = false;
    }
  };
  reader.readAsText(file);
}

function handleExport() {
  const link = document.createElement('a');
  link.href = route('admin.backup.export');
  link.setAttribute('download', '');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function handleImport() {
  if (!importForm.backup_file) {
    Swal.fire({
      icon: 'warning',
      title: 'Pilih Berkas',
      text: 'Silakan pilih berkas backup (.json) terlebih dahulu!',
      confirmButtonColor: '#06b6d4',
    });
    return;
  }

  const warningText = fileStats.value.isEmpty
    ? 'PERINGATAN KERAS: Berkas backup yang Anda pilih terdeteksi KOSONG (0 Siswa, 0 Guru)! Jika dilanjutkan, seluruh data sistem saat ini akan terhapus dan menjadi kosong. Yakin ingin melanjutkan?'
    : 'PERINGATAN: Seluruh data saat ini di sistem (termasuk user, materi, tugas, dan absensi) akan DIHAPUS dan digantikan sepenuhnya oleh data dari file backup!';

  Swal.fire({
    title: fileStats.value.isEmpty ? '⚠️ File Backup Kosong!' : 'Konfirmasi Restore Database',
    text: warningText,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#475569',
    confirmButtonText: 'Ya, Ganti & Restore!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Sedang Memproses Database...',
        text: 'Mengimpor data dan menyelaraskan sequence database. Mohon jangan menutup halaman ini.',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      importForm.post(route('admin.backup.import'), {
        preserveScroll: true,
        onSuccess: (page) => {
          Swal.fire({
            icon: 'success',
            title: 'Restore Berhasil!',
            text: page.props.flash?.message || 'Database sistem sekolah telah berhasil dikembalikan ke status file backup.',
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Buka Dashboard',
          }).then(() => {
            window.location.href = route('admin.dashboard');
          });
          selectedFileName.value = '';
          importForm.reset();
          if (fileInputRef.value) fileInputRef.value.value = '';
        },
        onError: (errors) => {
          Swal.fire({
            icon: 'error',
            title: 'Gagal Restore',
            text: errors.backup_file || 'Terjadi kesalahan sistem ketika memulihkan database.',
            confirmButtonColor: '#ef4444',
          });
        }
      });
    }
  });
}
</script>

<template>
  <div class="space-y-8 animate-in fade-in duration-300">
    
    <!-- Header Title Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/10 pb-5">
      <div>
        <h2 class="text-2xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Backup & Restore Database</span>
          <span class="text-cyan-400 text-base">✦</span>
        </h2>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">Cadangkan seluruh data sistem sekolah atau pulihkan database Anda dalam sekali klik.</p>
      </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      
      <!-- Panel 1: Export Database -->
      <div class="relative overflow-hidden rounded-3xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-6 sm:p-8 shadow-2xl flex flex-col justify-between space-y-6">
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="space-y-4">
          <div class="w-14 h-14 rounded-2xl bg-cyan-500/15 border border-cyan-500/30 flex items-center justify-center text-3xl shadow-md">
            📥
          </div>
          <div class="space-y-2">
            <h3 class="text-lg font-extrabold text-white">Ekspor Data (Backup)</h3>
            <p class="text-gray-400 text-xs sm:text-sm leading-relaxed font-medium">
              Unduh cadangan data lengkap sistem sekolah dalam format berkas **JSON**. File ini mencakup seluruh 30 tabel database termasuk user, guru, siswa, rombel, nilai, materi, absensi, hingga modul CBT & Bank Soal.
            </p>
          </div>

          <div class="p-4 bg-slate-950/40 rounded-2xl border border-white/5 space-y-2">
            <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-cyan-500 inline-block"></span>
              Cakupan Modul Data Backup:
            </h4>
            <ul class="text-xs text-gray-400 space-y-1 font-medium pl-4 list-disc">
              <li>Master Akademik: Siswa, Guru, Kelas, Jurusan, Mapel, Kelas Ampuan.</li>
              <li>Akademik & KBM: Jadwal, Absensi, Jurnal Mengajar Guru, Nilai TP.</li>
              <li>Modul CBT: Bank Soal, Butir Pertanyaan, Jadwal Ujian, Rekap Nilai CBT.</li>
              <li>Materi & Tugas: Modul Pembelajaran, Progres Membaca, Pengumpulan Tugas.</li>
            </ul>
          </div>
        </div>

        <div class="pt-4 border-t border-white/5">
          <button
            @click="handleExport"
            class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-blue-600 to-indigo-600 text-white py-3.5 px-6 rounded-2xl font-black text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-[1.02] active:scale-95 transition-all duration-200 min-h-[44px]"
          >
            <span>📥</span>
            <span>Unduh Cadangan Lengkap (.json)</span>
          </button>
        </div>
      </div>

      <!-- Panel 2: Import / Restore Database -->
      <div class="relative overflow-hidden rounded-3xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-6 sm:p-8 shadow-2xl flex flex-col justify-between space-y-6">
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="space-y-4">
          <div class="w-14 h-14 rounded-2xl bg-red-500/15 border border-red-500/30 flex items-center justify-center text-3xl shadow-md">
            📤
          </div>
          <div class="space-y-2">
            <h3 class="text-lg font-extrabold text-white">Pulihkan Data (Restore)</h3>
            <p class="text-gray-400 text-xs sm:text-sm leading-relaxed font-medium">
              Unggah berkas cadangan JSON untuk memulihkan seluruh keadaan database sekolah. Sistem akan memverifikasi struktur file dan menyisipkan data secara aman per batch.
            </p>
          </div>

          <!-- Danger Alert Banner -->
          <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-2xl flex gap-3">
            <span class="text-xl">⚠️</span>
            <div class="space-y-1">
              <h4 class="text-xs font-bold text-red-300 uppercase tracking-wider">Perhatian Penting</h4>
              <p class="text-[11px] text-gray-400 font-medium leading-relaxed">
                Tindakan restore bersifat destruktif terhadap database saat ini. Pastikan berkas yang Anda pilih berisi data lengkap yang sah.
              </p>
            </div>
          </div>
        </div>

        <div class="space-y-4 pt-4 border-t border-white/5">
          <div>
            <label class="block text-gray-300 font-bold uppercase text-[11px] mb-1.5">Pilih Berkas Cadangan (.json)</label>
            <div class="flex items-center gap-3">
              <input
                type="file"
                ref="fileInputRef"
                accept=".json,.txt"
                @change="onFileChange"
                class="hidden"
              />
              <button
                type="button"
                @click="fileInputRef.click()"
                class="flex-1 px-4 py-3 bg-slate-800 hover:bg-slate-700/80 border border-white/10 rounded-2xl text-xs sm:text-sm font-bold text-gray-300 transition-all active:scale-95 min-h-[44px] text-left truncate flex items-center justify-between"
              >
                <span class="truncate">📁 {{ selectedFileName || 'Pilih File Backup...' }}</span>
                <span v-if="fileStats.size" class="text-[11px] px-2.5 py-1 rounded-full bg-cyan-500/20 text-cyan-300 font-mono ml-2 shrink-0">
                  {{ fileStats.size }}
                </span>
              </button>
            </div>

            <!-- Smart File Inspector Card -->
            <div v-if="fileStats.isInspected" class="mt-3 p-3.5 rounded-2xl border transition-all duration-300"
                 :class="fileStats.isEmpty ? 'bg-red-950/40 border-red-500/40 text-red-200' : 'bg-slate-950/60 border-cyan-500/30 text-gray-200'">
              
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-black uppercase tracking-wider flex items-center gap-1.5"
                      :class="fileStats.isEmpty ? 'text-red-400' : 'text-cyan-400'">
                  <span>{{ fileStats.isEmpty ? '⚠️' : '🔍' }}</span>
                  <span>Hasil Analisis Berkas</span>
                </span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                      :class="fileStats.isEmpty ? 'bg-red-500/20 text-red-300' : 'bg-emerald-500/20 text-emerald-300'">
                  {{ fileStats.isEmpty ? 'Berkas Kosong' : 'Berkas Siap Dipulihkan' }}
                </span>
              </div>

              <!-- Warning for empty file -->
              <p v-if="fileStats.isEmpty" class="text-xs text-red-300 font-medium leading-relaxed">
                Berkas ini terdeteksi <strong>kosong (0 Siswa & 0 Guru)</strong>. Kemungkinan Anda memilih file backup dari database yang baru di-reset. Mohon periksa kembali file backup Anda!
              </p>

              <!-- Stats Grid for Valid File -->
              <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1 text-center">
                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                  <div class="text-base font-black text-cyan-300">{{ fileStats.siswaCount.toLocaleString() }}</div>
                  <div class="text-[10px] text-gray-400 font-semibold">Siswa</div>
                </div>
                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                  <div class="text-base font-black text-purple-300">{{ fileStats.guruCount.toLocaleString() }}</div>
                  <div class="text-[10px] text-gray-400 font-semibold">Guru</div>
                </div>
                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                  <div class="text-base font-black text-emerald-300">{{ fileStats.kelasCount.toLocaleString() }}</div>
                  <div class="text-[10px] text-gray-400 font-semibold">Kelas</div>
                </div>
                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                  <div class="text-base font-black text-amber-300">{{ fileStats.jadwalCount.toLocaleString() }}</div>
                  <div class="text-[10px] text-gray-400 font-semibold">Jadwal</div>
                </div>
              </div>
            </div>
          </div>

          <button
            @click="handleImport"
            :disabled="importForm.processing || !selectedFileName"
            class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-red-600 to-pink-600 text-white py-3.5 px-6 rounded-2xl font-black text-sm shadow-lg shadow-red-500/25 hover:shadow-red-500/40 hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:pointer-events-none transition-all duration-200 min-h-[44px]"
          >
            <span>📤</span>
            <span>Mulai Pulihkan Database</span>
          </button>
        </div>
      </div>

    </div>
  </div>
</template>
