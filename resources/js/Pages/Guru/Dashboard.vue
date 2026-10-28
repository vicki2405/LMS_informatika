<script setup>
import { ref, computed, onMounted, onUnmounted, watch, defineAsyncComponent } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { confirmDelete, showSuccessAlert, showToast } from '@/Utils/sweetalert';

// Modular Child Components
import Header from '@/Components/Guru/Header.vue';
import Sidebar from '@/Components/Guru/Sidebar.vue';
import MobileBottomNav from '@/Components/Guru/MobileBottomNav.vue';
import DashboardOverview from '@/Components/Guru/DashboardOverview.vue';

// Lazy loaded tab components
const JadwalGuru = defineAsyncComponent(() => import('@/Components/Guru/JadwalGuru.vue'));
const MenuKelas = defineAsyncComponent(() => import('@/Components/Guru/MenuKelas.vue'));
const MenuSiswa = defineAsyncComponent(() => import('@/Components/Guru/MenuSiswa.vue'));
const MenuAbsensiSiswa = defineAsyncComponent(() => import('@/Components/Guru/MenuAbsensiSiswa.vue'));
const FormPresensiKelas = defineAsyncComponent(() => import('@/Components/Guru/FormPresensiKelas.vue'));
const MenuJurnalMengajar = defineAsyncComponent(() => import('@/Components/Guru/MenuJurnalMengajar.vue'));
const MenuNilaiSiswa = defineAsyncComponent(() => import('@/Components/Guru/MenuNilaiSiswa.vue'));
const KelolaMateri = defineAsyncComponent(() => import('@/Components/Guru/KelolaMateri.vue'));
const KelolaTp = defineAsyncComponent(() => import('@/Components/Guru/KelolaTp.vue'));

// CBT Modular Components
const BankSoalCbt = defineAsyncComponent(() => import('@/Components/Guru/Cbt/BankSoalCbt.vue'));
const JadwalCbt = defineAsyncComponent(() => import('@/Components/Guru/Cbt/JadwalCbt.vue'));
const HasilNilaiCbt = defineAsyncComponent(() => import('@/Components/Guru/Cbt/HasilNilaiCbt.vue'));

const props = defineProps({
  tahunAjaranListProp: { type: Array, default: () => [] },
  jurusanListProp: { type: Array, default: () => [] },
  kelasListProp: { type: Array, default: () => [] },
  masterKelasListProp: { type: Array, default: () => [] },
  siswaListProp: { type: Array, default: () => [] },
  jadwalListProp: { type: Array, default: () => [] },
  absensiListProp: { type: Array, default: () => [] },
  jurnalListProp: { type: Array, default: () => [] },
  tpListProp: { type: Array, default: () => [] },
  nilaiListProp: { type: Array, default: () => [] },
  materiListProp: { type: Array, default: () => [] },
  mapelListProp: { type: Array, default: () => [] },
  teacherSubjectProp: { type: String, default: 'Informatika' },
  cbtBankSoalListProp: { type: Array, default: () => [] },
  cbtJadwalListProp: { type: Array, default: () => [] },
  cbtNilaiJadwalListProp: { type: Array, default: () => [] },
  cbtNilaiListProp: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userInitials = computed(() => {
  const name = user.value?.name || 'Guru';
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
});

const userPermissions = computed(() => user.value?.permissions || [
  'kelola-materi', 'lihat-materi', 'kelola-tugas', 'lihat-tugas', 'kelola-nilai', 'lihat-nilai'
]);

const getInitialTab = () => {
  if (typeof window !== 'undefined') {
    const params = new URLSearchParams(window.location.search);
    return params.get('tab') || 'dashboard';
  }
  return 'dashboard';
};

const sidebarOpen = ref(typeof window !== 'undefined' ? window.innerWidth >= 1024 : true);
const activeView = ref(getInitialTab());
const isLoading = ref(false);

const tabDataMap = {
  dashboard: ['kelasListProp', 'siswaListProp', 'jadwalListProp', 'absensiListProp', 'jurnalListProp', 'tpListProp', 'nilaiListProp', 'materiListProp'],
  jadwal: ['jadwalListProp', 'kelasListProp', 'masterKelasListProp', 'mapelListProp'],
  kelas: ['kelasListProp', 'jurusanListProp', 'masterKelasListProp'],
  siswa: ['siswaListProp', 'kelasListProp'],
  absensi: ['absensiListProp', 'siswaListProp', 'kelasListProp', 'jadwalListProp'],
  'input-absensi': ['absensiListProp', 'siswaListProp', 'kelasListProp', 'jadwalListProp'],
  jurnal: ['jurnalListProp', 'kelasListProp', 'jadwalListProp', 'mapelListProp'],
  nilai: ['tpListProp', 'nilaiListProp', 'siswaListProp', 'kelasListProp', 'tahunAjaranListProp', 'mapelListProp', 'cbtJadwalListProp', 'cbtNilaiListProp'],
  tp: ['tpListProp', 'mapelListProp', 'tahunAjaranListProp'],
  materi: ['materiListProp', 'kelasListProp', 'tpListProp', 'mapelListProp'],
  'cbt-bank-soal': ['cbtBankSoalListProp', 'masterKelasListProp', 'jurusanListProp', 'mapelListProp'],
  'cbt-jadwal': ['cbtJadwalListProp', 'cbtBankSoalListProp', 'masterKelasListProp'],
  'cbt-nilai': ['cbtNilaiListProp', 'cbtNilaiJadwalListProp', 'cbtJadwalListProp', 'masterKelasListProp', 'jurusanListProp', 'mapelListProp', 'siswaListProp', 'tpListProp', 'kelasListProp', 'tahunAjaranListProp'],
};

function reloadTabData(tab) {
  const neededProps = tabDataMap[tab] || [];
  const missingProps = neededProps.filter(p => props[p] === undefined);
  if (missingProps.length > 0) {
    isLoading.value = true;
    router.reload({ only: missingProps, onFinish: () => { isLoading.value = false; } });
  }
}

watch(activeView, (newTab) => {
  if (typeof window !== 'undefined') {
    const url = new URL(window.location.href);
    if (newTab && newTab !== 'dashboard') {
      url.searchParams.set('tab', newTab);
    } else {
      url.searchParams.delete('tab');
    }
    window.history.replaceState({}, '', url.toString());
  }
  reloadTabData(newTab);
});

function handlePopState() {
  const params = new URLSearchParams(window.location.search);
  activeView.value = params.get('tab') || 'dashboard';
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('popstate', handlePopState);
  }
  reloadTabData(activeView.value);
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('popstate', handlePopState);
  }
});

// Default Teacher Specialty Subject
const teacherSubject = ref(props.teacherSubjectProp || 'Informatika');

watch(() => props.teacherSubjectProp, (newVal) => {
  if (newVal) {
    teacherSubject.value = newVal;
  }
});

// Initial Schedule Data (10 Jam Mengajar per Hari)
const jadwalList = ref(props.jadwalListProp || []);

// Master Data State from props (Clean empty fallbacks)
const jurusanList = ref(props.jurusanListProp && props.jurusanListProp.length ? props.jurusanListProp : []);
const waliKelasList = ref(props.waliKelasListProp && props.waliKelasListProp.length ? props.waliKelasListProp : []);
const kelasList = ref(props.kelasListProp && props.kelasListProp.length ? props.kelasListProp : []);
const siswaList = ref(props.siswaListProp && props.siswaListProp.length ? props.siswaListProp : []);
const materiList = ref(props.materiListProp && props.materiListProp.length ? props.materiListProp : []);

// Keep kelasList reactive with server prop updates (after save/delete kelas ampuan)
watch(() => props.kelasListProp, (newVal) => {
  if (newVal) kelasList.value = newVal;
}, { deep: true });

// Compute student count per class dynamically
const kelasListWithCount = computed(() => {
  return kelasList.value.map(k => {
    const count = siswaList.value.filter(s => s.kelas === k.namaKelas).length;
    return { ...k, jumlahSiswa: count };
  });
});



// Notifications State
const notifications = ref([
  { id: 1, title: 'Progress Belajar Siswa', text: 'Ahmad Rizky (X RPL 1) membaca Modul Hardware', time: '5 menit lalu', read: false },
  { id: 2, title: 'Sub-Materi Selesai', text: 'Siti Nurhaliza (X TKJ 2) mengunggah tugas IP Address', time: '20 menit lalu', read: false },
  { id: 3, title: 'Pemberitahuan Sistem', text: 'Struktur kelas X DKV 1 berhasil diperbarui', time: '1 jam lalu', read: true }
]);
const unreadNotifCount = computed(() => notifications.value.filter(n => !n.read).length);

function markAllNotifRead() {
  notifications.value.forEach(n => n.read = true);
}

function handleLogout() {
  router.post(route('logout'));
}

// CRUD Handlers for Jadwal Guru
function onSaveJadwal({ editing, data }) {
  const payload = {
    id: editing?.id || null,
    hari: data.hari,
    jam_ke: data.jamKe || data.jam_ke,
    waktu: data.waktu || '',
    kelas: data.kelas,
    mata_pelajaran: data.mataPelajaran || data.mata_pelajaran,
    ruangan: data.ruangan || '',
    keterangan: data.keterangan || '',
  };

  router.post(route('guru.jadwal.store'), payload, {
    preserveScroll: true,
    only: ['jadwalListProp', 'kelasListProp'],
    onSuccess: () => showToast('Jadwal pelajaran berhasil disimpan!'),
    onError: (errors) => {
      const msg = errors.jadwal || errors.kelas || errors.guru || Object.values(errors)[0] || 'Gagal menyimpan jadwal.';
      showToast(msg, 'error');
    }
  });
}

function onDeleteJadwal(id) {
  confirmDelete({
    title: 'Hapus Jadwal Mengajar?',
    text: 'Jadwal mengajar ini akan dihapus dari database!',
    confirmButtonText: 'Ya, Hapus Jadwal!'
  }).then((res) => {
    if (res.isConfirmed) {
      const backup = [...jadwalList.value];
      jadwalList.value = jadwalList.value.filter(j => j.id !== id);

      router.delete(route('guru.jadwal.delete', id), {
        preserveScroll: true,
        only: ['jadwalListProp', 'kelasListProp'],
        onError: () => {
          jadwalList.value = backup;
          showToast('Gagal menghapus jadwal. Data dikembalikan.', 'error');
        }
      });
    }
  });
}

// CRUD Save/Delete Actions for Kelas Ampuan (persisted to DB via pivot table)
function onSaveKelas({ kelasIds }) {
  // kelasIds: array of kelas IDs selected by guru in the modal
  if (!kelasIds || kelasIds.length === 0) return;
  router.post(route('guru.kelas-ampuan.save'), { kelas_ids: kelasIds }, {
    preserveScroll: true,
    only: ['kelasListProp'],
    onSuccess: () => showSuccessAlert('Berhasil Disimpan!', 'Kelas ampuan berhasil disimpan ke database.'),
  });
}

function onDeleteKelas(kelasId) {
  confirmDelete({
    title: 'Hapus Kelas Ampuan?',
    text: 'Kelas ini akan dihapus dari daftar mengajar Anda.',
    confirmButtonText: 'Ya, Hapus!'
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(route('guru.kelas-ampuan.remove', kelasId), {
        preserveScroll: true,
        only: ['kelasListProp'],
        onSuccess: () => showSuccessAlert('Berhasil Dihapus!', 'Kelas berhasil dihapus dari daftar mengajar.'),
      });
    }
  });
}

// CRUD Save/Delete Actions for Jurusan
function onSaveJurusan({ editing, data }) {
  if (editing) {
    const idx = jurusanList.value.findIndex(j => j.id === editing.id);
    if (idx !== -1) jurusanList.value[idx] = { ...jurusanList.value[idx], ...data };
  } else {
    jurusanList.value.push({ id: Date.now(), ...data });
  }
  showSuccessAlert('Berhasil Disimpan!', 'Data Jurusan berhasil disimpan.');
}

function onDeleteJurusan(id) {
  confirmDelete({
    title: 'Hapus Jurusan?',
    text: 'Data jurusan ini akan dihapus!',
    confirmButtonText: 'Ya, Hapus Jurusan!'
  }).then((res) => {
    if (res.isConfirmed) {
      jurusanList.value = jurusanList.value.filter(j => j.id !== id);
      showSuccessAlert('Berhasil Dihapus!', 'Data Jurusan berhasil dihapus.');
    }
  });
}

// CRUD Save/Delete Actions for Wali Kelas
function onSaveWali({ editing, data }) {
  if (editing) {
    const idx = waliKelasList.value.findIndex(w => w.id === editing.id);
    if (idx !== -1) waliKelasList.value[idx] = { ...waliKelasList.value[idx], ...data };
  } else {
    waliKelasList.value.push({ id: Date.now(), ...data });
  }
  showSuccessAlert('Berhasil Disimpan!', 'Data Wali Kelas berhasil disimpan.');
}

function onDeleteWali(id) {
  confirmDelete({
    title: 'Hapus Wali Kelas?',
    text: 'Data wali kelas ini akan dihapus!',
    confirmButtonText: 'Ya, Hapus!'
  }).then((res) => {
    if (res.isConfirmed) {
      waliKelasList.value = waliKelasList.value.filter(w => w.id !== id);
      showSuccessAlert('Berhasil Dihapus!', 'Data Wali Kelas berhasil dihapus.');
    }
  });
}

// CRUD Save/Delete Actions for Siswa
function onSaveSiswa({ editing, data }) {
  if (editing) {
    const idx = siswaList.value.findIndex(s => s.id === editing.id);
    if (idx !== -1) siswaList.value[idx] = { ...siswaList.value[idx], ...data };
  } else {
    siswaList.value.push({ id: Date.now(), ...data, progress: 0, nilaiRata: 0 });
  }
  showSuccessAlert('Berhasil Disimpan!', 'Data Siswa berhasil disimpan.');
}

function onDeleteSiswa(id) {
  confirmDelete({
    title: 'Hapus Data Siswa?',
    text: 'Data siswa ini akan dihapus!',
    confirmButtonText: 'Ya, Hapus Siswa!'
  }).then((res) => {
    if (res.isConfirmed) {
      siswaList.value = siswaList.value.filter(s => s.id !== id);
      showSuccessAlert('Berhasil Dihapus!', 'Data Siswa berhasil dihapus.');
    }
  });
}

function onBulkDeleteSiswa(ids) {
  confirmDelete({
    title: 'Hapus Beberapa Siswa?',
    text: `Apakah Anda yakin ingin menghapus ${ids.length} data siswa yang dipilih?`,
    confirmButtonText: 'Ya, Hapus Semua!'
  }).then((res) => {
    if (res.isConfirmed) {
      siswaList.value = siswaList.value.filter(s => !ids.includes(s.id));
      showSuccessAlert('Berhasil Dihapus!', `${ids.length} Data Siswa berhasil dihapus.`);
    }
  });
}

function onImportSiswa(importedList) {
  siswaList.value.push(...importedList);
}

// Student Attendance State (H, A, S, I)
const absensiList = ref(Array.isArray(props.absensiListProp) ? props.absensiListProp : []);

function onSaveAbsensi({ editing, data }) {
  if (editing) {
    const idx = absensiList.value.findIndex(a => a.id === editing.id);
    if (idx !== -1) absensiList.value[idx] = { ...absensiList.value[idx], ...data };
  } else {
    absensiList.value.push({ id: Date.now(), ...data });
  }
}

function onSaveBatchAbsensi({ tanggal, kelas, rows }) {
  const normDate = String(tanggal).substring(0, 10);
  rows.forEach((row, index) => {
    const existingIdx = absensiList.value.findIndex(a => 
      (a.siswaId === row.siswaId || a.siswa_id === row.siswaId) && 
      (a.tanggal === normDate || (a.tanggal && String(a.tanggal).substring(0, 10) === normDate))
    );
    if (existingIdx !== -1) {
      absensiList.value[existingIdx] = {
        ...absensiList.value[existingIdx],
        status: row.status,
        keterangan: row.keterangan
      };
    } else {
      absensiList.value.push({
        id: Date.now() + index,
        tanggal: normDate,
        kelas: kelas,
        siswaId: row.siswaId,
        namaSiswa: row.namaSiswa,
        nis: row.nis,
        status: row.status,
        keterangan: row.keterangan
      });
    }
  });
  router.post(route('guru.absensi.store-batch'), { tanggal: normDate, kelas, rows }, { preserveScroll: true, only: ['absensiListProp'] });
}

// Full-Page Input Presensi State & Persistent Selected Class Tracking
const activeAbsensiKelas = ref(
  (typeof window !== 'undefined' ? localStorage.getItem('guru_active_absensi_kelas') : '') || ''
);
const inputAbsensiKelas = ref('');
const inputAbsensiTanggal = ref('');

function onOpenFullInputAbsensi({ kelas, tanggal }) {
  inputAbsensiKelas.value = kelas;
  inputAbsensiTanggal.value = tanggal;
  if (kelas) {
    activeAbsensiKelas.value = kelas;
    if (typeof window !== 'undefined') {
      localStorage.setItem('guru_active_absensi_kelas', kelas);
    }
  }
  activeView.value = 'input-absensi';
}

function onSaveFullBatchAbsensi({ tanggal, kelas, rows }) {
  if (kelas) {
    activeAbsensiKelas.value = kelas;
    if (typeof window !== 'undefined') {
      localStorage.setItem('guru_active_absensi_kelas', kelas);
    }
  }
  onSaveBatchAbsensi({ tanggal, kelas, rows });
  activeView.value = 'absensi'; // Switch back to Matrix View with the same class active!
}

function onBackFromFullInput(targetKelas) {
  if (targetKelas) {
    activeAbsensiKelas.value = targetKelas;
    if (typeof window !== 'undefined') {
      localStorage.setItem('guru_active_absensi_kelas', targetKelas);
    }
  }
  activeView.value = 'absensi';
}

function onDeleteAbsensi(id) {
  confirmDelete({
    title: 'Hapus Catatan Absensi?',
    text: 'Catatan absensi ini akan dihapus!',
    confirmButtonText: 'Ya, Hapus Absensi!'
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(route('guru.absensi.delete', id), {
        preserveScroll: true,
        only: ['absensiListProp']
      });
    }
  });
}

// Teacher Teaching Journal State (Jurnal Mengajar Guru)
const jurnalList = ref(Array.isArray(props.jurnalListProp) ? props.jurnalListProp : []);

watch(() => props.jurnalListProp, (newVal) => {
  if (newVal) {
    jurnalList.value = Array.isArray(newVal) ? [...newVal] : [];
  }
}, { deep: true });

function onSaveJurnal({ editing, data }) {
  if (editing) {
    const idx = jurnalList.value.findIndex(j => j.id === editing.id);
    if (idx !== -1) jurnalList.value[idx] = { ...jurnalList.value[idx], ...data };
  } else {
    jurnalList.value.push({ id: Date.now(), ...data });
  }

  const payload = {
    id: editing?.id || null,
    tanggal: data.tanggal,
    jam_ke: data.jamKe || 'Jam 1 - 2',
    kelas: data.kelas,
    mata_pelajaran: data.mataPelajaran || data.mata_pelajaran || teacherSubject.value || 'Informatika',
    materi: data.materi,
    uraian: data.uraian || '',
    catatan: data.catatan || '',
    tahun_pelajaran: data.tahunPelajaran || page.props.activeTahunAjaran?.kode || '2025/2026',
    tahunPelajaran: data.tahunPelajaran || page.props.activeTahunAjaran?.kode || '2025/2026',
    semester: data.semester || 'Ganjil',
  };

  router.post(route('guru.jurnal.store'), payload, {
    preserveScroll: true,
    only: ['jurnalListProp'],
    onSuccess: () => {
      showSuccessAlert('Berhasil Disimpan!', 'Catatan jurnal mengajar berhasil disimpan.');
    },
    onError: (errors) => {
      const msg = errors.kelas || errors.materi || Object.values(errors)[0] || 'Gagal menyimpan jurnal.';
      showToast(msg, 'error');
    }
  });
}

function onDeleteJurnal(id) {
  confirmDelete({
    title: 'Hapus Jurnal Mengajar?',
    text: 'Catatan jurnal mengajar ini akan dihapus!',
    confirmButtonText: 'Ya, Hapus Jurnal!'
  }).then((res) => {
    if (res.isConfirmed) {
      jurnalList.value = jurnalList.value.filter(j => j.id !== id);
      router.delete(route('guru.jurnal.delete', id), {
        preserveScroll: true,
        only: ['jurnalListProp']
      });
    }
  });
}

// Student Assessment & TP State (Penilaian Tujuan Pembelajaran per Semester)
const tahunAjaranList = ref(props.tahunAjaranListProp && props.tahunAjaranListProp.length ? props.tahunAjaranListProp : (page.props.tahunAjaranList || []));
watch(() => props.tahunAjaranListProp, (newVal) => {
  if (newVal && newVal.length) {
    tahunAjaranList.value = newVal;
  }
}, { immediate: true });

function onSaveTahunAjaran({ editing, data }) {
  if (editing) {
    const idx = tahunAjaranList.value.findIndex(t => t.id === editing.id);
    if (idx !== -1) tahunAjaranList.value[idx] = { ...tahunAjaranList.value[idx], ...data };
  } else {
    tahunAjaranList.value.push({ id: Date.now(), ...data });
  }
}

function onDeleteTahunAjaran(id) {
  confirmDelete({
    title: 'Hapus Tahun Ajaran?',
    text: 'Data Tahun Ajaran akan dihapus.',
    confirmButtonText: 'Ya, Hapus!'
  }).then((result) => {
    if (result.isConfirmed) {
      tahunAjaranList.value = tahunAjaranList.value.filter(t => t.id !== id);
    }
  });
}

function onSetActiveTahunAjaran(id) {
  tahunAjaranList.value.forEach(t => {
    t.isAktif = (t.id === id);
  });
}

const tpList = ref(Array.isArray(props.tpListProp) ? props.tpListProp : []);

const nilaiList = ref(Array.isArray(props.nilaiListProp) ? props.nilaiListProp : []);

function onSaveTp({ editing, data }) {
  if (editing && editing.id) {
    const idx = tpList.value.findIndex(t => t.id === editing.id);
    if (idx !== -1) tpList.value[idx] = { ...tpList.value[idx], ...data };
  } else {
    tpList.value.push({ id: Date.now(), ...data });
  }
  const payload = { ...data };
  if (editing && editing.id) {
    payload.id = editing.id;
  }
  router.post(route('guru.tp.store'), payload, {
    preserveScroll: true,
    only: ['tpListProp']
  });
}

function onDeleteTp(id) {
  confirmDelete({
    title: 'Hapus Tujuan Pembelajaran?',
    text: 'Nilai siswa terkait juga akan dibersihkan dari database!',
    confirmButtonText: 'Ya, Hapus TP!'
  }).then((result) => {
    if (result.isConfirmed) {
      tpList.value = tpList.value.filter(t => t.id !== id);
      nilaiList.value = nilaiList.value.filter(n => n.tpId !== id);
      router.delete(route('guru.tp.delete', id), {
        preserveScroll: true,
        only: ['tpListProp', 'nilaiListProp']
      });
    }
  });
}

function onSaveNilaiBatch({ kelas, tpId, scores }) {
  scores.forEach(s => {
    const existingIdx = nilaiList.value.findIndex(n => n.siswaId === s.siswaId && n.tpId === tpId);
    if (existingIdx !== -1) {
      nilaiList.value[existingIdx].nilai = s.nilai;
      nilaiList.value[existingIdx].catatan = s.catatan || null;
    } else if (s.nilai !== null) {
      nilaiList.value.push({
        id: Date.now() + Math.random(),
        kelas: kelas,
        tpId: tpId,
        siswaId: s.siswaId,
        nilai: s.nilai,
        catatan: s.catatan || null
      });
    }
  });
  router.post(route('guru.nilai.store-batch'), { tpId, scores }, {
    preserveScroll: true,
    only: ['nilaiListProp']
  });
}

function onDeleteMateri(id) {
  confirmDelete({
    title: 'Hapus Materi Pembelajaran?',
    text: 'Materi ini akan dihapus dari portal belajar siswa!',
    confirmButtonText: 'Ya, Hapus Materi!'
  }).then((result) => {
    if (result.isConfirmed) {
      materiList.value = materiList.value.filter(m => m.id !== id);
      router.delete(route('guru.materi.delete', id), {
        preserveScroll: true,
        only: ['materiListProp']
      });
    }
  });
}

// Reactively update refs when Inertia lazy props are loaded
watch(() => props.tahunAjaranListProp, (val) => { if (val && val.length) tahunAjaranList.value = val; });
watch(() => props.jurusanListProp, (val) => { if (val && val.length) jurusanList.value = val; });
watch(() => props.kelasListProp, (val) => { if (val && val.length) kelasList.value = val; });
watch(() => props.siswaListProp, (val) => { if (val !== undefined) siswaList.value = val || []; });
watch(() => props.jadwalListProp, (val) => { if (val !== undefined) jadwalList.value = val || []; });
watch(() => props.absensiListProp, (val) => { if (val !== undefined) absensiList.value = val || []; }, { immediate: true });
watch(() => props.jurnalListProp, (val) => { if (val !== undefined) jurnalList.value = val || []; });
watch(() => props.tpListProp, (val) => { if (val !== undefined) tpList.value = val || []; });
watch(() => props.nilaiListProp, (val) => { if (val !== undefined) nilaiList.value = val || []; });
watch(() => props.materiListProp, (val) => { if (val !== undefined) materiList.value = val || []; });
watch(() => page.props.flash?.message, (msg) => { if (msg) showToast(msg); });
</script>

<template>
  <Head title="Dashboard Guru — SMKS Muhammadiyah 2 Genteng" />

  <div class="min-h-screen bg-slate-900 text-white font-sans">
    
    <!-- Top Header Component -->
    <Header
      :user="user"
      :userInitials="userInitials"
      :unreadNotifCount="unreadNotifCount"
      :notifications="notifications"
      @toggle-sidebar="sidebarOpen = !sidebarOpen"
      @logout="handleLogout"
      @change-view="v => activeView = v"
      @mark-read="markAllNotifRead"
    />

    <div class="flex">
      
      <!-- Sidebar Component -->
      <Sidebar
        v-model:activeView="activeView"
        v-model:sidebarOpen="sidebarOpen"
        :userPermissions="userPermissions"
        :user="user"
      />

      <!-- Main Content Views -->
      <main class="flex-1 min-w-0 max-w-full min-h-[calc(100vh-4rem)] p-4 sm:p-6 lg:p-8 pb-24 lg:pb-8">

        <div v-if="isLoading" class="fixed top-4 right-4 z-50 flex items-center gap-2 px-4 py-2 rounded-lg bg-teal-600/90 text-white text-sm shadow-lg">
          <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          Memuat data...
        </div>

        <DashboardOverview
          v-if="activeView === 'dashboard'"
          :user="user"
          :kelasList="kelasList"
          :siswaList="siswaList"
          :jadwalList="jadwalList"
          :absensiList="absensiList"
          :jurnalList="jurnalList"
          :tpList="tpList"
          :nilaiList="nilaiList"
          :materiList="materiList"
          :teacherSubject="teacherSubject"
          @change-view="v => activeView = v"
          @open-full-input-absensi="onOpenFullInputAbsensi"
        />

        <!-- JADWAL GURU VIEW -->
        <JadwalGuru
          v-if="activeView === 'jadwal'"
          :jadwalList="jadwalList"
          :kelasList="kelasList"
          :masterKelasList="props.masterKelasListProp"
          :teacherSubject="teacherSubject"
          :mapelList="mapelListProp"
          @save-jadwal="onSaveJadwal"
          @delete-jadwal="onDeleteJadwal"
          @change-view="v => activeView = v"
          @go-to-absensi="onOpenFullInputAbsensi"
          @go-to-jurnal="onGoToJurnal"
        />

        <!-- KELAS VIEW -->
        <MenuKelas
          v-if="activeView === 'kelas'"
          :is-admin="false"
          :kelasListWithCount="kelasListWithCount"
          :masterKelasAdmin="props.masterKelasListProp"
          :masterWaliAdmin="waliKelasList"
          @save-kelas="onSaveKelas"
          @delete-kelas="onDeleteKelas"
        />

        <!-- SISWA VIEW -->
        <MenuSiswa
          v-if="activeView === 'siswa'"
          :siswaList="siswaList"
          :kelasList="kelasList"
          :canManage="false"
          @save-siswa="onSaveSiswa"
          @delete-siswa="onDeleteSiswa"
          @bulk-delete-siswa="onBulkDeleteSiswa"
          @import-siswa="onImportSiswa"
        />

        <!-- ABSENSI SISWA VIEW -->
        <MenuAbsensiSiswa
          v-if="activeView === 'absensi'"
          :absensiList="absensiList"
          :siswaList="siswaList"
          :kelasList="kelasList"
          :jadwalList="jadwalList"
          :initialKelas="activeAbsensiKelas"
          @update:selected-kelas="(k) => {
            activeAbsensiKelas = k;
            if (typeof window !== 'undefined' && k) localStorage.setItem('guru_active_absensi_kelas', k);
          }"
          @save-absensi="onSaveAbsensi"
          @save-batch-absensi="onSaveBatchAbsensi"
          @open-full-input-absensi="onOpenFullInputAbsensi"
          @delete-absensi="onDeleteAbsensi"
        />

        <!-- DEDICATED FULL-PAGE INPUT PRESENSI KELAS VIEW -->
        <FormPresensiKelas
          v-if="activeView === 'input-absensi'"
          :absensiList="absensiList"
          :siswaList="siswaList"
          :kelasList="kelasList"
          :jadwalList="jadwalList"
          :initialKelas="activeAbsensiKelas || inputAbsensiKelas"
          :initialTanggal="inputAbsensiTanggal"
          @save-batch-absensi="onSaveFullBatchAbsensi"
          @back-to-matrix="onBackFromFullInput"
        />

        <!-- JURNAL MENGAJAR GURU VIEW -->
        <MenuJurnalMengajar
          v-if="activeView === 'jurnal'"
          :jurnalList="jurnalList"
          :kelasList="kelasList"
          :jadwalList="jadwalList"
          :mapelList="mapelListProp"
          :teacherSubject="teacherSubject"
          :shortcutJadwal="pendingJurnalShortcut"
          @clear-shortcut="pendingJurnalShortcut = null"
          @save-jurnal="onSaveJurnal"
          @delete-jurnal="onDeleteJurnal"
        />

        <!-- NILAI SISWA (PENILAIAN TP) VIEW -->
        <MenuNilaiSiswa
          v-if="activeView === 'nilai'"
          :tpList="tpList"
          :nilaiList="nilaiList"
          :siswaList="siswaList"
          :kelasList="kelasList"
          :tahunAjaranList="tahunAjaranList"
          :teacherSubject="teacherSubject"
          :mapelList="mapelListProp"
          :jadwalList="jadwalList"
          :cbtJadwalList="props.cbtJadwalListProp"
          :cbtNilaiList="props.cbtNilaiListProp"
          @save-tp="onSaveTp"
          @delete-tp="onDeleteTp"
          @save-nilai-batch="onSaveNilaiBatch"
          @save-ta="onSaveTahunAjaran"
          @delete-ta="onDeleteTahunAjaran"
          @set-active-ta="onSetActiveTahunAjaran"
        />

        <!-- TUJUAN PEMBELAJARAN (TP) VIEW -->
        <KelolaTp
          v-if="activeView === 'tp'"
          :tpList="tpList"
          :mapelList="mapelListProp"
          :tahunAjaranList="tahunAjaranList"
          :teacherSubject="teacherSubject"
          @save-tp="onSaveTp"
          @delete-tp="onDeleteTp"
          @change-view="v => activeView = v"
        />

        <!-- KELOLA MATERI VIEW -->
        <KelolaMateri
          v-if="activeView === 'materi'"
          :materiList="materiList"
          :kelasList="kelasListProp"
          :tpList="tpList"
          :teacherSubject="teacherSubject"
          @delete-materi="onDeleteMateri"
        />

        <!-- CBT: BANK SOAL VIEW -->
        <BankSoalCbt
          v-if="activeView === 'cbt-bank-soal'"
          :bankSoalList="props.cbtBankSoalListProp"
          :masterKelasList="props.masterKelasListProp"
          :jurusanList="props.jurusanListProp"
          :mapelList="props.mapelListProp"
        />

        <!-- CBT: JADWAL PELAKSANAAN CBT VIEW -->
        <JadwalCbt
          v-if="activeView === 'cbt-jadwal'"
          :jadwalList="props.cbtJadwalListProp"
          :bankSoalList="props.cbtBankSoalListProp"
          :masterKelasList="props.masterKelasListProp"
          :jurusanList="props.jurusanListProp"
        />

        <!-- CBT: HASIL NILAI UJIAN VIEW -->
        <HasilNilaiCbt
          v-if="activeView === 'cbt-nilai'"
          :nilaiList="props.cbtNilaiListProp"
          :jadwalList="props.cbtNilaiJadwalListProp && props.cbtNilaiJadwalListProp.length ? props.cbtNilaiJadwalListProp : props.cbtJadwalListProp"
          :masterKelasList="props.masterKelasListProp"
          :jurusanList="props.jurusanListProp"
          :mapelList="props.mapelListProp"
          :siswaList="props.siswaListProp"
          :tpList="tpList"
          :kelasList="kelasList"
          :jadwalGuruList="props.jadwalListProp"
          :tahunAjaranList="tahunAjaranList"
          :teacherSubject="teacherSubject"
        />

      </main>
    </div>

    <!-- Mobile Bottom Navigation Bar -->
    <MobileBottomNav
      v-if="activeView !== 'input-absensi'"
      v-model:activeView="activeView"
      v-model:sidebarOpen="sidebarOpen"
    />
  </div>
</template>
