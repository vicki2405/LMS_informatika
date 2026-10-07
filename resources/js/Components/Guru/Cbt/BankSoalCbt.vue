<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmDelete, showSuccessAlert, showToast } from '@/Utils/sweetalert';

const props = defineProps({
  bankSoalList: { type: Array, default: () => [] },
  masterKelasList: { type: Array, default: () => [] },
  jurusanList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] },
});

// View mode: 'bank_list' (Daftar Paket Bank Soal) | 'editor_soal' (Full-view Studio Kelola Soal)
const currentView = ref('bank_list');

// Filter & Search State
const searchQuery = ref('');
const filterMapel = ref('');

// Modal Form Paket Bank Soal
const showBankModal = ref(false);
const isEditingBank = ref(false);
const bankForm = ref({
  id: null,
  judul: '',
  kode: '',
  mata_pelajaran_id: '',
  deskripsi: '',
  jumlah_pilihan: 5,
});

// Full-View Studio State (Kelola Butir Soal)
const activeBankSoalId = ref(null);
const isEditingSoal = ref(false);
const soalSearchQuery = ref('');
const mobileStudioTab = ref('form'); // 'form' | 'list'

const soalForm = ref({
  id: null,
  bank_soal_id: null,
  nomor_urut: 1,
  tipe_soal: 'pilihan_ganda', // 'pilihan_ganda' | 'match' | 'reorder' | 'categorize'
  stimulus_teks: '',
  show_stimulus: false,
  pertanyaan: '',
  opsi_a: '',
  opsi_b: '',
  opsi_c: '',
  opsi_d: '',
  opsi_e: '',
  kunci_jawaban: 'A',
  bobot: '2.5',
  // Data for Match: [{ id: 1, kiri: '', kanan: '' }]
  match_pairs: [
    { id: 1, kiri: '', kanan: '' },
    { id: 2, kiri: '', kanan: '' },
  ],
  // Data for Reorder: [{ id: 1, teks: '' }]
  reorder_items: [
    { id: 1, teks: '' },
    { id: 2, teks: '' },
    { id: 3, teks: '' },
  ],
  // Data for Categorize: categories and items
  categorize_data: {
    kategori: ['Kategori A', 'Kategori B'],
    items: [
      { id: 1, teks: '', kategori: 'Kategori A' },
      { id: 2, teks: '', kategori: 'Kategori B' },
    ],
  },
  // Media Soal (Image / Audio)
  media_soal_file: null,
  media_soal_preview: null,
  tipe_media_soal: null,
  posisi_media_soal: 'bawah',
  hapus_media_soal: 0,
  // Gambar Pilihan Jawaban
  gambar_opsi_a_file: null,
  gambar_opsi_a_preview: null,
  hapus_gambar_opsi_a: 0,
  gambar_opsi_b_file: null,
  gambar_opsi_b_preview: null,
  hapus_gambar_opsi_b: 0,
  gambar_opsi_c_file: null,
  gambar_opsi_c_preview: null,
  hapus_gambar_opsi_c: 0,
  gambar_opsi_d_file: null,
  gambar_opsi_d_preview: null,
  hapus_gambar_opsi_d: 0,
  gambar_opsi_e_file: null,
  gambar_opsi_e_preview: null,
  hapus_gambar_opsi_e: 0,
});

// Media Input & Lightbox Refs
const questionImageInput = ref(null);
const questionAudioInput = ref(null);
const previewLargeImageUrl = ref(null);

// Bulk Bobot State
const bulkBobotInput = ref('2,5');
const isSubmittingBulkBobot = ref(false);

// Import Soal Massal State
const showImportModal = ref(false);
const importTab = ref('word'); // 'word' | 'csv'
const rawImportText = ref('');
const importDefaultBobot = ref('2,0');
const parsedQuestions = ref([]);
const isSubmittingImport = ref(false);
const importSearchQuery = ref('');

const filteredParsedQuestions = computed(() => {
  if (!importSearchQuery.value.trim()) return parsedQuestions.value;
  const q = importSearchQuery.value.toLowerCase();
  return parsedQuestions.value.filter((item) => {
    return item.pertanyaan.toLowerCase().includes(q) ||
      String(item.nomor_urut).includes(q) ||
      item.opsi_a.toLowerCase().includes(q) ||
      item.opsi_b.toLowerCase().includes(q) ||
      item.opsi_c.toLowerCase().includes(q) ||
      item.opsi_d.toLowerCase().includes(q) ||
      (item.opsi_e && item.opsi_e.toLowerCase().includes(q));
  });
});

// Active Bank Soal computed dynamically from props so it updates instantly
const activeBankSoal = computed(() => {
  if (!activeBankSoalId.value) return null;
  return props.bankSoalList.find((b) => b.id === activeBankSoalId.value) || null;
});

// Filtered Bank Soal List
const filteredBankSoals = computed(() => {
  return props.bankSoalList.filter((b) => {
    const matchSearch = !searchQuery.value ||
      b.judul.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      b.kode.toLowerCase().includes(searchQuery.value.toLowerCase());

    const matchMapel = !filterMapel.value || String(b.mapelId) === String(filterMapel.value);

    return matchSearch && matchMapel;
  });
});

// Filtered Questions in Active Bank Soal
const filteredSoalsInActiveBank = computed(() => {
  if (!activeBankSoal.value?.soals) return [];
  if (!soalSearchQuery.value.trim()) return activeBankSoal.value.soals;
  const q = soalSearchQuery.value.toLowerCase();
  return activeBankSoal.value.soals.filter((s) => {
    return s.pertanyaan.toLowerCase().includes(q) ||
      String(s.nomorUrut).includes(q) ||
      (s.opsiA && s.opsiA.toLowerCase().includes(q)) ||
      (s.opsiB && s.opsiB.toLowerCase().includes(q)) ||
      (s.opsiC && s.opsiC.toLowerCase().includes(q)) ||
      (s.opsiD && s.opsiD.toLowerCase().includes(q)) ||
      (s.opsiE && s.opsiE.toLowerCase().includes(q));
  });
});

// Total points for active bank soal
const totalPoinActiveBank = computed(() => {
  if (!activeBankSoal.value?.soals) return 0;
  const sum = activeBankSoal.value.soals.reduce((acc, s) => acc + (parseFloat(s.bobot) || 0), 0);
  return Math.round(sum * 100) / 100;
});

// Helper formatting bobot (e.g. 2.5 -> '2,5' or 2 -> '2')
function formatBobot(val) {
  const num = parseFloat(val);
  if (isNaN(num)) return '0';
  return Number(num.toFixed(2)).toString().replace('.', ',');
}

// Open Bank Soal Modal
function openCreateBankModal() {
  isEditingBank.value = false;
  bankForm.value = {
    id: null,
    judul: '',
    kode: 'BS-' + Math.random().toString(36).substring(2, 7).toUpperCase(),
    mata_pelajaran_id: props.mapelList[0]?.id || '',
    deskripsi: '',
    jumlah_pilihan: 5,
  };
  showBankModal.value = true;
}

function openEditBankModal(b) {
  isEditingBank.value = true;
  bankForm.value = {
    id: b.id,
    judul: b.judul,
    kode: b.kode,
    mata_pelajaran_id: b.mapelId || '',
    deskripsi: b.deskripsi,
    jumlah_pilihan: b.jumlahPilihan || 5,
  };
  showBankModal.value = true;
}

function submitBankForm() {
  if (!bankForm.value.judul.trim()) {
    showToast('Judul Bank Soal wajib diisi', 'error');
    return;
  }

  router.post(route('guru.cbt.bank-soal.store'), bankForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      showBankModal.value = false;
      showSuccessAlert(
        isEditingBank.value ? 'Bank Soal Diperbarui' : 'Bank Soal Dibuat',
        'Data paket bank soal berhasil disimpan.'
      );
    },
  });
}

function deleteBankSoal(id) {
  confirmDelete({
    title: 'Hapus Bank Soal?',
    text: 'Seluruh butir soal dan jadwal terkait di bank soal ini akan terhapus!',
    confirmButtonText: 'Ya, Hapus!',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('guru.cbt.bank-soal.delete', id), {
        preserveScroll: true,
        onSuccess: () => {
          showSuccessAlert('Berhasil Dihapus', 'Bank soal berhasil dihapus.');
          if (activeBankSoalId.value === id) {
            currentView.value = 'bank_list';
            activeBankSoalId.value = null;
          }
        },
      });
    }
  });
}

// --- FULL-VIEW STUDIO METHODS (KELOLA BUTIR SOAL) ---

function openKelolaSoal(b) {
  activeBankSoalId.value = b.id;
  currentView.value = 'editor_soal';
  mobileStudioTab.value = 'form';
  resetSoalFormToNew();
}

function backToBankList() {
  currentView.value = 'bank_list';
}

function resetSoalFormToNew() {
  isEditingSoal.value = false;
  const currentCount = activeBankSoal.value?.soals?.length || 0;
  const defaultBobot = bulkBobotInput.value ? String(bulkBobotInput.value).trim() : '2,5';
  soalForm.value = {
    id: null,
    bank_soal_id: activeBankSoalId.value,
    nomor_urut: currentCount + 1,
    tipe_soal: 'pilihan_ganda',
    stimulus_teks: '',
    show_stimulus: false,
    pertanyaan: '',
    opsi_a: '',
    opsi_b: '',
    opsi_c: '',
    opsi_d: '',
    opsi_e: '',
    kunci_jawaban: 'A',
    bobot: defaultBobot,
    match_pairs: [
      { id: 1, kiri: '', kanan: '' },
      { id: 2, kiri: '', kanan: '' },
    ],
    reorder_items: [
      { id: 1, teks: '' },
      { id: 2, teks: '' },
      { id: 3, teks: '' },
    ],
    categorize_data: {
      kategori: ['Kategori A', 'Kategori B'],
      items: [
        { id: 1, teks: '', kategori: 'Kategori A' },
        { id: 2, teks: '', kategori: 'Kategori B' },
      ],
    },
    media_soal_file: null,
    media_soal_preview: null,
    tipe_media_soal: null,
    posisi_media_soal: 'bawah',
    hapus_media_soal: 0,
    gambar_opsi_a_file: null,
    gambar_opsi_a_preview: null,
    hapus_gambar_opsi_a: 0,
    gambar_opsi_b_file: null,
    gambar_opsi_b_preview: null,
    hapus_gambar_opsi_b: 0,
    gambar_opsi_c_file: null,
    gambar_opsi_c_preview: null,
    hapus_gambar_opsi_c: 0,
    gambar_opsi_d_file: null,
    gambar_opsi_d_preview: null,
    hapus_gambar_opsi_d: 0,
    gambar_opsi_e_file: null,
    gambar_opsi_e_preview: null,
    hapus_gambar_opsi_e: 0,
  };
}

function editSoal(s) {
  isEditingSoal.value = true;
  mobileStudioTab.value = 'form';

  let matchPairs = [
    { id: 1, kiri: '', kanan: '' },
    { id: 2, kiri: '', kanan: '' },
  ];
  let reorderItems = [
    { id: 1, teks: '' },
    { id: 2, teks: '' },
    { id: 3, teks: '' },
  ];
  let categorizeData = {
    kategori: ['Kategori A', 'Kategori B'],
    items: [
      { id: 1, teks: '', kategori: 'Kategori A' },
      { id: 2, teks: '', kategori: 'Kategori B' },
    ],
  };

  const rawInter = s.dataInteraktif;
  if (s.tipeSoal === 'match' && Array.isArray(rawInter) && rawInter.length > 0) {
    matchPairs = rawInter.map((p, idx) => ({
      id: p.id || (idx + 1),
      kiri: p.kiri || '',
      kanan: p.kanan || '',
    }));
  } else if (s.tipeSoal === 'reorder' && Array.isArray(rawInter) && rawInter.length > 0) {
    reorderItems = rawInter.map((item, idx) => ({
      id: item.id || (idx + 1),
      teks: item.teks || '',
    }));
  } else if (s.tipeSoal === 'categorize' && rawInter && typeof rawInter === 'object') {
    categorizeData = {
      kategori: Array.isArray(rawInter.kategori) && rawInter.kategori.length > 0 ? [...rawInter.kategori] : ['Kategori A', 'Kategori B'],
      items: Array.isArray(rawInter.items) && rawInter.items.length > 0 ? rawInter.items.map((it, idx) => ({
        id: it.id || (idx + 1),
        teks: it.teks || '',
        kategori: it.kategori || rawInter.kategori?.[0] || 'Kategori A',
      })) : [
        { id: 1, teks: '', kategori: 'Kategori A' },
        { id: 2, teks: '', kategori: 'Kategori B' },
      ],
    };
  }

  soalForm.value = {
    id: s.id,
    bank_soal_id: s.bankSoalId || activeBankSoalId.value,
    nomor_urut: s.nomorUrut,
    tipe_soal: s.tipeSoal || 'pilihan_ganda',
    stimulus_teks: s.stimulusTeks || '',
    show_stimulus: Boolean(s.stimulusTeks),
    pertanyaan: s.pertanyaan,
    opsi_a: s.opsiA || '',
    opsi_b: s.opsiB || '',
    opsi_c: s.opsiC || '',
    opsi_d: s.opsiD || '',
    opsi_e: s.opsiE || '',
    kunci_jawaban: s.kunciJawaban || 'A',
    bobot: s.bobot != null ? String(s.bobot).replace('.', ',') : '2,5',
    match_pairs: matchPairs,
    reorder_items: reorderItems,
    categorize_data: categorizeData,
    media_soal_file: null,
    media_soal_preview: s.mediaSoal || null,
    tipe_media_soal: s.tipeMediaSoal || null,
    posisi_media_soal: s.posisiMediaSoal || 'bawah',
    hapus_media_soal: 0,
    gambar_opsi_a_file: null,
    gambar_opsi_a_preview: s.gambarOpsiA || null,
    hapus_gambar_opsi_a: 0,
    gambar_opsi_b_file: null,
    gambar_opsi_b_preview: s.gambarOpsiB || null,
    hapus_gambar_opsi_b: 0,
    gambar_opsi_c_file: null,
    gambar_opsi_c_preview: s.gambarOpsiC || null,
    hapus_gambar_opsi_c: 0,
    gambar_opsi_d_file: null,
    gambar_opsi_d_preview: s.gambarOpsiD || null,
    hapus_gambar_opsi_d: 0,
    gambar_opsi_e_file: null,
    gambar_opsi_e_preview: s.gambarOpsiE || null,
    hapus_gambar_opsi_e: 0,
  };
  // Scroll to form smoothly on mobile
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    window.scrollTo({ top: 100, behavior: 'smooth' });
  }
}

// --- INTERACTIVE QUESTION BUILDER HELPERS ---
function addMatchPair() {
  const nextId = (soalForm.value.match_pairs.length > 0 ? Math.max(...soalForm.value.match_pairs.map(p => p.id || 0)) : 0) + 1;
  soalForm.value.match_pairs.push({ id: nextId, kiri: '', kanan: '' });
}

function removeMatchPair(idx) {
  if (soalForm.value.match_pairs.length > 2) {
    soalForm.value.match_pairs.splice(idx, 1);
  } else {
    showToast('Soal Menjodohkan minimal membutuhkan 2 pasangan!', 'warning');
  }
}

function addReorderItem() {
  const nextId = (soalForm.value.reorder_items.length > 0 ? Math.max(...soalForm.value.reorder_items.map(i => i.id || 0)) : 0) + 1;
  soalForm.value.reorder_items.push({ id: nextId, teks: '' });
}

function removeReorderItem(idx) {
  if (soalForm.value.reorder_items.length > 2) {
    soalForm.value.reorder_items.splice(idx, 1);
  } else {
    showToast('Soal Mengurutkan minimal membutuhkan 2 butir teks!', 'warning');
  }
}

function addCategory() {
  const num = soalForm.value.categorize_data.kategori.length + 1;
  soalForm.value.categorize_data.kategori.push('Kategori ' + String.fromCharCode(64 + num));
}

function removeCategory(idx) {
  if (soalForm.value.categorize_data.kategori.length > 2) {
    const removed = soalForm.value.categorize_data.kategori[idx];
    soalForm.value.categorize_data.kategori.splice(idx, 1);
    const fallback = soalForm.value.categorize_data.kategori[0];
    soalForm.value.categorize_data.items.forEach(it => {
      if (it.kategori === removed) it.kategori = fallback;
    });
  } else {
    showToast('Minimal harus ada 2 kelompok kategori!', 'warning');
  }
}

function addCategorizeItem() {
  const nextId = (soalForm.value.categorize_data.items.length > 0 ? Math.max(...soalForm.value.categorize_data.items.map(i => i.id || 0)) : 0) + 1;
  const defaultCat = soalForm.value.categorize_data.kategori[0] || 'Kategori A';
  soalForm.value.categorize_data.items.push({ id: nextId, teks: '', kategori: defaultCat });
}

function removeCategorizeItem(idx) {
  if (soalForm.value.categorize_data.items.length > 2) {
    soalForm.value.categorize_data.items.splice(idx, 1);
  } else {
    showToast('Minimal harus ada 2 butir item untuk dikelompokkan!', 'warning');
  }
}

// Media Upload & Manipulation Handlers
function triggerQuestionImageUpload() {
  questionImageInput.value?.click();
}

function triggerQuestionAudioUpload() {
  questionAudioInput.value?.click();
}

function handleQuestionImageSelected(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    showToast('File harus berupa gambar (JPG, PNG, WEBP, GIF, SVG)', 'error');
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    showToast('Ukuran gambar maksimal 10MB', 'error');
    return;
  }
  soalForm.value.media_soal_file = file;
  soalForm.value.media_soal_preview = URL.createObjectURL(file);
  soalForm.value.tipe_media_soal = 'image';
  soalForm.value.hapus_media_soal = 0;
  e.target.value = '';
}

function handleQuestionAudioSelected(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  if (!file.type.startsWith('audio/') && !file.name.match(/\.(mp3|wav|ogg|m4a)$/i)) {
    showToast('File harus berupa audio (MP3, WAV, OGG, M4A)', 'error');
    return;
  }
  if (file.size > 12 * 1024 * 1024) {
    showToast('Ukuran audio maksimal 12MB', 'error');
    return;
  }
  soalForm.value.media_soal_file = file;
  soalForm.value.media_soal_preview = URL.createObjectURL(file);
  soalForm.value.tipe_media_soal = 'audio';
  soalForm.value.hapus_media_soal = 0;
  e.target.value = '';
}

function removeQuestionMedia() {
  soalForm.value.media_soal_file = null;
  soalForm.value.media_soal_preview = null;
  soalForm.value.tipe_media_soal = null;
  soalForm.value.hapus_media_soal = 1;
}

function triggerOptionImageInput(letter) {
  const el = document.getElementById('opt-img-input-' + letter.toLowerCase());
  if (el) el.click();
}

function handleOptionImageSelected(letter, e) {
  const file = e.target.files?.[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    showToast('File harus berupa gambar (JPG, PNG, WEBP)', 'error');
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    showToast('Ukuran gambar opsi maksimal 5MB', 'error');
    return;
  }
  const l = letter.toLowerCase();
  soalForm.value['gambar_opsi_' + l + '_file'] = file;
  soalForm.value['gambar_opsi_' + l + '_preview'] = URL.createObjectURL(file);
  soalForm.value['hapus_gambar_opsi_' + l] = 0;
  e.target.value = '';
}

function removeOptionImage(letter) {
  const l = letter.toLowerCase();
  soalForm.value['gambar_opsi_' + l + '_file'] = null;
  soalForm.value['gambar_opsi_' + l + '_preview'] = null;
  soalForm.value['hapus_gambar_opsi_' + l] = 1;
}

function openImageLightbox(url) {
  previewLargeImageUrl.value = url;
}

function closeImageLightbox() {
  previewLargeImageUrl.value = null;
}

function setKunci(jawaban) {
  soalForm.value.kunci_jawaban = jawaban;
}

function handlePasteQuestionMedia(e) {
  const clipboardData = e.clipboardData || window.clipboardData;
  if (!clipboardData) return;
  const items = clipboardData.items;
  if (!items) return;

  for (let i = 0; i < items.length; i++) {
    if (items[i].type && items[i].type.startsWith('image/')) {
      const file = items[i].getAsFile();
      if (file) {
        if (file.size > 10 * 1024 * 1024) {
          showToast('Ukuran gambar dari clipboard melebihi 10MB', 'error');
          return;
        }
        soalForm.value.media_soal_file = file;
        soalForm.value.media_soal_preview = URL.createObjectURL(file);
        soalForm.value.tipe_media_soal = 'image';
        soalForm.value.hapus_media_soal = 0;
        showToast('Gambar berhasil ditempel (paste) langsung dari clipboard!', 'success');
        e.preventDefault();
        break;
      }
    }
  }
}

// Quick helper to insert template text
function insertHelperText(type) {
  if (type === 'bold') {
    soalForm.value.pertanyaan += ' **teks tebal** ';
  } else if (type === 'code') {
    soalForm.value.pertanyaan += '\n```\n// ketik kode script di sini\n```\n';
  } else if (type === 'list') {
    soalForm.value.pertanyaan += '\n1. Poin satu\n2. Poin dua\n3. Poin tiga\n';
  } else if (type === 'quote') {
    soalForm.value.pertanyaan += '\n> "Kutipan atau teks studi kasus..."\n';
  } else if (type === 'image_tag') {
    soalForm.value.pertanyaan += '\n[gambar]\n';
  }
}

function submitSoalForm(andContinue = false) {
  if (!soalForm.value.pertanyaan.trim()) {
    showToast('Teks pertanyaan soal wajib diisi', 'error');
    return;
  }
  const isFourOptions = (activeBankSoal.value?.jumlahPilihan || 5) === 4;
  const tipe = soalForm.value.tipe_soal || 'pilihan_ganda';

  let dataInteraktif = null;

  if (tipe === 'pilihan_ganda') {
    if (
      !soalForm.value.opsi_a.trim() ||
      !soalForm.value.opsi_b.trim() ||
      !soalForm.value.opsi_c.trim() ||
      !soalForm.value.opsi_d.trim() ||
      (!isFourOptions && !soalForm.value.opsi_e.trim())
    ) {
      showToast(isFourOptions ? 'Opsi pilihan ganda (A, B, C, D) wajib diisi' : 'Seluruh opsi pilihan ganda (A, B, C, D, E) wajib diisi', 'error');
      return;
    }
  } else if (tipe === 'match') {
    const validPairs = soalForm.value.match_pairs.filter(p => p.kiri.trim() && p.kanan.trim());
    if (validPairs.length < 2) {
      showToast('Soal Menjodohkan (Match) minimal membutuhkan 2 pasangan lengkap (kiri & kanan)', 'error');
      return;
    }
    dataInteraktif = validPairs;
  } else if (tipe === 'reorder') {
    const validItems = soalForm.value.reorder_items.filter(i => i.teks.trim());
    if (validItems.length < 2) {
      showToast('Soal Mengurutkan (Reorder) minimal membutuhkan 2 butir urutan teks', 'error');
      return;
    }
    dataInteraktif = validItems;
  } else if (tipe === 'categorize') {
    const validCats = soalForm.value.categorize_data.kategori.filter(k => k.trim());
    const validItems = soalForm.value.categorize_data.items.filter(i => i.teks.trim());
    if (validCats.length < 2) {
      showToast('Soal Mengelompokkan (Categorize) minimal membutuhkan 2 kelompok kategori', 'error');
      return;
    }
    if (validItems.length < 2) {
      showToast('Soal Mengelompokkan (Categorize) minimal membutuhkan 2 item untuk dikelompokkan', 'error');
      return;
    }
    dataInteraktif = {
      kategori: validCats,
      items: validItems,
    };
  }

  soalForm.value.bank_soal_id = activeBankSoalId.value;

  // Normalisasi bobot desimal (koma ke titik)
  if (soalForm.value.bobot !== undefined && soalForm.value.bobot !== null) {
    soalForm.value.bobot = String(soalForm.value.bobot).replace(',', '.');
  }

  // Siapkan payload tanpa properti internal helper / preview
  const payload = { ...soalForm.value };
  delete payload.media_soal_preview;
  delete payload.gambar_opsi_a_preview;
  delete payload.gambar_opsi_b_preview;
  delete payload.gambar_opsi_c_preview;
  delete payload.gambar_opsi_d_preview;
  delete payload.gambar_opsi_e_preview;
  delete payload.show_stimulus;
  delete payload.match_pairs;
  delete payload.reorder_items;
  delete payload.categorize_data;

  payload.data_interaktif = dataInteraktif ? JSON.stringify(dataInteraktif) : null;
  payload.stimulus_teks = soalForm.value.stimulus_teks ? soalForm.value.stimulus_teks.trim() : null;

  router.post(route('guru.cbt.soal.store'), payload, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      showToast(
        isEditingSoal.value ? 'Butir soal berhasil diperbarui!' : 'Butir soal berhasil ditambahkan!',
        'success'
      );
      if (andContinue) {
        resetSoalFormToNew();
      } else {
        isEditingSoal.value = false;
      }
    },
  });
}

function submitBulkBobot() {
  if (!activeBankSoalId.value) return;
  const cleanVal = String(bulkBobotInput.value || '').trim().replace(',', '.');
  const numVal = parseFloat(cleanVal);
  if (isNaN(numVal) || numVal <= 0) {
    showToast('Masukkan nilai bobot yang valid (> 0, misal: 2.5 atau 2,5)', 'error');
    return;
  }

  const totalSoal = activeBankSoal.value?.soals?.length || 0;
  if (totalSoal === 0) {
    showToast('Belum ada butir soal dalam bank soal ini untuk diatur bobotnya.', 'warning');
    return;
  }

  isSubmittingBulkBobot.value = true;
  router.post(route('guru.cbt.soal.update-all-bobot'), {
    bank_soal_id: activeBankSoalId.value,
    bobot: numVal,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmittingBulkBobot.value = false;
      showToast(`Bobot seluruh ${totalSoal} butir soal berhasil diubah menjadi ${bulkBobotInput.value}!`, 'success');
      if (!isEditingSoal.value) {
        soalForm.value.bobot = bulkBobotInput.value;
      }
    },
    onError: () => {
      isSubmittingBulkBobot.value = false;
      showToast('Gagal mengubah bobot massal.', 'error');
    },
    onFinish: () => {
      isSubmittingBulkBobot.value = false;
    },
  });
}

function deleteSoal(id) {
  confirmDelete({
    title: 'Hapus Butir Soal?',
    text: 'Soal pilihan ganda ini akan dihapus dari bank soal!',
    confirmButtonText: 'Ya, Hapus Soal!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(route('guru.cbt.soal.delete', id), {
        preserveScroll: true,
        onSuccess: () => {
          showSuccessAlert('Berhasil Dihapus', 'Butir soal telah dihapus.');
          if (soalForm.value.id === id) {
            resetSoalFormToNew();
          }
        },
      });
    }
  });
}

// --- SMART IMPORT METHODS (WORD & CSV) ---

function openImportModal() {
  importTab.value = 'word';
  importDefaultBobot.value = bulkBobotInput.value || '2,0';
  rawImportText.value = '';
  parsedQuestions.value = [];
  importSearchQuery.value = '';
  isSubmittingImport.value = false;
  showImportModal.value = true;
}

function loadSampleWordText() {
  rawImportText.value = `1. Komponen perangkat keras komputer yang berfungsi sebagai otak pemroses data utama adalah...
A. Random Access Memory (RAM)
B. Central Processing Unit (CPU)
C. Hard Disk Drive (HDD)
D. Power Supply Unit (PSU)
E. Video Graphics Array (VGA)
Kunci: B

2. Protokol jaringan yang digunakan untuk mentransfer halaman web secara aman dengan enkripsi SSL/TLS adalah...
A. HTTP
B. FTP
C. HTTPS
D. SMTP
E. Telnet
Kunci: C

3. Topologi jaringan komputer di mana setiap workstation terhubung langsung ke switch atau konsentrator pusat disebut...
A. Topologi Bus
B. Topologi Star
C. Topologi Ring
D. Topologi Mesh
E. Topologi Tree
Kunci: B`;
  parseRawImportText();
}

function parseRawImportText() {
  if (!rawImportText.value.trim()) {
    parsedQuestions.value = [];
    return;
  }
  const defaultBobot = importDefaultBobot.value ? String(importDefaultBobot.value).trim() : '2,0';
  parsedQuestions.value = parseQuestionsFromText(rawImportText.value, defaultBobot);
  if (parsedQuestions.value.length === 0) {
    showToast('Tidak ada butir soal yang berhasil diekstrak. Pastikan teks memuat nomor soal dan opsi A-E.', 'warning');
  } else {
    showToast(`${parsedQuestions.value.length} butir soal berhasil diekstrak!`, 'success');
  }
}

function parseQuestionsFromText(text, defaultBobot = '2,0') {
  if (!text || !text.trim()) return [];

  const rawLines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
  const items = [];
  let currentItem = null;
  const questionStartRegex = /^\s*(?:soal\s*(?:no\.?)?\s*)?(?:\(?(\d+)\s*[-.)\:]|\((\d+)\))\s*(.*)$/i;
  const optionRegex = /^\s*(?:\(?([A-Ea-e])\s*[-.)\:]|\(([A-Ea-e])\))\s*(.*)$/;
  const keyRegex = /^\s*(?:kunci(?:\s+jawaban)?|jawaban|answer|ans)[\s:\-=]+(?:opsi\s*|pilihan\s*)?\(?([A-Ea-e])\)?/i;

  for (let i = 0; i < rawLines.length; i++) {
    const line = rawLines[i].trim();
    if (!line) continue;

    // Check Answer Key
    const keyMatch = line.match(keyRegex);
    if (keyMatch && currentItem) {
      currentItem.kunci_jawaban = keyMatch[1].toUpperCase();
      currentItem.is_key_detected = true;
      continue;
    }

    // Check Option
    const optMatch = line.match(optionRegex);
    if (optMatch && currentItem && (currentItem.pertanyaan || currentItem.hasStartedOptions)) {
      const optLetter = (optMatch[1] || optMatch[2]).toUpperCase();
      const optText = optMatch[3] ? optMatch[3].trim() : '';
      const keyMap = { 'A': 'opsi_a', 'B': 'opsi_b', 'C': 'opsi_c', 'D': 'opsi_d', 'E': 'opsi_e' };
      if (keyMap[optLetter]) {
        currentItem[keyMap[optLetter]] = optText;
        currentItem.hasStartedOptions = true;
        continue;
      }
    }

    // Check Question Start
    const qMatch = line.match(questionStartRegex);
    if (qMatch) {
      if (currentItem && currentItem.pertanyaan) {
        items.push(finalizeParsedItem(currentItem, defaultBobot));
      }
      currentItem = {
        nomor_urut: items.length + 1,
        pertanyaan: qMatch[2] ? qMatch[2].trim() : '',
        opsi_a: '',
        opsi_b: '',
        opsi_c: '',
        opsi_d: '',
        opsi_e: '',
        kunci_jawaban: '',
        is_key_detected: false,
        bobot: defaultBobot,
        hasStartedOptions: false,
      };
      continue;
    }

    // Content continuation
    if (currentItem) {
      if (!currentItem.hasStartedOptions) {
        currentItem.pertanyaan += (currentItem.pertanyaan ? '\n' : '') + line;
      } else {
        if (currentItem.opsi_e) currentItem.opsi_e += ' ' + line;
        else if (currentItem.opsi_d) currentItem.opsi_d += ' ' + line;
        else if (currentItem.opsi_c) currentItem.opsi_c += ' ' + line;
        else if (currentItem.opsi_b) currentItem.opsi_b += ' ' + line;
        else if (currentItem.opsi_a) currentItem.opsi_a += ' ' + line;
      }
    } else {
      currentItem = {
        nomor_urut: 1,
        pertanyaan: line,
        opsi_a: '',
        opsi_b: '',
        opsi_c: '',
        opsi_d: '',
        opsi_e: '',
        kunci_jawaban: '',
        is_key_detected: false,
        bobot: defaultBobot,
        hasStartedOptions: false,
      };
    }
  }

  if (currentItem && currentItem.pertanyaan) {
    items.push(finalizeParsedItem(currentItem, defaultBobot));
  }

  return items.map((item, idx) => ({
    ...item,
    nomor_urut: idx + 1,
  }));
}

function finalizeParsedItem(item, defaultBobot) {
  delete item.hasStartedOptions;
  const keyDetected = Boolean(item.kunci_jawaban);
  const hasImageTag = Boolean(item.pertanyaan && item.pertanyaan.match(/\[(?:gambar|img|image)\]|\((?:gambar|img|image)\)/i));
  return {
    ...item,
    kunci_jawaban: keyDetected ? item.kunci_jawaban : 'A',
    is_key_detected: keyDetected,
    bobot: item.bobot || defaultBobot,
    has_image_tag: hasImageTag,
    gambar_base64: null,
    gambar_preview: null,
    posisi_media_soal: 'bawah',
  };
}

function triggerParsedImageUpload(idx) {
  const el = document.getElementById('parsed-img-input-' + idx);
  if (el) el.click();
}

function handleParsedImageSelected(idx, e) {
  const file = e.target.files?.[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    showToast('File harus berupa gambar (JPG, PNG, WEBP)', 'error');
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    showToast('Ukuran gambar maksimal 10MB', 'error');
    return;
  }
  attachImageToParsedItem(idx, file);
  e.target.value = '';
}

function attachImageToParsedItem(idx, file) {
  const reader = new FileReader();
  reader.onload = (e) => {
    if (parsedQuestions.value[idx]) {
      parsedQuestions.value[idx].gambar_base64 = e.target.result;
      parsedQuestions.value[idx].gambar_preview = e.target.result;
      showToast(`Gambar berhasil dipasang pada Soal #${parsedQuestions.value[idx].nomor_urut}!`, 'success');
    }
  };
  reader.readAsDataURL(file);
}

function removeParsedImage(idx) {
  if (parsedQuestions.value[idx]) {
    parsedQuestions.value[idx].gambar_base64 = null;
    parsedQuestions.value[idx].gambar_preview = null;
  }
}

function handlePasteOnParsedItem(idx, e) {
  const clipboardData = e.clipboardData || window.clipboardData;
  if (!clipboardData) return;
  const items = clipboardData.items;
  if (!items) return;

  for (let i = 0; i < items.length; i++) {
    if (items[i].type && items[i].type.startsWith('image/')) {
      const file = items[i].getAsFile();
      if (file) {
        if (file.size > 10 * 1024 * 1024) {
          showToast('Ukuran gambar dari clipboard melebihi 10MB', 'error');
          return;
        }
        attachImageToParsedItem(idx, file);
        e.preventDefault();
        break;
      }
    }
  }
}

function setParsedKey(itemIndex, letter) {
  if (parsedQuestions.value[itemIndex]) {
    parsedQuestions.value[itemIndex].kunci_jawaban = letter;
    parsedQuestions.value[itemIndex].is_key_detected = true;
  }
}

function removeParsedItem(itemIndex) {
  parsedQuestions.value.splice(itemIndex, 1);
  parsedQuestions.value.forEach((item, idx) => {
    item.nomor_urut = idx + 1;
  });
}

function applyBulkBobotToParsed() {
  const cleanVal = importDefaultBobot.value ? String(importDefaultBobot.value).trim() : '2,0';
  parsedQuestions.value.forEach((item) => {
    item.bobot = cleanVal;
  });
  showToast(`Bobot seluruh soal pratinjau diubah menjadi ${cleanVal}`, 'info');
}

// Download CSV Template
function downloadCsvTemplate() {
  const headers = ['No', 'Pertanyaan', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E', 'Kunci Jawaban', 'Bobot'];
  const sampleRows = [
    ['1', 'Ibukota negara Indonesia saat ini adalah...', 'Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang', 'A', '2.0'],
    ['2', 'Organ pernapasan utama pada manusia adalah...', 'Jantung', 'Paru-paru', 'Hati', 'Ginjal', 'Lambung', 'B', '2.0'],
    ['3', 'Satuan besaran arus listrik menurut SI adalah...', 'Volt', 'Watt', 'Ampere', 'Ohm', 'Joule', 'C', '2.0'],
  ];

  const csvContent = '\uFEFF' + [
    headers.map(h => `"${h}"`).join(','),
    ...sampleRows.map(row => row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(','))
  ].join('\r\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.setAttribute('download', 'Template_Bank_Soal_CBT.csv');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Detect Delimiter (Comma, Semicolon, or Tab)
function detectDelimiter(firstLine) {
  const countComma = (firstLine.match(/,/g) || []).length;
  const countSemicolon = (firstLine.match(/;/g) || []).length;
  const countTab = (firstLine.match(/\t/g) || []).length;
  if (countSemicolon > countComma && countSemicolon > countTab) return ';';
  if (countTab > countComma && countTab > countSemicolon) return '\t';
  return ',';
}

// Handle CSV File Upload
function handleCsvUpload(e) {
  const file = e.target.files?.[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (evt) => {
    const text = evt.target.result;
    parseCsvText(text);
  };
  reader.readAsText(file);
  e.target.value = ''; // Reset so the same file can be re-selected if edited
}

function parseCsvText(text) {
  const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
  if (lines.length < 2) {
    showToast('File CSV kosong atau tidak memiliki baris data.', 'error');
    return;
  }

  const delimiter = detectDelimiter(lines[0]);
  const headerCells = parseCsvLine(lines[0], delimiter).map(c => c.toLowerCase().trim());

  // Smart column mapping based on header text
  let colQ = headerCells.findIndex(c => /pertanyaan|soal|question/i.test(c));
  let colA = headerCells.findIndex(c => /opsi\s*a|pilihan\s*a|option\s*a/i.test(c));
  let colB = headerCells.findIndex(c => /opsi\s*b|pilihan\s*b|option\s*b/i.test(c));
  let colC = headerCells.findIndex(c => /opsi\s*c|pilihan\s*c|option\s*c/i.test(c));
  let colD = headerCells.findIndex(c => /opsi\s*d|pilihan\s*d|option\s*d/i.test(c));
  let colE = headerCells.findIndex(c => /opsi\s*e|pilihan\s*e|option\s*e/i.test(c));
  let colKey = headerCells.findIndex(c => /kunci|jawaban|answer|key/i.test(c));
  let colBobot = headerCells.findIndex(c => /bobot|poin|nilai|score/i.test(c));

  // Intelligent fallback positions if headers don't match exactly
  if (colQ === -1) colQ = headerCells.length > 8 ? 1 : 0;
  if (colA === -1) colA = colQ + 1;
  if (colB === -1) colB = colQ + 2;
  if (colC === -1) colC = colQ + 3;
  if (colD === -1) colD = colQ + 4;
  if (colE === -1) colE = colQ + 5;
  if (colKey === -1) colKey = colQ + 6;
  if (colBobot === -1) colBobot = colQ + 7;

  const defaultBobot = importDefaultBobot.value ? String(importDefaultBobot.value).trim() : '2,0';
  const items = [];

  for (let i = 1; i < lines.length; i++) {
    const line = lines[i].trim();
    if (!line) continue;

    const cells = parseCsvLine(line, delimiter);
    if (cells.length > colQ && cells[colQ] && cells[colQ].trim()) {
      const detectedKey = (colKey !== -1 && cells[colKey]) ? cells[colKey].toUpperCase().trim() : '';
      const bobotRaw = (colBobot !== -1 && cells[colBobot]) ? String(cells[colBobot]).trim() : defaultBobot;

      items.push({
        nomor_urut: items.length + 1,
        pertanyaan: cells[colQ] || `Soal #${items.length + 1}`,
        opsi_a: cells[colA] || '',
        opsi_b: cells[colB] || '',
        opsi_c: cells[colC] || '',
        opsi_d: cells[colD] || '',
        opsi_e: (colE !== -1 && cells[colE] && cells[colE] !== '-') ? cells[colE] : '-',
        kunci_jawaban: detectedKey || 'A',
        is_key_detected: Boolean(detectedKey),
        bobot: bobotRaw || defaultBobot,
      });
    }
  }

  if (items.length > 0) {
    parsedQuestions.value = items;
    showToast(`Berhasil membaca ${items.length} butir soal dari CSV!`, 'success');
  } else {
    showToast('Gagal mengekstrak baris soal dari CSV. Pastikan kolom sesuai template.', 'error');
  }
}

function parseCsvLine(line, delimiter = ',') {
  const result = [];
  let current = '';
  let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const char = line[i];
    if (char === '"') {
      if (inQuotes && line[i + 1] === '"') {
        current += '"';
        i++;
      } else {
        inQuotes = !inQuotes;
      }
    } else if (char === delimiter && !inQuotes) {
      result.push(current.trim());
      current = '';
    } else {
      current += char;
    }
  }
  result.push(current.trim());
  return result;
}

function submitBulkImport() {
  if (parsedQuestions.value.length === 0) {
    showToast('Belum ada butir soal yang diekstrak untuk diimpor.', 'warning');
    return;
  }

  for (let i = 0; i < parsedQuestions.value.length; i++) {
    const q = parsedQuestions.value[i];
    if (!q.pertanyaan.trim()) {
      showToast(`Soal #${i + 1} tidak memiliki teks pertanyaan!`, 'error');
      return;
    }
    if (!q.opsi_a.trim() || !q.opsi_b.trim() || !q.opsi_c.trim() || !q.opsi_d.trim()) {
      showToast(`Soal #${i + 1} belum memiliki opsi lengkap (A, B, C, D wajib diisi)!`, 'error');
      return;
    }
  }

  isSubmittingImport.value = true;
  const payload = {
    bank_soal_id: activeBankSoalId.value,
    soals: parsedQuestions.value.map(q => ({
      pertanyaan: q.pertanyaan,
      opsi_a: q.opsi_a,
      opsi_b: q.opsi_b,
      opsi_c: q.opsi_c,
      opsi_d: q.opsi_d,
      opsi_e: q.opsi_e || '-',
      kunci_jawaban: q.kunci_jawaban || 'A',
      bobot: String(q.bobot || importDefaultBobot.value || '2,0').replace(',', '.'),
      posisi_media_soal: q.posisi_media_soal || 'bawah',
      gambar_base64: q.gambar_base64 || null,
    })),
  };

  router.post(route('guru.cbt.soal.import-bulk'), payload, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmittingImport.value = false;
      showImportModal.value = false;
      showSuccessAlert(
        'Import Soal Berhasil!',
        `Sebanyak ${payload.soals.length} butir soal telah berhasil ditambahkan ke dalam Bank Soal.`
      );
      resetSoalFormToNew();
    },
    onError: () => {
      isSubmittingImport.value = false;
      showToast('Gagal mengimpor soal. Periksa kembali format data.', 'error');
    },
    onFinish: () => {
      isSubmittingImport.value = false;
    }
  });
}
</script>

<template>
  <div class="space-y-6">

    <!-- ========================================================================= -->
    <!-- VIEW 1: DAFTAR PAKET BANK SOAL                                            -->
    <!-- ========================================================================= -->
    <div v-if="currentView === 'bank_list'" class="space-y-6">
      <!-- Header Banner & Action -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-cyan-900/60 via-slate-900 to-indigo-950/70 border border-cyan-500/20 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
        <div class="absolute -top-24 -right-24 w-72 h-72 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs font-black tracking-wider uppercase mb-2">
              <span>📚 Master Bank Soal</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
              Bank Soal &amp; Butir Ujian CBT
            </h1>
            <p class="text-xs sm:text-sm text-gray-400 mt-1 max-w-2xl">
              Kelola paket soal ujian dan butir-butir pilihan ganda (A, B, C, D, E) untuk seluruh tingkatan kelas, jurusan, dan mata pelajaran.
            </p>
          </div>
          <button
            @click="openCreateBankModal"
            class="inline-flex items-center gap-2.5 px-5 py-3 rounded-2xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-cyan-500/20 transition-all duration-200 active:scale-95 shrink-0 min-h-[44px]"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Buat Bank Soal Baru</span>
          </button>
        </div>
      </div>

      <!-- Filter & Search Controls -->
      <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/80 border border-white/10 backdrop-blur-xl space-y-3 sm:space-y-0 sm:flex sm:items-center sm:gap-4 shadow-lg">
        <div class="flex-1 relative">
          <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari judul paket soal atau kode..."
            class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs sm:text-sm placeholder-gray-500 focus:outline-none focus:border-cyan-400 transition-colors min-h-[40px]"
          />
        </div>

        <!-- Filter Mapel -->
        <select
          v-model="filterMapel"
          class="w-full sm:w-48 py-2.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs sm:text-sm focus:outline-none focus:border-cyan-400 min-h-[40px]"
        >
          <option value="">Semua Mapel</option>
          <option v-for="m in mapelList" :key="m.id" :value="m.id">{{ m.nama }}</option>
        </select>



      </div>

      <!-- Empty State -->
      <div v-if="filteredBankSoals.length === 0" class="text-center py-16 px-4 rounded-3xl bg-slate-900/60 border border-white/10">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-3xl mb-3">
          📖
        </div>
        <h3 class="text-base font-bold text-white">Belum Ada Paket Bank Soal</h3>
        <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
          Belum ada bank soal yang sesuai filter pencarian. Klik "Buat Bank Soal Baru" untuk mulai membuat soal CBT.
        </p>
      </div>

      <!-- Desktop View: Table (hidden md:block) -->
      <div v-if="filteredBankSoals.length > 0" class="hidden md:block overflow-hidden rounded-2xl bg-slate-900/80 border border-white/10 shadow-xl backdrop-blur-xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
              <th class="py-4 px-5">Kode / Judul Paket</th>
              <th class="py-4 px-4">Mata Pelajaran</th>
              <th class="py-4 px-4 text-center">Jumlah Soal</th>
              <th class="py-4 px-5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 text-xs">
            <tr v-for="b in filteredBankSoals" :key="b.id" class="hover:bg-white/[0.03] transition-colors group">
              <td class="py-4 px-5">
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-300 text-[10px] font-mono font-bold border border-cyan-500/20">
                    {{ b.kode }}
                  </span>
                </div>
                <p class="font-bold text-white text-sm mt-1 group-hover:text-cyan-300 transition-colors">
                  {{ b.judul }}
                </p>
                <p v-if="b.deskripsi" class="text-[11px] text-gray-400 line-clamp-1 mt-0.5">
                  {{ b.deskripsi }}
                </p>
              </td>
              <td class="py-4 px-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-teal-500/10 text-teal-300 border border-teal-500/20 font-semibold">
                  📖 {{ b.mapelNama }}
                </span>
              </td>

              <td class="py-4 px-4 text-center">
                <span class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 font-black text-xs">
                  {{ b.jumlahSoal }} Butir
                </span>
              </td>
              <td class="py-4 px-5 text-right">
                <div class="inline-flex items-center gap-2">
                  <button
                    @click="openKelolaSoal(b)"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 transition-all active:scale-95 min-h-[38px]"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span>Input &amp; Kelola Soal</span>
                  </button>
                  <button
                    @click="openEditBankModal(b)"
                    title="Edit Paket"
                    class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white transition-colors min-h-[38px] min-w-[38px] flex items-center justify-center"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                  </button>
                  <button
                    @click="deleteBankSoal(b.id)"
                    title="Hapus Paket"
                    class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 transition-colors min-h-[38px] min-w-[38px] flex items-center justify-center"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile View: Card List (md:hidden) -->
      <div v-if="filteredBankSoals.length > 0" class="md:hidden space-y-4">
        <div
          v-for="b in filteredBankSoals"
          :key="b.id"
          class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 space-y-3 shadow-lg"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-300 text-[10px] font-mono font-bold border border-cyan-500/20">
                {{ b.kode }}
              </span>
              <h4 class="font-black text-white text-sm mt-1.5">{{ b.judul }}</h4>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 font-bold text-[11px] shrink-0">
              {{ b.jumlahSoal }} Soal
            </span>
          </div>

          <div class="text-[11px] text-gray-300 bg-white/5 p-2.5 rounded-xl">
            <span class="text-gray-400 block text-[10px] uppercase">Mata Pelajaran:</span>
            <span class="font-bold text-teal-300 truncate block text-xs mt-0.5">📖 {{ b.mapelNama }}</span>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <button
              @click="openKelolaSoal(b)"
              class="flex-1 inline-flex items-center justify-center gap-1.5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 min-h-[40px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
              <span>Input &amp; Kelola Soal</span>
            </button>
            <button
              @click="openEditBankModal(b)"
              class="p-2.5 rounded-xl bg-white/5 text-gray-300 border border-white/10 min-h-[40px] min-w-[40px] flex items-center justify-center"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
              </svg>
            </button>
            <button
              @click="deleteBankSoal(b.id)"
              class="p-2.5 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 min-h-[40px] min-w-[40px] flex items-center justify-center"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW 2: FULL-SCREEN / STANDALONE STUDIO KELOLA & INPUT SOAL               -->
    <!-- ========================================================================= -->
    <div v-else-if="currentView === 'editor_soal' && activeBankSoal" class="space-y-6">

      <!-- Breadcrumbs & Studio Navigation Header -->
      <div class="p-5 sm:p-6 rounded-3xl bg-slate-900/90 border border-cyan-500/30 shadow-2xl backdrop-blur-xl">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div class="space-y-2">
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs text-gray-400">
              <button @click="backToBankList" class="hover:text-cyan-300 flex items-center gap-1 font-bold transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Daftar Bank Soal</span>
              </button>
              <span>/</span>
              <span class="text-cyan-400 font-mono">{{ activeBankSoal.kode }}</span>
              <span>/</span>
              <span class="text-white font-bold">Studio Penulisan Soal</span>
            </div>

            <!-- Title & Metadata -->
            <div>
              <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2 flex-wrap">
                <span>{{ activeBankSoal.judul }}</span>
              </h2>
              <div class="flex items-center gap-2 sm:gap-3 flex-wrap text-xs text-gray-300 mt-1.5 font-medium">
                <span class="px-2.5 py-0.5 rounded-lg bg-teal-500/15 border border-teal-500/30 text-teal-300 font-bold">
                  📖 {{ activeBankSoal.mapelNama }}
                </span>
                <span class="px-2.5 py-0.5 rounded-lg bg-purple-500/15 border border-purple-500/30 text-purple-300 font-black">
                  🎯 Total {{ activeBankSoal.soals?.length || 0 }} Butir Soal ({{ formatBobot(totalPoinActiveBank) }} Poin)
                </span>
              </div>
            </div>
          </div>

          <!-- Quick Actions on Header -->
          <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
            <button
              @click="openImportModal"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-emerald-500/20 transition-all active:scale-95 min-h-[42px]"
              title="Import puluhan butir soal sekaligus dari dokumen Word atau file CSV"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
              <span>📥 Import Massal (Word / CSV)</span>
            </button>
            <button
              @click="resetSoalFormToNew"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 font-black text-xs sm:text-sm transition-all active:scale-95 min-h-[42px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
              <span>+ Tulis Soal Baru</span>
            </button>
            <button
              @click="backToBankList"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold text-xs sm:text-sm border border-white/10 transition-all min-h-[42px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              <span>Kembali</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile Tab Switcher (Form Input vs Daftar Soal) -->
      <div class="lg:hidden grid grid-cols-2 gap-2 bg-slate-900 p-1.5 rounded-2xl border border-white/10">
        <button
          @click="mobileStudioTab = 'form'"
          :class="[
            'py-2.5 text-xs font-black rounded-xl transition-all',
            mobileStudioTab === 'form' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-gray-400 hover:text-white'
          ]"
        >
          ✍️ {{ isEditingSoal ? 'Edit Soal #' + soalForm.nomor_urut : 'Tulis Soal Baru' }}
        </button>
        <button
          @click="mobileStudioTab = 'list'"
          :class="[
            'py-2.5 text-xs font-black rounded-xl transition-all',
            mobileStudioTab === 'list' ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-gray-400 hover:text-white'
          ]"
        >
          📋 Daftar Soal ({{ activeBankSoal.soals?.length || 0 }})
        </button>
      </div>

      <!-- Two-Column Studio Layout for Easy, Ergonomic Typing -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- ============================================================ -->
        <!-- COLUMN 1: FULL EDITOR PENGETIKAN SOAL (7 COLS ON DESKTOP)   -->
        <!-- ============================================================ -->
        <div :class="['lg:col-span-7 space-y-6', mobileStudioTab === 'form' ? 'block' : 'hidden lg:block']">
          <div class="rounded-3xl bg-slate-900/95 border border-cyan-500/30 p-6 sm:p-8 shadow-2xl space-y-6">

            <!-- Form Title & Mode Banner -->
            <div class="flex items-center justify-between border-b border-white/10 pb-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-300 flex items-center justify-center text-lg font-black shrink-0">
                  {{ isEditingSoal ? '#' + soalForm.nomor_urut : '✍️' }}
                </div>
                <div>
                  <h3 class="text-base sm:text-lg font-black text-white">
                    {{ isEditingSoal ? 'Edit Butir Soal #' + soalForm.nomor_urut : 'Tulis Soal Pilihan Ganda Baru' }}
                  </h3>
                  <p class="text-xs text-gray-400">
                    Opsi pilihan jawaban A, B, C, D, E dengan kunci jawaban interaktif.
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <span
                  :class="[
                    'px-3 py-1 rounded-full text-xs font-black border',
                    isEditingSoal
                      ? 'bg-amber-500/15 border-amber-500/30 text-amber-300'
                      : 'bg-cyan-500/15 border-cyan-500/30 text-cyan-300'
                  ]"
                >
                  {{ isEditingSoal ? 'Mode Edit' : 'Menulis Baru' }}
                </span>
                <button
                  v-if="isEditingSoal"
                  @click="resetSoalFormToNew"
                  class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white text-xs font-bold"
                >
                  Ganti ke Soal Baru
                </button>
              </div>
            </div>

            <!-- Form Content -->
            <form @submit.prevent="submitSoalForm(false)" class="space-y-6">

              <!-- Pilihan Tipe / Model Soal (Interactive Game Assessment) -->
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider">
                    Model / Tipe Soal *
                  </label>
                  <span class="text-[10px] text-cyan-300 font-medium">Bisa divariasikan per butir soal</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <!-- Pilihan Ganda -->
                  <button
                    type="button"
                    @click="soalForm.tipe_soal = 'pilihan_ganda'"
                    :class="[
                      'p-2.5 rounded-2xl border text-left transition-all flex items-center gap-2.5 min-h-[44px]',
                      soalForm.tipe_soal === 'pilihan_ganda'
                        ? 'bg-cyan-500/20 border-cyan-400 text-white ring-1 ring-cyan-400/50 shadow-md shadow-cyan-500/10'
                        : 'bg-white/5 border-white/10 text-gray-400 hover:text-white hover:bg-white/10'
                    ]"
                  >
                    <span class="text-xl shrink-0">🔘</span>
                    <div class="min-w-0">
                      <span class="block text-xs font-black truncate">Pilihan Ganda</span>
                      <span class="block text-[10px] opacity-75 truncate">Opsi A - E</span>
                    </div>
                  </button>

                  <!-- Menjodohkan (Match) -->
                  <button
                    type="button"
                    @click="soalForm.tipe_soal = 'match'"
                    :class="[
                      'p-2.5 rounded-2xl border text-left transition-all flex items-center gap-2.5 min-h-[44px]',
                      soalForm.tipe_soal === 'match'
                        ? 'bg-emerald-500/20 border-emerald-400 text-white ring-1 ring-emerald-400/50 shadow-md shadow-emerald-500/10'
                        : 'bg-white/5 border-white/10 text-gray-400 hover:text-white hover:bg-white/10'
                    ]"
                  >
                    <span class="text-xl shrink-0">🧩</span>
                    <div class="min-w-0">
                      <span class="block text-xs font-black truncate">Menjodohkan</span>
                      <span class="block text-[10px] opacity-75 truncate">Pasang Kiri-Kanan</span>
                    </div>
                  </button>

                  <!-- Mengurutkan (Reorder) -->
                  <button
                    type="button"
                    @click="soalForm.tipe_soal = 'reorder'"
                    :class="[
                      'p-2.5 rounded-2xl border text-left transition-all flex items-center gap-2.5 min-h-[44px]',
                      soalForm.tipe_soal === 'reorder'
                        ? 'bg-amber-500/20 border-amber-400 text-white ring-1 ring-amber-400/50 shadow-md shadow-amber-500/10'
                        : 'bg-white/5 border-white/10 text-gray-400 hover:text-white hover:bg-white/10'
                    ]"
                  >
                    <span class="text-xl shrink-0">🔢</span>
                    <div class="min-w-0">
                      <span class="block text-xs font-black truncate">Mengurutkan</span>
                      <span class="block text-[10px] opacity-75 truncate">Alur / Tahapan</span>
                    </div>
                  </button>

                  <!-- Mengelompokkan (Categorize) -->
                  <button
                    type="button"
                    @click="soalForm.tipe_soal = 'categorize'"
                    :class="[
                      'p-2.5 rounded-2xl border text-left transition-all flex items-center gap-2.5 min-h-[44px]',
                      soalForm.tipe_soal === 'categorize'
                        ? 'bg-purple-500/20 border-purple-400 text-white ring-1 ring-purple-400/50 shadow-md shadow-purple-500/10'
                        : 'bg-white/5 border-white/10 text-gray-400 hover:text-white hover:bg-white/10'
                    ]"
                  >
                    <span class="text-xl shrink-0">📂</span>
                    <div class="min-w-0">
                      <span class="block text-xs font-black truncate">Kategori</span>
                      <span class="block text-[10px] opacity-75 truncate">Kelompokkan Item</span>
                    </div>
                  </button>
                </div>
              </div>

              <!-- Meta: Nomor Urut (Locked / Readonly) & Bobot Poin (Desimal) -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider">
                      Nomor Urut Soal
                    </label>
                    <span class="inline-flex items-center gap-1 text-[10px] font-black px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-300 border border-amber-500/30">
                      🔒 Otomatis &amp; Terurut
                    </span>
                  </div>
                  <input
                    :value="'Soal #' + soalForm.nomor_urut"
                    type="text"
                    readonly
                    disabled
                    class="w-full px-4 py-2.5 rounded-xl bg-white/[0.04] border border-white/10 text-cyan-300 font-mono font-bold text-sm cursor-not-allowed select-none min-h-[42px]"
                    title="Nomor soal dikunci dan diurutkan otomatis oleh sistem tanpa celah urutan."
                  />
                </div>
                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider">
                      Bobot Nilai (Poin) *
                    </label>
                    <span class="text-[10px] text-gray-400 font-medium">Bisa desimal, misal: 2,5</span>
                  </div>
                  <input
                    v-model="soalForm.bobot"
                    type="text"
                    inputmode="decimal"
                    required
                    placeholder="Contoh: 2.5 atau 2,5"
                    class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white font-mono font-bold text-sm focus:outline-none focus:border-cyan-400 min-h-[42px]"
                  />
                </div>
              </div>

              <!-- Accordion/Toggle Wacana / Teks Stimulus (Passage) -->
              <div class="rounded-2xl border border-white/10 bg-white/[0.02] overflow-hidden">
                <button
                  type="button"
                  @click="soalForm.show_stimulus = !soalForm.show_stimulus"
                  class="w-full px-4 py-3 flex items-center justify-between text-left hover:bg-white/5 transition-colors min-h-[42px]"
                >
                  <div class="flex items-center gap-2 text-xs font-black text-teal-300">
                    <span class="text-base">📜</span>
                    <span>Teks Stimulus / Wacana Bacaan (Passage)</span>
                    <span v-if="soalForm.stimulus_teks" class="text-[10px] px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-300 font-mono border border-teal-500/30">
                      Ada Wacana Aktif
                    </span>
                  </div>
                  <span class="text-xs text-gray-400 font-mono">{{ (soalForm.show_stimulus || soalForm.stimulus_teks) ? '▲ Tutup' : '▼ Buka / Tambah Wacana' }}</span>
                </button>
                <div v-if="soalForm.show_stimulus || soalForm.stimulus_teks" class="p-4 pt-1 space-y-2 border-t border-white/5 animate-in fade-in">
                  <p class="text-[11px] text-gray-400">
                    Sediakan bahan bacaan, artikel, cerita, atau teks studi kasus di sini. Teks ini akan tampil terintegrasi bagi siswa saat menjawab.
                  </p>
                  <textarea
                    v-model="soalForm.stimulus_teks"
                    rows="4"
                    placeholder="Ketikkan teks wacana / cerita / artikel stimulus di sini..."
                    class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-teal-400 leading-relaxed font-sans"
                  ></textarea>
                </div>
              </div>

              <!-- Teks Pertanyaan Soal (Large Textarea) -->
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <label class="text-xs font-bold text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
                    <span>Teks Pertanyaan / Soal Ujian *</span>
                  </label>

                  <!-- Helper Formatting Tools -->
                  <div class="hidden sm:flex items-center gap-1 text-[11px]">
                    <button
                      type="button"
                      @click="insertHelperText('bold')"
                      class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-gray-300 font-bold"
                      title="Sisipkan format tebal"
                    >
                      [B] Tebal
                    </button>
                    <button
                      type="button"
                      @click="insertHelperText('code')"
                      class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-gray-300 font-mono"
                      title="Sisipkan blok kode"
                    >
                      &lt;/&gt; Kode
                    </button>
                    <button
                      type="button"
                      @click="insertHelperText('list')"
                      class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-gray-300"
                      title="Sisipkan daftar"
                    >
                      1. List
                    </button>
                    <button
                      type="button"
                      @click="insertHelperText('quote')"
                      class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-gray-300"
                      title="Sisipkan kutipan studi kasus"
                    >
                      &ldquo; Kutipan
                    </button>
                    <button
                      type="button"
                      @click="insertHelperText('image_tag')"
                      class="px-2 py-0.5 rounded bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 font-bold border border-cyan-500/20 flex items-center gap-1"
                      title="Sisipkan penanda tag [gambar] di posisi teks mana pun yang Anda inginkan"
                    >
                      <span>🖼️</span>
                      <span>Tag [gambar]</span>
                    </button>
                  </div>
                </div>

                <textarea
                  v-model="soalForm.pertanyaan"
                  rows="5"
                  required
                  @paste="handlePasteQuestionMedia"
                  placeholder="Ketikkan teks soal / pertanyaan secara lengkap di sini. Anda juga dapat menempelkan (Ctrl+V) gambar langsung dari clipboard atau Word ke area ini..."
                  class="w-full px-4 py-3.5 rounded-2xl bg-white/5 border border-white/10 text-white text-sm leading-relaxed placeholder-gray-500 focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition-all font-sans"
                ></textarea>

                <!-- Hidden Inputs for Question Media -->
                <input
                  ref="questionImageInput"
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
                  class="hidden"
                  @change="handleQuestionImageSelected"
                />
                <input
                  ref="questionAudioInput"
                  type="file"
                  accept="audio/mp3,audio/wav,audio/ogg,audio/mpeg,audio/x-m4a,audio/m4a"
                  class="hidden"
                  @change="handleQuestionAudioSelected"
                />

                <!-- Media Upload Buttons Bar -->
                <div class="flex flex-wrap items-center gap-2 pt-1">
                  <span class="text-[11px] font-bold text-gray-400">Media Soal:</span>
                  <button
                    type="button"
                    @click="triggerQuestionImageUpload"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/25 text-xs font-bold transition-all min-h-[36px]"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>{{ soalForm.tipe_media_soal === 'image' && soalForm.media_soal_preview ? 'Ganti Gambar Soal' : '+ Gambar Soal (Ctrl+V)' }}</span>
                  </button>

                  <button
                    type="button"
                    @click="triggerQuestionAudioUpload"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/25 text-xs font-bold transition-all min-h-[36px]"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                    <span>{{ soalForm.tipe_media_soal === 'audio' && soalForm.media_soal_preview ? 'Ganti Audio Soal' : '+ Audio Listening (Voice)' }}</span>
                  </button>

                  <span class="text-[10px] text-gray-500 hidden sm:inline">(Maks 10MB Gambar / 12MB Audio • Bisa Paste Ctrl+V)</span>
                </div>

                <!-- Preview Active Media Soal -->
                <div v-if="soalForm.media_soal_preview" class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3">
                  <!-- Image Preview -->
                  <div v-if="soalForm.tipe_media_soal === 'image'" class="space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                      <div class="flex items-start gap-3">
                        <img
                          :src="soalForm.media_soal_preview"
                          alt="Preview Gambar Soal"
                          class="h-24 w-32 rounded-xl object-contain bg-black/40 border border-white/10 cursor-zoom-in hover:opacity-90 transition-opacity"
                          @click="openImageLightbox(soalForm.media_soal_preview)"
                        />
                        <div class="space-y-1">
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-cyan-500/15 text-cyan-300 text-[10px] font-black uppercase">
                            🖼️ Gambar Soal Terpasang
                          </span>
                          <p class="text-[11px] text-gray-400">Klik gambar untuk memperbesar pratinjau.</p>
                        </div>
                      </div>

                      <button
                        type="button"
                        @click="removeQuestionMedia"
                        class="px-3 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs font-bold flex items-center gap-1 transition-colors self-start min-h-[36px]"
                        title="Hapus media ini dari soal"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>Hapus Gambar</span>
                      </button>
                    </div>

                    <!-- Posisi Gambar Selector -->
                    <div class="pt-2 border-t border-white/10 flex flex-wrap items-center gap-2">
                      <span class="text-xs font-bold text-gray-300">Posisi Gambar Terhadap Teks:</span>
                      <div class="inline-flex rounded-xl bg-slate-800/80 p-1 border border-white/10 gap-1">
                        <button
                          type="button"
                          @click="soalForm.posisi_media_soal = 'bawah'"
                          :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-bold transition-all min-h-[32px]',
                            soalForm.posisi_media_soal === 'bawah'
                              ? 'bg-cyan-500 text-slate-950 font-black shadow-md'
                              : 'text-gray-400 hover:text-white'
                          ]"
                        >
                          ⬇️ Di Bawah Teks
                        </button>
                        <button
                          type="button"
                          @click="soalForm.posisi_media_soal = 'atas'"
                          :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-bold transition-all min-h-[32px]',
                            soalForm.posisi_media_soal === 'atas'
                              ? 'bg-cyan-500 text-slate-950 font-black shadow-md'
                              : 'text-gray-400 hover:text-white'
                          ]"
                        >
                          ⬆️ Di Atas Teks
                        </button>
                        <button
                          type="button"
                          @click="soalForm.posisi_media_soal = 'samping'"
                          :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-bold transition-all min-h-[32px]',
                            soalForm.posisi_media_soal === 'samping'
                              ? 'bg-cyan-500 text-slate-950 font-black shadow-md'
                              : 'text-gray-400 hover:text-white'
                          ]"
                        >
                          ↔️ Di Samping (2 Kolom)
                        </button>
                      </div>
                      <span class="text-[10.5px] text-gray-400">
                        (Bisa juga ketik tag <code class="text-cyan-300 font-mono">[gambar]</code> di tengah teks soal)
                      </span>
                    </div>
                  </div>

                  <!-- Audio Preview -->
                  <div v-else-if="soalForm.tipe_media_soal === 'audio'" class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                      <div class="flex items-center gap-2 text-purple-300 text-xs font-black">
                        <span class="text-base">🎙️</span>
                        <span>Audio Listening Terpasang</span>
                      </div>
                      <button
                        type="button"
                        @click="removeQuestionMedia"
                        class="px-3 py-1 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs font-bold flex items-center gap-1 transition-colors min-h-[36px]"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>Hapus Audio</span>
                      </button>
                    </div>
                    <audio controls :src="soalForm.media_soal_preview" class="w-full h-10 rounded-xl"></audio>
                  </div>
                </div>
              </div>

              <!-- Opsi Pilihan Ganda (A, B, C, D, E) Full-Size Inputs with Image Option -->
              <div v-if="soalForm.tipe_soal === 'pilihan_ganda'" class="space-y-3.5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-1">
                  <div>
                    <label class="block text-xs font-black text-gray-300 uppercase tracking-wider">
                      Isi Teks Pilihan Jawaban (A, B, C, D, E) &amp; Tentukan Kunci *
                    </label>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                      Ketik teks pilihan jawaban, sisipkan gambar bila perlu, lalu klik tombol huruf (<strong class="text-cyan-400">A / B / C / D / E</strong>) pada opsi yang merupakan kunci jawaban benar.
                    </p>
                  </div>
                  <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-black shrink-0 self-start sm:self-auto">
                    <span>🎯 Kunci Terpilih:</span>
                    <span class="font-mono text-sm uppercase">Opsi {{ soalForm.kunci_jawaban }}</span>
                  </div>
                </div>

                <div
                  v-for="opt in [
                    { key: 'opsi_a', label: 'A', imgKey: 'gambar_opsi_a' },
                    { key: 'opsi_b', label: 'B', imgKey: 'gambar_opsi_b' },
                    { key: 'opsi_c', label: 'C', imgKey: 'gambar_opsi_c' },
                    { key: 'opsi_d', label: 'D', imgKey: 'gambar_opsi_d' },
                    { key: 'opsi_e', label: 'E', imgKey: 'gambar_opsi_e' },
                  ]"
                  :key="opt.key"
                  :class="[
                    'p-3 sm:p-4 rounded-2xl border transition-all duration-200 flex flex-col gap-2.5',
                    soalForm.kunci_jawaban === opt.label
                      ? 'bg-emerald-500/10 border-emerald-500/40 ring-1 ring-emerald-500/30'
                      : 'bg-white/[0.02] border-white/10 hover:border-white/20'
                  ]"
                >
                  <!-- Hidden Input for Option Image -->
                  <input
                    :id="'opt-img-input-' + opt.label.toLowerCase()"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    class="hidden"
                    @change="handleOptionImageSelected(opt.label, $event)"
                  />

                  <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <!-- Letter Badge / Key Selector Button -->
                    <div class="flex items-center justify-between sm:justify-start gap-3 shrink-0">
                      <button
                        type="button"
                        @click="setKunci(opt.label)"
                        :title="'Klik untuk jadikan opsi ' + opt.label + ' sebagai kunci jawaban'"
                        :class="[
                          'w-10 h-10 rounded-xl font-black text-sm flex items-center justify-center transition-all min-h-[40px] min-w-[40px]',
                          soalForm.kunci_jawaban === opt.label
                            ? 'bg-emerald-500 text-slate-950 ring-2 ring-emerald-300 shadow-md shadow-emerald-500/20'
                            : 'bg-white/10 text-gray-300 hover:bg-cyan-500 hover:text-slate-950'
                        ]"
                      >
                        {{ opt.label }}
                      </button>
                      <span
                        v-if="soalForm.kunci_jawaban === opt.label"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-black uppercase tracking-wider border border-emerald-500/30"
                      >
                        ✓ Kunci Benar
                      </span>
                      <button
                        v-else
                        type="button"
                        @click="setKunci(opt.label)"
                        class="px-2.5 py-1 rounded-full text-[11px] font-bold text-gray-400 hover:text-emerald-300 hover:bg-emerald-500/10 border border-transparent hover:border-emerald-500/30 transition-all"
                      >
                        Pilih Jadi Kunci
                      </button>
                    </div>

                    <!-- Text Input for Option & Upload Image Button -->
                    <div class="flex-1 flex items-center gap-2">
                      <input
                        v-model="soalForm[opt.key]"
                        type="text"
                        :placeholder="'Tulis pilihan jawaban untuk opsi ' + opt.label + '...'"
                        class="flex-1 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs sm:text-sm placeholder-gray-500 focus:outline-none focus:border-cyan-400 min-h-[42px]"
                      />

                      <!-- Button Upload Gambar Opsi -->
                      <button
                        type="button"
                        @click="triggerOptionImageInput(opt.label)"
                        :title="'Unggah gambar ilustrasi/grafik untuk opsi ' + opt.label"
                        class="px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-cyan-300 border border-white/10 text-xs font-bold flex items-center gap-1.5 shrink-0 min-h-[42px]"
                      >
                        <span>🖼️</span>
                        <span class="hidden sm:inline">{{ soalForm[opt.imgKey + '_preview'] ? 'Ganti' : '+ Gambar' }}</span>
                      </button>
                    </div>
                  </div>

                  <!-- Preview Gambar Opsi (if uploaded) -->
                  <div
                    v-if="soalForm[opt.imgKey + '_preview']"
                    class="flex items-center gap-3 pt-2 border-t border-white/5"
                  >
                    <img
                      :src="soalForm[opt.imgKey + '_preview']"
                      :alt="'Gambar Opsi ' + opt.label"
                      class="h-16 w-24 rounded-lg object-contain bg-black/40 border border-white/10 cursor-zoom-in hover:opacity-80 transition-opacity"
                      @click="openImageLightbox(soalForm[opt.imgKey + '_preview'])"
                    />
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="text-[11px] text-gray-300 font-medium">Gambar Opsi {{ opt.label }} terpasang</span>
                      <button
                        type="button"
                        @click="removeOptionImage(opt.label)"
                        class="px-2 py-1 rounded-lg bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 text-xs font-bold transition-colors min-h-[36px] flex items-center gap-1"
                        title="Hapus gambar dari opsi ini"
                      >
                        ✕ Hapus Gambar
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- MODEL MENJODOHKAN (MATCH) BUILDER -->
              <div v-else-if="soalForm.tipe_soal === 'match'" class="space-y-4 p-5 rounded-3xl bg-emerald-950/20 border border-emerald-500/30 animate-in fade-in">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-emerald-500/20 pb-3">
                  <div>
                    <h4 class="text-xs sm:text-sm font-black text-emerald-300 flex items-center gap-2">
                      <span>🧩 Pasangan Menjodohkan (Match)</span>
                    </h4>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                      Tuliskan pasangan yang benar antara Kolom Kiri dan Kolom Kanan. Pilihan kanan akan otomatis diacak saat siswa mengerjakan.
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="addMatchPair"
                    class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs transition-all shadow-md shadow-emerald-500/20 flex items-center gap-1.5 self-start sm:self-auto min-h-[38px]"
                  >
                    <span>+ Tambah Pasangan</span>
                  </button>
                </div>

                <div class="space-y-3">
                  <div
                    v-for="(pair, pIdx) in soalForm.match_pairs"
                    :key="pair.id"
                    class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 flex flex-col sm:flex-row items-center gap-3"
                  >
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-300 font-mono font-black text-xs flex items-center justify-center shrink-0 border border-emerald-500/30">
                      {{ pIdx + 1 }}
                    </div>
                    <div class="flex-1 w-full sm:w-auto">
                      <label class="block text-[10px] text-gray-400 font-bold uppercase mb-1">Informasi Kiri (Pernyataan / Soal)</label>
                      <input
                        v-model="pair.kiri"
                        type="text"
                        placeholder="Misal: Ibukota Indonesia"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-emerald-400 min-h-[40px]"
                      />
                    </div>
                    <span class="text-emerald-400 text-xl hidden sm:inline font-bold">➔</span>
                    <div class="flex-1 w-full sm:w-auto">
                      <label class="block text-[10px] text-gray-400 font-bold uppercase mb-1">Jawaban Kanan (Pasangan Benar)</label>
                      <input
                        v-model="pair.kanan"
                        type="text"
                        placeholder="Misal: Jakarta"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-emerald-400 min-h-[40px]"
                      />
                    </div>
                    <button
                      type="button"
                      @click="removeMatchPair(pIdx)"
                      class="p-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors self-end sm:self-center min-h-[38px] min-w-[38px] flex items-center justify-center"
                      title="Hapus baris pasangan ini"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- MODEL MENGURUTKAN (REORDER) BUILDER -->
              <div v-else-if="soalForm.tipe_soal === 'reorder'" class="space-y-4 p-5 rounded-3xl bg-amber-950/20 border border-amber-500/30 animate-in fade-in">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-amber-500/20 pb-3">
                  <div>
                    <h4 class="text-xs sm:text-sm font-black text-amber-300 flex items-center gap-2">
                      <span>🔢 Urutan Benar (Reorder Steps)</span>
                    </h4>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                      Tuliskan alur peristiwa/langkah dalam urutan yang BENAR dari atas ke bawah. Sistem akan mengacaknya saat ujian.
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="addReorderItem"
                    class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs transition-all shadow-md shadow-amber-500/20 flex items-center gap-1.5 self-start sm:self-auto min-h-[38px]"
                  >
                    <span>+ Tambah Butir Urutan</span>
                  </button>
                </div>

                <div class="space-y-3">
                  <div
                    v-for="(item, rIdx) in soalForm.reorder_items"
                    :key="item.id"
                    class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 flex items-center gap-3"
                  >
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-300 font-mono font-black text-xs flex items-center justify-center shrink-0 border border-amber-500/30">
                      {{ rIdx + 1 }}
                    </div>
                    <div class="flex-1">
                      <input
                        v-model="item.teks"
                        type="text"
                        :placeholder="'Langkah / Tahap urutan ke-' + (rIdx + 1) + '...'"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-amber-400 min-h-[40px]"
                      />
                    </div>
                    <button
                      type="button"
                      @click="removeReorderItem(rIdx)"
                      class="p-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors shrink-0 min-h-[38px] min-w-[38px] flex items-center justify-center"
                      title="Hapus butir urutan ini"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- MODEL MENGELOMPOKKAN (CATEGORIZE) BUILDER -->
              <div v-else-if="soalForm.tipe_soal === 'categorize'" class="space-y-4 p-5 rounded-3xl bg-purple-950/20 border border-purple-500/30 animate-in fade-in">
                <div class="border-b border-purple-500/20 pb-3">
                  <h4 class="text-xs sm:text-sm font-black text-purple-300 flex items-center gap-2">
                    <span>📂 Pengelompokan Kategori (Categorize)</span>
                  </h4>
                  <p class="text-[11px] text-gray-400 mt-0.5">
                    Tentukan nama kelompok kategori di Bagian 1, lalu masukkan butir-butir item beserta kategori benarnya di Bagian 2.
                  </p>
                </div>

                <!-- Bagian 1: Daftar Nama Kategori -->
                <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-300 uppercase tracking-wider">
                      Bagian 1: Nama Kelompok Kategori
                    </span>
                    <button
                      type="button"
                      @click="addCategory"
                      class="px-2.5 py-1 rounded-lg bg-purple-500/20 text-purple-300 text-xs font-black hover:bg-purple-500/30 transition-colors"
                    >
                      + Tambah Kategori
                    </button>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <div
                      v-for="(cat, cIdx) in soalForm.categorize_data.kategori"
                      :key="cIdx"
                      class="flex items-center gap-1.5 p-1.5 rounded-xl bg-white/5 border border-purple-500/30"
                    >
                      <input
                        v-model="soalForm.categorize_data.kategori[cIdx]"
                        type="text"
                        placeholder="Nama Kategori..."
                        class="flex-1 px-2.5 py-1.5 rounded-lg bg-transparent text-white text-xs font-bold focus:outline-none"
                      />
                      <button
                        type="button"
                        @click="removeCategory(cIdx)"
                        class="p-1 text-gray-500 hover:text-rose-400"
                        title="Hapus kategori"
                      >
                        ✕
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Bagian 2: Daftar Item yang Harus Dikelompokkan -->
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-300 uppercase tracking-wider">
                      Bagian 2: Butir Item &amp; Kategori Tujuannya
                    </span>
                    <button
                      type="button"
                      @click="addCategorizeItem"
                      class="px-3 py-1.5 rounded-xl bg-purple-500 hover:bg-purple-400 text-slate-950 font-black text-xs transition-all shadow-md shadow-purple-500/20 flex items-center gap-1.5 min-h-[38px]"
                    >
                      <span>+ Tambah Butir Item</span>
                    </button>
                  </div>

                  <div class="space-y-2.5">
                    <div
                      v-for="(it, iIdx) in soalForm.categorize_data.items"
                      :key="it.id"
                      class="p-3 rounded-xl bg-white/[0.03] border border-white/10 flex flex-col sm:flex-row items-center gap-3"
                    >
                      <div class="flex-1 w-full sm:w-auto">
                        <input
                          v-model="it.teks"
                          type="text"
                          placeholder="Teks item (misal: Harimau / Segitiga Sama Sisi)..."
                          class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-purple-400 min-h-[40px]"
                        />
                      </div>
                      <div class="w-full sm:w-48 shrink-0">
                        <select
                          v-model="it.kategori"
                          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/10 text-purple-300 font-bold text-xs focus:outline-none focus:border-purple-400 min-h-[40px]"
                        >
                          <option
                            v-for="catOpt in soalForm.categorize_data.kategori"
                            :key="catOpt"
                            :value="catOpt"
                          >
                            {{ catOpt }}
                          </option>
                        </select>
                      </div>
                      <button
                        type="button"
                        @click="removeCategorizeItem(iIdx)"
                        class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors shrink-0 self-end sm:self-center min-h-[38px] min-w-[38px] flex items-center justify-center"
                        title="Hapus item ini"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>



              <!-- Action Bar (Buttons) -->
              <div class="pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3">
                <button
                  type="button"
                  @click="resetSoalFormToNew"
                  class="w-full sm:w-auto px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 text-gray-300 font-bold text-xs sm:text-sm transition-colors min-h-[44px]"
                >
                  Bersihkan Form
                </button>

                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                  <button
                    type="button"
                    @click="submitSoalForm(true)"
                    class="flex-1 sm:flex-initial px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-cyan-300 border border-cyan-500/40 font-bold text-xs sm:text-sm transition-all min-h-[44px]"
                    title="Simpan soal saat ini dan langsung buka form nomor berikutnya"
                  >
                    Simpan &amp; Lanjut Soal Berikutnya ➔
                  </button>
                  <button
                    type="submit"
                    class="flex-1 sm:flex-initial px-6 py-3 rounded-2xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-cyan-500/20 active:scale-95 min-h-[44px]"
                  >
                    {{ isEditingSoal ? 'Simpan Perubahan Soal' : 'Simpan Soal Ini' }}
                  </button>
                </div>
              </div>

            </form>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- COLUMN 2: DAFTAR BUTIR SOAL & NAVIGATOR (5 COLS ON DESKTOP) -->
        <!-- ============================================================ -->
        <div :class="['lg:col-span-5 space-y-6', mobileStudioTab === 'list' ? 'block' : 'hidden lg:block']">
          <div class="rounded-3xl bg-slate-900/95 border border-white/10 p-5 sm:p-6 shadow-2xl space-y-5">

            <!-- Navigator Header & Search -->
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
              <div>
                <h4 class="text-sm sm:text-base font-black text-white flex items-center gap-2">
                  <span>📋 Daftar Butir Soal</span>
                  <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 text-xs font-mono font-black">
                    {{ activeBankSoal.soals?.length || 0 }} Butir
                  </span>
                </h4>
                <p class="text-[11px] text-gray-400 mt-0.5">Klik nomor soal atau kartu soal untuk mengedit.</p>
              </div>

              <div class="flex items-center gap-1.5">
                <button
                  @click="openImportModal"
                  class="px-2.5 py-1.5 rounded-xl bg-emerald-500/20 text-emerald-300 text-xs font-black hover:bg-emerald-500/30 transition-colors flex items-center gap-1"
                  title="Import puluhan butir soal dari Word atau file CSV"
                >
                  <span>📥 Import</span>
                </button>
                <button
                  @click="resetSoalFormToNew"
                  class="px-3 py-1.5 rounded-xl bg-cyan-500/20 text-cyan-300 text-xs font-black hover:bg-cyan-500/30 transition-colors"
                >
                  + Tambah
                </button>
              </div>
            </div>

            <!-- Panel Setting All Bobot Massal -->
            <div class="p-3.5 rounded-2xl bg-gradient-to-r from-indigo-950/60 to-slate-900 border border-indigo-500/30 space-y-2.5">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <span class="text-sm">⚡</span>
                  <span class="text-xs font-black text-indigo-300 uppercase tracking-wider">Setting Bobot Massal (All Soal)</span>
                </div>
                <span class="text-[10px] text-gray-400">Terapkan 1 nilai untuk seluruh butir</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="relative flex-1">
                  <input
                    v-model="bulkBobotInput"
                    type="text"
                    inputmode="decimal"
                    placeholder="Misal: 2,5"
                    class="w-full px-3 py-2 rounded-xl bg-white/5 border border-indigo-500/30 text-white font-mono font-bold text-xs focus:outline-none focus:border-indigo-400 min-h-[38px]"
                  />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-indigo-300">Poin/Soal</span>
                </div>
                <button
                  type="button"
                  @click="submitBulkBobot"
                  :disabled="isSubmittingBulkBobot || !activeBankSoal.soals?.length"
                  class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:bg-gray-800 disabled:text-gray-500 disabled:cursor-not-allowed text-white font-black text-xs transition-all flex items-center gap-1.5 shrink-0 min-h-[38px] shadow-md shadow-indigo-600/30"
                  title="Terapkan bobot ini ke seluruh butir soal di paket ini"
                >
                  <span v-if="isSubmittingBulkBobot" class="inline-block animate-spin">⏳</span>
                  <span>{{ isSubmittingBulkBobot ? 'Menyimpan...' : 'Terapkan ke Semua' }}</span>
                </button>
              </div>
              <div v-if="activeBankSoal.soals?.length > 0" class="text-[10.5px] text-indigo-200/70 flex items-center justify-between px-1">
                <span>Total estimasi poin:</span>
                <span class="font-mono font-bold text-indigo-300">
                  {{ formatBobot(activeBankSoal.soals.length * (parseFloat(String(bulkBobotInput).replace(',', '.')) || 0)) }} Poin
                </span>
              </div>
            </div>

            <!-- Quick Number Grid Navigator -->
            <div v-if="activeBankSoal.soals?.length > 0" class="space-y-2">
              <span class="text-[10px] font-black text-gray-400 uppercase tracking-wider font-mono">
                Navigasi Cepat Nomor Soal
              </span>
              <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto custom-scrollbar p-1">
                <button
                  v-for="(s, idx) in activeBankSoal.soals"
                  :key="s.id"
                  @click="editSoal(s)"
                  :class="[
                    'w-9 h-9 rounded-xl font-black text-xs transition-all flex items-center justify-center min-h-[36px] min-w-[36px]',
                    soalForm.id === s.id
                      ? 'bg-cyan-400 text-slate-950 ring-2 ring-cyan-200 shadow-md shadow-cyan-400/30'
                      : 'bg-white/5 text-gray-300 hover:bg-white/15 border border-white/10'
                  ]"
                >
                  {{ s.nomorUrut || (idx + 1) }}
                </button>
              </div>
            </div>

            <!-- Search Inside Questions -->
            <div class="relative">
              <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input
                v-model="soalSearchQuery"
                type="text"
                placeholder="Cari pertanyaan atau opsi..."
                class="w-full pl-9 pr-3 py-2 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-cyan-400 min-h-[38px]"
              />
            </div>

            <!-- Questions Cards List (Scrollable) -->
            <div class="space-y-3 max-h-[750px] overflow-y-auto pr-1 custom-scrollbar">
              <div
                v-if="filteredSoalsInActiveBank.length === 0"
                class="text-center py-12 px-4 rounded-2xl bg-white/[0.02] border border-white/5"
              >
                <span class="text-3xl block mb-2">📝</span>
                <p class="text-xs font-bold text-gray-300">Belum Ada Soal</p>
                <p class="text-[11px] text-gray-500 mt-1">Gunakan form di sebelah kiri untuk mulai menulis butir soal pertama.</p>
              </div>

              <div
                v-for="(s, idx) in filteredSoalsInActiveBank"
                :key="s.id"
                :class="[
                  'p-4 rounded-2xl border transition-all duration-200 space-y-2.5',
                  soalForm.id === s.id
                    ? 'bg-cyan-950/40 border-cyan-500/50 ring-1 ring-cyan-500/30'
                    : 'bg-white/[0.03] hover:bg-white/[0.06] border-white/10'
                ]"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="w-6 h-6 rounded-lg bg-cyan-500/20 text-cyan-300 font-black text-xs flex items-center justify-center">
                      {{ s.nomorUrut || (idx + 1) }}
                    </span>
                    <span class="text-[11px] font-mono font-bold text-cyan-300 px-2 py-0.5 rounded-md bg-cyan-500/10 border border-cyan-500/20">
                      {{ formatBobot(s.bobot) }} Poin
                    </span>

                    <!-- Badge Model Soal -->
                    <span v-if="s.tipeSoal === 'match'" class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-bold text-[10px] border border-emerald-500/30">
                      🧩 Menjodohkan
                    </span>
                    <span v-else-if="s.tipeSoal === 'reorder'" class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-bold text-[10px] border border-amber-500/30">
                      🔢 Mengurutkan
                    </span>
                    <span v-else-if="s.tipeSoal === 'categorize'" class="inline-flex items-center px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 font-bold text-[10px] border border-purple-500/30">
                      📂 Kategori
                    </span>
                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 font-bold text-[10px] border border-cyan-500/30">
                      🔘 Pilihan Ganda ({{ s.kunciJawaban }})
                    </span>

                    <span v-if="s.stimulusTeks" class="inline-flex items-center px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-300 font-bold text-[10px] border border-teal-500/30">
                      📜 Wacana
                    </span>
                  </div>

                  <div class="flex items-center gap-1">
                    <button
                      @click="editSoal(s)"
                      title="Edit Butir Soal"
                      class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </button>
                    <button
                      @click="deleteSoal(s.id)"
                      title="Hapus Butir Soal"
                      class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </div>

                <!-- Pertanyaan preview -->
                <p class="text-xs font-semibold text-white whitespace-pre-line leading-relaxed line-clamp-3">
                  {{ s.pertanyaan }}
                </p>

                <!-- Question Media Badge / Thumbnail in Card -->
                <div v-if="s.mediaSoal" class="pt-0.5">
                  <div v-if="s.tipeMediaSoal === 'image'" class="flex items-center gap-2">
                    <img
                      :src="s.mediaSoal"
                      alt="Thumbnail Soal"
                      class="h-10 w-14 rounded-lg object-contain bg-black/40 border border-white/10 cursor-zoom-in hover:opacity-80 transition-opacity"
                      @click="openImageLightbox(s.mediaSoal)"
                    />
                    <span class="text-[10px] text-cyan-300 font-bold">🖼️ Lampiran Gambar</span>
                  </div>
                  <div v-else-if="s.tipeMediaSoal === 'audio'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-purple-500/15 border border-purple-500/30 text-purple-300 text-[11px] font-bold">
                    <span>🎙️ Audio Listening</span>
                  </div>
                </div>

                <!-- Opsi mini preview -->
                <div class="space-y-1 text-[11px] pt-1 border-t border-white/5">
                  <div
                    v-for="opt in [
                      { k: 'A', val: s.opsiA, img: s.gambarOpsiA },
                      { k: 'B', val: s.opsiB, img: s.gambarOpsiB },
                      { k: 'C', val: s.opsiC, img: s.gambarOpsiC },
                      { k: 'D', val: s.opsiD, img: s.gambarOpsiD },
                      { k: 'E', val: s.opsiE, img: s.gambarOpsiE },
                    ]"
                    :key="opt.k"
                    :class="[
                      'px-2 py-0.5 rounded flex items-center justify-between gap-1.5 truncate',
                      s.kunciJawaban === opt.k
                        ? 'bg-emerald-500/15 text-emerald-200 font-bold'
                        : 'text-gray-400'
                    ]"
                  >
                    <div class="flex items-center gap-1.5 truncate">
                      <span class="font-mono font-bold">{{ opt.k }}.</span>
                      <span class="truncate">{{ opt.val }}</span>
                    </div>
                    <span v-if="opt.img" class="shrink-0 text-[10px] text-cyan-400 font-mono" title="Ada gambar opsi">🖼️</span>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL FORM PAKET BANK SOAL (ONLY FOR CREATING/EDITING THE BANK HEADER)   -->
    <!-- ========================================================================= -->
    <div v-if="showBankModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto">
      <div class="w-full max-w-xl rounded-3xl bg-slate-900 border border-white/10 p-6 sm:p-8 space-y-6 shadow-2xl my-8">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div>
            <h3 class="text-lg font-black text-white">
              {{ isEditingBank ? 'Edit Paket Bank Soal' : 'Buat Paket Bank Soal Baru' }}
            </h3>
            <p class="text-xs text-gray-400 mt-0.5">Konfigurasi data identitas paket bank soal CBT.</p>
          </div>
          <button @click="showBankModal = false" class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <form @submit.prevent="submitBankForm" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Judul Bank Soal *</label>
            <input
              v-model="bankForm.judul"
              type="text"
              required
              placeholder="Contoh: Penilaian Harian CBT: Konsep Pemrograman Web"
              class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-sm focus:outline-none focus:border-cyan-400 min-h-[42px]"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Kode Paket</label>
              <input
                v-model="bankForm.kode"
                type="text"
                placeholder="BS-RPL-01"
                class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-sm focus:outline-none focus:border-cyan-400 min-h-[42px]"
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Mata Pelajaran *</label>
              <select
                v-model="bankForm.mata_pelajaran_id"
                required
                class="w-full px-4 py-2.5 rounded-xl bg-slate-800 border border-white/10 text-white text-sm focus:outline-none focus:border-cyan-400 min-h-[42px]"
              >
                <option value="">Pilih Mata Pelajaran</option>
                <option v-for="m in mapelList" :key="m.id" :value="m.id">{{ m.nama }}</option>
              </select>
            </div>
          </div>



          <div>
            <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Deskripsi / Petunjuk</label>
            <textarea
              v-model="bankForm.deskripsi"
              rows="3"
              placeholder="Petunjuk pengerjaan ujian untuk siswa..."
              class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-sm focus:outline-none focus:border-cyan-400"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
            <button
              type="button"
              @click="showBankModal = false"
              class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 font-bold text-xs sm:text-sm min-h-[42px]"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-cyan-500/20 active:scale-95 min-h-[42px]"
            >
              {{ isEditingBank ? 'Simpan Perubahan' : 'Buat Bank Soal' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL SMART IMPORT SOAL MASSAL (WORD COPY-PASTE & CSV TEMPLATE)           -->
    <!-- ========================================================================= -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/85 backdrop-blur-md overflow-y-auto">
      <div class="w-full max-w-4xl rounded-3xl bg-slate-900 border border-cyan-500/30 p-5 sm:p-7 space-y-6 shadow-2xl my-6 max-h-[92vh] flex flex-col">
        
        <!-- Modal Header -->
        <div class="flex items-start justify-between border-b border-white/10 pb-4 shrink-0">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-[11px] font-black uppercase tracking-wider mb-1.5">
              <span>⚡ Smart Bulk Question Importer</span>
            </div>
            <h3 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
              <span>Import Butir Soal CBT Massal</span>
            </h3>
            <p class="text-xs sm:text-sm text-gray-400 mt-0.5">
              Paket Tujuan: <strong class="text-cyan-300">{{ activeBankSoal?.judul }}</strong> ({{ activeBankSoal?.kode }})
            </p>
          </div>
          <button
            @click="showImportModal = false"
            class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Tab Switcher (Word Paste vs CSV Upload) -->
        <div class="grid grid-cols-2 gap-2 bg-slate-950/60 p-1.5 rounded-2xl border border-white/10 shrink-0">
          <button
            type="button"
            @click="importTab = 'word'"
            :class="[
              'py-2.5 px-4 text-xs sm:text-sm font-black rounded-xl transition-all flex items-center justify-center gap-2',
              importTab === 'word'
                ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 shadow-md shadow-emerald-500/20'
                : 'text-gray-400 hover:text-white'
            ]"
          >
            <span>📋 Tempel Teks dari Word</span>
            <span class="text-[10px] uppercase px-2 py-0.5 rounded-full bg-black/20 font-bold hidden sm:inline">Paling Cepat</span>
          </button>
          <button
            type="button"
            @click="importTab = 'csv'"
            :class="[
              'py-2.5 px-4 text-xs sm:text-sm font-black rounded-xl transition-all flex items-center justify-center gap-2',
              importTab === 'csv'
                ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 shadow-md shadow-emerald-500/20'
                : 'text-gray-400 hover:text-white'
            ]"
          >
            <span>📊 Upload Template CSV</span>
          </button>
        </div>

        <!-- Scrollable Modal Body -->
        <div class="space-y-6 overflow-y-auto pr-1 custom-scrollbar flex-1">

          <!-- TAB 1: WORD SMART TEXT PARSER -->
          <div v-if="importTab === 'word'" class="space-y-4">
            <!-- Instructions & Quick Sample -->
            <div class="p-4 rounded-2xl bg-cyan-950/30 border border-cyan-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="space-y-1">
                <p class="text-xs font-bold text-cyan-200 flex items-center gap-1.5">
                  <span>💡 Cara Cepat Import dari Microsoft Word:</span>
                </p>
                <p class="text-[11px] text-gray-300 leading-relaxed">
                  Buka file Word soal Anda, tekan <kbd class="px-1.5 py-0.5 rounded bg-white/10 font-mono text-cyan-300">Ctrl+A</kbd> lalu <kbd class="px-1.5 py-0.5 rounded bg-white/10 font-mono text-cyan-300">Ctrl+C</kbd>, kemudian tempel (<kbd class="px-1.5 py-0.5 rounded bg-white/10 font-mono text-cyan-300">Ctrl+V</kbd>) di kotak teks di bawah. Format didukung: <code class="text-cyan-300">1. Pertanyaan</code>, <code class="text-cyan-300">A. Opsi</code>, <code class="text-cyan-300">Kunci: B</code>, serta penanda <code class="text-amber-300">[gambar]</code> jika soal memiliki gambar/diagram.
                </p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <button
                  type="button"
                  @click="loadSampleWordText"
                  class="px-3 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 text-xs font-black transition-all"
                >
                  Contoh Format Word
                </button>
                <button
                  v-if="rawImportText"
                  type="button"
                  @click="rawImportText = ''; parsedQuestions = []"
                  class="px-2.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-rose-400 text-xs font-bold transition-all"
                >
                  Bersihkan
                </button>
              </div>
            </div>

            <!-- Textarea Area -->
            <div class="space-y-2">
              <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider">
                Tempel Seluruh Teks Soal dari Word di Sini:
              </label>
              <textarea
                v-model="rawImportText"
                rows="8"
                placeholder="Contoh:
1. Apa fungsi utama RAM pada komputer?
A. Penyimpanan data permanen
B. Memori utama sementara berkecepatan tinggi
C. Mengolah instruksi grafis
D. Menyuplai daya
E. Mencetak dokumen
Kunci: B

2. Protokol untuk transfer web aman adalah...
A. HTTP
B. HTTPS
C. FTP
D. SMTP
E. Telnet
Kunci: B"
                class="w-full px-4 py-3 rounded-2xl bg-white/5 border border-white/10 text-white font-mono text-xs sm:text-sm leading-relaxed placeholder-gray-500 focus:outline-none focus:border-cyan-400 custom-scrollbar"
              ></textarea>
            </div>

            <!-- Parser Action Bar -->
            <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3">
              <div class="flex items-center gap-2 w-full sm:w-auto">
                <label class="text-xs font-bold text-gray-300 shrink-0">Bobot Default:</label>
                <input
                  v-model="importDefaultBobot"
                  type="text"
                  placeholder="Misal: 2,0"
                  class="w-24 px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white font-mono font-bold text-xs text-center focus:outline-none focus:border-cyan-400 min-h-[38px]"
                />
                <span class="text-xs text-gray-400">Poin / butir soal</span>
              </div>

              <button
                type="button"
                @click="parseRawImportText"
                class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-md shadow-cyan-500/20 active:scale-95 transition-all min-h-[40px] flex items-center justify-center gap-2"
              >
                <span>⚡ Ekstrak &amp; Pratinjau Soal</span>
              </button>
            </div>
          </div>

          <!-- TAB 2: CSV TEMPLATE UPLOAD -->
          <div v-else-if="importTab === 'csv'" class="space-y-5">
            <div class="p-5 rounded-2xl bg-indigo-950/30 border border-indigo-500/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div class="space-y-1">
                <h4 class="text-sm font-black text-white flex items-center gap-2">
                  <span>📥 Template Spreadsheet CSV</span>
                </h4>
                <p class="text-xs text-gray-300">
                  Unduh format template CSV, isi baris pertanyaan dan opsi di Excel, lalu unggah kembali file tersebut di sini.
                </p>
              </div>
              <button
                type="button"
                @click="downloadCsvTemplate"
                class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-black text-xs sm:text-sm shadow-md shadow-indigo-600/30 transition-all active:scale-95 min-h-[40px] flex items-center gap-2 shrink-0"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Unduh Format CSV</span>
              </button>
            </div>

            <!-- Upload Drop Area -->
            <div class="relative border-2 border-dashed border-white/20 hover:border-cyan-400 rounded-3xl p-8 text-center transition-colors bg-white/[0.01]">
              <input
                type="file"
                accept=".csv,text/csv"
                @change="handleCsvUpload"
                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
              />
              <div class="space-y-3 pointer-events-none">
                <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 text-cyan-300 mx-auto flex items-center justify-center text-xl">
                  📄
                </div>
                <div>
                  <p class="text-sm font-black text-white">Klik atau Tarik File CSV ke Sini</p>
                  <p class="text-xs text-gray-400 mt-0.5">Mendukung file .csv dari Excel atau Google Sheets</p>
                </div>
              </div>
            </div>
          </div>

          <!-- INTERACTIVE PREVIEW SECTION (WHEN QUESTIONS PARSED) -->
          <div v-if="parsedQuestions.length > 0" class="space-y-4 pt-4 border-t border-white/10">
            <!-- Preview Stat Banner -->
            <div class="p-4 rounded-2xl bg-slate-950/80 border border-cyan-500/30 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
              <div class="flex items-center gap-3 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-500/20 text-emerald-300 font-black text-xs border border-emerald-500/30">
                  <span>✅</span>
                  <span>{{ parsedQuestions.length }} Butir Soal Terdeteksi</span>
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-purple-500/20 text-purple-300 font-black text-xs border border-purple-500/30">
                  <span>🎯</span>
                  <span>Total Estimasi {{ formatBobot(parsedQuestions.reduce((sum, q) => sum + (parseFloat(String(q.bobot).replace(',', '.')) || 0), 0)) }} Poin</span>
                </span>
              </div>

              <!-- Quick Search inside preview -->
              <div class="w-full md:w-64">
                <input
                  v-model="importSearchQuery"
                  type="text"
                  placeholder="Cari dalam hasil pratinjau..."
                  class="w-full px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-cyan-400 min-h-[36px]"
                />
              </div>
            </div>

            <!-- Preview Questions Cards List -->
            <div class="space-y-3 max-h-96 overflow-y-auto pr-1 custom-scrollbar">
              <div
                v-for="(q, idx) in filteredParsedQuestions"
                :key="idx"
                @paste="handlePasteOnParsedItem(idx, $event)"
                class="p-4 rounded-2xl bg-white/[0.02] hover:bg-white/[0.04] border border-white/10 transition-all space-y-3"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="w-7 h-7 rounded-xl bg-cyan-500/20 text-cyan-300 font-black text-xs flex items-center justify-center">
                      #{{ q.nomor_urut }}
                    </span>
                    <span
                      :class="[
                        'px-2 py-0.5 rounded-full text-[10px] font-black border',
                        q.is_key_detected
                          ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300'
                          : 'bg-amber-500/15 border-amber-500/30 text-amber-300'
                      ]"
                    >
                      {{ q.is_key_detected ? '✓ Kunci: Opsi ' + q.kunci_jawaban : '⚠️ Kunci Otomatis (A) - Silakan Cek' }}
                    </span>
                    <span class="text-[11px] font-mono font-bold text-gray-400">
                      {{ q.bobot }} Poin
                    </span>
                  </div>

                  <button
                    type="button"
                    @click="removeParsedItem(idx)"
                    title="Hapus butir soal ini dari daftar import"
                    class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>

                <!-- Teks Pertanyaan -->
                <p class="text-xs font-semibold text-white whitespace-pre-line leading-relaxed">
                  {{ q.pertanyaan }}
                </p>

                <!-- Indikator Tag [gambar] & Upload Gambar Soal pada Kartu Import -->
                <div class="p-2.5 rounded-xl bg-slate-800/60 border border-white/10 space-y-2">
                  <!-- Hidden file input for this parsed item -->
                  <input
                    :id="'parsed-img-input-' + idx"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    class="hidden"
                    @change="handleParsedImageSelected(idx, $event)"
                  />

                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span v-if="q.has_image_tag" class="px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-300 border border-amber-500/30 text-[10.5px] font-black">
                        🖼️ Tag [gambar] terdeteksi
                      </span>
                      <button
                        type="button"
                        @click="triggerParsedImageUpload(idx)"
                        class="px-2.5 py-1 rounded-lg bg-cyan-500/15 hover:bg-cyan-500/25 text-cyan-300 border border-cyan-500/30 text-xs font-bold transition-all min-h-[32px] flex items-center gap-1.5"
                      >
                        <span>🖼️</span>
                        <span>{{ q.gambar_preview ? 'Ganti Gambar' : '+ Unggah / Paste (Ctrl+V) Gambar' }}</span>
                      </button>
                    </div>

                    <!-- Posisi Gambar Selector on Parsed Item -->
                    <div v-if="q.gambar_preview" class="flex items-center gap-1 text-xs">
                      <span class="text-[11px] text-gray-400 font-medium">Posisi:</span>
                      <select
                        v-model="q.posisi_media_soal"
                        class="px-2 py-1 rounded-lg bg-slate-900 border border-white/20 text-white text-xs focus:outline-none focus:border-cyan-400"
                      >
                        <option value="bawah">⬇️ Di Bawah</option>
                        <option value="atas">⬆️ Di Atas</option>
                        <option value="samping">↔️ Di Samping</option>
                      </select>
                    </div>
                  </div>

                  <!-- Preview Attached Image on Parsed Question -->
                  <div v-if="q.gambar_preview" class="flex items-center gap-3 pt-1 border-t border-white/5">
                    <img
                      :src="q.gambar_preview"
                      alt="Gambar Soal Import"
                      class="h-14 w-20 rounded-lg object-contain bg-black/40 border border-white/10 cursor-zoom-in hover:opacity-85"
                      @click="openImageLightbox(q.gambar_preview)"
                    />
                    <div class="flex items-center gap-2">
                      <span class="text-[11px] text-emerald-300 font-bold">✓ Gambar siap diimpor</span>
                      <button
                        type="button"
                        @click="removeParsedImage(idx)"
                        class="px-2 py-0.5 rounded-md bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 text-xs font-bold transition-colors"
                      >
                        ✕ Hapus
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Pilihan Opsi & Selector Kunci Cepat -->
                <div class="space-y-1.5 pt-2 border-t border-white/5">
                  <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                    Pilihan Jawaban (Klik huruf untuk menetapkan kunci):
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div
                      v-for="opt in [
                        { k: 'A', text: q.opsi_a },
                        { k: 'B', text: q.opsi_b },
                        { k: 'C', text: q.opsi_c },
                        { k: 'D', text: q.opsi_d },
                        { k: 'E', text: q.opsi_e },
                      ]"
                      :key="opt.k"
                      @click="setParsedKey(idx, opt.k)"
                      :class="[
                        'px-2.5 py-1.5 rounded-xl border text-xs cursor-pointer transition-all flex items-center gap-2',
                        q.kunci_jawaban === opt.k
                          ? 'bg-emerald-500/20 border-emerald-500/50 text-emerald-200 font-bold ring-1 ring-emerald-500/30'
                          : 'bg-white/[0.02] border-white/5 text-gray-300 hover:border-white/20'
                      ]"
                    >
                      <span
                        :class="[
                          'w-5 h-5 rounded-lg text-[11px] font-black flex items-center justify-center shrink-0',
                          q.kunci_jawaban === opt.k ? 'bg-emerald-500 text-slate-950' : 'bg-white/10 text-gray-400'
                        ]"
                      >
                        {{ opt.k }}
                      </span>
                      <span class="truncate">{{ opt.text || '-' }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Modal Footer Actions -->
        <div class="pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
          <button
            type="button"
            @click="showImportModal = false"
            class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 font-bold text-xs sm:text-sm min-h-[42px]"
          >
            Tutup
          </button>

          <button
            type="button"
            @click="submitBulkImport"
            :disabled="isSubmittingImport || parsedQuestions.length === 0"
            class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-emerald-500/20 active:scale-95 transition-all min-h-[42px] flex items-center justify-center gap-2"
          >
            <span v-if="isSubmittingImport" class="animate-spin">⏳</span>
            <span>{{ isSubmittingImport ? 'Menyimpan Soal...' : `💾 Simpan Semua Soal ke Bank Soal (${parsedQuestions.length} Soal)` }}</span>
          </button>
        </div>

      </div>
    </div>

    <!-- Lightbox Zoom Preview Modal for Large Viewing -->
    <div
      v-if="previewLargeImageUrl"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
      @click="closeImageLightbox"
    >
      <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" @click.stop>
        <button
          type="button"
          @click="closeImageLightbox"
          class="absolute -top-12 right-0 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors min-h-[40px] min-w-[40px] flex items-center justify-center"
          title="Tutup Preview"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img
          :src="previewLargeImageUrl"
          alt="Preview Gambar Soal"
          class="max-h-[85vh] max-w-full rounded-2xl object-contain border border-white/20 shadow-2xl"
        />
      </div>
    </div>

  </div>
</template>
