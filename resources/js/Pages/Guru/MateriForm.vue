<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Swal, { showToast } from '@/Utils/sweetalert';
import AutoTextarea from '@/Components/AutoTextarea.vue';
import { renderFormulaContent, SCIENCE_PRESETS } from '@/Utils/formulaRenderer';

const props = defineProps({
  materiProp: { type: Object, default: null },
  mapelListProp: { type: Array, default: () => [] },
  kelasListProp: { type: Array, default: () => [] },
  masterKelasListProp: { type: Array, default: () => [] },
  jadwalListProp: { type: Array, default: () => [] },
  tpListProp: { type: Array, default: () => [] },
  cbtJadwalListProp: { type: Array, default: () => [] },
});

const page = usePage();
const isEditing = computed(() => !!props.materiProp);

// 2-Step Wizard State:
// Step 1: Gerbang Masuk (Konfigurasi Pokok: Judul, Mapel, TP, Kelas, Semester, CBT, Format)
// Step 2: Studio Pembuatan Materi (Theme, Slide Editor, Live Mockup, Dokumen/Video)
const currentStep = ref(props.materiProp?.id ? 2 : 1);

// Mode options
const TIPE_OPTIONS = [
  { value: 'Slider PPT', label: '🖥️ Slider PPT Interaktif', desc: 'Slide presentasi multimedia dengan tema dan rumus ilmiah' },
  { value: 'Dokumen PDF/PPT', label: '📄 Dokumen / PDF', desc: 'Unggah berkas PDF, Word, atau tautan Drive' },
  { value: 'Video YouTube', label: '🎥 Video Pembelajaran', desc: 'Tautan video YouTube dengan auto pratinjau' },
];

// Theme options for Slider PPT
const THEME_OPTIONS = [
  { id: 'dark', label: 'Dark Space', bg: 'bg-slate-950 border-cyan-500/40 text-cyan-400', icon: '🌙' },
  { id: 'light', label: 'Clean White', bg: 'bg-white border-slate-300 text-slate-800', icon: '☀️' },
  { id: 'navy', label: 'Royal Navy', bg: 'bg-blue-950 border-blue-400/40 text-blue-300', icon: '🌊' },
  { id: 'emerald', label: 'Emerald Forest', bg: 'bg-emerald-950 border-emerald-400/40 text-emerald-300', icon: '🍃' },
];

const currentThemeConfig = computed(() => {
  switch (form.theme) {
    case 'light':
      return {
        wrapper: 'bg-white text-slate-900 border-slate-300 shadow-xl',
        badge: 'bg-slate-100 text-slate-700 border-slate-300',
        title: 'text-slate-900',
        prose: 'prose prose-slate max-w-none text-xs leading-relaxed text-slate-800',
        footer: 'border-slate-200 text-slate-500',
      };
    case 'navy':
      return {
        wrapper: 'bg-blue-950 text-blue-50 border-blue-800 shadow-xl shadow-blue-950/50',
        badge: 'bg-blue-900/60 text-blue-300 border-blue-700',
        title: 'text-blue-100',
        prose: 'prose prose-invert max-w-none text-xs leading-relaxed text-blue-100',
        footer: 'border-blue-900 text-blue-400',
      };
    case 'emerald':
      return {
        wrapper: 'bg-emerald-950 text-emerald-50 border-emerald-800 shadow-xl shadow-emerald-950/50',
        badge: 'bg-emerald-900/60 text-emerald-300 border-emerald-700',
        title: 'text-emerald-100',
        prose: 'prose prose-invert max-w-none text-xs leading-relaxed text-emerald-100',
        footer: 'border-emerald-900 text-emerald-400',
      };
    case 'dark':
    default:
      return {
        wrapper: 'bg-slate-950 text-slate-100 border-cyan-500/30 shadow-xl shadow-black/80',
        badge: 'bg-cyan-500/10 text-cyan-300 border-cyan-500/30',
        title: 'text-white',
        prose: 'prose prose-invert max-w-none text-xs leading-relaxed text-slate-200',
        footer: 'border-white/10 text-slate-400',
      };
  }
});

const LAYOUT_OPTIONS = [
  { id: 'standard', label: 'Standar (Teks Penuh)', icon: '📋' },
  { id: 'image_left', label: 'Gambar Kiri + Teks', icon: '⬅️' },
  { id: 'image_right', label: 'Teks + Gambar Kanan', icon: '➡️' },
  { id: 'highlight', label: 'Kotak Sorotan / Rumus', icon: '💡' },
];

const FASE_LABELS = ['Apersepsi', 'Konsep Utama', 'Rumus & Pembuktian', 'Contoh Soal', 'Studi Kasus', 'Latihan Mandiri', 'Rangkuman'];

const form = reactive({
  tpId: props.materiProp?.tpId || '',
  mapelId: props.materiProp?.mapelId || props.mapelListProp[0]?.id || '',
  judul: props.materiProp?.judul || '',
  tipe: props.materiProp?.tipe || 'Slider PPT',
  theme: props.materiProp?.theme || 'dark',
  cbtJadwalId: props.materiProp?.cbtJadwalId || null,
  fileUrl: props.materiProp?.filePath ? '' : (props.materiProp?.fileUrl || ''),
  deskripsiSingkat: props.materiProp?.deskripsiSingkat || '',
  isPublished: props.materiProp ? props.materiProp.isPublished !== false : true,
  semester: props.materiProp?.semester || 'Ganjil',
  kelas_id: props.materiProp?.kelasId ?? props.materiProp?.kelas_id ?? null,
  kegiatan: [],
});

const selectedTingkat = ref('Semua');

// Selected Mapel Object
const selectedMapelObj = computed(() => {
  return (props.mapelListProp || []).find(m => String(m.id) === String(form.mapelId));
});

// Set ID kelas yang terjadwal spesifik untuk mapel terpilih
const scheduledKelasIdsForSelectedMapel = computed(() => {
  const currentMapel = selectedMapelObj.value;
  const jadwals = props.jadwalListProp || [];
  if (!currentMapel || jadwals.length === 0) return new Set();

  const mapelNamaLower = (currentMapel.nama || '').trim().toLowerCase();
  const mapelKodeLower = (currentMapel.kode || '').trim().toLowerCase();

  const matching = jadwals.filter(j => {
    if (!j.mataPelajaran) return false;
    const jMapelLower = j.mataPelajaran.trim().toLowerCase();
    return jMapelLower === mapelNamaLower ||
           jMapelLower === mapelKodeLower ||
           mapelNamaLower.includes(jMapelLower) ||
           jMapelLower.includes(mapelKodeLower);
  });

  return new Set(matching.map(j => j.kelasId).filter(Boolean));
});

// Dynamic Kelas: Menampilkan seluruh kelas guru (atau master kelas), menandai kelas sesuai jadwal mapel
const availableKelasForSelectedMapel = computed(() => {
  const source = (props.kelasListProp && props.kelasListProp.length > 0)
    ? props.kelasListProp
    : (props.masterKelasListProp || []);

  const scheduledIds = scheduledKelasIdsForSelectedMapel.value;

  const map = new Map();
  source.forEach(k => {
    if (!k || !k.id) return;
    map.set(k.id, {
      id: k.id,
      nama: k.nama || k.namaKelas,
      namaKelas: k.namaKelas || k.nama,
      tingkat: k.tingkat || 'X',
      jurusan: k.jurusan || 'Umum',
      isScheduled: scheduledIds.has(k.id),
    });
  });

  return Array.from(map.values()).sort((a, b) => {
    // 1. Kelas yang terjadwal mapel ini diutamakan di posisi atas
    if (a.isScheduled && !b.isScheduled) return -1;
    if (!a.isScheduled && b.isScheduled) return 1;
    // 2. Urutkan berdasarkan tingkat
    const tCmp = (a.tingkat || '').localeCompare(b.tingkat || '');
    if (tCmp !== 0) return tCmp;
    // 3. Urutkan berdasarkan nama kelas
    return (a.nama || '').localeCompare(b.nama || '', undefined, { numeric: true });
  });
});

const filteredKelasByTingkat = computed(() => {
  const classes = availableKelasForSelectedMapel.value;
  if (!selectedTingkat.value || selectedTingkat.value === 'Semua') {
    return classes;
  }
  return classes.filter(k => (k.tingkat || '').toUpperCase() === selectedTingkat.value.toUpperCase());
});

const scheduledCountInSelectedTingkat = computed(() => {
  return filteredKelasByTingkat.value.filter(k => k.isScheduled).length;
});

function getTingkatFromKelasId(kelasId) {
  if (!kelasId) return 'Semua';
  const kObj = availableKelasForSelectedMapel.value.find(k => k.id === kelasId)
            || (props.kelasListProp || []).find(k => k.id === kelasId);
  return kObj ? (kObj.tingkat || 'Semua') : 'Semua';
}

if (form.kelas_id) {
  selectedTingkat.value = getTingkatFromKelasId(form.kelas_id);
} else if (!isEditing.value) {
  // Jika form baru, arahkan default tingkat ke jadwal mapel yang terpilih jika ada
  const scheduled = availableKelasForSelectedMapel.value.filter(k => k.isScheduled);
  if (scheduled.length > 0 && scheduled[0].tingkat) {
    selectedTingkat.value = scheduled[0].tingkat.toUpperCase();
  }
}

// Dynamic Filter TP sesuai Mapel yang dipilih dan Semester
const availableTpsForSelectedMapel = computed(() => {
  const currentMapel = selectedMapelObj.value;
  return (props.tpListProp || []).filter(tp => {
    // 1. Filter Semester jika diisi
    if (form.semester && tp.semester && tp.semester !== form.semester) {
      return false;
    }
    // Jika mapel belum dipilih, tampilkan semua
    if (!currentMapel) return true;
    // TP kategori Umum bisa dipakai di semua mapel
    if (!tp.mapel || tp.mapel === 'Umum') return true;

    const tpMapelLower = tp.mapel.trim().toLowerCase();
    const mapelNamaLower = (currentMapel.nama || '').trim().toLowerCase();
    const mapelKodeLower = (currentMapel.kode || '').trim().toLowerCase();

    return tpMapelLower === mapelNamaLower ||
           tpMapelLower === mapelKodeLower ||
           mapelNamaLower.includes(tpMapelLower) ||
           tpMapelLower.includes(mapelKodeLower);
  });
});

// Auto-select valid TP jika form.tpId kosong atau tidak ada di list mapel yang dipilih
if (!form.tpId && availableTpsForSelectedMapel.value.length > 0) {
  form.tpId = availableTpsForSelectedMapel.value[0].id;
}

// Watcher mapel & semester agar tpId selalu relevan
watch([() => form.mapelId, () => form.semester], () => {
  const available = availableTpsForSelectedMapel.value;
  const exists = available.some(t => String(t.id) === String(form.tpId));
  if (!exists) {
    form.tpId = available.length > 0 ? available[0].id : '';
  }
});

// Computed Helper untuk Locked Bar & Preview
const selectedTpObj = computed(() => {
  return (props.tpListProp || []).find(t => String(t.id) === String(form.tpId));
});

// State & Filter untuk Custom Searchable TP Selector
const isTpDropdownOpen = ref(false);
const tpSearchQuery = ref('');

const searchedTps = computed(() => {
  const list = availableTpsForSelectedMapel.value;
  const q = tpSearchQuery.value.trim().toLowerCase();
  if (!q) return list;
  return list.filter(tp => {
    const kode = (tp.kode || '').toLowerCase();
    const judul = (tp.judul || tp.deskripsi || '').toLowerCase();
    return kode.includes(q) || judul.includes(q);
  });
});

function selectTp(tp) {
  form.tpId = tp.id;
  isTpDropdownOpen.value = false;
  tpSearchQuery.value = '';
}

function handleClickOutside(event) {
  const container = document.getElementById('tp-selector-container');
  if (container && !container.contains(event.target)) {
    isTpDropdownOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

// Watcher mapelId agar kelas_id tetap valid jika mapel berganti
watch(() => form.mapelId, () => {
  if (form.kelas_id) {
    const exists = availableKelasForSelectedMapel.value.some(k => k.id === form.kelas_id);
    if (!exists) {
      form.kelas_id = null;
    }
  } else if (!isEditing.value) {
    // Jika belum memilih kelas spesifik pada form baru, otomatis sesuaikan tingkat jika mapel memiliki jadwal
    const scheduled = availableKelasForSelectedMapel.value.filter(k => k.isScheduled);
    if (scheduled.length > 0 && scheduled[0].tingkat) {
      selectedTingkat.value = scheduled[0].tingkat.toUpperCase();
    }
  }
});

// Watcher selectedTingkat agar kelas_id tetap sesuai tingkat
watch(selectedTingkat, (newTingkat) => {
  if (form.kelas_id && newTingkat !== 'Semua') {
    const kObj = availableKelasForSelectedMapel.value.find(k => k.id === form.kelas_id);
    if (kObj && (kObj.tingkat || '').toUpperCase() !== newTingkat.toUpperCase()) {
      form.kelas_id = null;
    }
  }
});

const selectedKelasName = computed(() => {
  if (!form.kelas_id) return `Seluruh Kelas (${selectedTingkat.value})`;
  const kObj = availableKelasForSelectedMapel.value.find(k => String(k.id) === String(form.kelas_id))
            || (props.kelasListProp || []).find(k => String(k.id) === String(form.kelas_id));
  return kObj ? `Kelas ${kObj.nama || kObj.namaKelas}` : `Seluruh Kelas (${selectedTingkat.value})`;
});

// Wizard Navigation Handlers
function goToStudio() {
  if (!form.judul.trim()) {
    showToast('Judul materi pembelajaran wajib diisi!', 'warning');
    return;
  }
  if (!form.mapelId) {
    showToast('Pilih mata pelajaran terlebih dahulu!', 'warning');
    return;
  }
  if (!form.tpId) {
    showToast('Pilih Tujuan Pembelajaran (TP) yang sesuai dengan mata pelajaran!', 'warning');
    return;
  }
  currentStep.value = 2;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToConfig() {
  currentStep.value = 1;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Slides setup
const kegiatanFiles = ref([]);
const kegiatanImagePreviews = ref([]);
const selectedFile = ref(null);
const saving = ref(false);
const activeSlideIndex = ref(0);
const activeFormulaCategory = ref('matematika');

function createDefaultSlide(label = 'Konsep Utama', urutan = 1) {
  return {
    label,
    judul: `Bagian ${urutan}`,
    layout: 'standard',
    konten: '',
    filePath: '',
    fileName: '',
    fileUrl: '',
  };
}

if (props.materiProp?.kegiatan && props.materiProp.kegiatan.length > 0) {
  form.kegiatan = props.materiProp.kegiatan.map(k => ({
    label: k.label || 'Konsep Utama',
    judul: k.judul || '',
    layout: k.layout || 'standard',
    konten: k.konten || '',
    filePath: k.filePath || '',
    fileName: k.fileName || '',
    fileUrl: k.fileUrl || '',
  }));
  kegiatanFiles.value = new Array(form.kegiatan.length).fill(null);
  kegiatanImagePreviews.value = form.kegiatan.map(k => k.fileUrl || '');
} else {
  form.kegiatan = [
    createDefaultSlide('Apersepsi', 1),
    createDefaultSlide('Konsep Utama', 2),
    createDefaultSlide('Rangkuman', 3),
  ];
  kegiatanFiles.value = new Array(form.kegiatan.length).fill(null);
  kegiatanImagePreviews.value = new Array(form.kegiatan.length).fill('');
}

function addSlide() {
  const urutan = form.kegiatan.length + 1;
  const nextLabel = FASE_LABELS[(urutan - 1) % FASE_LABELS.length];
  form.kegiatan.push(createDefaultSlide(nextLabel, urutan));
  kegiatanFiles.value.push(null);
  kegiatanImagePreviews.value.push('');
  activeSlideIndex.value = form.kegiatan.length - 1;
}

function removeSlide(idx) {
  if (form.kegiatan.length <= 1) {
    showToast('Minimal harus ada 1 slide materi.', 'warning');
    return;
  }
  form.kegiatan.splice(idx, 1);
  kegiatanFiles.value.splice(idx, 1);
  kegiatanImagePreviews.value.splice(idx, 1);
  if (activeSlideIndex.value >= form.kegiatan.length) {
    activeSlideIndex.value = form.kegiatan.length - 1;
  }
}

function duplicateSlide(idx) {
  const source = form.kegiatan[idx];
  const clone = {
    ...source,
    judul: `${source.judul} (Salinan)`,
  };
  form.kegiatan.splice(idx + 1, 0, clone);
  kegiatanFiles.value.splice(idx + 1, 0, null);
  kegiatanImagePreviews.value.splice(idx + 1, 0, kegiatanImagePreviews.value[idx] || '');
  activeSlideIndex.value = idx + 1;
}

function moveSlide(idx, dir) {
  const target = idx + dir;
  if (target < 0 || target >= form.kegiatan.length) return;
  const k = form.kegiatan;
  const f = kegiatanFiles.value;
  const p = kegiatanImagePreviews.value;
  [k[idx], k[target]] = [k[target], k[idx]];
  [f[idx], f[target]] = [f[target], f[idx]];
  [p[idx], p[target]] = [p[target], p[idx]];
  activeSlideIndex.value = target;
}

function onSlideImageSelected(idx, event) {
  const file = event.target.files[0] || null;
  kegiatanFiles.value[idx] = file;
  if (file) {
    kegiatanImagePreviews.value[idx] = URL.createObjectURL(file);
  }
}

function removeSlideImage(idx) {
  kegiatanFiles.value[idx] = null;
  kegiatanImagePreviews.value[idx] = '';
  form.kegiatan[idx].filePath = '';
  form.kegiatan[idx].fileUrl = '';
}

function insertFormula(latex) {
  const current = form.kegiatan[activeSlideIndex.value];
  if (!current) return;
  if (current.konten && !current.konten.endsWith('\n') && !current.konten.endsWith(' ')) {
    current.konten += '\n';
  }
  current.konten = (current.konten || '') + latex + ' ';
}

// Video YouTube preview helper
const youtubeEmbedUrl = computed(() => {
  if (form.tipe !== 'Video YouTube' || !form.fileUrl) return null;
  try {
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = form.fileUrl.match(regExp);
    if (match && match[2].length === 11) {
      return `https://www.youtube.com/embed/${match[2]}`;
    }
  } catch (e) {
    return null;
  }
  return null;
});

async function goBack() {
  if (currentStep.value === 2) {
    currentStep.value = 1;
    return;
  }

  const hasChanges = !!form.judul.trim() || !!form.deskripsiSingkat.trim() || (form.kegiatan && form.kegiatan.length > 0);
  if (hasChanges) {
    const res = await Swal.fire({
      title: 'Tinggalkan Halaman?',
      text: 'Perubahan materi yang belum disimpan akan hilang.',
      icon: 'warning',
      iconColor: '#f59e0b',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#475569',
      confirmButtonText: 'Ya, Tinggalkan',
      cancelButtonText: 'Tetap di Sini',
      background: '#0f172a',
      color: '#f8fafc',
      customClass: {
        popup: 'rounded-3xl border border-white/10 shadow-2xl backdrop-blur-xl',
        title: 'text-lg font-black text-white',
        htmlContainer: 'text-xs text-gray-300 font-medium',
        confirmButton: 'rounded-2xl px-5 py-2.5 text-xs shadow-lg font-bold',
        cancelButton: 'rounded-2xl px-5 py-2.5 text-xs font-bold'
      }
    });

    if (!res.isConfirmed) return;
  }

  router.get(route('guru.dashboard', { tab: 'materi' }));
}

function onMainFileSelected(e) {
  selectedFile.value = e.target.files[0] || null;
}

function submit() {
  if (!form.judul.trim()) {
    showToast('Judul materi wajib diisi!', 'warning');
    currentStep.value = 1;
    return;
  }
  if (!form.tpId) {
    showToast('Pilih Tujuan Pembelajaran (TP)!', 'warning');
    currentStep.value = 1;
    return;
  }

  saving.value = true;

  const fd = new FormData();
  fd.append('id', props.materiProp?.id || '');
  fd.append('tp_id', form.tpId || '');
  fd.append('kelas_id', form.kelas_id || '');
  fd.append('mapel_id', form.mapelId || '');
  fd.append('judul', form.judul.trim());
  fd.append('tipe', form.tipe);
  fd.append('theme', form.theme || 'dark');
  if (form.cbtJadwalId) {
    fd.append('cbt_jadwal_id', form.cbtJadwalId);
  }
  if (form.fileUrl) fd.append('file_url', form.fileUrl);
  fd.append('deskripsi', form.deskripsiSingkat || '');
  fd.append('is_published', form.isPublished ? '1' : '0');
  fd.append('semester', form.semester || 'Ganjil');

  if (selectedFile.value) {
    fd.append('file', selectedFile.value);
  }

  // Only append kegiatan for Slider PPT or if configured
  const kegiatan = form.kegiatan.map((k, i) => ({
    label: k.label || 'Konsep',
    judul: k.judul || '',
    layout: k.layout || 'standard',
    konten: k.konten || '',
    filePath: k.filePath || '',
    fileUrl: k.fileUrl || '',
    ...(kegiatanFiles.value[i] ? { _fileKey: i } : {}),
  }));
  fd.append('kegiatan', JSON.stringify(kegiatan));

  kegiatanFiles.value.forEach(f => {
    if (f) fd.append('kegiatan_file[]', f);
  });

  router.post(route('guru.materi.store'), fd, {
    preserveScroll: true,
    onSuccess: () => {
      showToast('Materi berhasil disimpan!', 'success');
    },
    onError: (errs) => {
      saving.value = false;
      showToast('Gagal menyimpan materi. Cek kembali form.', 'error');
    },
    onFinish: () => {
      saving.value = false;
    },
  });
}
</script>

<template>
  <Head :title="isEditing ? 'Edit Materi' : 'Buat Materi Baru'" />

  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans pb-32">
    <!-- Top Action Bar -->
    <header class="sticky top-0 z-50 bg-slate-900/90 backdrop-blur-xl border-b border-white/10 shadow-xl">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <button
            @click="goBack"
            type="button"
            class="px-3 sm:px-3.5 py-2 shrink-0 rounded-2xl bg-white/5 hover:bg-white/10 active:bg-white/15 border border-white/15 hover:border-cyan-500/40 text-slate-200 hover:text-white text-xs font-bold flex items-center gap-2 transition-all active:scale-95 shadow-sm min-h-[44px]"
            :title="currentStep === 2 ? 'Kembali ke Konfigurasi Pokok' : 'Kembali ke Daftar Materi'"
          >
            <svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span class="font-bold">{{ currentStep === 2 ? 'Ke Pengaturan' : 'Kembali' }}</span>
          </button>
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h1 class="text-base sm:text-lg font-black text-white truncate">
                {{ isEditing ? 'Edit Materi Pembelajaran' : 'Buat Materi Pembelajaran Baru' }}
              </h1>
              <span class="hidden md:inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                LMS Cepat
              </span>
            </div>
            <p class="text-[11px] text-slate-400 truncate">SMKS Muhammadiyah 2 Genteng · Standar Kurikulum Merdeka</p>
          </div>
        </div>

        <div class="flex items-center gap-3 shrink-0">
          <!-- Publish status toggle -->
          <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold select-none transition min-h-[44px]">
            <input type="checkbox" v-model="form.isPublished" class="rounded bg-slate-800 border-white/20 text-cyan-500 focus:ring-0 cursor-pointer">
            <span :class="form.isPublished ? 'text-emerald-400' : 'text-slate-400'">
              {{ form.isPublished ? '🟢 Diterbitkan' : '⚪ Draft' }}
            </span>
          </label>

          <button
            @click="submit"
            :disabled="saving"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl bg-gradient-to-r from-cyan-500 via-teal-500 to-indigo-600 text-white font-black text-xs shadow-lg shadow-cyan-500/20 hover:scale-105 active:scale-95 transition-all disabled:opacity-50 min-h-[44px]"
          >
            <span v-if="saving" class="animate-spin">⏳</span>
            <span v-else>💾</span>
            <span>{{ saving ? 'Menyimpan...' : 'Simpan Materi' }}</span>
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 pt-6 space-y-6">

      <!-- WIZARD STEP NAVIGATOR BAR -->
      <div class="flex items-center justify-center">
        <div class="inline-flex items-center bg-slate-900/90 border border-white/10 rounded-2xl p-1.5 shadow-xl max-w-2xl w-full">
          <button
            type="button"
            @click="goToConfig"
            class="flex-1 py-2.5 px-3 sm:px-4 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-2 min-h-[44px]"
            :class="currentStep === 1
              ? 'bg-gradient-to-r from-cyan-500 to-teal-500 text-white shadow-lg shadow-cyan-500/20'
              : 'text-slate-400 hover:text-white hover:bg-white/5'"
          >
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" :class="currentStep === 1 ? 'bg-white/25 text-white' : 'bg-white/10 text-slate-400'">
              1
            </span>
            <span class="truncate">1. Gerbang Masuk (Info Pokok)</span>
            <span v-if="currentStep === 2 && form.judul && form.tpId" class="text-emerald-400 text-xs">✓</span>
          </button>

          <div class="w-6 flex items-center justify-center text-slate-600 font-bold shrink-0">➔</div>

          <button
            type="button"
            @click="goToStudio"
            class="flex-1 py-2.5 px-3 sm:px-4 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-2 min-h-[44px]"
            :class="currentStep === 2
              ? 'bg-gradient-to-r from-cyan-500 to-teal-500 text-white shadow-lg shadow-cyan-500/20'
              : 'text-slate-400 hover:text-white hover:bg-white/5'"
          >
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" :class="currentStep === 2 ? 'bg-white/25 text-white' : 'bg-white/10 text-slate-400'">
              2
            </span>
            <span class="truncate">2. Studio Materi & Konten</span>
          </button>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- LANGKAH 1: GERBANG MASUK (KONFIGURASI POKOK PEMBELAJARAN)                   -->
      <!-- ========================================================================= -->
      <div v-show="currentStep === 1" class="space-y-6">

        <!-- Informasi Pokok Pembelajaran (Gerbang Masuk Card) -->
        <section class="bg-slate-900/90 border border-white/15 rounded-3xl p-6 shadow-2xl space-y-5 backdrop-blur-xl">
          <div class="flex items-center justify-between pb-3 border-b border-white/10">
            <div>
              <h2 class="text-sm sm:text-base font-black uppercase tracking-wider text-cyan-400 flex items-center gap-2">
                <span>📌</span> INFORMASI POKOK PEMBELAJARAN
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">
                Kunci mata pelajaran, TP, kelas, dan semester sebelum mulai menyusun slide materi pembelajaran.
              </p>
            </div>
            <span class="hidden sm:inline-flex px-3 py-1 rounded-xl text-xs font-bold bg-cyan-500/10 text-cyan-300 border border-cyan-500/30">
              Tahap 1 / 2
            </span>
          </div>

          <!-- Judul Materi -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">
              Judul Materi Pembelajaran <span class="text-rose-400">*</span>
            </label>
            <input
              v-model="form.judul"
              type="text"
              required
              placeholder="Contoh: Hukum Newton II & Dinamika Gerak Benda"
              class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-2xl px-4 py-3 text-sm text-white placeholder-slate-500 transition min-h-[44px]"
            />
          </div>

          <!-- 2 Col Grid: Mata Pelajaran & Semester -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Mata Pelajaran -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5">
                Mata Pelajaran <span class="text-rose-400">*</span>
              </label>
              <select
                v-model="form.mapelId"
                class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-2xl px-3.5 py-2.5 text-xs text-white min-h-[44px] cursor-pointer"
              >
                <option v-for="mp in mapelListProp" :key="mp.id" :value="mp.id" class="bg-slate-900 text-white py-1">
                  {{ mp.nama }} ({{ mp.kode }})
                </option>
              </select>
            </div>

            <!-- Semester -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1.5">Semester</label>
              <select
                v-model="form.semester"
                class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-2xl px-3.5 py-2.5 text-xs text-white min-h-[44px] cursor-pointer"
              >
                <option value="Ganjil" class="bg-slate-900 text-white py-1">Semester Ganjil</option>
                <option value="Genap" class="bg-slate-900 text-white py-1">Semester Genap</option>
              </select>
            </div>
          </div>

          <!-- Tujuan Pembelajaran (TP) - Full Width Custom Selector Bebas Glitch -->
          <div id="tp-selector-container" class="relative">
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                <span>🎯</span> Tujuan Pembelajaran (TP) <span class="text-rose-400">*</span>
              </label>
              <a
                href="/guru/dashboard?tab=tp"
                target="_blank"
                class="text-[11px] font-bold text-cyan-400 hover:text-cyan-300 flex items-center gap-1 transition"
                title="Buka menu Pengelolaan TP di tab baru"
              >
                <span>Kelola Master TP ↗</span>
              </a>
            </div>

            <!-- Trigger Button -->
            <div
              @click="isTpDropdownOpen = !isTpDropdownOpen"
              class="w-full bg-slate-950/90 border rounded-2xl px-4 py-3 text-xs cursor-pointer flex items-center justify-between gap-3 min-h-[48px] transition-all select-none"
              :class="isTpDropdownOpen ? 'border-cyan-400 ring-2 ring-cyan-500/20' : 'border-white/15 hover:border-white/30'"
            >
              <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <span v-if="selectedTpObj" class="px-2.5 py-1 rounded-lg bg-cyan-500/20 text-cyan-300 font-extrabold text-[11px] border border-cyan-500/30 shrink-0">
                  {{ selectedTpObj.kode }}
                </span>
                <span v-if="selectedTpObj" class="text-white font-medium truncate text-xs">
                  {{ selectedTpObj.judul || selectedTpObj.deskripsi }}
                </span>
                <span v-else class="text-slate-500 italic">
                  -- Klik untuk memilih Tujuan Pembelajaran (TP) --
                </span>
              </div>
              <div class="flex items-center gap-2 shrink-0 text-slate-400">
                <span class="text-[11px] text-cyan-400/80 font-bold hidden sm:inline">{{ availableTpsForSelectedMapel.length }} TP</span>
                <svg class="w-4 h-4 transition-transform text-slate-400" :class="isTpDropdownOpen ? 'rotate-180 text-cyan-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>

            <!-- Dropdown Popover Menu -->
            <div
              v-if="isTpDropdownOpen"
              class="absolute z-50 left-0 right-0 mt-2 bg-slate-900 border border-cyan-500/40 rounded-2xl shadow-2xl p-2.5 backdrop-blur-2xl space-y-2 animate-in fade-in zoom-in-95 duration-150"
            >
              <!-- Search Bar -->
              <div class="relative">
                <input
                  v-model="tpSearchQuery"
                  type="text"
                  placeholder="Ketik untuk mencari kode atau materi TP..."
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl pl-8 pr-8 py-2 text-xs text-white placeholder-slate-500 focus:outline-none"
                  @click.stop
                />
                <span class="absolute left-2.5 top-2 text-xs text-slate-500">🔍</span>
                <button
                  v-if="tpSearchQuery"
                  type="button"
                  @click.stop="tpSearchQuery = ''"
                  class="absolute right-2.5 top-2 text-xs text-slate-400 hover:text-white"
                >✕</button>
              </div>

              <!-- Options List -->
              <div class="max-h-64 overflow-y-auto space-y-1 pr-1">
                <div
                  v-if="searchedTps.length === 0"
                  class="py-6 text-center text-xs text-slate-400"
                >
                  Tidak ada TP yang cocok untuk mata pelajaran dan semester ini.
                </div>
                <div
                  v-for="tp in searchedTps"
                  :key="tp.id"
                  @click="selectTp(tp)"
                  class="p-2.5 rounded-xl cursor-pointer transition-all flex items-start gap-3 border"
                  :class="String(form.tpId) === String(tp.id)
                    ? 'bg-cyan-500/15 border-cyan-500/40 text-white ring-1 ring-cyan-500/30'
                    : 'bg-white/[0.02] border-white/5 hover:bg-white/10 hover:border-white/20 text-slate-300'"
                >
                  <span class="px-2 py-0.5 rounded-md bg-cyan-500/20 text-cyan-300 font-extrabold text-[11px] border border-cyan-500/30 shrink-0 mt-0.5">
                    {{ tp.kode }}
                  </span>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold leading-relaxed line-clamp-2" :class="String(form.tpId) === String(tp.id) ? 'text-cyan-200' : 'text-slate-200'">
                      {{ tp.judul || tp.deskripsi }}
                    </p>
                    <div class="flex items-center gap-2 mt-1 text-[10px] text-slate-400">
                      <span>📚 {{ tp.mapel || 'Umum' }}</span>
                      <span>·</span>
                      <span>📅 Semester {{ tp.semester || form.semester }}</span>
                      <span v-if="tp.kktp">· KKTP: {{ tp.kktp }}</span>
                    </div>
                  </div>
                  <span v-if="String(form.tpId) === String(tp.id)" class="text-cyan-400 text-xs font-bold shrink-0">✓</span>
                </div>
              </div>
            </div>

            <!-- Selected TP Preview Banner -->
            <div v-if="selectedTpObj" class="mt-2.5 p-3.5 rounded-2xl bg-cyan-950/30 border border-cyan-500/25 flex items-start gap-3">
              <span class="text-base shrink-0 mt-0.5">🎯</span>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                  <span class="text-xs font-black text-cyan-300">{{ selectedTpObj.kode }}</span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/15 text-cyan-300 font-bold border border-cyan-500/30">
                    Capaian Pembelajaran Terpilih
                  </span>
                  <span v-if="selectedTpObj.kktp" class="text-[10px] text-slate-400">
                    KKTP: {{ selectedTpObj.kktp }}
                  </span>
                </div>
                <p class="text-xs text-slate-200 leading-relaxed font-normal">
                  {{ selectedTpObj.judul || selectedTpObj.deskripsi }}
                </p>
              </div>
            </div>
          </div>

          <!-- Target Siswa: Tingkat & Kelas -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-white/5">
            <div class="min-w-0">
              <label class="block text-xs font-bold text-slate-300 mb-1.5">Tingkat Kelas</label>
              <div class="grid grid-cols-4 gap-2">
                <button
                  v-for="t in ['Semua', 'X', 'XI', 'XII']"
                  :key="t"
                  type="button"
                  @click="selectedTingkat = t"
                  class="py-2.5 rounded-xl text-xs font-bold border transition min-h-[44px] flex items-center justify-center min-w-0"
                  :class="selectedTingkat === t
                    ? 'bg-cyan-500 text-white border-cyan-400 shadow-md shadow-cyan-500/20'
                    : 'bg-white/5 text-slate-400 border-white/10 hover:bg-white/10'"
                >
                  {{ t }}
                </button>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-bold text-slate-300">Target Kelas Spesifik</label>
                <a
                  href="/guru/dashboard?tab=jadwal"
                  target="_blank"
                  class="text-[11px] font-bold text-cyan-400 hover:text-cyan-300 flex items-center gap-1 transition"
                  title="Lihat Jadwal Mengajar di tab baru"
                >
                  <span>📅</span>
                  <span>Cek Jadwal ↗</span>
                </a>
              </div>
              <select
                v-model="form.kelas_id"
                class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-2xl px-3.5 py-2.5 text-xs text-white min-h-[44px] cursor-pointer"
              >
                <option :value="null" class="bg-slate-900 text-white py-1">🌐 Seluruh Kelas (Tingkat {{ selectedTingkat }})</option>
                <option v-for="k in filteredKelasByTingkat" :key="k.id" :value="k.id" class="bg-slate-900 text-white py-1">
                  {{ k.isScheduled ? '✨' : '🏫' }} Kelas {{ k.nama || k.namaKelas }} ({{ k.jurusan || 'Umum' }}){{ k.isScheduled ? ' - Sesuai Jadwal' : '' }}
                </option>
              </select>
              <div class="mt-1.5 flex flex-wrap items-center justify-between gap-1 text-[11px]">
                <span class="text-cyan-400/90 font-medium">
                  📅 {{ filteredKelasByTingkat.length }} kelas tersedia di tingkat {{ selectedTingkat }}
                  <span v-if="scheduledCountInSelectedTingkat > 0" class="text-emerald-400 font-semibold">
                    ({{ scheduledCountInSelectedTingkat }} kelas terjadwal mapel ini)
                  </span>
                </span>
                <span v-if="filteredKelasByTingkat.length === 0" class="text-amber-400 font-medium">
                  ⚠️ Belum ada kelas di tingkat {{ selectedTingkat }}
                </span>
              </div>
            </div>
          </div>

          <!-- Tautkan Ujian CBT (Fitur Integrasi Utama) -->
          <div class="p-4 rounded-2xl bg-gradient-to-r from-indigo-950/40 via-purple-950/30 to-slate-900 border border-indigo-500/30 space-y-2">
            <div class="flex items-center justify-between">
              <label class="text-xs font-black text-indigo-300 flex items-center gap-1.5">
                <span>🎯</span> Tautkan ke Ujian CBT (Opsional)
              </label>
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 font-bold">
                Integrasi Otomatis
              </span>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed">
              Jika dipilih, setelah siswa selesai membaca seluruh materi ini di dashboard mereka, akan muncul tombol <strong>"🎯 Lanjut Kerjakan Ujian CBT"</strong> yang langsung membuka lembar soal dengan identitas siswa tetap terjaga.
            </p>
            <select
              v-model="form.cbtJadwalId"
              class="w-full bg-slate-950 border border-indigo-500/40 focus:border-indigo-400 rounded-xl px-3.5 py-2.5 text-xs text-indigo-200 font-semibold min-h-[44px] cursor-pointer"
            >
              <option :value="null" class="bg-slate-900 text-white py-1">-- Tidak Ada Ujian CBT Terkait --</option>
              <option v-for="cbt in cbtJadwalListProp" :key="cbt.id" :value="cbt.id" class="bg-slate-900 text-white py-1">
                📝 {{ cbt.judul }} (Durasi: {{ cbt.durasi }} Menit)
              </option>
            </select>
          </div>
        </section>

        <!-- Pilih Format Materi -->
        <section class="bg-slate-900/80 border border-white/10 rounded-3xl p-5 shadow-2xl backdrop-blur-md">
          <label class="text-xs font-black uppercase tracking-wider text-slate-400 mb-3 block">
            Pilih Format Pembuatan Materi
          </label>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <button
              v-for="opt in TIPE_OPTIONS"
              :key="opt.value"
              type="button"
              @click="form.tipe = opt.value"
              class="text-left p-4 rounded-2xl border transition-all relative overflow-hidden min-h-[44px]"
              :class="form.tipe === opt.value
                ? 'bg-gradient-to-br from-cyan-500/10 via-slate-800 to-indigo-500/10 border-cyan-500/60 ring-2 ring-cyan-500/30 shadow-lg'
                : 'bg-white/5 border-white/5 hover:border-white/20 hover:bg-white/[0.07] text-slate-300'"
            >
              <div class="font-extrabold text-sm text-white flex items-center justify-between mb-1">
                <span>{{ opt.label }}</span>
                <span v-if="form.tipe === opt.value" class="text-cyan-400 text-xs">✓ Terpilih</span>
              </div>
              <p class="text-xs text-slate-400 leading-relaxed">{{ opt.desc }}</p>
            </button>
          </div>
        </section>

        <!-- CTA Masuk ke Studio Materi -->
        <div class="p-6 rounded-3xl bg-gradient-to-r from-cyan-950/60 via-slate-900 to-indigo-950/60 border border-cyan-500/40 shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="space-y-1 text-center sm:text-left">
            <h3 class="text-sm font-black text-white flex items-center justify-center sm:justify-start gap-2">
              <span>🚀</span> Siap Menyusun Materi Pembelajaran?
            </h3>
            <p class="text-xs text-slate-400">
              Informasi pokok di atas akan dikunci sehingga Anda dapat fokus mengolah konten slide, rumus, dan gambar di Studio.
            </p>
          </div>
          <button
            type="button"
            @click="goToStudio"
            class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-white font-black text-xs shadow-xl shadow-cyan-500/25 transition-all flex items-center justify-center gap-2 active:scale-95 shrink-0 min-h-[48px]"
          >
            <span>Masuk ke Studio Materi</span>
            <span class="text-sm">➔</span>
          </button>
        </div>

      </div>

      <!-- ========================================================================= -->
      <!-- LANGKAH 2: STUDIO PEMBUATAN MATERI (SLIDER PPT / DOKUMEN / VIDEO)           -->
      <!-- ========================================================================= -->
      <div v-show="currentStep === 2" class="space-y-6">

        <!-- LOCKED METADATA SUMMARY BAR (Gerbang Informasi Terkunci) -->
        <div class="bg-gradient-to-r from-slate-900 via-slate-900 to-cyan-950/60 border border-cyan-500/30 rounded-3xl p-4 sm:p-5 shadow-2xl flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-cyan-500/15 border border-cyan-500/30 flex items-center justify-center text-lg text-cyan-400 shrink-0">
              🔒
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-xs font-black uppercase tracking-wider text-cyan-400">Informasi Terkunci:</span>
                <span class="text-xs font-bold text-white truncate">{{ form.judul || 'Tanpa Judul' }}</span>
              </div>
              <div class="flex items-center gap-2 flex-wrap text-[11px] text-slate-400">
                <span class="px-2.5 py-0.5 rounded-full bg-white/5 border border-white/10 text-cyan-300 font-semibold">
                  📚 {{ selectedMapelObj?.nama || 'Mapel' }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full bg-white/5 border border-white/10 text-emerald-300 font-semibold truncate max-w-[280px]">
                  🎯 {{ selectedTpObj?.kode ? selectedTpObj.kode + ' - ' + (selectedTpObj.judul || '') : 'TP Terpilih' }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full bg-white/5 border border-white/10 text-slate-300 font-semibold">
                  🏫 {{ selectedKelasName }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full bg-white/5 border border-white/10 text-purple-300 font-semibold">
                  📅 {{ form.semester }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 font-bold">
                  {{ form.tipe }}
                </span>
              </div>
            </div>
          </div>

          <button
            type="button"
            @click="goToConfig"
            class="px-4 py-2 rounded-xl bg-white/5 hover:bg-cyan-500/15 border border-white/15 hover:border-cyan-400/40 text-xs font-bold text-cyan-300 hover:text-cyan-200 transition flex items-center gap-2 min-h-[44px] active:scale-95 shadow-sm"
            title="Klik untuk membuka dan mengubah kembali informasi pokok"
          >
            <span>✏️</span>
            <span>Ubah Info Pokok</span>
          </button>
        </div>

        <!-- 3A. MODE: DOKUMEN PDF / PPT -->
        <section v-if="form.tipe === 'Dokumen PDF/PPT'" class="bg-slate-900/80 border border-white/10 rounded-3xl p-6 shadow-2xl space-y-5">
          <h2 class="text-sm font-black uppercase tracking-wider text-cyan-400 flex items-center gap-2">
            <span>📄</span> Berkas Dokumen PDF / PPT
          </h2>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">Unggah Berkas (PDF, PPT, DOCX - Maks 30MB)</label>
            <div class="border-2 border-dashed border-white/20 hover:border-cyan-400/50 rounded-2xl p-6 text-center bg-slate-950/40 transition">
              <input
                type="file"
                @change="onMainFileSelected"
                accept=".pdf,.ppt,.pptx,.doc,.docx"
                class="hidden"
                id="main-file-input"
              />
              <label for="main-file-input" class="cursor-pointer block space-y-2">
                <span class="text-3xl">📁</span>
                <p class="text-xs text-slate-300 font-bold">
                  {{ selectedFile ? selectedFile.name : (props.materiProp?.fileName || 'Klik untuk pilih file atau seret ke sini') }}
                </p>
                <p class="text-[11px] text-slate-500">Mendukung file PDF, PowerPoint (.pptx), atau Word (.docx)</p>
              </label>
            </div>
            <div v-if="props.materiProp?.fileUrl && !selectedFile" class="mt-2 text-xs text-cyan-400 flex items-center gap-2">
              <span>📎 Berkas saat ini:</span>
              <a :href="props.materiProp.fileUrl" target="_blank" class="underline hover:text-cyan-300">Lihat Dokumen</a>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">Atau Tautan Dokumen (Google Drive / Cloud URL)</label>
            <input
              v-model="form.fileUrl"
              type="url"
              placeholder="https://drive.google.com/file/d/..."
              class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-2xl px-4 py-2.5 text-xs text-white min-h-[44px]"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">Petunjuk Membaca / Deskripsi Ringkas</label>
            <AutoTextarea
              v-model="form.deskripsiSingkat"
              placeholder="Tuliskan instruksi atau rangkuman materi untuk siswa..."
              class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-2xl p-4 text-xs text-white"
            />
          </div>
        </section>

        <!-- 3B. MODE: VIDEO YOUTUBE -->
        <section v-else-if="form.tipe === 'Video YouTube'" class="bg-slate-900/80 border border-white/10 rounded-3xl p-6 shadow-2xl space-y-5">
          <h2 class="text-sm font-black uppercase tracking-wider text-cyan-400 flex items-center gap-2">
            <span>🎥</span> Video Pembelajaran Edukatif
          </h2>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">Tautan Video YouTube <span class="text-rose-400">*</span></label>
            <input
              v-model="form.fileUrl"
              type="url"
              placeholder="https://www.youtube.com/watch?v=..."
              class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-2xl px-4 py-2.5 text-xs text-white min-h-[44px]"
            />
            <p class="text-[11px] text-slate-500 mt-1">Cukup copy-paste URL video YouTube dari browser.</p>
          </div>

          <!-- YouTube Live Preview -->
          <div v-if="youtubeEmbedUrl" class="rounded-2xl overflow-hidden border border-white/15 bg-black aspect-video max-w-xl mx-auto shadow-2xl">
            <iframe
              :src="youtubeEmbedUrl"
              class="w-full h-full border-0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            ></iframe>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">Catatan / Tugas Menonton</label>
            <AutoTextarea
              v-model="form.deskripsiSingkat"
              placeholder="Contoh: Perhatikan menit ke-03:15 saat pembahasan rumus percepatan..."
              class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-2xl p-4 text-xs text-white"
            />
          </div>
        </section>

        <!-- 3C. MODE: SLIDER PPT INTERAKTIF (FITUR UTAMA) -->
        <section v-else class="space-y-6">
          <!-- Theme Selector Card -->
          <div class="bg-slate-900/80 border border-white/10 rounded-3xl p-5 shadow-2xl flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-xs font-black uppercase tracking-wider text-slate-400">Tema Visual Slide Siswa</h2>
              <p class="text-[11px] text-slate-500">Tampilan warna & kontras saat dibuka siswa secara fullscreen</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <button
                v-for="th in THEME_OPTIONS"
                :key="th.id"
                type="button"
                @click="form.theme = th.id"
                class="px-3 py-2 rounded-xl text-xs font-bold border transition flex items-center gap-2 shadow-sm min-h-[44px]"
                :class="form.theme === th.id
                  ? 'ring-2 ring-cyan-400 ' + th.bg
                  : 'bg-white/5 border-white/10 text-slate-400 hover:bg-white/10'"
              >
                <span>{{ th.icon }}</span>
                <span>{{ th.label }}</span>
              </button>
            </div>
          </div>

          <!-- Slider Builder Studio -->
          <div class="bg-slate-900/95 border border-white/15 rounded-3xl shadow-2xl overflow-hidden backdrop-blur-xl">

            <!-- BAR 1: Studio Slide Control Bar (Header & Actions Separated) -->
            <div class="bg-slate-950/90 border-b border-white/10 px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <span class="px-3 py-1.5 rounded-xl text-xs font-black bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 flex items-center gap-2 shadow-sm">
                  <span>📑</span>
                  <span>Slide {{ activeSlideIndex + 1 }} dari {{ form.kegiatan.length }}</span>
                </span>
                <span v-if="form.kegiatan[activeSlideIndex]?.label" class="hidden sm:inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold bg-white/5 text-slate-300 border border-white/10">
                  🏷️ {{ form.kegiatan[activeSlideIndex].label }}
                </span>
              </div>

              <!-- Slide Reordering & Operations Toolbar -->
              <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center rounded-xl bg-white/5 p-1 border border-white/10 shadow-sm">
                  <button
                    type="button"
                    @click="moveSlide(activeSlideIndex, -1)"
                    :disabled="activeSlideIndex === 0"
                    class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-white hover:bg-white/10 transition disabled:opacity-25 flex items-center gap-1 active:scale-95 min-h-[38px]"
                    title="Pindah ke Kiri"
                  >
                    <span>◀</span>
                    <span class="hidden md:inline">Geser Kiri</span>
                  </button>
                  <div class="w-px h-4 bg-white/10 mx-1"></div>
                  <button
                    type="button"
                    @click="moveSlide(activeSlideIndex, 1)"
                    :disabled="activeSlideIndex === form.kegiatan.length - 1"
                    class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-white hover:bg-white/10 transition disabled:opacity-25 flex items-center gap-1 active:scale-95 min-h-[38px]"
                    title="Pindah ke Kanan"
                  >
                    <span class="hidden md:inline">Geser Kanan</span>
                    <span>▶</span>
                  </button>
                </div>

                <button
                  type="button"
                  @click="duplicateSlide(activeSlideIndex)"
                  class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-slate-200 font-bold transition flex items-center gap-1.5 active:scale-95 shadow-sm min-h-[38px]"
                  title="Duplikasi Slide Ini"
                >
                  <span>📋</span>
                  <span>Duplikasi</span>
                </button>

                <button
                  type="button"
                  @click="removeSlide(activeSlideIndex)"
                  :disabled="form.kegiatan.length <= 1"
                  class="px-3 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-300 text-xs font-bold transition flex items-center gap-1.5 disabled:opacity-30 active:scale-95 shadow-sm min-h-[38px]"
                  title="Hapus Slide Ini"
                >
                  <span>🗑️</span>
                  <span>Hapus</span>
                </button>

                <button
                  type="button"
                  @click="addSlide"
                  class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-white text-xs font-black shadow-md shadow-cyan-500/20 transition flex items-center gap-1.5 active:scale-95 min-h-[38px]"
                >
                  <span>➕</span>
                  <span>Tambah Slide</span>
                </button>
              </div>
            </div>

            <!-- BAR 2: Slide Navigation Horizontal Thumbnail Carousel -->
            <div class="bg-slate-950/60 border-b border-white/10 px-4 py-3 overflow-x-auto">
              <div class="flex items-center gap-2.5 min-w-max pb-1">
                <button
                  v-for="(s, idx) in form.kegiatan"
                  :key="idx"
                  type="button"
                  @click="activeSlideIndex = idx"
                  class="group text-left px-3.5 py-2.5 rounded-2xl border transition-all flex flex-col gap-1 min-w-[150px] max-w-[190px] min-h-[44px]"
                  :class="activeSlideIndex === idx
                    ? 'bg-gradient-to-br from-cyan-950/90 via-slate-900 to-slate-900 border-cyan-400 ring-2 ring-cyan-500/30 shadow-lg shadow-cyan-500/20 text-white'
                    : 'bg-white/[0.03] border-white/10 text-slate-400 hover:bg-white/[0.07] hover:border-white/20 hover:text-slate-200'"
                >
                  <div class="flex items-center justify-between text-[11px] font-black">
                    <span :class="activeSlideIndex === idx ? 'text-cyan-300 font-extrabold' : 'text-slate-400'">
                      Slide #{{ idx + 1 }}
                    </span>
                    <span class="text-xs">
                      {{ s.layout === 'image_left' ? '⬅️' : (s.layout === 'image_right' ? '➡️' : (s.layout === 'highlight' ? '💡' : '📋')) }}
                    </span>
                  </div>
                  <div class="text-xs font-bold truncate leading-tight" :class="activeSlideIndex === idx ? 'text-white' : 'text-slate-300'">
                    {{ s.judul || 'Tanpa Judul' }}
                  </div>
                  <div class="text-[10px] text-slate-500 truncate font-medium">
                    {{ s.label || 'Konsep' }}
                  </div>
                </button>

                <!-- Quick Add Slide Button at end of strip -->
                <button
                  type="button"
                  @click="addSlide"
                  class="h-[68px] px-4 rounded-2xl border border-dashed border-cyan-500/40 hover:border-cyan-400 bg-cyan-500/5 hover:bg-cyan-500/10 text-cyan-400 text-xs font-bold flex flex-col items-center justify-center gap-1 transition-all shrink-0 active:scale-95"
                  title="Tambah Slide Baru di Akhir"
                >
                  <span class="text-base font-black">+</span>
                  <span class="text-[11px] font-bold">Slide Baru</span>
                </button>
              </div>
            </div>

            <!-- Active Slide Editor Body -->
            <div v-if="form.kegiatan[activeSlideIndex]" class="p-6 space-y-6">
              <!-- Row: Label, Judul Slide, Layout -->
              <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3">
                  <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                    <span>🏷️</span> Fase / Kategori Slide
                  </label>
                  <input
                    v-model="form.kegiatan[activeSlideIndex].label"
                    list="fase-list"
                    placeholder="Misal: Apersepsi"
                    class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-600 transition min-h-[44px]"
                  />
                  <datalist id="fase-list">
                    <option v-for="f in FASE_LABELS" :key="f" :value="f" />
                  </datalist>
                </div>

                <div class="md:col-span-5">
                  <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                    <span>✏️</span> Judul Sub-Materi / Slide
                  </label>
                  <input
                    v-model="form.kegiatan[activeSlideIndex].judul"
                    placeholder="Misal: Definisi & Konsep Percepatan Benda"
                    class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3.5 py-2.5 text-xs text-white font-bold placeholder-slate-600 transition min-h-[44px]"
                  />
                </div>

                <div class="md:col-span-4">
                  <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                    <span>📐</span> Tata Letak (Layout)
                  </label>
                  <select
                    v-model="form.kegiatan[activeSlideIndex].layout"
                    class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-xl px-3.5 py-2.5 text-xs text-white font-medium transition cursor-pointer min-h-[44px]"
                  >
                    <option v-for="lay in LAYOUT_OPTIONS" :key="lay.id" :value="lay.id" class="bg-slate-900 text-white py-1">
                      {{ lay.icon }} {{ lay.label }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Toolbar Rumus Ilmiah Cepat (Matematika, Fisika, Kimia) -->
              <div class="p-4 rounded-2xl bg-cyan-950/20 border border-cyan-500/20 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-black text-cyan-300 flex items-center gap-1.5">
                      <span>📐</span> Sisipkan Rumus Ilmiah:
                    </span>
                    <div class="inline-flex rounded-xl bg-slate-950 p-1 border border-white/10">
                      <button
                        type="button"
                        @click="activeFormulaCategory = 'matematika'"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition min-h-[32px]"
                        :class="activeFormulaCategory === 'matematika' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                      >
                        Matematika
                      </button>
                      <button
                        type="button"
                        @click="activeFormulaCategory = 'fisika'"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition min-h-[32px]"
                        :class="activeFormulaCategory === 'fisika' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                      >
                        Fisika
                      </button>
                      <button
                        type="button"
                        @click="activeFormulaCategory = 'kimia'"
                        class="px-3 py-1 rounded-lg text-xs font-bold transition min-h-[32px]"
                        :class="activeFormulaCategory === 'kimia' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                      >
                        Kimia
                      </button>
                    </div>
                  </div>
                  <span class="text-[11px] text-slate-400">Klik tombol rumus untuk menempelkan sintaks KaTeX ke naskah</span>
                </div>

                <!-- Quick Buttons -->
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="item in SCIENCE_PRESETS[activeFormulaCategory]"
                    :key="item.label"
                    type="button"
                    @click="insertFormula(item.latex)"
                    class="px-2.5 py-1.5 rounded-lg bg-slate-900/90 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-400/40 text-xs font-mono text-cyan-200 transition active:scale-95 shadow-sm flex items-center gap-1 min-h-[36px]"
                    :title="item.label + ' (' + item.latex + ')'"
                  >
                    <span class="font-bold text-[11px] text-slate-400">{{ item.label }}:</span>
                    <span class="text-cyan-300 font-bold">{{ item.symbol }}</span>
                  </button>
                </div>
              </div>

              <!-- Two-Column Studio: Editor Naskah (Kiri) + Live Student Slide Mockup (Kanan) -->
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                <!-- Kolom Kiri: Input Konten Slide & Gambar Terintegrasi -->
                <div class="space-y-2.5">
                  <div class="flex items-center justify-between flex-wrap gap-2">
                    <label class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                      <span>📝</span> Konten / Naskah Slide
                    </label>
                    <div class="flex items-center gap-2">
                      <input
                        type="file"
                        :id="'slide-img-' + activeSlideIndex"
                        @change="onSlideImageSelected(activeSlideIndex, $event)"
                        accept="image/*"
                        class="hidden"
                      />
                      <label
                        :for="'slide-img-' + activeSlideIndex"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-xs font-bold text-cyan-300 cursor-pointer transition shadow-sm active:scale-95 min-h-[38px]"
                        title="Tambahkan atau ganti gambar pendukung slide"
                      >
                        <span>📷</span>
                        <span>{{ kegiatanImagePreviews[activeSlideIndex] ? 'Ganti Gambar' : 'Tambah Gambar' }}</span>
                      </label>
                      <span class="text-[11px] text-cyan-400 font-mono hidden sm:inline">Markdown & KaTeX</span>
                    </div>
                  </div>

                  <!-- Preview Gambar Slide Mini Terintegrasi Langsung di Dalam Konten -->
                  <div
                    v-if="kegiatanImagePreviews[activeSlideIndex]"
                    class="p-2.5 rounded-xl bg-slate-950/80 border border-cyan-500/30 flex items-center justify-between gap-3 shadow-md"
                  >
                    <div class="flex items-center gap-3 min-w-0">
                      <div class="w-14 h-12 rounded-lg overflow-hidden border border-white/10 bg-black shrink-0 relative group">
                        <img :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                      </div>
                      <div class="min-w-0">
                        <div class="flex items-center gap-2">
                          <span class="text-xs font-bold text-slate-200 truncate">Gambar Slide Terlampir</span>
                          <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/15 text-cyan-300 font-semibold border border-cyan-500/20">
                            Layout: {{ form.kegiatan[activeSlideIndex].layout }}
                          </span>
                        </div>
                        <p class="text-[11px] text-slate-400 truncate">Gambar akan otomatis tampil sesuai layout pilihan.</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                      <label
                        :for="'slide-img-' + activeSlideIndex"
                        class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-[11px] font-bold text-slate-300 cursor-pointer transition border border-white/10 min-h-[34px] flex items-center"
                      >
                        Ganti
                      </label>
                      <button
                        type="button"
                        @click="removeSlideImage(activeSlideIndex)"
                        class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-300 text-[11px] font-bold transition flex items-center gap-1 min-h-[34px]"
                      >
                        <span>🗑️</span>
                        <span>Hapus</span>
                      </button>
                    </div>
                  </div>

                  <AutoTextarea
                    v-model="form.kegiatan[activeSlideIndex].konten"
                    placeholder="Tuliskan naskah penjelasan, poin-poin materi, definisi, atau masukkan rumus di atas..."
                    class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 rounded-2xl p-4 text-xs font-sans text-white leading-relaxed placeholder-slate-600 min-h-[220px]"
                  />
                  <p class="text-[11px] text-slate-500">
                    Tip: Gunakan baris kosong untuk paragraf baru, tanda minus <code class="text-cyan-400">-</code> untuk poin, dan <code class="text-cyan-400">$rumus$</code> untuk rumus inline.
                  </p>
                </div>

                <!-- Kolom Kanan: Pratinjau Tampilan Siswa (Real-Time Mockup Sesuai Tema) -->
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-black text-cyan-400 uppercase tracking-wider flex items-center gap-1.5">
                      <span>👁️</span> Pratinjau Tampilan Siswa
                    </span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-300 border border-cyan-500/20 font-bold">
                      Tema: {{ form.theme }}
                    </span>
                  </div>

                  <!-- Realistic Slide Card Container -->
                  <div
                    class="rounded-2xl border p-5 shadow-xl min-h-[220px] flex flex-col justify-between transition-all"
                    :class="currentThemeConfig.wrapper"
                  >
                    <!-- Slide Header inside Mockup -->
                    <div>
                      <div class="flex items-center justify-between gap-2 mb-2 pb-2 border-b" :class="currentThemeConfig.footer">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded" :class="currentThemeConfig.badge">
                          {{ form.kegiatan[activeSlideIndex]?.label || 'Konsep Utama' }}
                        </span>
                        <span class="text-[10px] font-mono opacity-60">
                          Slide {{ activeSlideIndex + 1 }} / {{ form.kegiatan.length }}
                        </span>
                      </div>

                      <h3 class="text-sm font-black mb-3 leading-snug" :class="currentThemeConfig.title">
                        {{ form.kegiatan[activeSlideIndex]?.judul || 'Judul Slide Pembelajaran' }}
                      </h3>

                      <!-- Slide Content & Media Layout Mockup -->
                      <div v-if="form.kegiatan[activeSlideIndex]?.layout === 'image_left'" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                        <div class="sm:col-span-5 rounded-xl overflow-hidden bg-black/40 border border-white/10 h-28 flex items-center justify-center">
                          <img v-if="kegiatanImagePreviews[activeSlideIndex]" :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                          <span v-else class="text-[10px] text-slate-500">🖼️ Gambar Kiri</span>
                        </div>
                        <div class="sm:col-span-7">
                          <div
                            class="line-clamp-6 text-xs leading-relaxed"
                            :class="currentThemeConfig.prose"
                            v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Belum ada konten slide. Ketik di sebelah kiri.*')"
                          ></div>
                        </div>
                      </div>

                      <div v-else-if="form.kegiatan[activeSlideIndex]?.layout === 'image_right'" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                        <div class="sm:col-span-7">
                          <div
                            class="line-clamp-6 text-xs leading-relaxed"
                            :class="currentThemeConfig.prose"
                            v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Belum ada konten slide. Ketik di sebelah kiri.*')"
                          ></div>
                        </div>
                        <div class="sm:col-span-5 rounded-xl overflow-hidden bg-black/40 border border-white/10 h-28 flex items-center justify-center">
                          <img v-if="kegiatanImagePreviews[activeSlideIndex]" :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                          <span v-else class="text-[10px] text-slate-500">🖼️ Gambar Kanan</span>
                        </div>
                      </div>

                      <div v-else-if="form.kegiatan[activeSlideIndex]?.layout === 'highlight'" class="p-3.5 rounded-xl border border-amber-400/40 bg-amber-500/10">
                        <div
                          class="text-xs leading-relaxed"
                          :class="currentThemeConfig.prose"
                          v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Belum ada konten slide. Ketik di sebelah kiri.*')"
                        ></div>
                      </div>

                      <div v-else>
                        <div v-if="kegiatanImagePreviews[activeSlideIndex]" class="mb-3 rounded-xl overflow-hidden bg-black/40 border border-white/10 max-h-36">
                          <img :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                        </div>
                        <div
                          class="text-xs leading-relaxed"
                          :class="currentThemeConfig.prose"
                          v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Belum ada konten slide. Ketik di sebelah kiri.*')"
                        ></div>
                      </div>
                    </div>

                    <!-- Mockup Footer -->
                    <div class="pt-3 mt-4 border-t flex items-center justify-between text-[10px] opacity-70" :class="currentThemeConfig.footer">
                      <span>SMKS Muhammadiyah 2 Genteng</span>
                      <span>Modul Interaktif E-Learning</span>
                    </div>
                  </div>
                </div>

              </div>

            </div>
          </div>

        </section>

        <!-- Bottom Navigation & Action Bar in Step 2 -->
        <div class="flex items-center justify-between pt-4 border-t border-white/10">
          <button
            type="button"
            @click="goToConfig"
            class="px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/15 text-xs font-black text-slate-300 hover:text-white transition flex items-center gap-2 min-h-[44px] active:scale-95"
          >
            <span>←</span>
            <span>Kembali ke Gerbang Masuk</span>
          </button>

          <button
            type="button"
            @click="submit"
            :disabled="saving"
            class="px-6 py-3 rounded-2xl bg-gradient-to-r from-cyan-500 via-teal-500 to-indigo-600 text-white font-black text-xs shadow-xl shadow-cyan-500/25 hover:scale-105 active:scale-95 transition-all disabled:opacity-50 min-h-[44px] flex items-center gap-2"
          >
            <span v-if="saving" class="animate-spin">⏳</span>
            <span v-else>💾</span>
            <span>{{ saving ? 'Menyimpan Materi...' : 'Simpan Seluruh Materi' }}</span>
          </button>
        </div>

      </div>

    </main>
  </div>
</template>
