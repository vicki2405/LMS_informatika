<script setup>
import { ref } from 'vue';
import { showToast } from '@/Utils/sweetalert';

const props = defineProps({
  tugasList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  siswaObj: { type: Object, default: () => ({}) },
  activeSiswa: { type: Object, default: () => null },
});

const emit = defineEmits(['submit-tugas']);

const showUploadModal = ref(false);
const selectedTugasUpload = ref(null);
const uploadForm = ref({
  siswaId: props.siswaObj?.id || '',
  catatan: '',
  linkHasil: ''
});

function openUploadModal(tugas) {
  selectedTugasUpload.value = tugas;
  uploadForm.value = {
    siswaId: props.activeSiswa?.id || props.siswaObj?.id || (props.siswaList?.[0]?.id ?? ''),
    catatan: '',
    linkHasil: ''
  };
  showUploadModal.value = true;
}

function handleKumpulTugas() {
  if (!uploadForm.value.linkHasil) {
    showToast('Masukkan link pengerjaan tugas atau tautan Google Drive / GitHub!', 'warning');
    return;
  }
  emit('submit-tugas', {
    tugasId: selectedTugasUpload.value.id,
    data: { ...uploadForm.value }
  });
  showUploadModal.value = false;
  showToast('Tugas Anda berhasil dikirimkan!', 'success');
}
</script>

<template>
  <div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Tugas & Upload Penugasan</h1>
        <p class="text-gray-400 mt-1">Daftar penugasan kelas, batas waktu pengumpulan, dan portal pengiriman tugas</p>
      </div>

      <span class="bg-cyan-500/10 text-cyan-300 text-xs font-bold px-3.5 py-1.5 rounded-xl border border-cyan-500/20 w-fit">
        📋 {{ tugasList.length }} Tugas Terdaftar
      </span>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden md:block bg-white/5 rounded-2xl border border-white/10 overflow-hidden shadow-xl">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-300">
          <thead class="bg-white/5 text-gray-400 uppercase text-xs">
            <tr>
              <th class="px-6 py-4">Mata Pelajaran</th>
              <th class="px-6 py-4">Judul Tugas</th>
              <th class="px-6 py-4">Batas Waktu (Deadline)</th>
              <th class="px-6 py-4">Status Pengiriman</th>
              <th class="px-6 py-4 text-center">Nilai</th>
              <th class="px-6 py-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            <tr v-for="t in tugasList" :key="t.id" class="hover:bg-white/5 transition-colors">
              <td class="px-6 py-4">
                <span class="bg-cyan-500/20 text-cyan-300 text-xs font-semibold px-3 py-1 rounded-full border border-cyan-500/30">
                  {{ t.mapel }}
                </span>
              </td>
              <td class="px-6 py-4 font-bold text-white">{{ t.judul }}</td>
              <td class="px-6 py-4 text-xs font-mono text-amber-300">⏰ {{ t.deadline }}</td>
              <td class="px-6 py-4 text-xs">
                <span :class="[
                  'px-3 py-1 rounded-full font-semibold border',
                  t.status === 'Sudah Dikirim' || t.status === 'Dinilai' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                  'bg-amber-500/20 text-amber-300 border-amber-500/30'
                ]">
                  {{ t.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-center font-mono font-bold text-white">
                {{ t.nilai !== null && t.nilai !== undefined ? t.nilai : '-' }}
              </td>
              <td class="px-6 py-4 text-right">
                <button
                  v-if="t.status === 'Belum Dikerjakan'"
                  @click="openUploadModal(t)"
                  class="text-cyan-300 hover:text-white font-bold text-xs bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 rounded-xl shadow-lg shadow-cyan-500/20"
                >
                  🚀 Kumpul Tugas
                </button>
                <span v-else class="text-xs text-gray-400 font-semibold italic">
                  ✓ Tugas Terkirim
                </span>
              </td>
            </tr>
            <tr v-if="tugasList.length === 0">
              <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                Belum ada penugasan aktif untuk kelas Anda saat ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile Card List View (md:hidden) -->
    <div class="md:hidden space-y-3">
      <div
        v-for="t in tugasList"
        :key="'mobile-' + t.id"
        class="bg-slate-800/90 border border-white/10 rounded-2xl p-4 shadow-lg space-y-3"
      >
        <div class="flex items-start justify-between gap-2">
          <span class="bg-cyan-500/20 text-cyan-300 text-xs font-semibold px-2.5 py-1 rounded-lg border border-cyan-500/30">
            {{ t.mapel }}
          </span>
          <span :class="[
            'px-2.5 py-1 rounded-lg font-semibold text-[11px] border',
            t.status === 'Sudah Dikirim' || t.status === 'Dinilai' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
            'bg-amber-500/20 text-amber-300 border-amber-500/30'
          ]">
            {{ t.status }}
          </span>
        </div>

        <div>
          <h4 class="font-bold text-white text-base leading-snug">{{ t.judul }}</h4>
          <p class="text-xs font-mono text-amber-300 mt-1 flex items-center gap-1">
            <span>⏰</span>
            <span>Batas Waktu: {{ t.deadline }}</span>
          </p>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-white/10">
          <div class="text-xs text-gray-400">
            Nilai:
            <span class="font-bold font-mono text-white text-sm ml-1">
              {{ t.nilai !== null && t.nilai !== undefined ? t.nilai : '-' }}
            </span>
          </div>

          <div>
            <button
              v-if="t.status === 'Belum Dikerjakan'"
              @click="openUploadModal(t)"
              class="min-h-[44px] px-4 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold text-xs shadow-md shadow-cyan-500/20 flex items-center gap-1.5 active:scale-95 transition-all"
            >
              🚀 Kumpul Tugas
            </button>
            <span v-else class="text-xs text-gray-400 font-semibold italic flex items-center gap-1">
              ✓ Tugas Terkirim
            </span>
          </div>
        </div>
      </div>

      <div v-if="tugasList.length === 0" class="bg-slate-800/60 border border-white/10 rounded-2xl p-8 text-center text-gray-400 text-xs">
        Belum ada penugasan aktif untuk kelas Anda saat ini.
      </div>
    </div>

    <!-- MODAL KUMPUL TUGAS -->
    <div v-if="showUploadModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-slate-800 border border-white/10 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-bold text-white mb-1">Kumpul Tugas Siswa</h3>
        <p class="text-xs text-cyan-300 mb-4">{{ selectedTugasUpload?.judul }} ({{ selectedTugasUpload?.mapel }})</p>

        <form @submit.prevent="handleKumpulTugas" class="space-y-4">
          <!-- Student Selector if on shared class account -->
          <div v-if="siswaList && siswaList.length > 0">
            <label class="block text-xs font-semibold text-gray-300 uppercase mb-1">Nama Siswa Pengumpul</label>
            <select
              v-model="uploadForm.siswaId"
              class="w-full min-h-[44px] bg-slate-700 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:border-cyan-400 outline-none text-sm"
              required
            >
              <option value="" disabled>-- Pilih Nama Anda --</option>
              <option v-for="s in siswaList" :key="s.id" :value="s.id">
                {{ s.nis }} — {{ s.nama }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-300 uppercase mb-1">Link Hasil Pengerjaan (Google Drive / GitHub / URL)</label>
            <input
              v-model="uploadForm.linkHasil"
              type="url"
              placeholder="https://drive.google.com/..."
              class="w-full min-h-[44px] bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:border-cyan-400 outline-none text-sm"
              required
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-300 uppercase mb-1">Catatan Pengerjaan (Opsional)</label>
            <textarea
              v-model="uploadForm.catatan"
              rows="3"
              placeholder="Pesan singkat untuk bapak/ibu guru..."
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-cyan-400 outline-none text-sm"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button
              type="button"
              @click="showUploadModal = false"
              class="min-h-[44px] px-5 py-2.5 rounded-xl border border-white/10 text-gray-400 text-sm font-semibold hover:bg-white/5"
            >
              Batal
            </button>
            <button
              type="submit"
              class="min-h-[44px] px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-sm font-bold shadow-lg shadow-cyan-500/25 active:scale-95 transition-all"
            >
              Kirimkan Tugas
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
