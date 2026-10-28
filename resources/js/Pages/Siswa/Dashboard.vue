<script setup>
import { ref, computed, onMounted, onUnmounted, watch, defineAsyncComponent } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { showToast } from '@/Utils/sweetalert';

// Modular Siswa Components
import Header from '@/Components/Siswa/Header.vue';
import Sidebar from '@/Components/Siswa/Sidebar.vue';
import MobileBottomNav from '@/Components/Siswa/MobileBottomNav.vue';
import DashboardOverview from '@/Components/Siswa/DashboardOverview.vue';
import CbtSiswa from '@/Components/Siswa/CbtSiswa.vue';

// Lazy loaded secondary components
const MateriSiswa = defineAsyncComponent(() => import('@/Components/Siswa/MateriSiswa.vue'));
const TugasSiswa = defineAsyncComponent(() => import('@/Components/Siswa/TugasSiswa.vue'));
const NilaiPerTP = defineAsyncComponent(() => import('@/Components/Siswa/NilaiPerTP.vue'));

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
  tpListProp: { type: Array, default: () => [] },
  mapelListProp: { type: Array, default: () => [] },
  nilaiListProp: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userInitials = computed(() => {
  const name = props.siswaObjProp?.nama || user.value?.name || 'Siswa';
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
});

const validTabs = ['dashboard', 'materi', 'cbt', 'tugas', 'nilai'];

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

const kelasSiswa = ref(props.siswaObjProp?.kelas || 'Kelas Siswa');

// Central Shared Classroom Account Identity Management
const isAkunKelas = computed(() => Boolean(props.siswaObjProp?.isAkunKelas));
const hasSiswaList = computed(() => Boolean(props.siswaListProp?.length));
const activeSiswaId = ref(null);
const showIdentityModal = ref(false);
const studentSearchText = ref('');

const activeSiswa = computed(() => {
  if (!activeSiswaId.value) return null;
  return props.siswaListProp?.find(s => String(s.id) === String(activeSiswaId.value)) || null;
});

// Portal lock screen: all menus are locked until student explicitly selects their identity
const isLocked = computed(() => isAkunKelas.value && hasSiswaList.value && !activeSiswa.value);

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('popstate', handlePopState);
    if (isAkunKelas.value && hasSiswaList.value) {
      try {
        const savedId = localStorage.getItem('siswacloud_active_siswa_id');
        if (savedId && props.siswaListProp.some(s => String(s.id) === String(savedId))) {
          activeSiswaId.value = Number(savedId);
        } else {
          showIdentityModal.value = true;
        }
      } catch (e) {
        showIdentityModal.value = true;
      }
    }
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('popstate', handlePopState);
  }
});

function selectStudent(student) {
  activeSiswaId.value = student.id;
  try {
    localStorage.setItem('siswacloud_active_siswa_id', String(student.id));
  } catch (e) {}
  showIdentityModal.value = false;
  showToast(`Selamat datang, ${student.nama}!`, 'success');
}

function switchStudent() {
  studentSearchText.value = '';
  showIdentityModal.value = true;
}

const filteredClassSiswaList = computed(() => {
  const list = props.siswaListProp || [];
  if (!studentSearchText.value.trim()) return list;
  const q = studentSearchText.value.toLowerCase();
  return list.filter(s =>
    (s.nama || '').toLowerCase().includes(q) ||
    (s.nis && String(s.nis).toLowerCase().includes(q))
  );
});

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
    siswa_id: activeSiswa.value?.id || data?.siswaId || null,
    linkHasil: data?.linkHasil,
    catatan: data?.catatan,
  }, {
    preserveScroll: true,
    only: ['tugasListProp'],
  });
}

const selectedMateriId = ref(null);
const autoStartCbtPayload = ref(null);

function handleOpenMateri(materiId) {
  selectedMateriId.value = materiId;
  activeView.value = 'materi';
}

function handleStartCbtWithToken(payload) {
  autoStartCbtPayload.value = payload;
  activeView.value = 'cbt';
}
</script>

<template>
  <Head title="Dashboard Siswa — SMKS Muhammadiyah 2 Genteng" />

  <div class="min-h-screen bg-slate-900 text-white font-sans relative">
    
    <!-- Top Header Component -->
    <Header
      :user="user"
      :userInitials="userInitials"
      :unreadNotifCount="unreadNotifCount"
      :notifications="notifications"
      :kelasSiswa="kelasSiswa"
      :activeSiswa="activeSiswa"
      @toggle-sidebar="sidebarOpen = !sidebarOpen"
      @logout="handleLogout"
      @change-view="v => !isLocked && (activeView = v)"
      @mark-read="markAllNotifRead"
      @switch-siswa="switchStudent"
    />

    <!-- Main Container: BLURRED and LOCKED if isLocked is true -->
    <div
      class="flex transition-all duration-300"
      :class="isLocked ? 'pointer-events-none select-none filter blur-sm opacity-40' : ''"
    >
      <Sidebar
        v-model:activeView="activeView"
        v-model:sidebarOpen="sidebarOpen"
        :kelasSiswa="kelasSiswa"
      />
      
      <main class="flex-1 min-w-0 max-w-full min-h-[calc(100vh-4rem)] p-3.5 sm:p-6 lg:p-8 pb-28 sm:pb-32 lg:pb-8">

        <!-- 1. DASHBOARD UTAMA OVERVIEW -->
        <DashboardOverview
          v-if="activeView === 'dashboard'"
          :siswaObj="siswaObjProp"
          :kelasSiswa="kelasSiswa"
          :siswaList="props.siswaListProp"
          :activeSiswa="activeSiswa"
          :cbtJadwalList="props.cbtJadwalListProp"
          :cbtNilaiList="props.cbtNilaiListProp"
          @change-view="v => activeView = v"
          @switch-siswa="switchStudent"
        />

        <!-- 2. MATERI PEMBELAJARAN VIEW -->
        <MateriSiswa
          v-if="activeView === 'materi'"
          :materiList="materiList"
          :siswaList="props.siswaListProp"
          :siswaObj="props.siswaObjProp"
          :activeSiswa="activeSiswa"
          :targetMateriId="selectedMateriId"
          @change-view="v => activeView = v"
          @switch-siswa="switchStudent"
          @start-cbt-with-token="handleStartCbtWithToken"
        />

        <!-- 3. CBT (UJIAN ONLINE) VIEW -->
        <CbtSiswa
          v-if="activeView === 'cbt'"
          :siswaList="props.siswaListProp"
          :cbtJadwalList="props.cbtJadwalListProp"
          :cbtNilaiList="props.cbtNilaiListProp"
          :cbtSesiList="props.cbtSesiListProp"
          :siswaObj="props.siswaObjProp"
          :activeSiswa="activeSiswa"
          :autoStartExam="autoStartCbtPayload"
          @open-materi="handleOpenMateri"
        />

        <TugasSiswa
          v-if="activeView === 'tugas'"
          :tugasList="tugasList"
          :siswaList="props.siswaListProp"
          :siswaObj="props.siswaObjProp"
          :activeSiswa="activeSiswa"
          @submit-tugas="onSubmitTugas"
        />

        <NilaiPerTP
          v-if="activeView === 'nilai'"
          :tpList="props.tpListProp"
          :mapelList="props.mapelListProp"
          :nilaiList="props.nilaiListProp"
          :siswaList="props.siswaListProp"
          :kelasSiswa="kelasSiswa"
          :nilaiTPList="nilaiTPList"
          :siswaObj="props.siswaObjProp"
          :activeSiswa="activeSiswa"
        />

      </main>
    </div>

    <!-- Mobile Bottom Navigation Bar (5-Tab Modern Floating Dock & Drawer) -->
    <MobileBottomNav
      v-if="!isLocked"
      v-model:activeView="activeView"
      v-model:sidebarOpen="sidebarOpen"
      :kelasSiswa="kelasSiswa"
    />

    <!-- PORTAL LOCK SCREEN & IDENTITY SELECTION MODAL -->
    <!-- Jika isLocked (belum pilih nama di akun kelas bersama), modal TIDAK BISA ditutup dan seluruh menu terkunci -->
    <div
      v-if="isLocked || showIdentityModal"
      class="fixed inset-0 z-[100] bg-slate-950/90 backdrop-blur-xl flex items-center justify-center p-3.5 sm:p-6 animate-in fade-in duration-300"
    >
      <div
        class="bg-slate-900 border-2 rounded-3xl max-w-lg w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden"
        :class="isLocked ? 'border-amber-500/40 shadow-amber-500/10' : 'border-cyan-500/40 shadow-cyan-500/10'"
      >
        <!-- Modal Top Bar -->
        <div class="p-5 bg-slate-950 border-b border-white/10 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <div
              class="w-10 h-10 rounded-2xl flex items-center justify-center text-xl shrink-0 border"
              :class="isLocked ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30'"
            >
              {{ isLocked ? '🔒' : '👤' }}
            </div>
            <div class="min-w-0">
              <h2 class="text-base sm:text-lg font-black text-white truncate">
                {{ isLocked ? 'Kunci Portal Belajar Siswa' : 'Ganti Identitas Siswa' }}
              </h2>
              <p class="text-xs text-slate-400 truncate">
                Akun Bersama: Kelas {{ kelasSiswa }}
              </p>
            </div>
          </div>

          <!-- Close button only enabled if ALREADY selected an identity before -->
          <button
            v-if="!isLocked"
            @click="showIdentityModal = false"
            class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold flex items-center justify-center transition shrink-0"
            title="Batal"
          >
            ✕
          </button>
        </div>

        <!-- Lock Warning Notice Banner -->
        <div class="px-5 py-3 bg-amber-500/10 border-b border-amber-500/20 flex items-start gap-2.5 text-xs text-amber-300">
          <span class="text-base leading-none shrink-0">⚠️</span>
          <p class="leading-relaxed">
            <strong class="font-extrabold text-white">Menu Terkunci:</strong> Silakan klik nama Anda di bawah ini untuk membuka modul materi, tugas, ujian CBT, dan mencatat absensi otomatis kelas hari ini.
          </p>
        </div>

        <!-- Search Input -->
        <div class="p-4 border-b border-white/10 bg-slate-900/50">
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">🔍</span>
            <input
              v-model="studentSearchText"
              type="text"
              placeholder="Cari nama Anda atau nomor NIS..."
              class="w-full pl-9 pr-4 py-2.5 bg-slate-950 border border-white/15 rounded-xl text-xs text-white placeholder-slate-400 focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 outline-none transition"
              autofocus
            />
          </div>
        </div>

        <!-- Student List Selection -->
        <div class="flex-1 overflow-y-auto p-4 space-y-2 max-h-[50vh]">
          <div
            v-for="s in filteredClassSiswaList"
            :key="s.id"
            @click="selectStudent(s)"
            class="group p-3 rounded-2xl border transition-all cursor-pointer flex items-center justify-between gap-3 active:scale-[0.99]"
            :class="activeSiswaId === s.id ? 'bg-cyan-500/20 border-cyan-400 text-white shadow-md shadow-cyan-500/10' : 'bg-slate-950/60 border-white/5 hover:border-cyan-400/30 hover:bg-white/[0.04] text-slate-200'"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div
                class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 border"
                :class="s.jenisKelamin === 'P' ? 'bg-pink-500/10 text-pink-300 border-pink-500/20' : 'bg-blue-500/10 text-blue-300 border-blue-500/20'"
              >
                {{ s.jenisKelamin === 'P' ? '👧' : '👦' }}
              </div>
              <div class="min-w-0">
                <div class="font-black text-sm text-white group-hover:text-cyan-300 transition truncate">
                  {{ s.nama }}
                </div>
                <div class="text-[11px] font-mono text-slate-400 flex items-center gap-2">
                  <span>NIS: {{ s.nis || ('100' + s.id) }}</span>
                  <span v-if="activeSiswaId === s.id" class="text-cyan-400 font-bold">• Sedang Aktif</span>
                </div>
              </div>
            </div>

            <div class="shrink-0">
              <span
                class="px-3 py-1 rounded-xl text-xs font-bold transition border"
                :class="activeSiswaId === s.id ? 'bg-cyan-500 text-slate-950 border-cyan-400' : 'bg-white/5 border-white/10 text-slate-400 group-hover:bg-cyan-500/20 group-hover:text-cyan-300 group-hover:border-cyan-400/40'"
              >
                {{ activeSiswaId === s.id ? 'Aktif ✓' : 'Pilih Saya →' }}
              </span>
            </div>
          </div>

          <div v-if="filteredClassSiswaList.length === 0" class="text-center py-8 text-xs text-slate-400">
            Nama tidak ditemukan. Periksa ejaan kata kunci pencarian.
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-3.5 bg-slate-950 border-t border-white/10 text-center text-[11px] text-slate-400">
          <span>SMKS Muhammadiyah 2 Genteng • Sistem Presensi Otomatis Pembelajaran</span>
        </div>
      </div>
    </div>

  </div>
</template>