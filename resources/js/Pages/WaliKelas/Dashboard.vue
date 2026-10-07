<script setup>
import { ref, computed, onMounted, onUnmounted, watch, defineAsyncComponent } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { confirmDelete, showSuccessAlert, showToast } from '@/Utils/sweetalert';

// Modular Wali Kelas Components
import Header from '@/Components/WaliKelas/Header.vue';
import Sidebar from '@/Components/WaliKelas/Sidebar.vue';
import MobileBottomNav from '@/Components/WaliKelas/MobileBottomNav.vue';
import DashboardOverview from '@/Components/WaliKelas/DashboardOverview.vue';

// Lazy loaded tab components
const KetuntasanTugas = defineAsyncComponent(() => import('@/Components/WaliKelas/KetuntasanTugas.vue'));
const NilaiSemester = defineAsyncComponent(() => import('@/Components/WaliKelas/NilaiSemester.vue'));
const DataSiswaKelas = defineAsyncComponent(() => import('@/Components/WaliKelas/DataSiswaKelas.vue'));
const KelolaJadwalWali = defineAsyncComponent(() => import('@/Components/WaliKelas/KelolaJadwalWali.vue'));
const RekapAbsensiWali = defineAsyncComponent(() => import('@/Components/WaliKelas/RekapAbsensiWali.vue'));
const DataKasusSiswa = defineAsyncComponent(() => import('@/Components/WaliKelas/DataKasusSiswa.vue'));

const props = defineProps({
  kelasBinaanProp: { type: String, default: 'X RPL 1' },
  kelasIdProp: { type: Number, default: null },
  siswaListProp: { type: Array, default: () => [] },
  absensiListProp: { type: Array, default: () => [] },
  rekapTugasListProp: { type: Array, default: () => [] },
  rekapNilaiCatatanListProp: { type: Array, default: () => [] },
  rekapNilaiSemesterProp: { type: Array, default: () => [] },
  jadwalListProp: { type: Array, default: () => [] },
  guruListProp: { type: Array, default: () => [] },
  tpListProp: { type: Array, default: () => [] },
  mapelListProp: { type: Array, default: () => [] },
  kasusListProp: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userInitials = computed(() => {
  const name = user.value?.name || 'Siti Aminah';
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
});

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
  dashboard: ['siswaListProp', 'rekapTugasListProp', 'rekapNilaiSemesterProp'],
  absensi: ['absensiListProp', 'siswaListProp', 'jadwalListProp'],
  tugas: ['rekapTugasListProp', 'rekapNilaiCatatanListProp'],
  nilai: ['rekapNilaiSemesterProp', 'tpListProp', 'mapelListProp'],
  siswa: ['siswaListProp'],
  jadwal: ['jadwalListProp', 'guruListProp', 'mapelListProp'],
  kasus: ['kasusListProp', 'siswaListProp'],
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
const kelasBinaan = ref(props.kelasBinaanProp || 'Belum Ditugaskan');

// Data Siswa Kelas Binaan
const siswaList = ref(props.siswaListProp && props.siswaListProp.length ? props.siswaListProp : []);
watch(() => props.siswaListProp, (val) => { if (val) siswaList.value = val; });

// Absensi List
const absensiList = ref(props.absensiListProp && props.absensiListProp.length ? props.absensiListProp : []);
watch(() => props.absensiListProp, (val) => { if (val) absensiList.value = val; });

// Rekap Ketuntasan Tugas dari Guru Mapel
const rekapTugasList = ref(props.rekapTugasListProp && props.rekapTugasListProp.length ? props.rekapTugasListProp : []);
watch(() => props.rekapTugasListProp, (val) => { if (val) rekapTugasList.value = val; });

// Rekap Catatan / Notif Ketuntasan Nilai TP dari Guru Mapel
const rekapNilaiCatatanList = ref(props.rekapNilaiCatatanListProp && props.rekapNilaiCatatanListProp.length ? props.rekapNilaiCatatanListProp : []);
watch(() => props.rekapNilaiCatatanListProp, (val) => { if (val) rekapNilaiCatatanList.value = val; });

// Rekap Matriks Ledger Nilai Per TP
const rekapNilaiSemester = ref(props.rekapNilaiSemesterProp && props.rekapNilaiSemesterProp.length ? props.rekapNilaiSemesterProp : []);
watch(() => props.rekapNilaiSemesterProp, (val) => { if (val) rekapNilaiSemester.value = val; });

// Data Kasus Siswa
const kasusList = ref(props.kasusListProp && props.kasusListProp.length ? props.kasusListProp : []);
watch(() => props.kasusListProp, (val) => { if (val) kasusList.value = val; });

// Notifications State
const notifications = ref([
  { id: 1, title: 'Input Penilaian Guru', text: 'Guru Informatika mengunggah rekap nilai TP-01, TP-02, dan TP-03 untuk kelas X RPL 1.', time: '15 menit lalu', read: false }
]);
const unreadNotifCount = computed(() => notifications.value.filter(n => !n.read).length);

function markAllNotifRead() {
  notifications.value.forEach(n => n.read = true);
}

function handleLogout() {
  router.post(route('logout'));
}

function onSaveSiswa({ editing, data }) {
  if (editing) {
    const idx = siswaList.value.findIndex(s => s.id === editing.id);
    if (idx !== -1) siswaList.value[idx] = { ...siswaList.value[idx], ...data };
  } else {
    siswaList.value.push({ id: Date.now(), ...data });
  }
  router.post(route('wali-kelas.siswa.store'), data, { preserveScroll: true, only: ['siswaListProp'] });
}

function onDeleteSiswa(id) {
  confirmDelete({
    title: 'Hapus Siswa dari Kelas Binaan?',
    text: 'Siswa ini akan dihapus dari kelas binaan Anda!',
    confirmButtonText: 'Ya, Hapus Siswa!'
  }).then((res) => {
    if (res.isConfirmed) {
      siswaList.value = siswaList.value.filter(s => s.id !== id);
      router.delete(route('wali-kelas.siswa.delete', id), {
        preserveScroll: true,
        only: ['siswaListProp'],
        onSuccess: () => showSuccessAlert('Berhasil Dihapus!', 'Siswa berhasil dihapus dari kelas binaan.')
      });
    }
  });
}

function onImportSiswa(importedList) {
  siswaList.value.push(...importedList);
}

function onSaveJadwal(data) {
  router.post(route('wali-kelas.jadwal.store'), data, {
    preserveScroll: true,
    only: ['jadwalListProp'],
    onSuccess: () => showSuccessAlert('Berhasil Disimpan!', 'Jadwal Pelajaran Kelas berhasil disimpan.')
  });
}

function onDeleteJadwal(id) {
  confirmDelete({
    title: 'Hapus Jadwal Pelajaran?',
    text: 'Jadwal pelajaran kelas binaan ini akan dihapus!',
    confirmButtonText: 'Ya, Hapus Jadwal!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('wali-kelas.jadwal.delete', id), {
        preserveScroll: true,
        only: ['jadwalListProp'],
        onSuccess: () => showSuccessAlert('Berhasil Dihapus!', 'Jadwal Pelajaran Kelas berhasil dihapus.')
      });
    }
  });
}

function onSaveBatchAbsensi({ tanggal, kelasId, rows }) {
  router.post(route('wali-kelas.absensi.store-batch'), { tanggal, kelasId, rows }, {
    preserveScroll: true,
    only: ['absensiListProp']
  });
}

function onSaveKasus(data) {
  router.post(route('wali-kelas.kasus.store'), data, {
    preserveScroll: true,
    only: ['kasusListProp'],
    onSuccess: () => showSuccessAlert('Berhasil Disimpan!', 'Data Kasus Siswa berhasil disimpan.')
  });
}

function onDeleteKasus(id) {
  confirmDelete({
    title: 'Hapus Catatan Kasus?',
    text: 'Catatan kasus siswa ini akan dihapus permanen!',
    confirmButtonText: 'Ya, Hapus Kasus!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('wali-kelas.kasus.delete', id), {
        preserveScroll: true,
        only: ['kasusListProp'],
        onSuccess: () => showSuccessAlert('Berhasil Dihapus!', 'Catatan kasus berhasil dihapus.')
      });
    }
  });
}
</script>

<template>
  <Head title="Dashboard Wali Kelas — SMKS Muhammadiyah 2 Genteng" />

  <div class="min-h-screen bg-slate-900 text-white font-sans">
    
    <Header
      :user="user"
      :userInitials="userInitials"
      :unreadNotifCount="unreadNotifCount"
      :notifications="notifications"
      :kelasBinaan="kelasBinaan"
      @toggle-sidebar="sidebarOpen = !sidebarOpen"
      @logout="handleLogout"
      @change-view="v => activeView = v"
      @mark-read="markAllNotifRead"
    />

    <div class="flex">
      
      <Sidebar
        v-model:activeView="activeView"
        v-model:sidebarOpen="sidebarOpen"
        :kelasBinaan="kelasBinaan"
        :user="user"
      />

      <main class="flex-1 min-w-0 max-w-full min-h-[calc(100vh-4rem)] p-3 sm:p-6 lg:p-8 pb-24 lg:pb-8">

        <div v-if="isLoading" class="fixed top-4 right-4 z-50 flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600/90 text-white text-sm shadow-lg">
          <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          Memuat data...
        </div>

        <DashboardOverview
          v-if="activeView === 'dashboard'"
          :kelasBinaan="kelasBinaan"
          :siswaList="siswaList"
          :rekapTugasList="rekapTugasList"
          :rekapNilaiSemester="rekapNilaiSemester"
          @change-view="v => activeView = v"
        />

        <RekapAbsensiWali
          v-if="activeView === 'absensi'"
          :kelasBinaan="kelasBinaan"
          :kelasId="props.kelasIdProp"
          :siswaList="siswaList"
          :absensiList="absensiList"
          :jadwalList="props.jadwalListProp"
          :user="user"
          @save-batch-absensi="onSaveBatchAbsensi"
        />

        <KetuntasanTugas
          v-if="activeView === 'tugas'"
          :rekapTugasList="rekapTugasList"
          :rekapNilaiCatatanList="rekapNilaiCatatanList"
          :kelasBinaan="kelasBinaan"
        />

        <NilaiSemester
          v-if="activeView === 'nilai'"
          :rekapNilaiSemester="rekapNilaiSemester"
          :kelasBinaan="kelasBinaan"
          :tpList="props.tpListProp"
          :mapelList="props.mapelListProp"
        />

        <DataSiswaKelas
          v-if="activeView === 'siswa'"
          :siswaList="siswaList"
          :kelasBinaan="kelasBinaan"
          @save-siswa="onSaveSiswa"
          @delete-siswa="onDeleteSiswa"
          @import-siswa="onImportSiswa"
        />

        <KelolaJadwalWali
          v-if="activeView === 'jadwal'"
          :kelasBinaan="kelasBinaan"
          :jadwalList="props.jadwalListProp"
          :guruList="props.guruListProp"
          :mapelList="props.mapelListProp"
          @save-jadwal="onSaveJadwal"
          @delete-jadwal="onDeleteJadwal"
        />

        <DataKasusSiswa
          v-if="activeView === 'kasus'"
          :kasusList="kasusList"
          :siswaList="siswaList"
          :kelasBinaan="kelasBinaan"
          @save-kasus="onSaveKasus"
          @delete-kasus="onDeleteKasus"
        />

      </main>
    </div>

    <!-- Mobile Bottom Navigation Bar -->
    <MobileBottomNav
      v-model:activeView="activeView"
      v-model:sidebarOpen="sidebarOpen"
    />
  </div>
</template>
