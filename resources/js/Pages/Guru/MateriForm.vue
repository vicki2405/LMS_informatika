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

// Canva-style Presentation Themes for Slider PPT
const THEME_OPTIONS = [
  { id: 'dark', label: 'Cyber Neon', desc: 'Obsidian & Cyan', dotBg: 'bg-[#090d16]', dotAccent: 'from-cyan-400 to-teal-300' },
  { id: 'light', label: 'Canva Studio', desc: 'Clean White & Indigo', dotBg: 'bg-white', dotAccent: 'from-indigo-600 to-purple-500' },
  { id: 'aurora', label: 'Sunset Aurora', desc: 'Violet & Coral Sunset', dotBg: 'bg-[#180927]', dotAccent: 'from-rose-500 to-amber-400' },
  { id: 'navy', label: 'Royal Sapphire', desc: 'Deep Navy & Ice Blue', dotBg: 'bg-[#06122c]', dotAccent: 'from-sky-400 to-blue-500' },
  { id: 'emerald', label: 'Emerald Forest', desc: 'Jade Green & Mint', dotBg: 'bg-[#031d14]', dotAccent: 'from-emerald-400 to-teal-300' },
  { id: 'monochrome', label: 'Monochrome Glass', desc: 'Frosted Slate & Silver', dotBg: 'bg-[#131b2e]', dotAccent: 'from-slate-200 to-slate-400' },
];

const currentThemeConfig = computed(() => {
  switch (form.theme) {
    case 'light':
      return {
        wrapper: 'bg-gradient-to-b from-white via-slate-50 to-white text-slate-900 border-slate-200 shadow-2xl shadow-indigo-100/70',
        badge: 'bg-indigo-50 text-indigo-700 border-indigo-200 font-black',
        title: 'text-slate-950 font-black',
        prose: 'prose prose-slate max-w-none text-xs leading-relaxed text-slate-800',
        footer: 'border-slate-200 text-slate-500',
        glow: 'rgba(99, 102, 241, 0.15)',
      };
    case 'aurora':
      return {
        wrapper: 'bg-gradient-to-b from-[#1c0c2e] via-[#140824] to-[#0e051a] text-pink-50 border-purple-500/30 shadow-2xl shadow-purple-950/60',
        badge: 'bg-rose-500/15 text-rose-300 border-rose-400/40 font-black',
        title: 'text-white font-black',
        prose: 'prose prose-invert max-w-none text-xs leading-relaxed text-pink-100',
        footer: 'border-purple-500/20 text-rose-300',
        glow: 'rgba(244, 63, 94, 0.22)',
      };
    case 'navy':
      return {
        wrapper: 'bg-gradient-to-b from-[#081836] via-[#06142e] to-[#040f24] text-blue-50 border-sky-400/30 shadow-2xl shadow-blue-950/70',
        badge: 'bg-sky-500/15 text-sky-300 border-sky-400/40 font-black',
        title: 'text-white font-black',
        prose: 'prose prose-invert max-w-none text-xs leading-relaxed text-blue-100',
        footer: 'border-blue-500/20 text-sky-400',
        glow: 'rgba(56, 189, 248, 0.22)',
      };
    case 'emerald':
      return {
        wrapper: 'bg-gradient-to-b from-[#052e22] via-[#04241b] to-[#021912] text-emerald-50 border-emerald-400/30 shadow-2xl shadow-emerald-950/70',
        badge: 'bg-emerald-500/15 text-emerald-300 border-emerald-400/40 font-black',
        title: 'text-white font-black',
        prose: 'prose prose-invert max-w-none text-xs leading-relaxed text-emerald-100',
        footer: 'border-emerald-500/20 text-emerald-400',
        glow: 'rgba(16, 185, 129, 0.22)',
      };
    case 'monochrome':
      return {
        wrapper: 'bg-gradient-to-b from-slate-900/95 via-slate-900/90 to-slate-950/95 text-slate-100 border-white/20 shadow-2xl shadow-black/80 backdrop-blur-xl',
        badge: 'bg-white/10 text-slate-200 border-white/20 font-black',
        title: 'text-white font-black',
        prose: 'prose prose-invert max-w-none text-xs leading-relaxed text-slate-200',
        footer: 'border-white/10 text-slate-400',
        glow: 'rgba(255, 255, 255, 0.1)',
      };
    case 'dark':
    default:
      return {
        wrapper: 'bg-gradient-to-b from-slate-900/95 via-slate-900/90 to-slate-950/95 text-slate-100 border-cyan-500/30 shadow-2xl shadow-cyan-950/40',
        badge: 'bg-cyan-500/15 text-cyan-300 border-cyan-400/40 font-black',
        title: 'text-white font-black',
        prose: 'prose prose-invert max-w-none text-xs leading-relaxed text-slate-200',
        footer: 'border-white/10 text-cyan-400',
        glow: 'rgba(6, 182, 212, 0.22)',
      };
  }
});

const currentThemeLabel = computed(() => {
  return THEME_OPTIONS.find(t => t.id === form.theme)?.label || 'Cyber Neon';
});

const LAYOUT_OPTIONS = [
  { id: 'standard', label: 'Standar (Teks Penuh)', icon: '📋' },
  { id: 'image_left', label: 'Gambar Kiri + Teks', icon: '⬅️' },
  { id: 'image_right', label: 'Teks + Gambar Kanan', icon: '➡️' },
  { id: 'highlight', label: 'Kotak Sorotan / Rumus', icon: '💡' },
];

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
  kelas_id: props.materiProp ? (props.materiProp.kelasId ?? props.materiProp.kelas_id ?? 'all') : 'all',
  kegiatan: [],
});

const mobileViewMode = ref('editor'); // 'editor' | 'preview' untuk mode mobile

// 1. Daftar Mata Pelajaran yang 100% disinkronkan dari Jadwal Mengajar Guru (tab=jadwal)
const availableMapelFromJadwal = computed(() => {
  const jadwals = props.jadwalListProp || [];
  
  // Ambil seluruh nama mata pelajaran unik yang aktif diset guru di tab jadwal
  const uniqueMapelNamesInJadwal = Array.from(new Set(
    jadwals.map(j => (j.mataPelajaran || '').trim()).filter(Boolean)
  ));

  // Jika guru memiliki jadwal mengajar di tab=jadwal
  if (uniqueMapelNamesInJadwal.length > 0) {
    const list = [];
    uniqueMapelNamesInJadwal.forEach((mapelName, idx) => {
      const mLower = mapelName.toLowerCase();
      const masterObj = (props.mapelListProp || []).find(m => {
        const nLower = (m.nama || '').trim().toLowerCase();
        const kLower = (m.kode || '').trim().toLowerCase();
        return nLower === mLower || kLower === mLower || nLower.includes(mLower) || mLower.includes(nLower);
      });

      const matchingJadwals = jadwals.filter(j => (j.mataPelajaran || '').trim().toLowerCase() === mLower);
      const uniqueKelasCount = new Set(matchingJadwals.map(j => j.kelasId).filter(Boolean)).size;

      list.push({
        id: masterObj ? masterObj.id : (props.mapelListProp[idx]?.id || mapelName),
        rawName: mapelName,
        nama: masterObj?.nama || mapelName,
        kode: masterObj?.kode || mapelName.substring(0, 4).toUpperCase(),
        totalSesi: matchingJadwals.length,
        totalKelas: uniqueKelasCount,
        isFromJadwal: true,
      });
    });

    return list.sort((a, b) => a.nama.localeCompare(b.nama));
  }

  // Fallback jika guru belum menginput sesi di tab jadwal
  return (props.mapelListProp || []).map(m => ({
    id: m.id,
    rawName: m.nama,
    nama: m.nama,
    kode: m.kode || 'MPL',
    totalSesi: 0,
    totalKelas: 0,
    isFromJadwal: false,
  }));
});

// Objek Mata Pelajaran yang aktif dipilih
const selectedMapelObj = computed(() => {
  return availableMapelFromJadwal.value.find(m => String(m.id) === String(form.mapelId) || m.rawName === String(form.mapelId))
      || availableMapelFromJadwal.value[0]
      || null;
});

// Inisialisasi mapelId jika belum terpilih
if (!form.mapelId && availableMapelFromJadwal.value.length > 0) {
  form.mapelId = availableMapelFromJadwal.value[0].id;
} else if (form.mapelId && availableMapelFromJadwal.value.length > 0) {
  const exists = availableMapelFromJadwal.value.some(m => String(m.id) === String(form.mapelId) || m.rawName === String(form.mapelId));
  if (!exists) {
    form.mapelId = availableMapelFromJadwal.value[0].id;
  }
}

// 2. Data Sesi Jadwal Mengajar Guru dari tab=jadwal yang sesuai dengan Mata Pelajaran terpilih
const schedulesForSelectedMapel = computed(() => {
  const currentMapel = selectedMapelObj.value;
  const jadwals = props.jadwalListProp || [];
  if (!currentMapel || jadwals.length === 0) return [];

  const mapelNamaLower = (currentMapel.nama || currentMapel.rawName || '').trim().toLowerCase();
  const mapelKodeLower = (currentMapel.kode || '').trim().toLowerCase();

  return jadwals.filter(j => {
    if (!j.mataPelajaran) return false;
    const jMapelLower = j.mataPelajaran.trim().toLowerCase();
    return jMapelLower === mapelNamaLower ||
           jMapelLower === mapelKodeLower ||
           mapelNamaLower.includes(jMapelLower) ||
           jMapelLower.includes(mapelKodeLower);
  });
});

// 3. Daftar Kelas yang disinkronkan secara otomatis dan ketat dengan Jadwal Mengajar Guru
const availableKelasFromJadwal = computed(() => {
  const schedules = schedulesForSelectedMapel.value;
  const map = new Map();

  schedules.forEach(j => {
    if (!j.kelasId) return;
    const kId = Number(j.kelasId);
    const rawName = String(j.kelas || j.namaKelas || `Kelas #${kId}`).trim();
    const cleanName = rawName.replace(/^kelas\s+/i, '');

    if (!map.has(kId)) {
      map.set(kId, {
        id: kId,
        nama: cleanName,
        tingkat: j.tingkat || 'X',
        jurusan: j.jurusan || '',
        sessionsByDay: { [j.hari || 'Jadwal']: j.jamKe ? [j.jamKe] : [] },
        isScheduled: true,
      });
    } else {
      const entry = map.get(kId);
      const h = j.hari || 'Jadwal';
      if (!entry.sessionsByDay[h]) {
        entry.sessionsByDay[h] = [];
      }
      if (j.jamKe && !entry.sessionsByDay[h].includes(j.jamKe)) {
        entry.sessionsByDay[h].push(j.jamKe);
      }
    }
  });

  // Jika guru memiliki jadwal mengajar di tab=jadwal
  if (map.size > 0) {
    return Array.from(map.values()).map(k => {
      // Format hari & jam menjadi ringkas dan padat, contoh: "Sabtu (Jam 1, 2, 3)"
      const daySummaries = Object.entries(k.sessionsByDay).map(([day, periods]) => {
        const sortedPeriods = periods.filter(Boolean).sort((a, b) => Number(a) - Number(b));
        if (sortedPeriods.length === 0) return day;
        return `${day} (Jam ${sortedPeriods.join(', ')})`;
      });

      return {
        ...k,
        jadwalInfo: daySummaries.join(' • '),
      };
    }).sort((a, b) => (a.nama || '').localeCompare(b.nama || '', undefined, { numeric: true }));
  }

  // Fallback pengampuan kelas guru jika jadwal belum diinput admin
  const fallbackList = (props.kelasListProp && props.kelasListProp.length > 0)
    ? props.kelasListProp
    : (props.masterKelasListProp || []);

  return fallbackList.map(k => {
    const rawName = String(k.nama || k.namaKelas || `Kelas #${k.id}`).trim();
    const cleanName = rawName.replace(/^kelas\s+/i, '');
    return {
      id: k.id,
      nama: cleanName,
      tingkat: k.tingkat || 'X',
      jurusan: k.jurusan || '',
      jadwalInfo: 'Penugasan Guru',
      isScheduled: false,
    };
  }).sort((a, b) => (a.nama || '').localeCompare(b.nama || '', undefined, { numeric: true }));
});

// Otomatis sinkronkan kelas terpilih jika berganti mata pelajaran agar tidak salah kirim materi
watch(() => form.mapelId, () => {
  const classes = availableKelasFromJadwal.value;
  if (form.kelas_id === 'all') {
    return; // Tetap 'all' jika guru memilih semua kelas pada mapel tersebut
  }
  const exists = classes.some(k => k.id === form.kelas_id);
  if (!exists) {
    form.kelas_id = classes.length > 0 ? 'all' : null;
  }
}, { immediate: true });

// Validasi jika saat load pertama kali kelas_id belum terpilih
if (!form.kelas_id && availableKelasFromJadwal.value.length > 0) {
  form.kelas_id = 'all';
}

// 4. Filter TP sesuai Mapel dan Semester yang dipilih
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
}, { immediate: true });

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

// Slides setup
const kegiatanFiles = ref([]);
const kegiatanImagePreviews = ref([]);
const selectedFile = ref(null);
const saving = ref(false);
const activeSlideIndex = ref(0);
const activeFormulaCategory = ref('matematika');
const isFormulaDrawerOpen = ref(false);
const slideTextareaRef = ref(null);

function createDefaultSlide(urutan = 1) {
  return {
    label: '',
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
    label: k.label || '',
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
    createDefaultSlide(1),
    createDefaultSlide(2),
    createDefaultSlide(3),
  ];
  kegiatanFiles.value = new Array(form.kegiatan.length).fill(null);
  kegiatanImagePreviews.value = new Array(form.kegiatan.length).fill('');
}

function addSlide() {
  const urutan = form.kegiatan.length + 1;
  form.kegiatan.push(createDefaultSlide(urutan));
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
    case 'font_size': {
      const size = payload || '16px';
      replacement = `<span style="font-size: ${size}; line-height: 1.3;">${selectedText || 'Teks'}</span>`;
      cursorOffset = selectedText ? replacement.length : (`<span style="font-size: ${size}; line-height: 1.3;">`).length;
      break;
    }
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

function onFontSizeChange(e) {
  const size = e?.target?.value;
  if (!size) return;
  applyFormat('font_size', size);
  if (e?.target) {
    e.target.value = '';
  }
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

// Wizard step: 1 = Target & Format Setup, 2 = Full Studio Pembuatan Konten
const currentStep = ref(props.materiProp ? 2 : 1);

const selectedKelasObj = computed(() => {
  if (form.kelas_id === 'all') {
    const total = availableKelasFromJadwal.value.length;
    const names = availableKelasFromJadwal.value.map(k => k.nama).join(', ');
    return {
      id: 'all',
      nama: 'Semua Kelas',
      jadwalInfo: total > 0 ? `Menjangkau: ${names}` : 'Sesuai jadwal pengampuan guru',
      isScheduled: true,
    };
  }
  return availableKelasFromJadwal.value.find(k => k.id === form.kelas_id)
      || (props.kelasListProp || []).find(k => k.id === form.kelas_id)
      || null;
});

function nextToStep2() {
  if (!form.mapelId) {
    showToast('Pilih mata pelajaran terlebih dahulu!', 'warning');
    return;
  }
  if (!form.kelas_id) {
    showToast('Pilih kelas tujuan materi sesuai jadwal mengajar!', 'warning');
    return;
  }
  if (!form.tpId) {
    showToast('Pilih sasaran Tujuan Pembelajaran (TP)!', 'warning');
    return;
  }
  if (!form.judul.trim()) {
    showToast('Judul materi pembelajaran wajib diisi!', 'warning');
    return;
  }
  currentStep.value = 2;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backToStep1() {
  currentStep.value = 1;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onMainFileSelected(e) {
  selectedFile.value = e.target.files[0] || null;
}

function goBack() {
  router.visit(route('guru.dashboard', { tab: 'materi' }));
}

function submit() {
  if (!form.mapelId) {
    showToast('Pilih mata pelajaran terlebih dahulu!', 'warning');
    return;
  }
  if (!form.kelas_id) {
    showToast('Pilih kelas tujuan materi sesuai jadwal mengajar!', 'warning');
    return;
  }
  if (!form.tpId) {
    showToast('Pilih sasaran Tujuan Pembelajaran (TP)!', 'warning');
    return;
  }
  if (!form.judul.trim()) {
    showToast('Judul materi pembelajaran wajib diisi!', 'warning');
    return;
  }

  saving.value = true;

  const fd = new FormData();
  fd.append('id', props.materiProp?.id || '');
  fd.append('tp_id', form.tpId || '');
  fd.append('kelas_id', form.kelas_id === 'all' ? '' : (form.kelas_id || ''));
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
    label: k.label || '',
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
      showToast('Materi pembelajaran berhasil disimpan!', 'success');
    },
    onError: () => {
      saving.value = false;
      showToast('Gagal menyimpan materi. Periksa kembali formulir.', 'error');
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
    <!-- Top Action & Stepper Bar -->
    <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-white/10 shadow-2xl">
      <div class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
        <!-- Brand & Back Button -->
        <div class="flex items-center gap-3 min-w-0">
          <button
            @click="goBack"
            type="button"
            class="px-3 py-2 shrink-0 rounded-xl bg-white/5 hover:bg-white/10 active:bg-white/15 border border-white/15 hover:border-cyan-500/40 text-slate-200 hover:text-white text-xs font-bold flex items-center gap-2 transition active:scale-95 shadow-sm min-h-[40px]"
            title="Kembali ke Daftar Materi"
          >
            <svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span class="hidden sm:inline font-bold">Daftar Materi</span>
          </button>

          <div class="min-w-0">
            <h1 class="text-sm sm:text-base font-black text-white truncate flex items-center gap-2">
              <span>{{ isEditing ? 'Edit Materi Pembelajaran' : 'Buat Materi Baru' }}</span>
              <span class="hidden md:inline-flex text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-300 border border-cyan-500/25 font-bold">
                SMKS Muhammadiyah 2 Genteng
              </span>
            </h1>
          </div>
        </div>

        <!-- Stepper Indicators (Langkah 1 & 2) -->
        <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-white/15">
          <button
            type="button"
            @click="currentStep = 1"
            class="px-3 sm:px-4 py-1.5 text-xs font-bold rounded-lg transition flex items-center gap-1.5 min-h-[36px]"
            :class="currentStep === 1
              ? 'bg-cyan-500 text-white shadow-md shadow-cyan-500/30'
              : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'"
          >
            <span class="w-5 h-5 rounded-full bg-black/20 flex items-center justify-center text-[10px] font-black">1</span>
            <span>Target & Format</span>
          </button>

          <button
            type="button"
            @click="nextToStep2"
            class="px-3 sm:px-4 py-1.5 text-xs font-bold rounded-lg transition flex items-center gap-1.5 min-h-[36px]"
            :class="currentStep === 2
              ? 'bg-gradient-to-r from-cyan-500 to-indigo-600 text-white shadow-md shadow-cyan-500/30'
              : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'"
          >
            <span class="w-5 h-5 rounded-full bg-black/20 flex items-center justify-center text-[10px] font-black">2</span>
            <span>Studio Materi</span>
          </button>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5 shrink-0">
          <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold select-none transition min-h-[40px]">
            <input type="checkbox" v-model="form.isPublished" class="rounded bg-slate-800 border-white/20 text-cyan-500 focus:ring-0 cursor-pointer">
            <span :class="form.isPublished ? 'text-emerald-400 font-bold' : 'text-slate-400'">
              {{ form.isPublished ? '🟢 Diterbitkan' : '⚪ Draft' }}
            </span>
          </label>

          <button
            v-if="currentStep === 1"
            type="button"
            @click="nextToStep2"
            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-white font-black text-xs shadow-lg shadow-cyan-500/25 active:scale-95 transition min-h-[40px]"
          >
            <span>Lanjut ke Studio</span>
            <span>➡️</span>
          </button>

          <button
            v-else
            type="button"
            @click="submit"
            :disabled="saving"
            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-500 via-teal-500 to-indigo-600 text-white font-black text-xs shadow-lg shadow-cyan-500/25 hover:scale-105 active:scale-95 transition disabled:opacity-50 min-h-[40px]"
          >
            <span v-if="saving" class="animate-spin">⏳</span>
            <span v-else>💾</span>
            <span>{{ saving ? 'Menyimpan...' : 'Simpan Materi' }}</span>
          </button>
        </div>
      </div>
    </header>

    <!-- ========================================================================= -->
    <!-- TAMPILAN 1: PENGATURAN TARGET & FORMAT MATERI (WIZARD STEP 1)             -->
    <!-- ========================================================================= -->
    <main v-if="currentStep === 1" class="max-w-4xl mx-auto px-4 sm:px-6 pt-6 pb-24 space-y-6">

      <!-- Kartu 1: Target Pembelajaran & Kelas Mengajar -->
      <section class="bg-slate-900/90 border border-white/15 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl relative z-30">
        <div class="flex items-center gap-3 pb-5 border-b border-white/10 mb-6">
          <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center text-lg font-black shrink-0">
            1
          </div>
          <div class="min-w-0">
            <h2 class="text-sm sm:text-base font-black text-white flex items-center gap-2">
              Target Pembelajaran & Jadwal Kelas
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-300 border border-cyan-500/30 font-bold">
                Sinkron Tab Jadwal
              </span>
            </h2>
            <p class="text-xs text-slate-400">
              Pilihan kelas dan mata pelajaran disinkronkan langsung dari jadwal mengajar guru untuk mencegah materi salah kelas.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <!-- 1. Mata Pelajaran (100% dari Jadwal Mengajar) -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center justify-between">
              <span>Mata Pelajaran <span class="text-rose-400">*</span></span>
              <span class="text-cyan-400 text-[10px] font-bold">✨ Dari Jadwal</span>
            </label>
            <select
              v-model="form.mapelId"
              class="w-full bg-slate-950 border border-cyan-500/40 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-500/30 rounded-2xl px-4 py-3 text-xs sm:text-sm text-cyan-300 font-bold min-h-[46px] cursor-pointer outline-none shadow-inner"
            >
              <option v-if="availableMapelFromJadwal.length === 0" :value="''" disabled class="bg-slate-900 text-slate-400 py-1">
                -- Belum ada jadwal mengajar di tab=jadwal --
              </option>
              <option v-for="mp in availableMapelFromJadwal" :key="mp.id" :value="mp.id" class="bg-slate-900 text-white py-2">
                📚 {{ mp.nama }} ({{ mp.kode }})
              </option>
            </select>
            <p class="text-[11px] text-cyan-400/90 mt-1.5 flex items-center gap-1 font-medium truncate">
              <span v-if="selectedMapelObj && selectedMapelObj.isFromJadwal">✓ {{ selectedMapelObj.totalKelas }} kelas aktif di tab jadwal</span>
              <span v-else-if="availableMapelFromJadwal.some(m => m.isFromJadwal)">✓ {{ availableMapelFromJadwal.length }} mapel ditemukan di jadwal</span>
              <span v-else class="text-amber-400">⚠️ Menggunakan mapel profil guru</span>
            </p>
          </div>

          <!-- 2. Semester -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center justify-between">
              <span>Semester <span class="text-rose-400">*</span></span>
              <span class="text-slate-400 text-[10px]">Tahun Ajaran Aktif</span>
            </label>
            <select
              v-model="form.semester"
              class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-500/30 rounded-2xl px-4 py-3 text-xs sm:text-sm text-white font-semibold min-h-[46px] cursor-pointer outline-none shadow-inner"
            >
              <option value="Ganjil" class="bg-slate-900 text-white py-2">📅 Semester Ganjil (1)</option>
              <option value="Genap" class="bg-slate-900 text-white py-2">📅 Semester Genap (2)</option>
            </select>
            <p class="text-[11px] text-slate-500 mt-1.5">Membantu pengelompokan rapor dan arsip</p>
          </div>

          <!-- 3. Target Kelas (Tersinkron dengan tab=jadwal — Clean & Compact) -->
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center justify-between">
              <span>Kelas Tujuan <span class="text-rose-400">*</span></span>
              <span class="text-emerald-400 text-[10px] font-bold">✨ Dari Jadwal Guru</span>
            </label>
            <select
              v-model="form.kelas_id"
              class="w-full bg-slate-950 border border-emerald-500/40 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-500/30 rounded-2xl px-4 py-3 text-xs sm:text-sm text-emerald-300 font-bold min-h-[46px] cursor-pointer outline-none shadow-inner"
            >
              <option v-if="availableKelasFromJadwal.length === 0" :value="null" disabled class="bg-slate-900 text-slate-400 py-1">
                -- Belum ada jadwal kelas pada mapel ini --
              </option>
              
              <!-- OPSI: SEMUA KELAS PADA MAPEL TERPILIH -->
              <option
                v-if="availableKelasFromJadwal.length > 0"
                value="all"
                class="bg-slate-900 text-cyan-300 font-bold py-2"
              >
                Semua Kelas
              </option>

              <!-- OPSI: KELAS TUNGGAL / INDIVIDUAL -->
              <option
                v-for="k in availableKelasFromJadwal"
                :key="k.id"
                :value="k.id"
                class="bg-slate-900 text-white py-2"
              >
                {{ k.nama }}
              </option>
            </select>
            <p class="text-[11px] text-emerald-400/90 mt-1.5 flex items-center gap-1 font-medium truncate">
              <span v-if="form.kelas_id === 'all'" class="text-cyan-300 font-medium truncate">
                Menjangkau {{ availableKelasFromJadwal.length }} kelas: {{ availableKelasFromJadwal.map(k => k.nama).join(', ') || 'Belum ada kelas' }}
              </span>
              <span v-else-if="selectedKelasObj?.jadwalInfo">🗓️ Sesi: {{ selectedKelasObj.jadwalInfo }}</span>
              <span v-else-if="schedulesForSelectedMapel.length > 0">✓ {{ schedulesForSelectedMapel.length }} sesi mengajar aktif</span>
              <span v-else class="text-amber-400">⚠️ Tidak ada sesi mengajar di tab jadwal</span>
            </p>
          </div>

          <!-- 4. Sasaran TP (Tujuan Pembelajaran) — Dropdown Tanpa Clipping dengan High Stacking -->
          <div id="tp-selector-container" class="relative z-40">
            <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center justify-between">
              <span>Sasaran TP (Tujuan Pembelajaran) <span class="text-rose-400">*</span></span>
              <span class="text-cyan-400 text-[10px] font-bold">{{ availableTpsForSelectedMapel.length }} Tersedia</span>
            </label>

            <!-- Trigger Button -->
            <div
              @click="isTpDropdownOpen = !isTpDropdownOpen"
              class="w-full bg-slate-950 border rounded-2xl px-4 py-3 text-xs sm:text-sm cursor-pointer flex items-center justify-between gap-2 min-h-[46px] transition select-none shadow-inner"
              :class="isTpDropdownOpen ? 'border-cyan-400 ring-2 ring-cyan-500/20' : 'border-white/15 hover:border-white/30'"
            >
              <span v-if="selectedTpObj" class="text-white font-medium truncate text-xs sm:text-sm">
                <span class="text-cyan-300 font-extrabold mr-1">{{ selectedTpObj.kode }}</span>
                {{ selectedTpObj.judul || selectedTpObj.deskripsi }}
              </span>
              <span v-else class="text-slate-500 italic truncate text-xs sm:text-sm">
                -- Pilih Sasaran TP Kurikulum Merdeka --
              </span>
              <svg class="w-4 h-4 text-slate-400 shrink-0" :class="isTpDropdownOpen ? 'rotate-180 text-cyan-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>

            <!-- Dropdown List Tanpa Terpotong (Melayang di atas Card 2) -->
            <div
              v-if="isTpDropdownOpen"
              class="absolute z-50 left-0 right-0 mt-2 bg-slate-900 border border-cyan-500/60 rounded-2xl shadow-2xl p-3 backdrop-blur-2xl space-y-2 animate-in fade-in zoom-in-95 duration-100"
            >
              <input
                v-model="tpSearchQuery"
                type="text"
                placeholder="Cari kode atau materi TP..."
                class="w-full bg-slate-950 border border-white/20 focus:border-cyan-400 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none"
                @click.stop
              />
              <div class="max-h-60 overflow-y-auto space-y-1.5 pr-1">
                <div v-if="searchedTps.length === 0" class="py-4 text-center text-xs text-slate-400">
                  Tidak ada TP yang cocok untuk mapel & semester ini.
                </div>
                <div
                  v-for="tp in searchedTps"
                  :key="tp.id"
                  @click="selectTp(tp)"
                  class="p-2.5 rounded-xl cursor-pointer transition flex items-start gap-2.5 border text-xs"
                  :class="String(form.tpId) === String(tp.id)
                    ? 'bg-cyan-500/20 border-cyan-500/50 text-white font-bold'
                    : 'bg-white/[0.02] border-white/5 hover:bg-white/10 text-slate-300'"
                >
                  <span class="px-2 py-0.5 rounded-md bg-cyan-500/20 text-cyan-300 font-extrabold text-[10px] shrink-0 mt-0.5">
                    {{ tp.kode }}
                  </span>
                  <span class="line-clamp-2 flex-1 text-slate-200">{{ tp.judul || tp.deskripsi }}</span>
                </div>
              </div>
            </div>

            <p v-if="!isTpDropdownOpen" class="text-[11px] text-slate-500 mt-1.5">Difilter otomatis sesuai mata pelajaran & semester</p>
          </div>
        </div>
      </section>

      <!-- Kartu 2: Identitas & Format Materi -->
      <section class="bg-slate-900/90 border border-white/15 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl space-y-6 relative z-10">
        <div class="flex items-center gap-3 pb-5 border-b border-white/10">
          <div class="w-10 h-10 rounded-2xl bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 flex items-center justify-center text-lg font-black shrink-0">
            2
          </div>
          <div class="min-w-0">
            <h2 class="text-sm sm:text-base font-black text-white">
              Identitas & Format Konten Materi
            </h2>
            <p class="text-xs text-slate-400">
              Tentukan judul materi dan pilih format presentasi yang paling efektif untuk siswa.
            </p>
          </div>
        </div>

        <!-- Judul Materi -->
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center justify-between">
            <span>Judul Materi Pembelajaran <span class="text-rose-400">*</span></span>
            <span class="text-slate-400 text-[10px]">Tampil di dashboard siswa</span>
          </label>
          <input
            v-model="form.judul"
            type="text"
            required
            placeholder="Contoh: Pengenalan Algoritma Pemrograman & Flowchart"
            class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-500/30 rounded-2xl px-4 py-3.5 text-sm text-white font-bold placeholder-slate-600 transition min-h-[48px] outline-none shadow-inner"
          />
        </div>

        <!-- 3 Pilihan Format Materi (Kartu Interaktif) -->
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-2">Pilih Format Pembelajaran</label>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
            <div
              v-for="opt in TIPE_OPTIONS"
              :key="opt.value"
              @click="form.tipe = opt.value"
              class="p-4 rounded-2xl border cursor-pointer transition select-none flex flex-col justify-between gap-3 min-h-[110px]"
              :class="form.tipe === opt.value
                ? 'bg-cyan-500/15 border-cyan-400 text-white shadow-xl shadow-cyan-500/20 ring-1 ring-cyan-500/40'
                : 'bg-slate-950/60 border-white/10 hover:border-white/20 text-slate-400 hover:text-slate-200'"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-black">{{ opt.label }}</span>
                <span v-if="form.tipe === opt.value" class="text-cyan-400 text-sm font-black">✓</span>
              </div>
              <p class="text-[11px] leading-relaxed opacity-80">
                <span v-if="opt.value === 'Slider PPT'">Slide interaktif dengan rumus KaTeX, perataan naskah, dan ilustrasi visual.</span>
                <span v-else-if="opt.value === 'Dokumen PDF/PPT'">Unggah modul ajar PDF, file presentasi PPT, atau berkas Word.</span>
                <span v-else>Sematkan video pembelajaran dari YouTube dengan pemutar interaktif langsung.</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Tautkan Ujian CBT -->
        <div class="pt-2">
          <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center justify-between">
            <span>Tautkan ke Ujian CBT (Opsional)</span>
            <span class="text-slate-500 text-[10px]">Siswa dapat langsung mengikuti tes setelah membaca</span>
          </label>
          <select
            v-model="form.cbtJadwalId"
            class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-2xl px-4 py-3 text-xs sm:text-sm text-white min-h-[46px] cursor-pointer outline-none shadow-inner"
          >
            <option :value="null" class="bg-slate-900 text-white py-1.5">-- Tidak Ada Ujian Terkait --</option>
            <option v-for="cbt in cbtJadwalListProp" :key="cbt.id" :value="cbt.id" class="bg-slate-900 text-white py-1.5">
              📝 {{ cbt.judul }} ({{ cbt.durasi }} Menit)
            </option>
          </select>
        </div>

        <!-- Tombol Lanjut ke Studio Materi -->
        <div class="pt-6 border-t border-white/10 flex flex-wrap items-center justify-between gap-3">
          <button
            type="button"
            @click="goBack"
            class="px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/15 text-xs font-bold text-slate-300 hover:text-white transition active:scale-95 min-h-[46px]"
          >
            ← Kembali ke Dashboard
          </button>

          <button
            type="button"
            @click="nextToStep2"
            class="px-8 py-3 rounded-2xl bg-gradient-to-r from-cyan-500 via-teal-500 to-indigo-600 text-white font-black text-xs sm:text-sm shadow-xl shadow-cyan-500/25 hover:scale-105 active:scale-95 transition flex items-center gap-2 min-h-[46px]"
          >
            <span>Lanjut ke Studio Materi</span>
            <span>➡️</span>
          </button>
        </div>
      </section>

    </main>

    <!-- ========================================================================= -->
    <!-- TAMPILAN 2: STUDIO PEMBUATAN MATERI (FULL-WIDTH WORKSPACE STEP 2)         -->
    <!-- ========================================================================= -->
    <main v-else class="w-full max-w-[1600px] mx-auto px-3 sm:px-6 pt-4 pb-28 space-y-4">

      <!-- 1. Workspace Header Terpadu: Target Context & Segmented Theme Selector -->
      <div class="bg-slate-900/90 border border-white/10 rounded-2xl px-4 py-2.5 shadow-xl backdrop-blur-xl flex flex-wrap items-center justify-between gap-3">
        <!-- Target Info & Link Ubah Target -->
        <div class="flex items-center gap-2 flex-wrap min-w-0">
          <button
            type="button"
            @click="backToStep1"
            class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-300 hover:text-white transition flex items-center gap-1.5 active:scale-95"
            title="Klik untuk mengubah Pengaturan Target"
          >
            <span>⚙️</span>
            <span>Ubah Target</span>
          </button>

          <div class="h-4 w-px bg-white/15 hidden sm:block"></div>

          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-xs px-2.5 py-1 rounded-lg bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 font-bold flex items-center gap-1">
              <span>📚</span>
              <span>{{ selectedMapelObj?.nama || 'Mapel' }}</span>
            </span>

            <span class="text-xs px-2.5 py-1 rounded-lg bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 font-bold flex items-center gap-1">
              <span>{{ form.kelas_id === 'all' ? '🌐' : '🏫' }}</span>
              <span>{{ selectedKelasObj?.nama || 'Semua Kelas' }}</span>
            </span>

            <span v-if="selectedTpObj" class="text-xs px-2.5 py-1 rounded-lg bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 font-bold flex items-center gap-1">
              <span>🎯</span>
              <span>{{ selectedTpObj.kode }}</span>
            </span>

            <span class="text-xs px-2.5 py-1 rounded-lg bg-purple-500/15 text-purple-300 border border-purple-500/30 font-bold flex items-center gap-1">
              <span>📄</span>
              <span>{{ form.tipe }}</span>
            </span>
          </div>

          <span class="text-xs sm:text-sm font-bold text-white truncate max-w-sm hidden xl:inline">
            "{{ form.judul }}"
          </span>
        </div>

        <!-- Theme Segmented Control (Canva Presentation Style) -->
        <div v-if="form.tipe === 'Slider PPT'" class="flex items-center flex-wrap gap-2">
          <span class="text-xs font-bold text-slate-300 flex items-center gap-1.5 shrink-0">
            <span>🎨</span>
            <span>Tema:</span>
          </span>
          <div class="flex items-center flex-wrap gap-1.5 bg-slate-950/80 p-1.5 rounded-2xl border border-white/10 shadow-inner">
            <button
              v-for="th in THEME_OPTIONS"
              :key="th.id"
              type="button"
              @click="form.theme = th.id"
              class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 active:scale-95 border"
              :class="form.theme === th.id
                ? 'bg-white/15 text-white border-cyan-400 shadow-md shadow-cyan-500/20 ring-1 ring-cyan-400'
                : 'text-slate-400 hover:text-slate-200 border-transparent hover:bg-white/5'"
              :title="th.label + ' - ' + th.desc"
            >
              <!-- Canva-like Dual Swatch Dot -->
              <span class="relative w-4 h-4 rounded-full border border-white/25 shrink-0 overflow-hidden flex items-center justify-center" :class="th.dotBg">
                <span class="absolute inset-y-0 right-0 w-2 bg-gradient-to-br" :class="th.dotAccent"></span>
              </span>
              <span>{{ th.label }}</span>
              <span v-if="form.theme === th.id" class="text-[10px] text-cyan-300 font-black">✓</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- STUDIO FORMAT: 1. BERKAS DOKUMEN PDF / PPT                                -->
      <!-- ========================================================================= -->
      <section v-if="form.tipe === 'Dokumen PDF/PPT'" class="bg-slate-900/90 border border-white/15 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl space-y-6">
        <div>
          <h2 class="text-base font-black text-white flex items-center gap-2">
            <span>📄</span>
            <span>Unggah Berkas Modul PDF / Presentasi PPT</span>
          </h2>
          <p class="text-xs text-slate-400 mt-1">
            Unggah modul ajar langsung atau sematkan tautan berkas cloud (Google Drive, OneDrive).
          </p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">Berkas Modul (PDF, PPT, PPTX, DOCX - Maksimal 30MB)</label>
            <div class="border-2 border-dashed border-white/20 hover:border-cyan-400/50 rounded-3xl p-8 text-center bg-slate-950/60 transition cursor-pointer">
              <input
                type="file"
                @change="onMainFileSelected"
                accept=".pdf,.ppt,.pptx,.doc,.docx"
                class="hidden"
                id="main-file-input"
              />
              <label for="main-file-input" class="cursor-pointer block space-y-2">
                <span class="text-4xl block">📁</span>
                <p class="text-sm text-slate-200 font-bold">
                  {{ selectedFile ? selectedFile.name : (props.materiProp?.fileName || 'Klik atau seret berkas ke sini') }}
                </p>
                <p class="text-xs text-slate-500">Mendukung format PDF, PowerPoint (.pptx), atau Word (.docx)</p>
              </label>
            </div>
            <div v-if="props.materiProp?.fileUrl && !selectedFile" class="mt-2 text-xs text-cyan-400 flex items-center gap-2">
              <span>📎 Berkas saat ini:</span>
              <a :href="props.materiProp.fileUrl" target="_blank" class="underline hover:text-cyan-300">Lihat Berkas Aktif</a>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">Atau Tautan Google Drive / Cloud File</label>
            <input
              v-model="form.fileUrl"
              type="url"
              placeholder="https://drive.google.com/file/d/..."
              class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-2xl px-4 py-3 text-xs text-white min-h-[46px]"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">Petunjuk / Catatan Belajar untuk Siswa</label>
            <AutoTextarea
              v-model="form.deskripsiSingkat"
              placeholder="Tuliskan petunjuk membaca atau panduan yang harus diperhatikan siswa..."
              class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-2xl p-4 text-xs text-white min-h-[140px]"
            />
          </div>
        </div>
      </section>

      <!-- ========================================================================= -->
      <!-- STUDIO FORMAT: 2. VIDEO PEMBELAJARAN YOUTUBE                              -->
      <!-- ========================================================================= -->
      <section v-else-if="form.tipe === 'Video YouTube'" class="bg-slate-900/90 border border-white/15 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl space-y-6">
        <div>
          <h2 class="text-base font-black text-white flex items-center gap-2">
            <span>🎥</span>
            <span>Sematkan Video Pembelajaran YouTube</span>
          </h2>
          <p class="text-xs text-slate-400 mt-1">
            Masukkan tautan video YouTube. Pemutar interaktif langsung dapat dicoba di bawah.
          </p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1.5">URL Video YouTube <span class="text-rose-400">*</span></label>
            <input
              v-model="form.fileUrl"
              type="url"
              placeholder="https://www.youtube.com/watch?v=..."
              class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-2xl px-4 py-3 text-xs text-white min-h-[46px]"
            />
          </div>

          <!-- Live YouTube Player Preview -->
          <div v-if="youtubeEmbedUrl" class="rounded-3xl overflow-hidden border border-white/15 bg-black aspect-video max-w-2xl mx-auto shadow-2xl">
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
              placeholder="Contoh: Perhatikan penjelasan konsep pada menit 03:15..."
              class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-2xl p-4 text-xs text-white min-h-[140px]"
            />
          </div>
        </div>
      </section>

      <!-- ========================================================================= -->
      <!-- STUDIO FORMAT: 3. SLIDER PPT INTERAKTIF (FULL WORKSPACE)                  -->
      <!-- ========================================================================= -->
      <section v-else class="space-y-4">

        <!-- Studio Slide Builder (Full Width) -->
        <div class="bg-slate-900/95 border border-white/15 rounded-3xl shadow-2xl overflow-hidden backdrop-blur-xl">

          <!-- 1. Strip Thumbnail Slide Horizontal -->
          <div class="bg-slate-950/90 border-b border-white/10 px-4 py-3 flex items-center justify-between gap-3 overflow-x-auto">
            <div class="flex items-center gap-2 min-w-max">
              <button
                v-for="(s, idx) in form.kegiatan"
                :key="idx"
                type="button"
                @click="activeSlideIndex = idx"
                class="px-3.5 py-2 rounded-2xl border text-left transition flex items-center gap-2 min-h-[42px]"
                :class="activeSlideIndex === idx
                  ? 'bg-cyan-500/20 border-cyan-400 text-white font-bold shadow-md shadow-cyan-500/25 ring-1 ring-cyan-500/40'
                  : 'bg-white/5 border-white/10 text-slate-400 hover:bg-white/10 hover:text-slate-200'"
              >
                <span class="w-5 h-5 rounded-lg flex items-center justify-center text-[10px] font-black" :class="activeSlideIndex === idx ? 'bg-cyan-500 text-white' : 'bg-white/10 text-slate-300'">
                  {{ idx + 1 }}
                </span>
                <span class="text-xs truncate max-w-[130px]">{{ s.judul || ('Bagian ' + (idx + 1)) }}</span>
              </button>

              <!-- Tambah Slide Button -->
              <button
                type="button"
                @click="addSlide"
                class="px-3.5 py-2 rounded-2xl border border-dashed border-cyan-500/50 hover:border-cyan-400 bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 text-xs font-bold flex items-center gap-1.5 transition active:scale-95 min-h-[42px]"
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
                class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-300 disabled:opacity-25 transition min-h-[36px]"
                title="Pindah ke Kiri"
              >
                ◀
              </button>
              <button
                type="button"
                @click="moveSlide(activeSlideIndex, 1)"
                :disabled="activeSlideIndex === form.kegiatan.length - 1"
                class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-300 disabled:opacity-25 transition min-h-[36px]"
                title="Pindah ke Kanan"
              >
                ▶
              </button>
              <button
                type="button"
                @click="duplicateSlide(activeSlideIndex)"
                class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-300 transition min-h-[36px]"
                title="Duplikasi Slide"
              >
                📋 Salin
              </button>
              <button
                type="button"
                @click="removeSlide(activeSlideIndex)"
                :disabled="form.kegiatan.length <= 1"
                class="px-2.5 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-300 text-xs font-bold disabled:opacity-25 transition min-h-[36px]"
                title="Hapus Slide"
              >
                🗑️
              </button>
            </div>
          </div>

          <!-- 2. Active Slide Sub-Header (Judul & Tata Letak) -->
          <div v-if="form.kegiatan[activeSlideIndex]" class="px-5 py-3.5 border-b border-white/10 bg-slate-950/40">
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5 items-center">
              <div class="sm:col-span-8">
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Judul Sub-Materi / Slide</label>
                <input
                  v-model="form.kegiatan[activeSlideIndex].judul"
                  placeholder="Misal: Konsep Dasar Flowchart & Notasi"
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl px-3.5 py-2.5 text-xs text-white font-bold min-h-[42px] outline-none"
                />
              </div>

              <div class="sm:col-span-4">
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Tata Letak (Layout)</label>
                <select
                  v-model="form.kegiatan[activeSlideIndex].layout"
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 rounded-xl px-3.5 py-2.5 text-xs text-white font-medium min-h-[42px] cursor-pointer outline-none"
                >
                  <option v-for="lay in LAYOUT_OPTIONS" :key="lay.id" :value="lay.id" class="bg-slate-900 text-white py-1.5">
                    {{ lay.icon }} {{ lay.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- 3. Active Slide Editor & Preview Body (Dual Column Workspace) -->
          <div v-if="form.kegiatan[activeSlideIndex]" class="p-5 space-y-4">

            <!-- Mobile Switcher Tab (< lg) -->
            <div class="lg:hidden flex items-center bg-slate-950 p-1 rounded-2xl border border-white/15">
              <button
                type="button"
                @click="mobileViewMode = 'editor'"
                class="flex-1 py-2.5 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 min-h-[42px]"
                :class="mobileViewMode === 'editor' ? 'bg-cyan-500 text-white shadow-md shadow-cyan-500/25' : 'text-slate-400 hover:text-white'"
              >
                <span>✏️ Mode Naskah Slide</span>
              </button>
              <button
                type="button"
                @click="mobileViewMode = 'preview'"
                class="flex-1 py-2.5 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 min-h-[42px]"
                :class="mobileViewMode === 'preview' ? 'bg-cyan-500 text-white shadow-md shadow-cyan-500/25' : 'text-slate-400 hover:text-white'"
              >
                <span>👁️ Pratinjau Tampilan Siswa</span>
              </button>
            </div>

            <!-- Two-Column Workspace: Editor + Live Preview -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

              <!-- ============================================== -->
              <!-- KOLOM KIRI: EDITOR NASKAH + TOOLBAR INTERAKTIF -->
              <!-- ============================================== -->
              <div class="space-y-3" :class="mobileViewMode === 'editor' ? 'block' : 'hidden lg:block'">

                <!-- RICH TEXT & ALIGNMENT TOOLBAR -->
                <div class="bg-slate-950 border border-white/15 rounded-2xl p-2.5 shadow-sm space-y-2">
                  <div class="flex items-center flex-wrap gap-1.5">

                    <!-- GAYA TEKS -->
                    <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('bold')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white font-black text-xs transition flex items-center justify-center active:scale-95"
                        title="Tebal (Bold: **teks**)"
                      >
                        B
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('italic')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white italic font-serif text-xs transition flex items-center justify-center active:scale-95"
                        title="Miring (Italic: *teks*)"
                      >
                        I
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('underline')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white underline text-xs transition flex items-center justify-center active:scale-95"
                        title="Garis Bawah (Underline)"
                      >
                        U
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('strikethrough')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white line-through text-xs transition flex items-center justify-center active:scale-95"
                        title="Coret (Strikethrough)"
                      >
                        S
                      </button>
                    </div>

                    <!-- UKURAN TEKS (FONT SIZE) -->
                    <div class="flex items-center bg-white/5 rounded-xl px-2 h-8 border border-white/10 hover:border-cyan-500/40 transition">
                      <select
                        @change="onFontSizeChange($event)"
                        class="bg-transparent text-slate-200 text-xs font-semibold focus:outline-none cursor-pointer border-none py-0 pl-0 pr-4 appearance-auto"
                        title="Size"
                      >
                        <option value="" disabled selected class="bg-slate-900 text-slate-400">Size</option>
                        <option value="12px" class="bg-slate-900 text-slate-200">12</option>
                        <option value="14px" class="bg-slate-900 text-slate-200">14</option>
                        <option value="16px" class="bg-slate-900 text-slate-200">16</option>
                        <option value="18px" class="bg-slate-900 text-slate-200">18</option>
                        <option value="20px" class="bg-slate-900 text-slate-200">20</option>
                        <option value="24px" class="bg-slate-900 text-slate-200">24</option>
                        <option value="28px" class="bg-slate-900 text-slate-200">28</option>
                        <option value="32px" class="bg-slate-900 text-slate-200">32</option>
                      </select>
                    </div>

                    <!-- HEADING -->
                    <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('h1')"
                        class="px-2.5 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white font-black text-xs transition flex items-center justify-center active:scale-95"
                        title="Judul Besar (Heading 1)"
                      >
                        H1
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('h2')"
                        class="px-2.5 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white font-bold text-xs transition flex items-center justify-center active:scale-95"
                        title="Sub-Judul (Heading 2)"
                      >
                        H2
                      </button>
                    </div>

                    <!-- PERATAAN TEKS (ALIGNMENT) -->
                    <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('align_left')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs transition flex items-center justify-center active:scale-95"
                        title="Rata Kiri"
                      >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M3 5h18v2H3V5zm0 4h12v2H3V9zm0 4h18v2H3v-2zm0 4h12v2H3v-2z"/>
                        </svg>
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('align_center')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs transition flex items-center justify-center active:scale-95"
                        title="Rata Tengah"
                      >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M3 5h18v2H3V5zm3 4h12v2H6V9zm-3 4h18v2H3v-2zm3 4h12v2H6v-2z"/>
                        </svg>
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('align_right')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs transition flex items-center justify-center active:scale-95"
                        title="Rata Kanan"
                      >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M3 5h18v2H3V5zm6 4h12v2H9V9zm-6 4h18v2H3v-2zm6 4h12v2H9v-2z"/>
                        </svg>
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('align_justify')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs transition flex items-center justify-center active:scale-95"
                        title="Rata Kanan Kiri (Justify)"
                      >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M3 5h18v2H3V5zm0 4h18v2H3V9zm0 4h18v2H3v-2zm0 4h18v2H3v-2z"/>
                        </svg>
                      </button>
                    </div>

                    <!-- DAFTAR & LIST -->
                    <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('bullet_list')"
                        class="px-2 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
                        title="List Simbol (Bullet •)"
                      >
                        •
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('numbered_list')"
                        class="px-2 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
                        title="List Angka (1, 2, 3)"
                      >
                        1.
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('alpha_list')"
                        class="px-2 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
                        title="List Huruf (a, b, c)"
                      >
                        a.
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('checklist')"
                        class="px-2 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1 active:scale-95"
                        title="Checklist / Kotak Centang"
                      >
                        ☑
                      </button>
                    </div>

                    <!-- ELEMEN TAMBAHAN -->
                    <div class="flex items-center bg-white/5 rounded-xl p-0.5 border border-white/10">
                      <button
                        type="button"
                        @click="applyFormat('quote')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
                        title="Kutipan / Blok Catatan"
                      >
                        ”
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('table')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
                        title="Sisipkan Tabel"
                      >
                        ⊞
                      </button>
                      <button
                        type="button"
                        @click="applyFormat('divider')"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-slate-200 hover:text-white text-xs font-bold transition flex items-center justify-center active:scale-95"
                        title="Garis Pemisah"
                      >
                        ―
                      </button>
                    </div>

                    <!-- SISIPKAN RUMUS ILMIAH (TOGGLE) -->
                    <button
                      id="formula-toggle-btn"
                      type="button"
                      @click="isFormulaDrawerOpen = !isFormulaDrawerOpen"
                      class="px-3 h-8 rounded-xl border text-xs font-bold transition flex items-center gap-1.5 active:scale-95 shadow-sm"
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
                      class="px-3 h-8 rounded-xl bg-teal-500/10 hover:bg-teal-500/20 border border-teal-500/30 text-teal-300 text-xs font-bold cursor-pointer transition flex items-center gap-1.5 active:scale-95 shadow-sm"
                      title="Tambahkan foto atau ilustrasi pada slide ini"
                    >
                      <span>📷</span>
                      <span>{{ kegiatanImagePreviews[activeSlideIndex] ? 'Ganti Foto' : 'Foto' }}</span>
                    </label>

                  </div>

                  <!-- POPUP / DRAWER RUMUS ILMIAH -->
                  <div
                    v-if="isFormulaDrawerOpen"
                    id="formula-drawer-box"
                    class="p-3 bg-slate-900 border border-cyan-500/40 rounded-2xl space-y-2.5 shadow-2xl animate-in fade-in duration-150"
                  >
                    <div class="flex items-center justify-between">
                      <div class="inline-flex rounded-xl bg-slate-950 p-1 border border-white/10">
                        <button
                          type="button"
                          @click="activeFormulaCategory = 'matematika'"
                          class="px-2.5 py-1 rounded-lg text-xs font-bold transition"
                          :class="activeFormulaCategory === 'matematika' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                        >
                          Matematika
                        </button>
                        <button
                          type="button"
                          @click="activeFormulaCategory = 'fisika'"
                          class="px-2.5 py-1 rounded-lg text-xs font-bold transition"
                          :class="activeFormulaCategory === 'fisika' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                        >
                          Fisika
                        </button>
                        <button
                          type="button"
                          @click="activeFormulaCategory = 'kimia'"
                          class="px-2.5 py-1 rounded-lg text-xs font-bold transition"
                          :class="activeFormulaCategory === 'kimia' ? 'bg-cyan-500 text-white shadow' : 'text-slate-400 hover:text-white'"
                        >
                          Kimia
                        </button>
                      </div>

                      <button
                        type="button"
                        @click="isFormulaDrawerOpen = false"
                        class="text-xs text-slate-400 hover:text-white px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10"
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
                        class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-cyan-500/20 border border-white/10 hover:border-cyan-400/40 text-xs text-cyan-200 transition active:scale-95 shadow-sm flex items-center gap-1.5"
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
                  class="p-3 rounded-2xl bg-slate-950/80 border border-cyan-500/30 flex items-center justify-between gap-3 shadow-md"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-14 h-14 rounded-xl overflow-hidden border border-white/10 bg-black shrink-0">
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
                      class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-bold text-slate-300 cursor-pointer border border-white/10"
                    >
                      Ganti
                    </label>
                    <button
                      type="button"
                      @click="removeSlideImage(activeSlideIndex)"
                      class="px-2.5 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-300 text-xs font-bold transition"
                    >
                      Hapus
                    </button>
                  </div>
                </div>

                <!-- Textarea Editor Naskah Full Height -->
                <AutoTextarea
                  ref="slideTextareaRef"
                  v-model="form.kegiatan[activeSlideIndex].konten"
                  placeholder="Ketik penjelasan naskah materi di sini. Gunakan tombol toolbar di atas untuk menebalkan, membuat daftar poin, perataan teks, atau menyisipkan rumus..."
                  class="w-full bg-slate-950 border border-white/15 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-500/30 rounded-2xl p-4 text-xs font-sans text-white leading-relaxed placeholder-slate-600 min-h-[380px]"
                />

              </div>

              <!-- ============================================== -->
              <!-- KOLOM KANAN: REAL-TIME PRATINJAU TAMPILAN SISWA -->
              <!-- ============================================== -->
              <div class="space-y-3" :class="mobileViewMode === 'preview' ? 'block' : 'hidden lg:block'">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-black text-cyan-400 uppercase tracking-wider flex items-center gap-1.5">
                    <span>👁️</span> Pratinjau Tampilan Siswa
                  </span>
                  <span class="text-[10px] px-2.5 py-0.5 rounded-full bg-white/10 text-slate-200 border border-white/15 font-bold flex items-center gap-1">
                    <span>🎨</span> {{ currentThemeLabel }}
                  </span>
                </div>

                <!-- Realistic Slide Container with Canva Ambient Glow -->
                <div class="relative group">
                  <div
                    class="pointer-events-none absolute -inset-2 rounded-[2.5rem] blur-xl opacity-35 transition-all duration-500"
                    :style="{ backgroundColor: currentThemeConfig.glow }"
                  ></div>
                  <div
                    class="relative rounded-3xl border p-6 shadow-2xl min-h-[420px] flex flex-col justify-between transition-all duration-300 overflow-hidden"
                    :class="currentThemeConfig.wrapper"
                  >
                  <!-- Slide Header inside Mockup -->
                  <div>
                    <div class="flex items-center justify-between gap-2 mb-3 pb-3 border-b" :class="currentThemeConfig.footer">
                      <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-lg" :class="currentThemeConfig.badge">
                        {{ selectedMapelObj?.nama || 'Materi Pembelajaran' }}
                      </span>
                      <span class="text-[10px] font-mono opacity-60">
                        Slide {{ activeSlideIndex + 1 }} / {{ form.kegiatan.length }}
                      </span>
                    </div>

                    <h3 class="text-base font-black mb-3 leading-snug" :class="currentThemeConfig.title">
                      {{ form.kegiatan[activeSlideIndex]?.judul || 'Judul Slide Pembelajaran' }}
                    </h3>

                    <!-- Slide Content & Media Layout Mockup -->
                    <div v-if="form.kegiatan[activeSlideIndex]?.layout === 'image_left'" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
                      <div class="sm:col-span-5 rounded-2xl overflow-hidden bg-black/40 border border-white/10 h-40 flex items-center justify-center">
                        <img v-if="kegiatanImagePreviews[activeSlideIndex]" :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                        <span v-else class="text-xs text-slate-500">🖼️ Foto Kiri</span>
                      </div>
                      <div class="sm:col-span-7">
                        <div
                          class="text-xs leading-relaxed slide-markdown-content"
                          :class="currentThemeConfig.prose"
                          v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Ketik naskah di sebelah kiri...*')"
                        ></div>
                      </div>
                    </div>

                    <div v-else-if="form.kegiatan[activeSlideIndex]?.layout === 'image_right'" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
                      <div class="sm:col-span-7">
                        <div
                          class="text-xs leading-relaxed slide-markdown-content"
                          :class="currentThemeConfig.prose"
                          v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Ketik naskah di sebelah kiri...*')"
                        ></div>
                      </div>
                      <div class="sm:col-span-5 rounded-2xl overflow-hidden bg-black/40 border border-white/10 h-40 flex items-center justify-center">
                        <img v-if="kegiatanImagePreviews[activeSlideIndex]" :src="kegiatanImagePreviews[activeSlideIndex]" class="w-full h-full object-cover" />
                        <span v-else class="text-xs text-slate-500">🖼️ Foto Kanan</span>
                      </div>
                    </div>

                    <div v-else-if="form.kegiatan[activeSlideIndex]?.layout === 'highlight'" class="p-4 rounded-2xl border" :class="form.theme === 'light' ? 'border-amber-500/40 bg-amber-50 text-slate-900' : 'border-amber-400/40 bg-amber-500/10'">
                      <div
                        class="text-xs leading-relaxed slide-markdown-content"
                        :class="currentThemeConfig.prose"
                        v-html="renderFormulaContent(form.kegiatan[activeSlideIndex]?.konten || '*Ketik naskah di sebelah kiri...*')"
                      ></div>
                    </div>

                    <div v-else>
                      <div v-if="kegiatanImagePreviews[activeSlideIndex]" class="mb-4 rounded-2xl overflow-hidden bg-black/40 border border-white/10 max-h-48">
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
        </div>

      </section>

    </main>
  </div>
</template>

<style scoped>
/* Scoped adjustments for Slide Preview Markdown */
:deep(.slide-markdown-content p) {
  margin-bottom: 0.65rem;
  line-height: 1.6;
}

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

:deep(.katex-display-container) {
  max-width: 100%;
  overflow-x: auto;
}
</style>
