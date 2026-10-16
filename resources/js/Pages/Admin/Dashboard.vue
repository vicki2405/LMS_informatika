<script setup>
import { ref, computed, onMounted, onUnmounted, watch, defineAsyncComponent } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { confirmDelete, showSuccessAlert, showToast } from '@/Utils/sweetalert';

// Modular Admin Components
import Header from '@/Components/Admin/Header.vue';
import Sidebar from '@/Components/Admin/Sidebar.vue';
import MobileBottomNav from '@/Components/Admin/MobileBottomNav.vue';
import DashboardOverview from '@/Components/Admin/DashboardOverview.vue';

const KelolaUserPassword = defineAsyncComponent(() => import('@/Components/Admin/KelolaUserPassword.vue'));
const KelolaMataPelajaran = defineAsyncComponent(() => import('@/Components/Admin/KelolaMataPelajaran.vue'));
const KelolaGuru = defineAsyncComponent(() => import('@/Components/Admin/KelolaGuru.vue'));
const MenuKelas = defineAsyncComponent(() => import('@/Components/Guru/MenuKelas.vue'));
const MenuSiswa = defineAsyncComponent(() => import('@/Components/Guru/MenuSiswa.vue'));
const KelolaBackup = defineAsyncComponent(() => import('@/Components/Admin/KelolaBackup.vue'));
const KelolaTahunPelajaran = defineAsyncComponent(() => import('@/Components/Admin/KelolaTahunPelajaran.vue'));

const props = defineProps({
  guruListProp: { type: Array, default: () => [] },
  waliKelasListProp: { type: Array, default: () => [] },
  siswaListProp: { type: Array, default: () => [] },
  kelasListProp: { type: Array, default: () => [] },
  jurusanListProp: { type: Array, default: () => [] },
  mapelListProp: { type: Array, default: () => [] },
  kelompokMapelListProp: { type: Array, default: () => [] },
  userAccountsListProp: { type: Array, default: () => [] },
  tahunAjaranListProp: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userInitials = computed(() => {
  const name = user.value?.name || 'Admin';
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
  dashboard: ['guruListProp', 'waliKelasListProp', 'siswaListProp', 'userAccountsListProp'],
  'tahun-ajaran': ['tahunAjaranListProp'],
  guru: ['guruListProp', 'mapelListProp'],
  mapel: ['mapelListProp', 'kelompokMapelListProp'],
  kelas: ['kelasListProp', 'jurusanListProp', 'waliKelasListProp', 'guruListProp'],
  siswa: ['siswaListProp', 'kelasListProp'],
  users: ['userAccountsListProp', 'guruListProp', 'siswaListProp', 'mapelListProp', 'kelasListProp'],
  backup: [],
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

// State for Data Guru
const guruList = ref(props.guruListProp && props.guruListProp.length ? props.guruListProp : []);
const waliKelasList = ref(props.waliKelasListProp && props.waliKelasListProp.length ? props.waliKelasListProp : []);
const siswaList = ref(props.siswaListProp && props.siswaListProp.length ? props.siswaListProp : []);
const kelasList = ref(props.kelasListProp && props.kelasListProp.length ? props.kelasListProp : []);
const jurusanList = ref(props.jurusanListProp && props.jurusanListProp.length ? props.jurusanListProp : []);
const mapelList = ref(props.mapelListProp && props.mapelListProp.length ? props.mapelListProp : []);
const kelompokMapelList = ref(props.kelompokMapelListProp && props.kelompokMapelListProp.length ? props.kelompokMapelListProp : []);
const userAccountsList = ref(props.userAccountsListProp && props.userAccountsListProp.length ? props.userAccountsListProp : []);

watch(() => props.guruListProp, (val) => { if (val) guruList.value = val; });
watch(() => props.waliKelasListProp, (val) => { if (val) waliKelasList.value = val; });
watch(() => props.siswaListProp, (val) => { if (val) siswaList.value = val; });
watch(() => props.kelasListProp, (val) => { if (val) kelasList.value = val; });
watch(() => props.jurusanListProp, (val) => { if (val) jurusanList.value = val; });
watch(() => props.mapelListProp, (val) => { if (val) mapelList.value = val; });
watch(() => props.kelompokMapelListProp, (val) => { if (val) kelompokMapelList.value = val; });
watch(() => props.userAccountsListProp, (val) => { if (val) userAccountsList.value = val; });

// Notifications State
const notifications = ref([
  { id: 1, title: 'Input Guru Baru', text: 'Data guru Budi Santoso berhasil didaftarkan.', time: '10 menit lalu', read: false },
  { id: 2, title: 'Perubahan Password', text: 'Admin telah mereset password akun guru_informatika.', time: '1 jam lalu', read: false }
]);
const unreadNotifCount = computed(() => notifications.value.filter(n => !n.read).length);

function markAllNotifRead() {
  notifications.value.forEach(n => n.read = true);
}

function handleLogout() {
  router.post(route('logout'));
}



// CRUD Handlers for Data Siswa (Admin Side)
function onSaveSiswa({ editing, data }) {
  router.post(route('admin.siswa.store'), data, {
    preserveScroll: true,
    only: ['siswaListProp']
  });
}

function onDeleteSiswa(id) {
  confirmDelete({
    title: 'Hapus Data Siswa?',
    text: 'Data siswa ini akan dihapus dari database!',
    confirmButtonText: 'Ya, Hapus Siswa!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.siswa.delete', id), {
        preserveScroll: true,
        only: ['siswaListProp']
      });
    }
  });
}

function onBulkDeleteSiswa(ids) {
  confirmDelete({
    title: 'Hapus Beberapa Siswa?',
    text: `Apakah Anda yakin ingin menghapus ${ids.length} data siswa yang dipilih?`,
    confirmButtonText: 'Ya, Hapus Semua!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route('admin.siswa.bulk-delete'), { ids }, {
        preserveScroll: true,
        only: ['siswaListProp']
      });
    }
  });
}

function onImportSiswa(importedList) {
  router.post(route('admin.siswa.import-batch'), { rows: importedList }, {
    preserveScroll: true,
    only: ['siswaListProp', 'kelasListProp']
  });
}

// CRUD Handlers for Master Kelas & Jurusan
function onSaveKelas({ editing, data }) {
  router.post(route('admin.kelas.store'), data, {
    preserveScroll: true,
    only: ['kelasListProp', 'waliKelasListProp']
  });
}

function onDeleteKelas(id) {
  confirmDelete({
    title: 'Hapus Data Kelas?',
    text: 'Data kelas ini akan dihapus permanen dari database!',
    confirmButtonText: 'Ya, Hapus Kelas!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.kelas.delete', id), {
        preserveScroll: true,
        only: ['kelasListProp', 'waliKelasListProp']
      });
    }
  });
}

function onSaveJurusan({ editing, data }) {
  router.post(route('admin.jurusan.store'), data, {
    preserveScroll: true,
    only: ['jurusanListProp']
  });
}

function onDeleteJurusan(id) {
  confirmDelete({
    title: 'Hapus Data Jurusan?',
    text: 'Data jurusan ini akan dihapus permanen!',
    confirmButtonText: 'Ya, Hapus Jurusan!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.jurusan.delete', id), {
        preserveScroll: true,
        only: ['jurusanListProp']
      });
    }
  });
}

// CRUD Handlers for Master Data Mata Pelajaran
function onSaveMapel(data) {
  router.post(route('admin.mapel.store'), data, {
    preserveScroll: true,
    only: ['mapelListProp']
  });
}

function onDeleteMapel(id) {
  confirmDelete({
    title: 'Hapus Mata Pelajaran?',
    text: 'Mata pelajaran ini akan dihapus dari database sekolah!',
    confirmButtonText: 'Ya, Hapus Mapel!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.mapel.delete', id), {
        preserveScroll: true,
        only: ['mapelListProp']
      });
    }
  });
}

function onSaveKelompokMapel(data) {
  router.post(route('admin.kelompok.store'), data, {
    preserveScroll: true,
    only: ['kelompokMapelListProp']
  });
}

function onDeleteKelompokMapel(id) {
  confirmDelete({
    title: 'Hapus Kelompok Mapel?',
    text: 'Kelompok mata pelajaran ini akan dihapus dari database!',
    confirmButtonText: 'Ya, Hapus Kelompok!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.kelompok.delete', id), {
        preserveScroll: true,
        only: ['kelompokMapelListProp']
      });
    }
  });
}

// Master Guru Handlers
function onSaveGuru(payload) {
  const data = payload.data ? payload.data : payload;
  router.post(route('admin.guru.store'), data, {
    preserveScroll: true,
    only: ['guruListProp', 'waliKelasListProp', 'userAccountsListProp']
  });
}

function onDeleteGuru(id) {
  confirmDelete({
    title: 'Hapus Master Guru?',
    text: 'Data master guru ini akan dihapus permanen dari database!',
    confirmButtonText: 'Ya, Hapus!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.guru.delete', id), {
        preserveScroll: true,
        only: ['guruListProp', 'waliKelasListProp', 'userAccountsListProp']
      });
    }
  });
}

// User & Password Handlers
function onSaveUser({ editing, data }) {
  router.post(route('admin.user.store'), data, {
    preserveScroll: true,
    only: ['userAccountsListProp', 'guruListProp', 'waliKelasListProp']
  });
}

function onResetPassword({ userId, newPassword }) {
  router.post(route('admin.user.reset-password'), { userId, newPassword }, {
    preserveScroll: true,
    only: ['userAccountsListProp']
  });
}

function onDeleteUser(id) {
  confirmDelete({
    title: 'Hapus Akun User?',
    text: 'Akun user ini akan dihapus permanen dari database!',
    confirmButtonText: 'Ya, Hapus Akun!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.user.delete', id), {
        preserveScroll: true,
        only: ['userAccountsListProp']
      });
    }
  });
}
</script>

<template>
  <Head title="Dashboard Admin — SMKS Muhammadiyah 2 Genteng" />

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
      />

      <!-- Main Content Views -->
      <main class="flex-1 min-w-0 max-w-full min-h-[calc(100vh-4rem)] p-3 sm:p-6 lg:p-8 pb-24 lg:pb-8">

        <div v-if="isLoading" class="fixed top-4 right-4 z-50 flex items-center gap-2 px-4 py-2 rounded-lg bg-purple-600/90 text-white text-sm shadow-lg">
          <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          Memuat data...
        </div>

        <!-- DASHBOARD OVERVIEW -->
        <DashboardOverview
          v-if="activeView === 'dashboard'"
          :guruList="guruList"
          :waliKelasList="waliKelasList"
          :siswaList="siswaList"
          :userAccountsList="userAccountsList"
          @change-view="v => activeView = v"
        />


        <!-- MASTER TAHUN PELAJARAN VIEW (SETTING UTAMA & RAPOR) -->
        <KelolaTahunPelajaran
          v-if="activeView === 'tahun-ajaran'"
          :tahunAjaranList="tahunAjaranListProp"
        />

        <!-- MASTER GURU VIEW -->
        <KelolaGuru
          v-if="activeView === 'guru'"
          :guruList="guruList"
          :mapelList="mapelList"
          @save-guru="onSaveGuru"
          @delete-guru="onDeleteGuru"
        />

        <!-- MASTER MATA PELAJARAN & KELOMPOK MAPEL VIEW -->
        <KelolaMataPelajaran
          v-if="activeView === 'mapel'"
          :mapelList="mapelList"
          :kelompokMapelList="kelompokMapelList"
          @save-mapel="onSaveMapel"
          @delete-mapel="onDeleteMapel"
          @save-kelompok="onSaveKelompokMapel"
          @delete-kelompok="onDeleteKelompokMapel"
        />

        <!-- PENGATURAN KELAS & JURUSAN VIEW (FULL ADMIN MASTER CONTROL CENTER) -->
        <MenuKelas
          v-if="activeView === 'kelas'"
          :isAdmin="true"
          :kelasListWithCount="kelasList"
          :jurusanList="jurusanList"
          :waliKelasList="waliKelasList"
          :guruList="guruList"
          @save-kelas="onSaveKelas"
          @delete-kelas="onDeleteKelas"
          @save-jurusan="onSaveJurusan"
          @delete-jurusan="onDeleteJurusan"
        />

        <!-- KELOLA SISWA VIEW (FULL CRUD FOR ADMIN) -->
        <MenuSiswa
          v-if="activeView === 'siswa'"
          :siswaList="siswaList"
          :kelasList="kelasList"
          :canManage="true"
          @save-siswa="onSaveSiswa"
          @delete-siswa="onDeleteSiswa"
          @bulk-delete-siswa="onBulkDeleteSiswa"
          @import-siswa="onImportSiswa"
        />

        <KelolaUserPassword
          v-if="activeView === 'users'"
          :userAccountsList="userAccountsList"
          :guruList="guruList"
          :siswaList="siswaList"
          :mapelList="mapelList"
          :kelasList="kelasList"
          @save-user="onSaveUser"
          @reset-password="onResetPassword"
          @delete-user="onDeleteUser"
          @save-guru="onSaveGuru"
          @delete-guru="onDeleteGuru"
        />

        <KelolaBackup
          v-if="activeView === 'backup'"
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
