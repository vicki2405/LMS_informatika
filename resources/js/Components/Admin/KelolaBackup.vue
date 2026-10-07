<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const importForm = useForm({
  backup_file: null
});

const fileInputRef = ref(null);
const selectedFileName = ref('');

function onFileChange(event) {
  const file = event.target.files[0];
  if (file) {
    importForm.backup_file = file;
    selectedFileName.value = file.name;
  }
}

function handleExport() {
  window.open(route('admin.backup.export'), '_blank');
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

  Swal.fire({
    title: 'Konfirmasi Restore Database',
    text: 'PERINGATAN: Seluruh data saat ini di sistem (termasuk semua user, materi, tugas, dan absensi) akan DIHAPUS dan digantikan sepenuhnya oleh data dari file backup!',
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
        text: 'Mengimpor data dan menyelaraskan sequence database. Jangan tutup halaman ini.',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      importForm.post(route('admin.backup.import'), {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Restore Berhasil!',
            text: 'Database sistem sekolah telah berhasil dikembalikan ke status file backup.',
            confirmButtonColor: '#10b981',
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
              Unduh cadangan data lengkap sistem sekolah dalam format berkas **JSON**. File ini berisi seluruh record tabel terenkripsi UTF-8, termasuk detail user pengajar, siswa, kurikulum mapel, absensi, hingga materi tugas kelas.
            </p>
          </div>

          <div class="p-4 bg-slate-950/40 rounded-2xl border border-white/5 space-y-2">
            <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-cyan-500 inline-block"></span>
              Karakteristik Backup JSON:
            </h4>
            <ul class="text-xs text-gray-400 space-y-1 font-medium pl-4 list-disc">
              <li>Kompatibel di semua tipe database (MySQL, PostgreSQL, SQLite).</li>
              <li>Aman ditransfer ke server Laragon lokal maupun hosting cloud VPS.</li>
              <li>Menyimpan password login terenkripsi Laravel Hash.</li>
            </ul>
          </div>
        </div>

        <div class="pt-4 border-t border-white/5">
          <button
            @click="handleExport"
            class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-blue-600 to-indigo-600 text-white py-3.5 px-6 rounded-2xl font-black text-sm shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:scale-[1.02] active:scale-95 transition-all duration-200 min-h-[44px]"
          >
            <span>📥</span>
            <span>Unduh Cadangan (.json)</span>
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
              Unggah berkas cadangan JSON yang telah diekspor sebelumnya untuk mengembalikan seluruh keadaan database. Sistem akan menghapus data tabel saat ini dan menggantinya dengan isi berkas tersebut.
            </p>
          </div>

          <!-- Danger Alert Banner -->
          <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-2xl flex gap-3">
            <span class="text-xl">⚠️</span>
            <div class="space-y-1">
              <h4 class="text-xs font-bold text-red-300 uppercase tracking-wider">Perhatian Penting</h4>
              <p class="text-[11px] text-gray-400 font-medium leading-relaxed">
                Tindakan restore bersifat destruktif terhadap database saat ini. Pastikan Anda mengunggah file backup yang tepat agar integritas data pengguna tidak terganggu.
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
                class="flex-1 px-4 py-3 bg-slate-800 hover:bg-slate-700/80 border border-white/10 rounded-2xl text-xs sm:text-sm font-bold text-gray-300 transition-all active:scale-95 min-h-[44px] text-left truncate"
              >
                📁 {{ selectedFileName || 'Pilih File Backup...' }}
              </button>
            </div>
          </div>

          <button
            @click="handleImport"
            :disabled="importForm.processing"
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
