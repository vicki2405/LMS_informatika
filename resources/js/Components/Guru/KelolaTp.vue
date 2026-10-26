<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { confirmDelete, showToast } from '@/Utils/sweetalert';

const page = usePage();

const props = defineProps({
  tpList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] },
  tahunAjaranList: { type: Array, default: () => [] },
  teacherSubject: { type: String, default: 'Informatika' }
});

const emit = defineEmits(['save-tp', 'delete-tp', 'change-view']);

// Filter & Search states
const searchQuery = ref('');
const selectedMapel = ref('');
const selectedSemester = ref('');
const selectedJenis = ref('');

// Modal Form states
const showModal = ref(false);
const editingTp = ref(null);
const form = ref({
  kode: '',
  judul: '',
  mapel: '',
  kktp: 75,
  semester: 'Ganjil',
  jenis: 'Formatif',
  tahunAjaran: ''
});

// Available filter options
const availableMapels = computed(() => {
  const set = new Set();
  if (props.teacherSubject) {
    props.teacherSubject.split(',').map(s => s.trim()).forEach(s => set.add(s));
  }
  if (Array.isArray(props.mapelList)) {
    props.mapelList.forEach(m => {
      if (m?.nama) set.add(m.nama);
    });
  }
  props.tpList.forEach(t => {
    if (t?.mapel) set.add(t.mapel);
  });
  return Array.from(set).filter(Boolean);
});

// Filtered TP List
const filteredTps = computed(() => {
  return props.tpList.filter(tp => {
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase();
      const matchKode = tp.kode?.toLowerCase().includes(q);
      const matchJudul = tp.judul?.toLowerCase().includes(q);
      if (!matchKode && !matchJudul) return false;
    }
    if (selectedMapel.value && tp.mapel !== selectedMapel.value) {
      return false;
    }
    if (selectedSemester.value && tp.semester !== selectedSemester.value) {
      return false;
    }
    if (selectedJenis.value && tp.jenis !== selectedJenis.value) {
      return false;
    }
    return true;
  });
});

// Summary Stats
const stats = computed(() => {
  const total = props.tpList.length;
  const ganjil = props.tpList.filter(t => t.semester === 'Ganjil').length;
  const genap = props.tpList.filter(t => t.semester === 'Genap').length;
  const avgKktp = total > 0
    ? Math.round(props.tpList.reduce((acc, t) => acc + (Number(t.kktp) || 75), 0) / total)
    : 75;
  return { total, ganjil, genap, avgKktp };
});

function getActiveSemester() {
  try {
    const saved = typeof window !== 'undefined' ? localStorage.getItem('guru_active_semester') : null;
    if (saved && ['Ganjil', 'Genap'].includes(saved)) return saved;
  } catch (e) {}
  const currentMonth = new Date().getMonth() + 1;
  return (currentMonth >= 1 && currentMonth <= 6) ? 'Genap' : 'Ganjil';
}

function openCreateModal() {
  editingTp.value = null;
  const defaultMapel = availableMapels.value[0] || props.teacherSubject || 'Informatika';
  const activeTa = props.tahunAjaranList.find(t => t.isAktif || t.is_aktif)?.kode || page.props.activeTahunAjaran?.kode || '2026/2027';
  
  form.value = {
    kode: `TP.${props.tpList.length + 1}`,
    judul: '',
    mapel: defaultMapel,
    kktp: 75,
    semester: getActiveSemester(),
    jenis: 'Formatif',
    tahunAjaran: activeTa
  };
  showModal.value = true;
}

function openEditModal(tp) {
  editingTp.value = tp;
  form.value = {
    kode: tp.kode || '',
    judul: tp.judul || '',
    mapel: tp.mapel || availableMapels.value[0] || 'Informatika',
    kktp: tp.kktp || 75,
    semester: tp.semester || 'Ganjil',
    jenis: tp.jenis || 'Formatif',
    tahunAjaran: tp.tahunAjaran || ''
  };
  showModal.value = true;
}

function submitForm() {
  if (!form.value.kode.trim()) {
    showToast('Kode TP wajib diisi!', 'warning');
    return;
  }
  if (!form.value.judul.trim()) {
    showToast('Tujuan / Capaian Pembelajaran wajib diisi!', 'warning');
    return;
  }

  emit('save-tp', {
    editing: editingTp.value,
    data: {
      kode: form.value.kode.trim(),
      judul: form.value.judul.trim(),
      mapel: form.value.mapel,
      kktp: parseInt(form.value.kktp) || 75,
      semester: form.value.semester,
      jenis: form.value.jenis,
      tahunAjaran: form.value.tahunAjaran
    }
  });

  showModal.value = false;
}

async function handleDelete(tp) {
  const result = await confirmDelete({
    title: 'Hapus Tujuan Pembelajaran?',
    text: `Yakin ingin menghapus ${tp.kode} - "${tp.judul}"? Modul materi & nilai yang terhubung mungkin akan terpengaruh.`,
    confirmButtonText: 'Ya, Hapus TP'
  });

  if (result.isConfirmed) {
    emit('delete-tp', tp.id);
  }
}

function navigateToMateri(tp) {
  window.location.href = `/guru/materi/create?tp_id=${tp.id}`;
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Banner & Action -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-cyan-950/80 via-slate-900 to-indigo-950/70 border border-cyan-500/25 p-6 md:p-8 shadow-2xl backdrop-blur-xl">
      <div class="absolute -right-12 -top-12 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 text-xs font-bold mb-3">
            <span>🎯</span>
            <span>Kurikulum Merdeka & Kriteria Ketercapaian (KKTP)</span>
          </div>
          <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight">
            Master Tujuan Pembelajaran (TP)
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-2xl leading-relaxed">
            Kelola indikator dan sasaran kompetensi pembelajaran. TP yang Anda daftarkan di sini akan langsung tersedia saat menyusun <strong>Modul Materi</strong> dan menginput <strong>Penilaian Siswa</strong>.
          </p>
        </div>

        <button
          type="button"
          @click="openCreateModal"
          class="shrink-0 px-5 py-3.5 rounded-2xl bg-gradient-to-r from-cyan-500 via-teal-500 to-emerald-500 hover:from-cyan-400 hover:to-emerald-400 text-white font-extrabold text-sm shadow-xl shadow-cyan-500/25 transition-all active:scale-95 flex items-center justify-center gap-2 min-h-[44px]"
        >
          <span class="text-lg">➕</span>
          <span>Tambah TP Baru</span>
        </button>
      </div>

      <!-- Quick Metrics Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-6 border-t border-white/10">
        <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total TP Aktif</p>
          <p class="text-2xl font-black text-white mt-1">{{ stats.total }} <span class="text-xs font-normal text-slate-400">Sasaran</span></p>
        </div>
        <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
          <p class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider">Semester Ganjil</p>
          <p class="text-2xl font-black text-cyan-300 mt-1">{{ stats.ganjil }} <span class="text-xs font-normal text-slate-400">TP</span></p>
        </div>
        <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
          <p class="text-[11px] font-bold text-indigo-400 uppercase tracking-wider">Semester Genap</p>
          <p class="text-2xl font-black text-indigo-300 mt-1">{{ stats.genap }} <span class="text-xs font-normal text-slate-400">TP</span></p>
        </div>
        <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10">
          <p class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider">Standar KKTP</p>
          <p class="text-2xl font-black text-emerald-300 mt-1">{{ stats.avgKktp }} <span class="text-xs font-normal text-slate-400">Poin</span></p>
        </div>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 shadow-lg flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
      <div class="relative flex-1 min-w-[220px]">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari kode atau capaian TP..."
          class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-950 border border-white/10 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 text-xs text-white placeholder-slate-500 transition min-h-[44px]"
        />
        <span class="absolute left-3.5 top-3 text-slate-400">🔍</span>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <!-- Filter Mapel -->
        <select
          v-model="selectedMapel"
          class="px-3 py-2.5 rounded-xl bg-slate-950 border border-white/10 text-xs text-white focus:border-cyan-400 transition cursor-pointer min-h-[44px]"
        >
          <option value="">Semua Mata Pelajaran</option>
          <option v-for="m in availableMapels" :key="m" :value="m">{{ m }}</option>
        </select>

        <!-- Filter Semester -->
        <select
          v-model="selectedSemester"
          class="px-3 py-2.5 rounded-xl bg-slate-950 border border-white/10 text-xs text-white focus:border-cyan-400 transition cursor-pointer min-h-[44px]"
        >
          <option value="">Semua Semester</option>
          <option value="Ganjil">Semester Ganjil</option>
          <option value="Genap">Semester Genap</option>
        </select>

        <!-- Filter Jenis Asesmen -->
        <select
          v-model="selectedJenis"
          class="px-3 py-2.5 rounded-xl bg-slate-950 border border-white/10 text-xs text-white focus:border-cyan-400 transition cursor-pointer min-h-[44px]"
        >
          <option value="">Semua Jenis</option>
          <option value="Formatif">Formatif</option>
          <option value="Sumatif">Sumatif</option>
          <option value="Teori">Teori</option>
          <option value="Praktik">Praktik</option>
          <option value="Proyek">Proyek</option>
        </select>

        <button
          v-if="searchQuery || selectedMapel || selectedSemester || selectedJenis"
          @click="searchQuery = ''; selectedMapel = ''; selectedSemester = ''; selectedJenis = '';"
          class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-400 hover:text-white transition min-h-[44px]"
        >
          Reset Filter
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="filteredTps.length === 0" class="p-12 text-center rounded-3xl bg-slate-900/60 border border-dashed border-white/15">
      <div class="w-16 h-16 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center text-3xl mx-auto mb-4">
        🎯
      </div>
      <h3 class="text-base font-black text-white">Belum Ada Tujuan Pembelajaran</h3>
      <p class="text-xs text-slate-400 mt-1.5 max-w-md mx-auto">
        Tujuan Pembelajaran (TP) yang Anda buat akan muncul di sini dan dapat langsung dihubungkan ke materi ajar serta daftar nilai siswa.
      </p>
      <button
        @click="openCreateModal"
        class="mt-5 px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-white font-bold text-xs shadow-lg shadow-cyan-500/20 transition min-h-[44px]"
      >
        + Buat TP Pertama Sekarang
      </button>
    </div>

    <!-- DESKTOP VIEW: Table Layout (hidden md:block) -->
    <div v-else class="hidden md:block bg-slate-900/90 border border-white/10 rounded-3xl shadow-xl overflow-hidden backdrop-blur-xl">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-950/80 border-b border-white/10 text-slate-400 font-extrabold uppercase tracking-wider text-[11px]">
              <th class="px-5 py-4 w-28">Kode TP</th>
              <th class="px-5 py-4">Tujuan / Capaian Pembelajaran</th>
              <th class="px-5 py-4 w-44">Mata Pelajaran</th>
              <th class="px-4 py-4 w-24 text-center">Semester</th>
              <th class="px-4 py-4 w-24 text-center">KKTP</th>
              <th class="px-4 py-4 w-28 text-center">Jenis</th>
              <th class="px-5 py-4 w-44 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <tr
              v-for="tp in filteredTps"
              :key="tp.id"
              class="hover:bg-white/[0.03] transition-colors group"
            >
              <!-- Kode TP -->
              <td class="px-5 py-4 font-black">
                <span class="px-2.5 py-1 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 font-mono text-xs">
                  {{ tp.kode }}
                </span>
              </td>

              <!-- Judul / Deskripsi TP -->
              <td class="px-5 py-4">
                <p class="font-bold text-white text-xs leading-relaxed">{{ tp.judul }}</p>
                <div class="flex items-center gap-2 mt-1">
                  <span class="text-[10px] text-slate-500 font-medium">TA: {{ tp.tahunAjaran || 'Aktif' }}</span>
                </div>
              </td>

              <!-- Mapel -->
              <td class="px-5 py-4">
                <span class="px-2.5 py-1 rounded-xl bg-slate-800 border border-white/10 text-slate-300 font-medium inline-block truncate max-w-[160px]">
                  {{ tp.mapel || 'Umum' }}
                </span>
              </td>

              <!-- Semester -->
              <td class="px-4 py-4 text-center">
                <span
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                  :class="tp.semester === 'Ganjil' ? 'bg-cyan-500/10 text-cyan-300 border border-cyan-500/20' : 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20'"
                >
                  {{ tp.semester }}
                </span>
              </td>

              <!-- KKTP -->
              <td class="px-4 py-4 text-center">
                <span class="px-2.5 py-1 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 font-black text-xs">
                  {{ tp.kktp || 75 }}
                </span>
              </td>

              <!-- Jenis -->
              <td class="px-4 py-4 text-center">
                <span class="text-[11px] font-medium text-slate-300 px-2 py-0.5 rounded bg-white/5 border border-white/10">
                  {{ tp.jenis || 'Formatif' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-5 py-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    type="button"
                    @click="navigateToMateri(tp)"
                    class="p-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 transition shadow-sm"
                    title="Buat Materi dari TP ini"
                  >
                    <span>📚</span>
                  </button>
                  <button
                    type="button"
                    @click="openEditModal(tp)"
                    class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/10 transition shadow-sm"
                    title="Edit Tujuan Pembelajaran"
                  >
                    <span>✏️</span>
                  </button>
                  <button
                    type="button"
                    @click="handleDelete(tp)"
                    class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/20 transition shadow-sm"
                    title="Hapus Tujuan Pembelajaran"
                  >
                    <span>🗑️</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MOBILE VIEW: Card List Layout (md:hidden) -->
    <div v-if="filteredTps.length > 0" class="md:hidden space-y-3">
      <div
        v-for="tp in filteredTps"
        :key="tp.id"
        class="p-4 rounded-2xl bg-slate-900 border border-white/10 shadow-md space-y-3"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 font-mono text-xs font-black">
              {{ tp.kode }}
            </span>
            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-white/5 text-slate-400 border border-white/10">
              {{ tp.semester }}
            </span>
          </div>

          <span class="px-2 py-0.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 font-black text-xs">
            KKTP: {{ tp.kktp || 75 }}
          </span>
        </div>

        <div>
          <h4 class="text-sm font-bold text-white leading-snug">{{ tp.judul }}</h4>
          <div class="flex items-center gap-2 mt-2 flex-wrap text-[11px]">
            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300">📖 {{ tp.mapel || 'Umum' }}</span>
            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300">⚙️ {{ tp.jenis || 'Formatif' }}</span>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-2 pt-2 border-t border-white/10">
          <button
            type="button"
            @click="navigateToMateri(tp)"
            class="px-2 py-2 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 font-bold text-xs border border-cyan-500/30 transition flex items-center justify-center gap-1 min-h-[44px]"
          >
            <span>📚</span>
            <span>Materi</span>
          </button>
          <button
            type="button"
            @click="openEditModal(tp)"
            class="px-2 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-200 font-bold text-xs border border-white/10 transition flex items-center justify-center gap-1 min-h-[44px]"
          >
            <span>✏️</span>
            <span>Edit</span>
          </button>
          <button
            type="button"
            @click="handleDelete(tp)"
            class="px-2 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 font-bold text-xs border border-rose-500/20 transition flex items-center justify-center gap-1 min-h-[44px]"
          >
            <span>🗑️</span>
            <span>Hapus</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL FORM: Tambah / Edit Tujuan Pembelajaran -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200"
    >
      <div class="w-full max-w-xl bg-slate-900 border border-white/15 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-slate-950/80 border-b border-white/10 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-xl">🎯</span>
            <h3 class="text-sm md:text-base font-black text-white">
              {{ editingTp ? 'Edit Tujuan Pembelajaran' : 'Tambah Tujuan Pembelajaran Baru' }}
            </h3>
          </div>
          <button
            type="button"
            @click="showModal = false"
            class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white flex items-center justify-center transition"
          >
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <form @submit.prevent="submitForm" class="p-6 overflow-y-auto space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode TP -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5">
                Kode TP <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.kode"
                type="text"
                required
                placeholder="Contoh: TP-01 atau TP.1.1"
                class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono min-h-[44px]"
              />
            </div>

            <!-- Mata Pelajaran -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5">
                Mata Pelajaran <span class="text-rose-400">*</span>
              </label>
              <select
                v-model="form.mapel"
                required
                class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3.5 py-2.5 text-xs text-white min-h-[44px] cursor-pointer"
              >
                <option v-for="m in availableMapels" :key="m" :value="m">{{ m }}</option>
              </select>
            </div>
          </div>

          <!-- Judul / Deskripsi Capaian TP -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">
              Capaian / Sasaran Pembelajaran <span class="text-rose-400">*</span>
            </label>
            <textarea
              v-model="form.judul"
              rows="3"
              required
              placeholder="Contoh: Peserta didik mampu memahami konsep logika algoritma percabangan dan perulangan..."
              class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl p-3.5 text-xs text-white leading-relaxed placeholder-slate-600"
            ></textarea>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- KKTP Standar -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5">
                KKTP Minimal <span class="text-rose-400">*</span>
              </label>
              <input
                v-model="form.kktp"
                type="number"
                min="0"
                max="100"
                required
                class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono min-h-[44px]"
              />
            </div>

            <!-- Semester -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5">
                Semester
              </label>
              <select
                v-model="form.semester"
                class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3.5 py-2.5 text-xs text-white min-h-[44px] cursor-pointer"
              >
                <option value="Ganjil">Ganjil</option>
                <option value="Genap">Genap</option>
              </select>
            </div>

            <!-- Jenis Asesmen -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5">
                Jenis Asesmen
              </label>
              <select
                v-model="form.jenis"
                class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3.5 py-2.5 text-xs text-white min-h-[44px] cursor-pointer"
              >
                <option value="Formatif">Formatif</option>
                <option value="Sumatif">Sumatif</option>
                <option value="Teori">Teori</option>
                <option value="Praktik">Praktik</option>
                <option value="Proyek">Proyek</option>
              </select>
            </div>
          </div>

          <!-- Modal Footer Actions -->
          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button
              type="button"
              @click="showModal = false"
              class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 transition min-h-[44px]"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-white font-extrabold text-xs shadow-lg shadow-cyan-500/20 transition active:scale-95 min-h-[44px]"
            >
              {{ editingTp ? 'Simpan Perubahan' : 'Buat TP' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
