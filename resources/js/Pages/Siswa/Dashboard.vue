<script setup>
import { ref, computed, onMounted, onUnmounted, watch, defineAsyncComponent } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { showSuccessAlert, showToast } from '@/Utils/sweetalert';

// Modular Siswa Components
import Header from '@/Components/Siswa/Header.vue';
import Sidebar from '@/Components/Siswa/Sidebar.vue';
import MobileBottomNav from '@/Components/Siswa/MobileBottomNav.vue';
import DashboardOverview from '@/Components/Siswa/DashboardOverview.vue';
import CbtSiswa from '@/Components/Siswa/CbtSiswa.vue';

// Lazy loaded secondary components (hidden for now, retained for future phases)
const MateriSiswa = defineAsyncComponent(() => import('@/Components/Siswa/MateriSiswa.vue'));
const TugasSiswa = defineAsyncComponent(() => import('@/Components/Siswa/TugasSiswa.vue'));
const NilaiPerTP = defineAsyncComponent(() => import('@/Components/Siswa/NilaiPerTP.vue'));
const MenuJadwalSiswa = defineAsyncComponent(() => import('@/Components/Siswa/MenuJadwalSiswa.vue'));

const props = defineProps({
  siswaObjProp: { type: Object, default: () => ({}) },
  siswaListProp: { type: Array, default: () => [] },
  cbtJadwalListProp: { type: Array, default: () => [] },
  cbtNilaiListProp: { type: Array, default: () => [] },
  cbtSesiListProp: { type: Array, default: () => [] },
  jadwalListProp: { type: Array, default: () => [] },
  materiListProp: { type: Array, default: () => [] },
  tugasListProp: { type: Array, default: () => [] },
  nilaiTpListProp: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userInitials = computed(() => {
  const name = props.siswaObjProp?.nama || user.value?.name || 'Siswa';
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
});

const validTabs = ['dashboard', 'cbt', 'materi', 'tugas', 'nilai', 'jadwal'];

const getInitialTab = () => {
  if (typeof window !== 'undefined') {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab && validTabs.includes(tab)) return tab;
    return 'dashboard';
  }
  return 'dashboard';
};

const sidebarOpen = ref(typeof window !== 'undefined' ? window.innerWidth >= 1024 : true);
const activeView = ref(getInitialTab());

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
});

function handlePopState() {
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab');
  activeView.value = (tab && validTabs.includes(tab)) ? tab : 'dashboard';
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('popstate', handlePopState);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('popstate', handlePopState);
  }
});

const kelasSiswa = ref(props.siswaObjProp?.kelas || 'Kelas Siswa');

// Real database states without dummy data
const materiList = ref(props.materiListProp || []);
const tugasList = ref(props.tugasListProp || []);
const nilaiTPList = ref(props.nilaiTpListProp || []);
const notifications = ref([]);

const unreadNotifCount = computed(() => notifications.value.filter(n => !n.read).length);

function markAllNotifRead() {
  notifications.value.forEach(n => n.read = true);
}

function handleLogout() {
  router.post(route('logout'));
}

function onSubmitTugas({ tugasId, data }) {
  router.post(route('siswa.tugas.submit'), {
    tugasId,
    siswa_id: data?.siswaId || null,
    linkHasil: data?.linkHasil,
    catatan: data?.catatan,
  }, {
    preserveScroll: true,
    only: ['tugasListProp'],
  });
}
</script>

<template>
  <Head title="Dashboard Siswa — SMKS Muhammadiyah 2 Genteng" />

  <div class="min-h-screen bg-slate-900 text-white font-sans">
    
    <!-- Top Header Component -->
    <Header
      :user="user"
      :userInitials="userInitials"
      :unreadNotifCount="unreadNotifCount"
      :notifications="notifications"
      :kelasSiswa="kelasSiswa"
      @toggle-sidebar="sidebarOpen = !sidebarOpen"
      @logout="handleLogout"
      @change-view="v => activeView = v"
      @mark-read="markAllNotifRead"
    />

    <div class="flex">
      
      <Sidebar v-model:activeView="activeView" v-model:sidebarOpen="sidebarOpen" :kelasSiswa="kelasSiswa" />
      <main class="flex-1 min-w-0 max-w-full min-h-[calc(100vh-4rem)] p-3.5 sm:p-6 lg:p-8 pb-28 sm:pb-32 lg:pb-8">

        <!-- 1. DASHBOARD UTAMA OVERVIEW -->
        <DashboardOverview
          v-if="activeView === 'dashboard'"
          :siswaObj="siswaObjProp"
          :kelasSiswa="kelasSiswa"
          :siswaList="props.siswaListProp"
          :cbtJadwalList="props.cbtJadwalListProp"
          :cbtNilaiList="props.cbtNilaiListProp"
          @change-view="v => activeView = v"
        />

        <!-- 2. CBT (UJIAN ONLINE) VIEW -->
        <CbtSiswa
          v-if="activeView === 'cbt'"
          :siswaList="props.siswaListProp"
          :cbtJadwalList="props.cbtJadwalListProp"
          :cbtNilaiList="props.cbtNilaiListProp"
          :cbtSesiList="props.cbtSesiListProp"
          :siswaObj="props.siswaObjProp"
        />

        <!-- 3. MATERI PEMBELAJARAN VIEW -->
        <MateriSiswa
          v-if="activeView === 'materi'"
          :materiList="materiList"
          :siswaList="props.siswaListProp"
          :siswaObj="props.siswaObjProp"
          @change-view="v => activeView = v"
        />

        <TugasSiswa
          v-if="activeView === 'tugas'"
          :tugasList="tugasList"
          :siswaList="props.siswaListProp"
          :siswaObj="props.siswaObjProp"
          @submit-tugas="onSubmitTugas"
        />

        <NilaiPerTP
          v-if="activeView === 'nilai'"
          :nilaiTPList="nilaiTPList"
        />

        <MenuJadwalSiswa
          v-if="activeView === 'jadwal'"
          :siswaObj="siswaObjProp"
          :jadwalList="jadwalListProp"
        />

      </main>
    </div>

    <!-- Mobile Bottom Navigation Bar (5-Tab Modern Floating Dock & Drawer) -->
    <MobileBottomNav
      v-model:activeView="activeView"
      v-model:sidebarOpen="sidebarOpen"
      :kelasSiswa="kelasSiswa"
    />
  </div>
</template>
