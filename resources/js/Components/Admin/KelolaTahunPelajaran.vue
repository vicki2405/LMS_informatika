<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmDelete, showToast } from '@/Utils/sweetalert';

const props = defineProps({
  tahunAjaranList: {
    type: Array,
    default: () => []
  }
});

const isSubmitting = ref(false);
const showModal = ref(false);
const isEditing = ref(false);
const searchQuery = ref('');

const form = ref({
  id: null,
  kode: '',
  is_aktif: false
});

// Currently active / default school year
const activeTahunAjaran = computed(() => {
  return (props.tahunAjaranList || []).find(ta => ta.is_aktif) || null;
});

// Filtered list based on search
const filteredList = computed(() => {
  const list = props.tahunAjaranList || [];
  if (!searchQuery.value.trim()) return list;
  const q = searchQuery.value.toLowerCase();
  return list.filter(ta => (ta.kode || '').toLowerCase().includes(q));
});

function openAddModal() {
  isEditing.value = false;
  form.value = {
    id: null,
    kode: '',
    is_aktif: (props.tahunAjaranList || []).length === 0
  };
  showModal.value = true;
}

function openEditModal(ta) {
  isEditing.value = true;
  form.value = {
    id: ta.id,
    kode: ta.kode,
    is_aktif: ta.is_aktif
  };
  showModal.value = true;
}

function submitForm() {
  if (!form.value.kode.trim()) {
    showToast('Tahun Pelajaran wajib diisi (misal: 2026/2027)', 'warning');
    return;
  }

  isSubmitting.value = true;
  router.post(route('admin.tahun-ajaran.store'), form.value, {
    preserveScroll: true,
    onSuccess: () => {
      showModal.value = false;
      showToast(`Tahun Pelajaran ${form.value.kode} berhasil disimpan!`, 'success');
      isSubmitting.value = false;
    },
    onError: (err) => {
      isSubmitting.value = false;
      const msg = Object.values(err)[0] || 'Gagal menyimpan Tahun Pelajaran';
      showToast(msg, 'error');
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function handleSetDefault(ta) {
  if (ta.is_aktif) return;

  isSubmitting.value = true;
  router.post(route('admin.tahun-ajaran.set-default', ta.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      showToast(`Tahun Pelajaran ${ta.kode} berhasil dijadikan Default Aktif!`, 'success');
      isSubmitting.value = false;
    },
    onError: () => {
      isSubmitting.value = false;
      showToast('Gagal mengubah Tahun Pelajaran default', 'error');
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function handleDelete(ta) {
  if (ta.is_aktif) {
    showToast('Tahun Pelajaran yang sedang aktif tidak dapat dihapus!', 'warning');
    return;
  }

  confirmDelete(`Hapus Tahun Pelajaran ${ta.kode}?`, 'Data yang terikat dengan tahun ajaran ini mungkin terdampak.').then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.tahun-ajaran.delete', ta.id), {
        preserveScroll: true,
        onSuccess: () => {
          showToast(`Tahun Pelajaran ${ta.kode} berhasil dihapus!`, 'success');
        },
        onError: (err) => {
          const msg = Object.values(err)[0] || 'Gagal menghapus Tahun Pelajaran';
          showToast(msg, 'error');
        }
      });
    }
  });
}
</script>

<template>
  <div class="space-y-5 animate-in fade-in duration-200">
    
    <!-- Header Ringkas & Aksi Utama (Satu Baris Terpadu) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
            Tahun Pelajaran
          </h1>
          <span
            v-if="activeTahunAjaran"
            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-[11px] font-bold"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Aktif: {{ activeTahunAjaran.kode }}</span>
          </span>
        </div>
        <p class="text-gray-400 text-xs mt-0.5">
          Acuan resmi kalender akademik, pencatatan nilai, dan kop cetak berkas rapor sekolah.
        </p>
      </div>

      <!-- Toolbar: Pencarian Ringkas & Tombol Tambah -->
      <div class="flex items-center gap-2.5 shrink-0">
        <div class="relative w-44 sm:w-56">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari tahun..."
            class="w-full pl-8 pr-3 py-2 bg-slate-900 border border-white/10 focus:border-purple-400 rounded-xl text-xs text-white placeholder-gray-500 outline-none transition-all h-[40px]"
          />
          <span class="absolute left-2.5 top-2.5 text-xs text-gray-500">🔍</span>
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute right-2.5 top-2.5 text-xs text-gray-400 hover:text-white"
          >
            ✕
          </button>
        </div>

        <button
          type="button"
          @click="openAddModal"
          class="inline-flex items-center gap-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white px-4 py-2 rounded-xl font-bold text-xs shadow-lg shadow-purple-950/30 active:scale-95 transition-all h-[40px]"
        >
          <span>➕</span>
          <span class="hidden sm:inline">Tambah Tahun</span>
          <span class="sm:hidden">Tambah</span>
        </button>
      </div>
    </div>

    <!-- DESKTOP VIEW: TABEL RINGKAS (hidden md:block) -->
    <div class="hidden md:block bg-slate-900/90 rounded-2xl border border-white/10 overflow-hidden shadow-xl">
      <table class="w-full text-left text-xs sm:text-sm text-gray-300">
        <thead class="bg-slate-950/70 text-gray-400 uppercase text-[11px] font-bold tracking-wider border-b border-white/10">
          <tr>
            <th class="px-4 py-3.5 text-center w-12 border-r border-white/5">No</th>
            <th class="px-5 py-3.5 border-r border-white/5">Tahun Pelajaran</th>
            <th class="px-5 py-3.5 text-center w-48 border-r border-white/5">Status Default</th>
            <th class="px-5 py-3.5 text-right w-40">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5 font-medium">
          <tr
            v-for="(ta, index) in filteredList"
            :key="ta.id"
            class="hover:bg-white/[0.02] transition-colors"
            :class="{'bg-purple-500/[0.03]': ta.is_aktif}"
          >
            <!-- 1. Nomor -->
            <td class="px-4 py-3 text-center font-mono text-gray-400 text-xs border-r border-white/5">
              {{ index + 1 }}
            </td>

            <!-- 2. Tahun Pelajaran -->
            <td class="px-5 py-3 border-r border-white/5">
              <div class="flex items-center gap-2.5">
                <span class="text-sm font-black text-white tracking-wide">
                  {{ ta.kode }}
                </span>
                <span
                  v-if="ta.is_aktif"
                  class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[10px] font-extrabold tracking-wide"
                >
                  AKTIF SISTEM
                </span>
              </div>
            </td>

            <!-- 3. Status Default & Toggle Action -->
            <td class="px-5 py-3 text-center border-r border-white/5">
              <span
                v-if="ta.is_aktif"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-bold"
              >
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Default Aktif</span>
              </span>
              <button
                v-else
                type="button"
                @click="handleSetDefault(ta)"
                :disabled="isSubmitting"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-800 hover:bg-purple-600 text-gray-300 hover:text-white border border-white/10 hover:border-purple-500 text-xs font-bold transition-all active:scale-95"
                title="Jadikan tahun pelajaran ini sebagai default sistem"
              >
                <span>⭐</span>
                <span>Jadikan Default</span>
              </button>
            </td>

            <!-- 4. Tombol Aksi (Edit & Hapus) -->
            <td class="px-5 py-3 text-right">
              <div class="flex items-center justify-end gap-1.5">
                <button
                  type="button"
                  @click="openEditModal(ta)"
                  class="p-1.5 px-2.5 text-xs font-bold text-gray-300 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg border border-white/10 transition-all active:scale-95"
                  title="Edit Nama Tahun"
                >
                  ✏️ Edit
                </button>
                <button
                  type="button"
                  @click="handleDelete(ta)"
                  :disabled="ta.is_aktif"
                  class="p-1.5 px-2.5 text-xs font-bold rounded-lg border transition-all active:scale-95"
                  :class="ta.is_aktif ? 'opacity-30 cursor-not-allowed bg-transparent text-gray-600 border-white/5' : 'text-rose-400 bg-rose-500/10 hover:bg-rose-500/25 border-rose-500/20'"
                  :title="ta.is_aktif ? 'Tahun pelajaran aktif tidak bisa dihapus' : 'Hapus Tahun'"
                >
                  🗑️ Hapus
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="filteredList.length === 0">
            <td colspan="4" class="px-6 py-10 text-center text-gray-400 text-xs">
              Tidak ada data tahun pelajaran yang sesuai pencarian.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MOBILE VIEW: KARTU LIST RINGKAS (md:hidden) -->
    <div class="block md:hidden space-y-2.5">
      <div
        v-for="(ta, index) in filteredList"
        :key="ta.id"
        class="bg-slate-900/90 rounded-xl p-3.5 border border-white/10 shadow-md space-y-2.5"
        :class="{'border-purple-500/40 bg-purple-950/10': ta.is_aktif}"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-white/5 text-[10px] font-mono text-gray-400 flex items-center justify-center">
              {{ index + 1 }}
            </span>
            <span class="text-sm font-black text-white">
              {{ ta.kode }}
            </span>
          </div>

          <span
            v-if="ta.is_aktif"
            class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold"
          >
            DEFAULT
          </span>
          <button
            v-else
            type="button"
            @click="handleSetDefault(ta)"
            :disabled="isSubmitting"
            class="px-2.5 py-1 rounded-lg bg-purple-600 text-white text-[11px] font-bold active:scale-95 min-h-[36px]"
          >
            ⭐ Set Default
          </button>
        </div>

        <div class="pt-2 border-t border-white/5 flex items-center justify-end gap-2">
          <button
            type="button"
            @click="openEditModal(ta)"
            class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-gray-300 text-xs font-bold min-h-[36px] active:scale-95"
          >
            ✏️ Edit
          </button>
          <button
            type="button"
            @click="handleDelete(ta)"
            :disabled="ta.is_aktif"
            class="px-3 py-1.5 rounded-lg text-xs font-bold min-h-[36px] active:scale-95 border"
            :class="ta.is_aktif ? 'opacity-30 cursor-not-allowed bg-transparent text-gray-600 border-white/5' : 'bg-rose-500/10 text-rose-400 border-rose-500/20'"
          >
            🗑️ Hapus
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL POPUP: TAMBAH / EDIT TAHUN PELAJARAN -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-in fade-in duration-150"
    >
      <div class="w-full max-w-sm rounded-2xl bg-slate-900 border border-white/15 p-5 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="text-sm font-black text-white">
            {{ isEditing ? 'Edit Tahun Pelajaran' : 'Tambah Tahun Pelajaran' }}
          </h3>
          <button
            type="button"
            @click="showModal = false"
            class="text-gray-400 hover:text-white p-1 rounded-lg hover:bg-white/10"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-3.5">
          <div>
            <label class="block text-xs font-bold text-gray-300 uppercase mb-1">
              Tahun Pelajaran <span class="text-rose-400">*</span>
            </label>
            <input
              v-model="form.kode"
              type="text"
              required
              placeholder="Misal: 2026/2027"
              class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-white/15 focus:border-purple-400 text-white font-bold text-sm outline-none h-[42px]"
            />
          </div>

          <div class="p-3 rounded-xl bg-white/5 border border-white/10">
            <label class="flex items-center gap-2.5 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.is_aktif"
                class="w-4 h-4 rounded text-purple-500 focus:ring-purple-400 bg-slate-800 border-white/20"
              />
              <span class="text-xs font-bold text-white select-none">
                Jadikan Default & Aktif Sistem
              </span>
            </label>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/10">
            <button
              type="button"
              @click="showModal = false"
              :disabled="isSubmitting"
              class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 text-xs font-bold h-[38px]"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md active:scale-95 transition-all h-[38px]"
            >
              <span>💾</span>
              <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
