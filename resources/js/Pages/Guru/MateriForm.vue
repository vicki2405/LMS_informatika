<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
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

// Mode options
const TIPE_OPTIONS = [
  { value: 'Slider PPT', label: '🖥️ Slider PPT Interaktif' },
  { value: 'Dokumen PDF/PPT', label: '📄 Dokumen PDF / Berkas' },
  { value: 'Video YouTube', label: '🎥 Video YouTube' },
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
const isInfoSectionCollapsed = ref(false);

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

// Dynamic Kelas
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
    if (a.isScheduled && !b.isScheduled) return -1;
    if (!a.isScheduled && b.isScheduled) return 1;
    const tCmp = (a.tingkat || '').localeCompare(b.tingkat || '');
    if (tCmp !== 0) return tCmp;
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

function getTingkatFromKelasId(kelasId) {
  if (!kelasId) return 'Semua';
  const kObj = availableKelasForSelectedMapel.value.find(k => k.id === kelasId)
            || (props.kelasListProp || []).find(k => k.id === kelasId);
  return kObj ? (kObj.tingkat || 'Semua') : 'Semua';
}

if (form.kelas_id) {
  selectedTingkat.value = getTingkatFromKelasId(form.kelas_id);
} else if (!isEditing.value) {
  const scheduled = availableKelasForSelectedMapel.value.filter(k => k.isScheduled);
  if (scheduled.length > 0 && scheduled[0].tingkat) {
    selectedTingkat.value = scheduled[0].tingkat.toUpperCase();
  }
}

// Dynamic Filter TP sesuai Mapel yang dipilih dan Semester
const availableTpsForSelectedMapel = computed(() => {
  const currentMapel = selectedMapelObj.value;
  return (props.tpListProp || []).filter(tp => {
    if (form.semester && tp.semester && tp.semester !== form.semester) {
      return false;
    }
    if (!currentMapel) return true;
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

if (!form.tpId && availableTpsForSelectedMapel.value.length > 0) {
  form.tpId = availableTpsForSelectedMapel.value[0].id;
}

watch([() => form.mapelId, () => form.semester], () => {
  const available = availableTpsForSelectedMapel.value;
  const exists = available.some(t => String(t.id) === String(form.tpId));
  if (!exists) {
    form.tpId = available.length > 0 ? available[0].id : '';
  }
});

const selectedTpObj = computed(() => {
  return (props.tpListProp || []).find(t => String(t.id) === String(form.tpId));
});

// TP Dropdown state
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
  const formulaBox = document.getElementById('formula-drawer-box');
  const formulaBtn = document.getElementById('formula-toggle-btn');
  if (isFormulaDrawerOpen.value && formulaBox && !formulaBox.contains(event.target) && formulaBtn && !formulaBtn.contains(event.target)) {
    isFormulaDrawerOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

watch(() => form.mapelId, () => {
  if (form.kelas_id) {
    const exists = availableKelasForSelectedMapel.value.some(k => k.id === form.kelas_id);
    if (!exists) {
      form.kelas_id = null;
    }
  }
});

watch(selectedTingkat, (newTingkat) => {
  if (form.kelas_id && newTingkat !== 'Semua') {
    const kObj = availableKelasForSelectedMapel.value.find(k => k.id === form.kelas_id);
    if (kObj && (kObj.tingkat || '').toUpperCase() !== newTingkat.toUpperCase()) {
      form.kelas_id = null;
    }
  }
});

// Slides setup
const kegiatanFiles = ref([]);
const kegiatanImagePreviews = ref([]);
const selectedFile = ref(null);
const saving = ref(false);
const activeSlideIndex = ref(0);
const activeFormulaCategory = ref('matematika');
const isFormulaDrawerOpen = ref(false);
const slideTextareaRef = ref(null);

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

// =========================================================================
// TEXT FORMATTING TOOLBAR ENGINE (Rich Text / Markdown / Alignment / Lists)
// =========================================================================
function applyFormat(type, payload = null) {
  const current = form.kegiatan[activeSlideIndex.value];
  if (!current) return;

  const textarea = slideTextareaRef.value?.el || slideTextareaRef.value;
  let text = current.konten || '';
  let start = 0;
  let end = 0;

  if (textarea && typeof textarea.selectionStart === 'number') {
    start = textarea.selectionStart;
    end = textarea.selectionEnd;
  } else {
    start = text.length;
    end = text.length;
  }

  const selectedText = text.substring(start, end);
  let replacement = '';
  let cursorOffset = 0;

  switch (type) {
    case 'bold':
      replacement = `**${selectedText || 'Teks Tebal'}**`;
      cursorOffset = selectedText ? replacement.length : 2;
      break;
    case 'italic':
      replacement = `*${selectedText || 'Teks Miring'}*`;
      cursorOffset = selectedText ? replacement.length : 1;
      break;
    case 'underline':
      replacement = `<u>${selectedText || 'Teks Bergaris Bawah'}</u>`;
      cursorOffset = selectedText ? replacement.length : 3;
      break;
    case 'strikethrough':
      replacement = `~~${selectedText || 'Teks Dicoret'}~~`;
      cursorOffset = selectedText ? replacement.length : 2;
      break;
    case 'h1':
      replacement = `\n# ${selectedText || 'Judul Utama'}\n`;
      cursorOffset = replacement.length;
      break;
    case 'h2':
      replacement = `\n## ${selectedText || 'Sub-Judul'}\n`;
      cursorOffset = replacement.length;
      break;
    case 'align_left':
      replacement = `\n<div style="text-align: left;">\n\n${selectedText || 'Teks rata kiri'}\n\n</div>\n`;
      cursorOffset = replacement.length;
      break;
    case 'align_center':
      replacement = `\n<div style="text-align: center;">\n\n${selectedText || 'Teks rata tengah'}\n\n</div>\n`;
      cursorOffset = replacement.length;
      break;
    case 'align_right':
      replacement = `\n<div style="text-align: right;">\n\n${selectedText || 'Teks rata kanan'}\n\n</div>\n`;
      cursorOffset = replacement.length;
      break;
    case 'align_justify':
      replacement = `\n<div style="text-align: justify;">\n\n${selectedText || 'Teks rata kanan kiri yang rapi dan tertata dengan baik.'}\n\n</div>\n`;
      cursorOffset = replacement.length;
      break;
    case 'bullet_list':
      if (selectedText) {
        replacement = selectedText.split('\n').map(l => `- ${l}`).join('\n');
      } else {
        replacement = '\n- Poin pertama\n- Poin kedua\n- Poin ketiga\n';
      }
      cursorOffset = replacement.length;
      break;
    case 'numbered_list':
      if (selectedText) {
        replacement = selectedText.split('\n').map((l, i) => `${i + 1}. ${l}`).join('\n');
      } else {
        replacement = '\n1. Langkah kesatu\n2. Langkah kedua\n3. Langkah ketiga\n';
      }
      cursorOffset = replacement.length;
      break;
    case 'alpha_list':
      if (selectedText) {
        const lines = selectedText.split('\n').map(l => `  <li>${l}</li>`).join('\n');
        replacement = `\n<ol type="a">\n${lines}\n</ol>\n`;
      } else {
        replacement = '\n<ol type="a">\n  <li>Poin a</li>\n  <li>Poin b</li>\n  <li>Poin c</li>\n</ol>\n';
      }
      cursorOffset = replacement.length;
      break;
    case 'checklist':
      if (selectedText) {
        replacement = selectedText.split('\n').map(l => `- [ ] ${l}`).join('\n');
      } else {
        replacement = '\n- [ ] Tugas evaluasi 1\n- [ ] Tugas evaluasi 2\n';
      }
      cursorOffset = replacement.length;
      break;
    case 'quote':
      if (selectedText) {
        replacement = selectedText.split('\n').map(l => `> ${l}`).join('\n');
      } else {
        replacement = '\n> Catatan penting / kesimpulan materi...\n';
      }
      cursorOffset = replacement.length;
      break;
    case 'table':
      replacement = '\n| No | Konsep / Materi | Keterangan |\n|---|---|---|\n| 1 | Poin Pertama | Penjelasan materi... |\n| 2 | Poin Kedua | Penjelasan materi... |\n';
      cursorOffset = replacement.length;
      break;
    case 'divider':
      replacement = '\n\n---\n\n';
      cursorOffset = replacement.length;
      break;
    case 'formula':
      replacement = payload ? ` ${payload} ` : ' $$x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}$$ ';
      cursorOffset = replacement.length;
      isFormulaDrawerOpen.value = false;
      break;
    default:
      break;
  }

  current.konten = text.substring(0, start) + replacement + text.substring(end);

  nextTick(() => {
    if (textarea) {
      textarea.focus();
      const newPos = start + cursorOffset;
      if (typeof textarea.setSelectionRange === 'function') {
        textarea.setSelectionRange(newPos, newPos);
      }
      if (slideTextareaRef.value?.resize) {
        slideTextareaRef.value.resize();
      }
    }
  });
}

// YouTube Preview Helper
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

function onMainFileSelected(e) {
  selectedFile.value = e.target.files[0] || null;
}

function goBack() {
  router.visit(route('guru.dashboard', { tab: 'materi' }));
}

function submit() {
  if (!form.judul.trim()) {
    showToast('Judul materi pembelajaran wajib diisi!', 'warning');
    return;
  }
  if (!form.mapelId) {
    showToast('Pilih mata pelajaran terlebih dahulu!', 'warning');
    return;
  }
  if (!form.tpId) {
    showToast('Pilih Tujuan Pembelajaran (TP)!', 'warning');
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

  kegiatanFiles.value.forEach((f, i) => {
    if (f) {
      fd.append(`kegiatan_files[${i}]`, f);
      fd.append('kegiatan_file[]', f);
    }
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
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <button
            @click="goBack"
            type="button"
            class="px-3.5 py-2 shrink-0 rounded-xl bg-white/5 hover:bg-white/10 active:bg-white/15 border border-white/15 hover:border-cyan-500/40 text-slate-200 hover:text-white text-xs font-bold flex items-center gap-2 transition active:scale-95 shadow-sm min-h-[44px]"
            title="Kembali ke Daftar Materi"
          >
            <svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span class="font-bold">Kembali</span>
          </button>
          <div class="min-w-0">
            <h1 class="text-base sm:text-lg font-black text-white truncate">
              {{ isEditing ? 'Edit Materi Pembelajaran' : 'Buat Materi Pembelajaran Baru' }}
            </h1>
            <p class="text-[11px] text-slate-400 truncate">SMKS Muhammadiyah 2 Genteng</p>
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
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 via-teal-500 to-indigo-600 text-white font-black text-xs shadow-lg shadow-cyan-500/20 hover:scale-105 active:scale-95 transition disabled:opacity-50 min-h-[44px]"
          >
            <span v-if="saving" class="animate-spin">⏳</span>
            <span v-else>💾</span>
            <span>{{ saving ? 'Menyimpan...' : 'Simpan Materi' }}</span>
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 pt-5 space-y-6">

      <!-- ========================================================================= -->
      <!-- CARD 1: PENGATURAN INFORMASI MATERI (COMPACT & FOKUS)                     -->
      <!-- ========================================================================= -->
      <section class="bg-slate-900/90 border border-white/15 rounded-2xl shadow-xl backdrop-blur-xl transition-all">
        <!-- Collapsible Header -->
        <div
          @click="isInfoSectionCollapsed = !isInfoSectionCollapsed"
          class="px-5 py-3.5 flex items-center justify-between cursor-pointer border-b border-white/10 hover:bg-white/[0.02] transition rounded-t-2xl select-none"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="text-base text-cyan-400">⚙️</span>
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-200">
              Informasi Pokok Materi
            </h2>
            <span v-if="form.judul" class="text-xs text-cyan-300 font-semibold truncate hidden sm:inline">
              — {{ form.judul }}
            </span>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <span class="text-[11px] text-slate-400 font-medium">
              {{ isInfoSectionCollapsed ? 'Buka Pengaturan' : 'Tutup Pengaturan' }}
            </span>
            <svg
              class="w-4 h-4 text-slate-400 transition-transform duration-200"
              :class="isInfoSectionCollapsed ? '-rotate-90' : 'rotate-0'"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </div>

        <!-- Form Body -->
        <div v-show="!isInfoSectionCollapsed" class="p-5 space-y-4">
          <!-- Judul Materi -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">
              Judul Materi Pembelajaran <span class="text-rose-400">*</span>
            </label>
            <input
              v-model="form.judul"
              type="text"
              required
              placeholder="Contoh: Hukum Newton II & Dinamika Gerak Benda"
              class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-500/20 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 transition min-h-[44px]"
            />
          </div>

          <!-- Grid Baris 1: Mapel, Semester, Kelas, TP -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <!-- Mata Pelajaran -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">
                Mata Pelajaran <span class="text-rose-400">*</span>
              </label>
              <select
                v-model="form.mapelId"
                class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white min-h-[42px] cursor-pointer"
              >
                <option v-for="mp in mapelListProp" :key="mp.id" :value="mp.id" class="bg-slate-900 text-white py-1">
                  {{ mp.nama }} ({{ mp.kode }})
                </option>
              </select>
            </div>

            <!-- Semester -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">Semester</label>
              <select
                v-model="form.semester"
                class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white min-h-[42px] cursor-pointer"
              >
                <option value="Ganjil" class="bg-slate-900 text-white py-1">Semester Ganjil</option>
                <option value="Genap" class="bg-slate-900 text-white py-1">Semester Genap</option>
              </select>
            </div>

            <!-- Target Kelas -->
            <div>
              <label class="block text-xs font-bold text-slate-300 mb-1">Target Kelas</label>
              <select
                v-model="form.kelas_id"
                class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white min-h-[42px] cursor-pointer"
              >
                <option :value="null" class="bg-slate-900 text-white py-1">🌐 Seluruh Kelas</option>
                <option v-for="k in availableKelasForSelectedMapel" :key="k.id" :value="k.id" class="bg-slate-900 text-white py-1">
                  {{ k.isScheduled ? '✨' : '🏫' }} Kelas {{ k.nama || k.namaKelas }} ({{ k.jurusan || 'Umum' }})
                </option>
              </select>
            </div>

            <!-- Tujuan Pembelajaran (TP) -->
            <div id="tp-selector-container" class="relative">
              <label class="block text-xs font-bold text-slate-300 mb-1">
                Tujuan Pembelajaran (TP) <span class="text-rose-400">*</span>
              </label>

              <!-- Trigger Button -->
              <div
                @click="isTpDropdownOpen = !isTpDropdownOpen"
                class="w-full bg-slate-950/90 border rounded-xl px-3 py-2 text-xs cursor-pointer flex items-center justify-between gap-2 min-h-[42px] transition select-none"
                :class="isTpDropdownOpen ? 'border-cyan-400 ring-1 ring-cyan-500/20' : 'border-white/15 hover:border-white/30'"
              >
                <span v-if="selectedTpObj" class="text-white font-medium truncate text-xs">
                  <span class="text-cyan-300 font-bold mr-1">{{ selectedTpObj.kode }}</span>
                  {{ selectedTpObj.judul || selectedTpObj.deskripsi }}
                </span>
                <span v-else class="text-slate-500 italic truncate text-xs">
                  -- Pilih TP --
                </span>
                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" :class="isTpDropdownOpen ? 'rotate-180 text-cyan-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>

              <!-- Dropdown List -->
              <div
                v-if="isTpDropdownOpen"
                class="absolute z-50 left-0 right-0 mt-1.5 bg-slate-900 border border-cyan-500/40 rounded-xl shadow-2xl p-2 backdrop-blur-2xl space-y-1.5 animate-in fade-in zoom-in-95 duration-100"
              >
                <input
                  v-model="tpSearchQuery"
                  type="text"
                  placeholder="Cari kode atau materi TP..."
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-lg px-2.5 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none"
                  @click.stop
                />
                <div class="max-h-56 overflow-y-auto space-y-1 pr-1">
                  <div v-if="searchedTps.length === 0" class="py-4 text-center text-xs text-slate-400">
                    Tidak ada TP yang cocok.
                  </div>
                  <div
                    v-for="tp in searchedTps"
                    :key="tp.id"
                    @click="selectTp(tp)"
                    class="p-2 rounded-lg cursor-pointer transition flex items-start gap-2 border text-xs"
                    :class="String(form.tpId) === String(tp.id)
                      ? 'bg-cyan-500/15 border-cyan-500/40 text-white font-bold'
                      : 'bg-white/[0.02] border-white/5 hover:bg-white/10 text-slate-300'"
                  >
                    <span class="px-1.5 py-0.5 rounded bg-cyan-500/20 text-cyan-300 font-extrabold text-[10px] shrink-0 mt-0.5">
                      {{ tp.kode }}
                    </span>
                    <span class="truncate flex-1">{{ tp.judul || tp.deskripsi }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Grid Baris 2: Format Materi & Tautkan CBT -->
          <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5 pt-1">
            <!-- Format Materi (Pills) -->
            <div class="sm:col-span-7">
              <label class="block text-xs font-bold text-slate-300 mb-1">Format Pembuatan Materi</label>
              <div class="grid grid-cols-3 gap-2">
                <button
                  v-for="opt in TIPE_OPTIONS"
                  :key="opt.value"
                  type="button"
                  @click="form.tipe = opt.value"
                  class="py-2 px-2.5 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-1.5 min-h-[40px]"
                  :class="form.tipe === opt.value
                    ? 'bg-cyan-500/20 text-cyan-300 border-cyan-400 shadow-sm'
                    : 'bg-white/5 text-slate-400 border-white/10 hover:bg-white/10 hover:text-white'"
                >
                  <span class="truncate">{{ opt.label }}</span>
                </button>
              </div>
            </div>

            <!-- Tautkan Ujian CBT -->
            <div class="sm:col-span-5">
              <label class="block text-xs font-bold text-slate-300 mb-1">Tautkan ke Ujian CBT (Opsional)</label>
              <select
                v-model="form.cbtJadwalId"
                class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white min-h-[40px] cursor-pointer"
              >
                <option :value="null" class="bg-slate-900 text-white py-1">-- Tidak Ada Ujian Terkait --</option>
                <option v-for="cbt in cbtJadwalListProp" :key="cbt.id" :value="cbt.id" class="bg-slate-900 text-white py-1">
                  📝 {{ cbt.judul }} ({{ cbt.durasi }} Menit)
                </option>
              </select>
            </div>
          </div>
        </div>
      </section>

      <!-- ========================================================================= -->
      <!-- CARD 2A: MODE BERKAS DOKUMEN / PDF                                        -->
      <!-- ========================================================================= -->
      <section v-if="form.tipe === 'Dokumen PDF/PPT'" class="bg-slate-900/90 border border-white/15 rounded-2xl p-6 shadow-xl space-y-4">
        <h2 class="text-xs font-black uppercase tracking-wider text-cyan-400 flex items-center gap-2">
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
            <label for="main-file-input" class="cursor-pointer block space-y-1.5">
              <span class="text-3xl">📁</span>
              <p class="text-xs text-slate-300 font-bold">
                {{ selectedFile ? selectedFile.name : (props.materiProp?.fileName || 'Klik untuk pilih berkas') }}
              </p>
              <p class="text-[11px] text-slate-500">Mendukung format PDF, PowerPoint (.pptx), atau Word (.docx)</p>
            </label>
          </div>
          <div v-if="props.materiProp?.fileUrl && !selectedFile" class="mt-2 text-xs text-cyan-400 flex items-center gap-2">
            <span>📎 Berkas saat ini:</span>
            <a :href="props.materiProp.fileUrl" target="_blank" class="underline hover:text-cyan-300">Lihat Dokumen</a>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1.5">Atau Tautan Google Drive / URL Cloud</label>
          <input
            v-model="form.fileUrl"
            type="url"
            placeholder="https://drive.google.com/file/d/..."
            class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-xl px-4 py-2.5 text-xs text-white min-h-[44px]"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1.5">Instruksi / Catatan Singkat untuk Siswa</label>
          <AutoTextarea
            v-model="form.deskripsiSingkat"
            placeholder="Tuliskan petunjuk membaca atau poin penting yang harus diperhatikan..."
            class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-xl p-3.5 text-xs text-white"
          />
        </div>
      </section>

      <!-- ========================================================================= -->
      <!-- CARD 2B: MODE VIDEO YOUTUBE                                               -->
      <!-- ========================================================================= -->
      <section v-else-if="form.tipe === 'Video YouTube'" class="bg-slate-900/90 border border-white/15 rounded-2xl p-6 shadow-xl space-y-4">
        <h2 class="text-xs font-black uppercase tracking-wider text-cyan-400 flex items-center gap-2">
          <span>🎥</span> Tautan Video Pembelajaran
        </h2>

        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1.5">URL Video YouTube <span class="text-rose-400">*</span></label>
          <input
            v-model="form.fileUrl"
            type="url"
            placeholder="https://www.youtube.com/watch?v=..."
            class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-xl px-4 py-2.5 text-xs text-white min-h-[44px]"
          />
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
          <label class="block text-xs font-bold text-slate-300 mb-1.5">Catatan Video untuk Siswa</label>
          <AutoTextarea
            v-model="form.deskripsiSingkat"
            placeholder="Contoh: Simak penjelasan menit ke-02:15..."
            class="w-full bg-slate-950/80 border border-white/15 focus:border-cyan-400 rounded-xl p-3.5 text-xs text-white"
          />
        </div>
      </section>

      <!-- ========================================================================= -->
      <!-- CARD 2C: MODE SLIDER PPT INTERAKTIF (STUDIO MATERI UTAMA)                 -->
      <!-- ========================================================================= -->
      <section v-else class="space-y-4">

        <!-- Bar Tema & Kontrol Slide -->
        <div class="bg-slate-900/90 border border-white/15 rounded-2xl p-3.5 sm:p-4 shadow-xl flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-black text-slate-300 mr-1">Tema Slide:</span>
            <button
              v-for="th in THEME_OPTIONS"
              :key="th.id"
              type="button"
              @click="form.theme = th.id"
              class="px-2.5 py-1.5 rounded-lg text-xs font-bold border transition flex items-center gap-1.5 min-h-[36px]"
              :class="form.theme === th.id
                ? 'ring-2 ring-cyan-400 ' + th.bg
                : 'bg-white/5 border-white/10 text-slate-400 hover:bg-white/10'"
            >
              <span>{{ th.icon }}</span>
              <span>{{ th.label }}</span>
            </button>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-cyan-300 px-3 py-1.5 rounded-lg bg-cyan-500/10 border border-cyan-500/20">
              Slide {{ activeSlideIndex + 1 }} dari {{ form.kegiatan.length }}
            </span>
          </div>
        </div>

        <!-- Studio Slide Builder -->
        <div class="bg-slate-900/95 border border-white/15 rounded-2xl shadow-2xl overflow-hidden backdrop-blur-xl">

          <!-- 1. Strip Thumbnail Slide Horizontal -->
          <div class="bg-slate-950/90 border-b border-white/10 px-4 py-3 flex items-center justify-between gap-3 overflow-x-auto">
            <div class="flex items-center gap-2 min-w-max">
              <button
                v-for="(s, idx) in form.kegiatan"
                :key="idx"
                type="button"
                @click="activeSlideIndex = idx"
                class="px-3 py-2 rounded-xl border text-left transition flex items-center gap-2 min-h-[42px]"
                :class="activeSlideIndex === idx
                  ? 'bg-cyan-500/20 border-cyan-400 text-white font-bold shadow-md shadow-cyan-500/20'
                  : 'bg-white/5 border-white/10 text-slate-400 hover:bg-white/10 hover:text-slate-200'"
              >
                <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-black" :class="activeSlideIndex === idx ? 'bg-cyan-500 text-white' : 'bg-white/10 text-slate-300'">
                  {{ idx + 1 }}
                </span>
                <span class="text-xs truncate max-w-[120px]">{{ s.judul || ('Bagian ' + (idx + 1)) }}</span>
              </button>

              <!-- Tambah Slide Button -->
              <button
                type="button"
                @click="addSlide"
                class="px-3 py-2 rounded-xl border border-dashed border-cyan-500/40 hover:border-cyan-400 bg-cyan-500/5 hover:bg-cyan-500/10 text-cyan-400 text-xs font-bold flex items-center gap-1.5 transition active:scale-95 min-h-[42px]"
              >
                <span>➕</span>
                <span>Tambah Slide</span>
              </button>
            </div>

            <!-- Slide Reordering Action Buttons -->
            <div class="flex items-center gap-1.5 shrink-0 pl-2">
              <button
                type="button"
                @click="moveSlide(activeSlideIndex, -1)"
                :disabled="activeSlideIndex === 0"
                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-300 disabled:opacity-25 transition min-h-[36px]"
                title="Pindah ke Kiri"
              >
                ◀
              </button>
              <button
                type="button"
                @click="moveSlide(activeSlideIndex, 1)"
                :disabled="activeSlideIndex === form.kegiatan.length - 1"
                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-300 disabled:opacity-25 transition min-h-[36px]"
                title="Pindah ke Kanan"
              >
                ▶
              </button>
              <button
                type="button"
                @click="duplicateSlide(activeSlideIndex)"
                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-300 transition min-h-[36px]"
                title="Duplikasi Slide"
              >
                📋 Salin
              </button>
              <button
                type="button"
                @click="removeSlide(activeSlideIndex)"
                :disabled="form.kegiatan.length <= 1"
                class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-300 text-xs font-bold disabled:opacity-25 transition min-h-[36px]"
                title="Hapus Slide"
              >
                🗑️
              </button>
            </div>
          </div>

          <!-- 2. Active Slide Sub-Header (Fase, Judul, Layout) -->
          <div v-if="form.kegiatan[activeSlideIndex]" class="px-5 pt-4 pb-2 border-b border-white/10 bg-slate-950/40">
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
              <div class="sm:col-span-3">
                <label class="block text-[11px] font-bold text-slate-400 mb-1">🏷️ Kategori / Fase</label>
                <input
                  v-model="form.kegiatan[activeSlideIndex].label"
                  list="fase-list"
                  placeholder="Misal: Konsep Utama"
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white min-h-[40px]"
                />
                <datalist id="fase-list">
                  <option v-for="f in FASE_LABELS" :key="f" :value="f" />
                </datalist>
              </div>

              <div class="sm:col-span-5">
                <label class="block text-[11px] font-bold text-slate-400 mb-1">✏️ Judul Sub-Materi / Slide</label>
                <input
                  v-model="form.kegiatan[activeSlideIndex].judul"
                  placeholder="Misal: Penjelasan Rumus Percepatan"
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white font-bold min-h-[40px]"
                />
              </div>

              <div class="sm:col-span-4">
                <label class="block text-[11px] font-bold text-slate-400 mb-1">📐 Tata Letak (Layout)</label>
                <select
                  v-model="form.kegiatan[activeSlideIndex].layout"
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white font-medium min-h-[40px] cursor-pointer"
                >
                  <option v-for="lay in LAYOUT_OPTIONS" :key="lay.id" :value="lay.id" class="bg-slate-900 text-white py-1">
                    {{ lay.icon }} {{ lay.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- 3. Active Slide Editor & Preview Body -->
          <div v-if="form.kegiatan[activeSlideIndex]" class="p-5 space-y-4">

            <!-- Two-Column Workspace: Editor + Live Preview -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

              <!-- ============================================== -->
              <!-- KOLOM KIRI: EDITOR NASKAH + TOOLBAR INTERAKTIF -->
              <!-- ============================================== -->
              <div class="space-y-3">

                <!-- RICH TEXT & ALIGNMENT TOOLBAR -->
                <div class="bg-slate-950 border border-white/15 rounded-xl p-2 shadow-sm space-y-2">
                  <div class="flex items-center flex-wrap gap-1">

                    <!-- GAYA TEKS -->
                    <div class="flex items-center bg-white/5 rounded-lg p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('bold')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white font-black text-xs transition flex items-center justify-center active:scale-95"
                        title="Tebal (Bold: **teks**)"
                      >
                        B
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('italic')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white italic font-serif text-xs transition flex items-center justify-center active:scale-95"
                        title="Miring (Italic: *teks*)"
                      >
                        I
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('underline')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white underline text-xs transition flex items-center justify-center active:scale-95"
                        title="Garis Bawah (Underline)"
                      >
                        U
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('strikethrough')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white line-through text-xs transition flex items-center justify-center active:scale-95"
                        title="Coret (Strikethrough)"
                      >
                        S
                      </button>
                    </div>

                    <!-- HEADING -->
                    <div class="flex items-center bg-white/5 rounded-lg p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('h1')"
                        class="px-2 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white font-black text-xs transition flex items-center justify-center active:scale-95"
                        title="Judul Besar (Heading 1)"
                      >
                        H1
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('h2')"
                        class="px-2 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white font-bold text-xs transition flex items-center justify-center active:scale-95"
                        title="Sub-Judul (Heading 2)"
                      >
                        H2
                      </button>
                    </div>

                    <!-- PERATAAN TEKS (ALIGNMENT) -->
                    <div class="flex items-center bg-white/5 rounded-lg p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('align_left')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs transition flex items-center justify-center active:scale-95"
                        title="Rata Kiri"
                      >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M3 5h18v2H3V5zm0 4h12v2H3V9zm0 4h18v2H3v-2zm0 4h12v2H3v-2z"/>
                        </svg>
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('align_center')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs transition flex items-center justify-center active:scale-95"
                        title="Rata Tengah"
                      >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M3 5h18v2H3V5zm3 4h12v2H6V9zm-3 4h18v2H3v-2zm3 4h12v2H6v-2z"/>
                        </svg>
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('align_right')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs transition flex items-center justify-center active:scale-95"
                        title="Rata Kanan"
                      >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M3 5h18v2H3V5zm6 4h12v2H9V9zm-6 4h18v2H3v-2zm6 4h12v2H9v-2z"/>
                        </svg>
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('align_justify')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs transition flex items-center justify-center active:scale-95"
                        title="Rata Kanan Kiri (Justify)"
                      >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M3 5h18v2H3V5zm0 4h18v2H3V9zm0 4h18v2H3v-2zm0 4h18v2H3v-2z"/>
                        </svg>
                      </button>
                    </div>

                    <!-- DAFTAR & LIST -->
                    <div class="flex items-center bg-white/5 rounded-lg p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('bullet_list')"
                        class="px-2 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
                        title="List Simbol (Bullet •)"
                      >
                        •
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('numbered_list')"
                        class="px-2 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
                        title="List Angka (1, 2, 3)"
                      >
                        1.
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('alpha_list')"
                        class="px-2 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
                        title="List Huruf (a, b, c)"
                      >
                        a.
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('checklist')"
                        class="px-2 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
                        title="Checklist / Kotak Centang"
                      >
                        ☑
                      </button>
                    </div>

                    <!-- ELEMEN TAMBAHAN -->
                    <div class="flex items-center bg-white/5 rounded-lg p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('quote')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
                        title="Kutipan / Blok Catatan"
                      >
                        ”
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('table')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
                        title="Sisipkan Tabel"
                      >
                        ⊞
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('divider')"
                        class="w-8 h-8 rounded hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
                        title="Garis Pemisah"
                      >
                        ―
                      </button>
                    </div>

                    <!-- SISIPKAN RUMUS ILMIAH (TOGGLE ON-DEMAND) -->
                    <button
                      id="formula-toggle-btn"
                      type="button"
                      @click="isFormulaDrawerOpen = !isFormulaDrawerOpen"
                      class="px-3 h-8 rounded-lg border text-xs font-bold transition flex items-center gap-1.5 active:scale-95 shadow-sm"
                      :class="isFormulaDrawerOpen
                        ? 'bg-cyan-500 text-white border-cyan-400'
                        : 'bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border-cyan-500/30'"
                      title="Buka / Tutup Pilihan Rumus Ilmiah"
                    >
                      <span>📐</span>
                      <span>Rumus</span>
                    </button>

                    <!-- SISIPKAN GAMBAR SLIDE -->
                    <input
                      type="file"
                      :id="'slide-img-' + activeSlideIndex"
                      @change="onSlideImageSelected(activeSlideIndex, $event)"
                      accept="image/*"
                      class="hidden"
                    />
                    <label
                      :for="'slide-img-' + activeSlideIndex"
                      class="px-3 h-8 rounded-lg bg-teal-500/10 hover:bg-teal-500/20 border border-teal-500/30 text-teal-300 text-xs font-bold cursor-pointer transition flex items-center gap-1.5 active:scale-95 shadow-sm"
                      title="Tambahkan foto atau ilustrasi pada slide ini"
                    >
                      <span>📷</span>
                      <span>{{ kegiatanImagePreviews[activeSlideIndex] ? 'Ganti Foto' : 'Foto' }}</span>
                    </label>

                  </div>

                  <!-- POPUP / DRAWER RUMUS ILMIAH (HANYA MUNCUL JIKA DIBUKA) -->
                  <div
                    v-if="isFormulaDrawerOpen"
                    id="formula-drawer-box"
                    class="p-3 bg-slate-900 border border-cyan-500/40 rounded-xl space-y-2.5 shadow-2xl animate-in fade-in duration-150"
                  >
                    <div class="flex items-center justify-between">
                      <div class="inline-flex rounded-lg bg-slate-950 p-1 border border-white/10">
                        <button
                          type="button"
                          @click="activeFormulaCategory = 'matematika'"
                          class="px-2.5 py-1 rounded text-xs font-bold transition"
                          :class="activeFormulaCategory === 'matematika' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                        >
                          Matematika
                        </button>
                        <button
                          type="button"
                          @click="activeFormulaCategory = 'fisika'"
                          class="px-2.5 py-1 rounded text-xs font-bold transition"
                          :class="activeFormulaCategory === 'fisika' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                        >
                          Fisika
                        </button>
                        <button
                          type="button"
                          @click="activeFormulaCategory = 'kimia'"
                          class="px-2.5 py-1 rounded text-xs font-bold transition"
                          :class="activeFormulaCategory === 'kimia' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                        >
                          Kimia
                        </button>
                      </div>

                      <button
                        type="button"
                        @click="isFormulaDrawerOpen = false"
                        class="text-xs text-slate-400 hover:text-white px-2 py-1 rounded bg-white/5 hover:bg-white/10"
                      >
                        ✕ Tutup
                      </button>
                    </div>

                    <div class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto pr-1">
                      <button
                        v-for="item in SCIENCE_PRESETS[activeFormulaCategory]"
                        :key="item.label"
                        type="button"
                        @click="applyFormat('formula', item.latex)"
                        class="px-2.5 py-1 rounded-md bg-slate-950 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-400/40 text-xs text-cyan-200 transition active:scale-95 shadow-sm flex items-center gap-1.5"
                        :title="item.label"
                      >
                        <span class="text-slate-400 text-[11px]">{{ item.label }}:</span>
                        <span class="text-cyan-300 font-mono font-bold">{{ item.symbol }}</span>
                      </button>
                    </div>
                  </div>

                </div>

                <!-- Preview Gambar Slide Mini (Jika Ada) -->
                <div
                  v-if="kegiatanImagePreviews[activeSlideIndex]"
                  class="p-2.5 rounded-xl bg-slate-950/80 border border-cyan-500/30 flex items-center justify-between gap-3 shadow-md"
                >
                  <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-12 h-12 rounded-lg overflow-hidden border border-white/10 bg-black shrink-0">
                      <img :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-bold text-slate-200 truncate">Foto Slide Terlampir</p>
                      <p class="text-[11px] text-slate-400">Layout: {{ form.kegiatan[activeSlideIndex].layout }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <label
                      :for="'slide-img-' + activeSlideIndex"
                      class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 cursor-pointer border border-white/10"
                    >
                      Ganti
                    </label>
                    <button
                      type="button"
                      @click="removeSlideImage(activeSlideIndex)"
                      class="px-2 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-300 text-xs font-bold transition"
                    >
                      Hapus
                    </button>
                  </div>
                </div>

                <!-- Textarea Editor Naskah -->
                <AutoTextarea
                  ref="slideTextareaRef"
                  v-model="form.kegiatan[activeSlideIndex].konten"
                  placeholder="Ketik penjelasan materi di sini. Gunakan tombol toolbar di atas untuk menebalkan, membuat daftar poin, perataan teks, atau menyisipkan rumus..."
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-500/20 rounded-2xl p-4 text-xs font-sans text-white leading-relaxed placeholder-slate-600 min-h-[280px]"
                />

              </div>

              <!-- ============================================== -->
              <!-- KOLOM KANAN: REAL-TIME PRATINJAU TAMPILAN SISWA -->
              <!-- ============================================== -->
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-black text-cyan-400 uppercase tracking-wider flex items-center gap-1.5">
                    <span>👁️</span> Pratinjau Tampilan Siswa
                  </span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-300 border border-cyan-500/20 font-bold">
                    Tema: {{ form.theme }}
                  </span>
                </div>

                <!-- Realistic Slide Container -->
                <div
                  class="rounded-2xl border p-5 shadow-2xl min-h-[340px] flex flex-col justify-between transition-all"
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
                      <div class="sm:col-span-5 rounded-xl overflow-hidden bg-black/40 border border-white/10 h-32 flex items-center justify-center">
                        <img v-if="kegiatanImagePreviews[activeSlideIndex]" :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                        <span v-else class="text-[10px] text-slate-500">🖼️ Foto Kiri</span>
                      </div>
                      <div class="sm:col-span-7">
                        <div
                          class="text-xs leading-relaxed slide-markdown-content"
                          :class="currentThemeConfig.prose"
                          v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Ketik naskah di sebelah kiri...*')"
                        ></div>
                      </div>
                    </div>

                    <div v-else-if="form.kegiatan[activeSlideIndex]?.layout === 'image_right'" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                      <div class="sm:col-span-7">
                        <div
                          class="text-xs leading-relaxed slide-markdown-content"
                          :class="currentThemeConfig.prose"
                          v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Ketik naskah di sebelah kiri...*')"
                        ></div>
                      </div>
                      <div class="sm:col-span-5 rounded-xl overflow-hidden bg-black/40 border border-white/10 h-32 flex items-center justify-center">
                        <img v-if="kegiatanImagePreviews[activeSlideIndex]" :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                        <span v-else class="text-[10px] text-slate-500">🖼️ Foto Kanan</span>
                      </div>
                    </div>

                    <div v-else-if="form.kegiatan[activeSlideIndex]?.layout === 'highlight'" class="p-3.5 rounded-xl border border-amber-400/40 bg-amber-500/10">
                      <div
                        class="text-xs leading-relaxed slide-markdown-content"
                        :class="currentThemeConfig.prose"
                        v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Ketik naskah di sebelah kiri...*')"
                      ></div>
                    </div>

                    <div v-else>
                      <div v-if="kegiatanImagePreviews[activeSlideIndex]" class="mb-3 rounded-xl overflow-hidden bg-black/40 border border-white/10 max-h-40">
                        <img :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                      </div>
                      <div
                        class="text-xs leading-relaxed slide-markdown-content"
                        :class="currentThemeConfig.prose"
                        v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Ketik naskah di sebelah kiri...*')"
                      ></div>
                    </div>
                  </div>

                  <!-- Mockup Footer -->
                  <div class="pt-3 mt-4 border-t flex items-center justify-between text-[10px] opacity-70" :class="currentThemeConfig.footer">
                    <span>SMKS Muhammadiyah 2 Genteng</span>
                    <span>Modul Interaktif Siswa</span>
                  </div>
                </div>
              </div>

            </div>

          </div>
        </div>

      </section>

      <!-- Bottom Floating Simpan Action -->
      <div class="flex items-center justify-between pt-4 border-t border-white/10">
        <button
          type="button"
          @click="goBack"
          class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/15 text-xs font-bold text-slate-300 hover:text-white transition active:scale-95 min-h-[44px]"
        >
          ← Kembali ke Daftar Materi
        </button>

        <button
          type="button"
          @click="submit"
          :disabled="saving"
          class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 via-teal-500 to-indigo-600 text-white font-black text-xs shadow-xl shadow-cyan-500/25 hover:scale-105 active:scale-95 transition disabled:opacity-50 min-h-[44px] flex items-center gap-2"
        >
          <span v-if="saving" class="animate-spin">⏳</span>
          <span v-else>💾</span>
          <span>{{ saving ? 'Menyimpan Materi...' : 'Simpan Materi' }}</span>
        </button>
      </div>

    </main>
  </div>
</template>

<style scoped>
/* Scoped adjustments for Slide Preview Markdown */
:deep(.slide-markdown-content u) {
  text-decoration: underline;
  text-underline-offset: 3px;
}

:deep(.slide-markdown-content s),
:deep(.slide-markdown-content del) {
  text-decoration: line-through;
  opacity: 0.75;
}

:deep(.slide-markdown-content ol) {
  padding-left: 1.5rem;
  margin: 0.75rem 0;
  list-style-type: decimal;
}

:deep(.slide-markdown-content ol[type="a"]) {
  list-style-type: lower-alpha;
}

:deep(.slide-markdown-content ol[type="A"]) {
  list-style-type: upper-alpha;
}

:deep(.slide-markdown-content ul) {
  padding-left: 1.25rem;
  margin: 0.75rem 0;
  list-style-type: disc;
}

:deep(.slide-markdown-content li) {
  margin-bottom: 0.35rem;
  line-height: 1.5;
}

:deep(.slide-markdown-content table) {
  width: 100%;
  border-collapse: collapse;
  margin: 0.75rem 0;
  font-size: 0.85em;
}

:deep(.slide-markdown-content th),
:deep(.slide-markdown-content td) {
  border: 1px solid rgba(255, 255, 255, 0.15);
  padding: 0.4rem 0.6rem;
  text-align: left;
}

:deep(.slide-markdown-content th) {
  background: rgba(255, 255, 255, 0.08);
  font-weight: 800;
}

:deep(.slide-markdown-content blockquote) {
  border-left: 3px solid #38bdf8;
  padding: 0.4rem 0.8rem;
  margin: 0.75rem 0;
  background: rgba(56, 189, 248, 0.08);
  border-radius: 0 0.5rem 0.5rem 0;
  font-style: italic;
}
</style>
