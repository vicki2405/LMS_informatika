<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  canLogin: {
    type: Boolean,
    default: true,
  },
  canRegister: {
    type: Boolean,
    default: true,
  },
});

const mobileMenuOpen = ref(false);
const selectedRoleFilter = ref('all');
const selectedJurusanModal = ref(null);

// Dynamic Animated Subtitles Cycle
const animatedSubtitles = [
  '⚡ Presensi Batch Harian & Pencatatan Jurnal Mengajar Guru',
  '🎯 Assesmen TP (KKTP) & Matriks Ledger Nilai Rapor Semester',
  '🏫 Integrated Gateway Administrator, Wali Kelas, Guru Mapel & Siswa',
  '🚀 Terhubung dengan 5 Kompetensi Keahlian TKR, TSM, TEI, Animasi & Kuliner'
];
const currentSubtitleIndex = ref(0);
let subtitleTimer = null;

onMounted(() => {
  subtitleTimer = setInterval(() => {
    currentSubtitleIndex.value = (currentSubtitleIndex.value + 1) % animatedSubtitles.length;
  }, 3200);
});

onUnmounted(() => {
  if (subtitleTimer) clearInterval(subtitleTimer);
});

const jurusanList = [
  {
    kode: 'TKR',
    nama: 'Teknik Kendaraan Ringan',
    icon: '🚗',
    color: 'from-amber-500 via-orange-500 to-red-600',
    shadow: 'shadow-orange-500/20',
    border: 'hover:border-orange-500/50',
    desc: 'Otomotif, Perbaikan Mesin EFI & Maintenance Kendaraan Ringan.',
    prospek: 'Teknisi Otomotif, Service Advisor, Wirausaha Perbengkelan',
    lab: 'Bengkel Otomotif TKR'
  },
  {
    kode: 'TSM',
    nama: 'Teknik Sepeda Motor',
    icon: '🏍️',
    color: 'from-blue-500 via-indigo-500 to-cyan-400',
    shadow: 'shadow-blue-500/20',
    border: 'hover:border-cyan-500/50',
    desc: 'Teknologi Mesin Sepeda Motor Injeksi, Chasis & Kelistrikan.',
    prospek: 'Mekanik Resmi Motor, Junior Engineer, Entrepreneur',
    lab: 'Bengkel Standar Honda TSM'
  },
  {
    kode: 'TEI',
    nama: 'Teknik Elektronika Industri',
    icon: '⚡',
    color: 'from-cyan-400 via-teal-400 to-emerald-500',
    shadow: 'shadow-teal-500/20',
    border: 'hover:border-teal-500/50',
    desc: 'Otomasi Industri, Microcontroller, PLC & Robotika Dasar.',
    prospek: 'Teknisi Otomasi Industri, Instrumentasi, IoT Developer',
    lab: 'Lab Robotika & PLC TEI'
  },
  {
    kode: 'ANIMASI',
    nama: 'Animasi',
    icon: '🎨',
    color: 'from-purple-500 via-fuchsia-500 to-pink-500',
    shadow: 'shadow-purple-500/20',
    border: 'hover:border-purple-500/50',
    desc: 'Seni Komunikasi Visual, Animasi 2D/3D, Compositing & Media Digital.',
    prospek: 'Animator 2D/3D, 3D Modeler, Storyboard Artist, Graphic Designer',
    lab: 'Studio Animasi & Editing Workstation'
  },
  {
    kode: 'KULINER',
    nama: 'Kuliner',
    icon: '🍳',
    color: 'from-amber-400 via-emerald-400 to-teal-500',
    shadow: 'shadow-emerald-500/20',
    border: 'hover:border-emerald-500/50',
    desc: 'Seni Kuliner, Pastry, Bakery & Management Industri Boga.',
    prospek: 'Chef Professional, Baker, Food & Beverage Entrepreneur',
    lab: 'Dapur Kitchen Lab & Pastry Room'
  }
];

const duplicatedJurusanList = computed(() => [...jurusanList, ...jurusanList]);

const systemFeatures = [
  {
    title: 'Keamanan Akses Portal',
    icon: '🔐',
    color: 'from-amber-500 to-orange-500',
    desc: 'Pelindungan data civitas akademika dengan sistem enkripsi dan pembatasan hak akses berbasis peran (Role-Based Access Control) yang terintegrasi secara ketat.'
  },
  {
    title: 'Manajemen Pembelajaran',
    icon: '📚',
    color: 'from-cyan-500 to-teal-500',
    desc: 'Penyediaan modul materi ajar digital dan penugasan terstruktur yang dapat diakses oleh guru dan siswa kapan saja secara terpusat.'
  },
  {
    title: 'Presensi Terpadu',
    icon: '📝',
    color: 'from-indigo-500 to-blue-500',
    desc: 'Pencatatan kehadiran harian siswa secara cepat dan efisien oleh Guru Mapel maupun Wali Kelas binaan.'
  },
  {
    title: 'Ledger Nilai Digital',
    icon: '📊',
    color: 'from-emerald-500 to-green-500',
    desc: 'Transparansi rekapitulasi nilai capaian Tujuan Pembelajaran (TP) untuk pemantauan hasil belajar siswa secara real-time.'
  }
];
</script>

<template>
  <Head title="Portal Internal SMKS Muhammadiyah 2 Genteng" />

  <div class="min-h-screen bg-slate-950 text-white font-sans selection:bg-cyan-500 selection:text-slate-950 overflow-x-hidden">
    
    <!-- NAVIGATION BAR OFFICIAL SCHOOL (FULL RESPONSIVE) -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-slate-950/90 backdrop-blur-2xl border-b border-white/10 transition-all duration-300">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
          
          <!-- School Logo & Title -->
          <div class="flex items-center gap-3 shrink-0">
            <div class="w-10 h-10 sm:w-11 sm:h-11 bg-gradient-to-tr from-emerald-500 via-teal-500 to-cyan-400 rounded-2xl flex items-center justify-center font-black text-white text-xl sm:text-2xl shadow-lg shadow-teal-500/25 border border-white/20 hover:scale-105 transition-transform cursor-pointer">
              🏫
            </div>
            <div>
              <span class="font-black text-sm sm:text-base tracking-tight text-white block leading-tight">
                SMKS MUHAMMADIYAH 2 GENTENG
              </span>
              <span class="text-[9px] sm:text-[10px] font-bold text-cyan-400 uppercase tracking-wider block">Portal Informasi Akademik Internal</span>
            </div>
          </div>

          <!-- Desktop Links -->
          <div class="hidden lg:flex items-center gap-8">
            <a href="#jurusan" class="text-xs sm:text-sm font-semibold text-gray-300 hover:text-white transition-colors">Jurusan</a>
            <a href="#peran" class="text-xs sm:text-sm font-semibold text-gray-300 hover:text-white transition-colors">Hak Akses Role</a>
            <a href="#panduan" class="text-xs sm:text-sm font-semibold text-gray-300 hover:text-white transition-colors">Panduan Sistem</a>
            
            <Link
              v-if="$page.props.auth?.user"
              :href="route('dashboard')"
              class="bg-gradient-to-r from-cyan-500 via-blue-600 to-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-xl shadow-blue-600/25 hover:shadow-cyan-500/30 transition-all duration-300 hover:-translate-y-0.5 border border-white/10 active:scale-95 flex items-center gap-2"
            >
              <span>🚀</span> Dashboard Saya
            </Link>
            <Link
              v-else
              :href="route('login')"
              class="bg-gradient-to-r from-cyan-500 via-blue-600 to-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-xl shadow-blue-600/25 hover:shadow-cyan-500/30 transition-all duration-300 hover:-translate-y-0.5 border border-white/10 active:scale-95 flex items-center gap-2"
            >
              <span>🔐</span> Masuk Akun Portal
            </Link>
          </div>

          <!-- Mobile Hamburger Toggle Button -->
          <button
            @click="mobileMenuOpen = !mobileMenuOpen"
            class="lg:hidden p-2.5 rounded-xl bg-white/5 border border-white/10 text-gray-300 hover:text-white active:scale-95 transition-all min-h-[44px] min-w-[44px] flex items-center justify-center"
            aria-label="Toggle Navigation Menu"
          >
            <svg v-if="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

        </div>

        <!-- Mobile Drawer Navigation Overlay -->
        <div v-if="mobileMenuOpen" class="lg:hidden pb-6 pt-3 space-y-3 border-t border-white/10 animate-fade-in">
          <a href="#jurusan" @click="mobileMenuOpen = false" class="block px-4 py-3 rounded-xl font-semibold text-gray-300 hover:bg-white/10 hover:text-white text-sm transition-colors">
            📚 5 Kompetensi Keahlian (Jurusan)
          </a>
          <a href="#peran" @click="mobileMenuOpen = false" class="block px-4 py-3 rounded-xl font-semibold text-gray-300 hover:bg-white/10 hover:text-white text-sm transition-colors">
            🔑 Hak Akses Peran Pengguna
          </a>
          <a href="#panduan" @click="mobileMenuOpen = false" class="block px-4 py-3 rounded-xl font-semibold text-gray-300 hover:bg-white/10 hover:text-white text-sm transition-colors">
            💡 Panduan & Layanan Bantuan IT
          </a>
          
          <div class="pt-2">
            <Link
              v-if="$page.props.auth?.user"
              :href="route('dashboard')"
              class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-extrabold px-5 py-3.5 rounded-xl text-sm shadow-lg min-h-[48px]"
            >
              🚀 Dashboard Saya
            </Link>
            <Link
              v-else
              :href="route('login')"
              class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-cyan-500 via-teal-500 to-emerald-500 text-slate-950 font-black px-5 py-3.5 rounded-xl text-sm shadow-lg min-h-[48px]"
            >
              🔐 Masuk ke Portal Akun
            </Link>
          </div>
        </div>

      </div>
    </nav>

    <!-- HERO SECTION PORTAL INTERNAL (ANIMATED & DYNAMIC GLOW) -->
    <section class="relative pt-32 sm:pt-40 pb-20 sm:pb-28 px-4 sm:px-6 lg:px-8 overflow-hidden">
      
      <!-- Glowing Background Animations -->
      <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[320px] sm:w-[600px] h-[320px] sm:h-[600px] bg-gradient-to-tr from-teal-500/20 via-cyan-500/25 to-blue-600/20 rounded-full blur-[100px] sm:blur-[140px] pointer-events-none animate-pulse"></div>
      <div class="absolute top-20 left-5 sm:left-10 w-48 sm:w-80 h-48 sm:h-80 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute bottom-10 right-5 sm:right-10 w-60 sm:w-96 h-60 sm:h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="max-w-7xl mx-auto relative z-10 text-center">
        
        <!-- Live Academic Semester Badge -->
        <div class="inline-flex items-center gap-2.5 bg-white/5 border border-white/15 backdrop-blur-xl rounded-full px-4 py-2 mb-6 sm:mb-8 shadow-xl hover:bg-white/10 transition-all cursor-default">
          <span class="flex h-2.5 w-2.5 relative">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-400"></span>
          </span>
          <span class="text-xs sm:text-sm font-extrabold text-emerald-300 tracking-wide">Penggunaan Internal • Semester Ganjil TA 2025/2026</span>
        </div>

        <!-- Main Animated Headline (Without 'Selamat Datang di Portal') -->
        <h1 class="text-3xl sm:text-5xl lg:text-7xl font-black tracking-tight leading-[1.15] mb-6 max-w-5xl mx-auto">
          <span class="text-white block mb-2 sm:mb-3">Sistem Informasi Akademik</span>
          <span class="bg-gradient-to-r from-cyan-400 via-teal-300 to-emerald-400 bg-[length:200%_auto] animate-gradient-text bg-clip-text text-transparent inline-block hover:scale-[1.02] transition-transform">
            SMKS Muhammadiyah 2 Genteng
          </span>
        </h1>

        <!-- Dynamic Animated Subtitle Rotator -->
        <div class="min-h-[56px] flex items-center justify-center mb-8 sm:mb-10 px-2">
          <p
            :key="currentSubtitleIndex"
            class="text-sm sm:text-lg lg:text-xl text-cyan-300 font-semibold max-w-3xl leading-relaxed animate-fade-switch bg-cyan-500/10 border border-cyan-500/20 px-5 py-2.5 rounded-2xl shadow-lg"
          >
            {{ animatedSubtitles[currentSubtitleIndex] }}
          </p>
        </div>

        <!-- CTA Buttons (Stacked on Mobile, Row on Tablet/Desktop) -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16 max-w-md sm:max-w-none mx-auto">
          <Link
            :href="route('login')"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-gradient-to-r from-cyan-400 via-teal-400 to-emerald-500 text-slate-950 font-black text-base px-9 py-4 rounded-2xl shadow-2xl shadow-teal-500/25 hover:shadow-cyan-400/40 hover:scale-105 active:scale-95 transition-all duration-300 min-h-[52px]"
          >
            <span>🔐</span> Masuk ke Portal Akun
          </Link>
          <a
            href="#peran"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 text-white font-bold text-base px-8 py-4 rounded-2xl border border-white/15 backdrop-blur-xl transition-all duration-300 min-h-[52px]"
          >
            Lihat Akses Peran ↓
          </a>
        </div>

        <!-- 5 JURUSAN OFFICIAL SHOWCASE CAROUSEL (BERJALAN KANAN KE KIRI LALU KEMBALI) -->
        <div id="jurusan" class="pt-6">
          <div class="flex items-center justify-center gap-2 mb-6">
            <span class="text-xs font-black uppercase tracking-widest text-cyan-400 bg-cyan-500/10 px-4 py-1.5 rounded-full border border-cyan-500/20 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
              5 Kompetensi Keahlian (Jurusan) Resmi — Arahkan Kursor / Sentuh Kartu Untuk Berhenti
            </span>
          </div>

          <!-- Running Track Outer Container -->
          <div class="relative overflow-hidden py-4 -mx-4 px-4 sm:mx-0 sm:px-0">
            <!-- Left & Right Gradient Blur Faders -->
            <div class="hidden sm:block absolute top-0 bottom-0 left-0 w-20 bg-gradient-to-r from-slate-950 to-transparent z-10 pointer-events-none"></div>
            <div class="hidden sm:block absolute top-0 bottom-0 right-0 w-20 bg-gradient-to-l from-slate-950 to-transparent z-10 pointer-events-none"></div>

            <!-- Animated Continuous 1-Way Running Track (Right to Left Endless Stream) -->
            <div class="flex gap-4 sm:gap-6 w-max animate-marquee-infinite hover:[animation-play-state:paused] px-2 sm:px-4">
              <div
                v-for="(j, idx) in duplicatedJurusanList"
                :key="j.kode + '-' + idx"
                @click="selectedJurusanModal = j"
                :class="[
                  'w-64 sm:w-72 bg-slate-900/90 border border-white/10 p-5 rounded-2xl transition-all duration-300 shadow-xl flex flex-col justify-between cursor-pointer group shrink-0 hover:-translate-y-2 hover:border-cyan-500/40 hover:shadow-cyan-500/10',
                  j.border
                ]"
              >
                <div>
                  <div class="flex items-center justify-between mb-3">
                    <span class="text-3xl p-2 bg-white/5 rounded-xl border border-white/10 group-hover:scale-110 transition-transform">
                      {{ j.icon }}
                    </span>
                    <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-lg bg-white/10 text-cyan-300 border border-white/10 font-mono">
                      {{ j.kode }}
                    </span>
                  </div>
                  
                  <h3 class="text-sm font-extrabold text-white mb-1.5 group-hover:text-cyan-300 transition-colors">
                    {{ j.nama }}
                  </h3>
                  <p class="text-xs text-gray-400 leading-relaxed line-clamp-2">
                    {{ j.desc }}
                  </p>
                </div>

                <div class="pt-4 mt-4 border-t border-white/10 flex items-center justify-between text-[11px] font-bold text-cyan-400 group-hover:text-cyan-300">
                  <span>Rincian Jurusan</span>
                  <span class="group-hover:translate-x-1 transition-transform">→</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- 4 PERAN SISTEM INTERNAL SECTION WITH INTERACTIVE FILTER TABS -->
    <!-- FITUR UTAMA PORTAL AKADEMIK SECTION -->
    <section id="peran" class="py-20 sm:py-28 px-4 sm:px-6 lg:px-8 relative bg-slate-900/40 border-y border-white/10">
      <div class="max-w-7xl mx-auto">
        
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
          <span class="text-xs font-black uppercase tracking-widest text-emerald-400 bg-emerald-500/10 px-4 py-1.5 rounded-full border border-emerald-500/20">
            Fasilitas Portal Akademik
          </span>
          <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white mt-4 mb-4">
            Fitur Utama Sistem
          </h2>
          <p class="text-gray-400 text-sm sm:text-base leading-relaxed px-2">
            Sistem Informasi Akademik terintegrasi untuk mendukung kegiatan operasional, belajar mengajar, dan administrasi sekolah.
          </p>
        </div>

        <!-- Feature Cards Grid (1-col on Mobile, 2-col on Tablet/Desktop) -->
        <div class="grid md:grid-cols-2 gap-6 sm:gap-8">
          <div
            v-for="feat in systemFeatures"
            :key="feat.title"
            class="bg-slate-900/90 border border-white/10 hover:border-white/30 rounded-3xl p-6 sm:p-8 shadow-2xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group flex flex-col justify-between"
          >
            <!-- Top Gradient Accent Line -->
            <div :class="['h-2.5 w-full absolute top-0 left-0 bg-gradient-to-r', feat.color]"></div>

            <div class="flex items-start gap-4">
              <span class="text-4xl p-3 bg-white/5 rounded-2xl border border-white/10 group-hover:scale-110 transition-transform">
                {{ feat.icon }}
              </span>
              <div>
                <h3 class="text-xl font-extrabold text-white group-hover:text-cyan-300 transition-colors">{{ feat.title }}</h3>
                <p class="text-xs sm:text-sm text-gray-400 mt-2 leading-relaxed">
                  {{ feat.desc }}
                </p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- PANDUAN PENULISAN SISTEM & BANTUAN IT -->
    <section id="panduan" class="py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/30 rounded-3xl p-6 sm:p-12 shadow-2xl flex flex-col md:flex-row items-center justify-between gap-8">
          <div class="space-y-4 max-w-2xl text-center md:text-left">
            <span class="text-xs font-extrabold text-indigo-400 uppercase tracking-wider bg-indigo-500/10 px-3.5 py-1 rounded-full border border-indigo-500/20 inline-block">
              Layanan Bantuan IT Sekolah
            </span>
            <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-white">
              Bantuan Kendala Login Akun Sistem
            </h2>
            <p class="text-xs sm:text-sm text-gray-300 leading-relaxed">
              Jika Anda mengalami kendala lupa password atau belum mempunyai akun login (Siswa/Guru/Wali Kelas), silakan hubungi tim Administrasi / IT Sekolah SMKS Muhammadiyah 2 Genteng.
            </p>
          </div>

          <Link
            :href="route('login')"
            class="w-full md:w-auto px-8 py-4 bg-gradient-to-r from-blue-500 to-cyan-500 text-white font-extrabold text-sm rounded-2xl shadow-xl hover:shadow-cyan-500/30 transition-all shrink-0 min-h-[50px] flex items-center justify-center gap-2 active:scale-95"
          >
            🔑 Halaman Login Akun
          </Link>
        </div>
      </div>
    </section>

    <!-- MODAL DETAIL JURUSAN (INTERACTIVE OVERLAY) -->
    <div v-if="selectedJurusanModal" class="fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-white/20 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-4 animate-fade-in relative">
        <button
          @click="selectedJurusanModal = null"
          class="absolute top-4 right-4 w-9 h-9 bg-white/10 hover:bg-white/20 text-gray-300 rounded-full flex items-center justify-center text-sm font-bold transition-all"
        >
          ✕
        </button>

        <div class="flex items-center gap-3">
          <span class="text-4xl p-3 bg-white/5 rounded-2xl border border-white/10">
            {{ selectedJurusanModal.icon }}
          </span>
          <div>
            <span class="text-xs font-mono font-bold text-cyan-400 uppercase">Jurusan {{ selectedJurusanModal.kode }}</span>
            <h3 class="text-xl font-extrabold text-white">{{ selectedJurusanModal.nama }}</h3>
          </div>
        </div>

        <p class="text-xs sm:text-sm text-gray-300 leading-relaxed pt-2">
          {{ selectedJurusanModal.desc }}
        </p>

        <div class="space-y-3 pt-3 border-t border-white/10">
          <div>
            <span class="text-xs font-bold text-gray-400 block mb-1">💼 Prospek Karir Lulusan:</span>
            <p class="text-xs text-cyan-300 font-semibold bg-cyan-500/10 p-2.5 rounded-xl border border-cyan-500/20">
              {{ selectedJurusanModal.prospek }}
            </p>
          </div>

          <div>
            <span class="text-xs font-bold text-gray-400 block mb-1">🏫 Fasilitas Lab Praktik:</span>
            <p class="text-xs text-emerald-300 font-semibold bg-emerald-500/10 p-2.5 rounded-xl border border-emerald-500/20">
              {{ selectedJurusanModal.lab }}
            </p>
          </div>
        </div>

        <div class="pt-4 text-right">
          <button
            @click="selectedJurusanModal = null"
            class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-cyan-500 text-white rounded-xl text-xs font-bold shadow-lg min-h-[40px]"
          >
            Tutup Informasi
          </button>
        </div>
      </div>
    </div>

    <!-- FOOTER INTERNAL SMKS MUHAMMADIYAH 2 GENTENG -->
    <footer class="py-12 px-4 sm:px-6 lg:px-8 border-t border-white/10 bg-slate-950 text-center">
      <div class="max-w-7xl mx-auto space-y-4">
        <div class="flex items-center justify-center gap-2">
          <span class="text-2xl">🏫</span>
          <span class="font-extrabold text-base text-white">SMKS MUHAMMADIYAH 2 GENTENG</span>
        </div>
        <p class="text-xs text-gray-400">
          Sistem Informasi Manajemen Sekolah Terpadu — Khusus Penggunaan Internal Sekolah
        </p>
        <div class="text-[11px] text-gray-600 pt-4 border-t border-white/5">
          © 2026 SMKS Muhammadiyah 2 Genteng. Hak Cipta Dilindungi Undang-Undang.
        </div>
      </div>
    </footer>

  </div>
</template>

<style scoped>
@keyframes gradientShift {
  0% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0% 50%;
  }
}

.animate-gradient-text {
  background-size: 200% auto;
  animation: gradientShift 5s ease-in-out infinite;
}

.animate-fade-switch {
  animation: fadeSwitch 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeSwitch {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes marqueeInfinite {
  0% {
    transform: translateX(0%);
  }
  100% {
    transform: translateX(-50%);
  }
}

.animate-marquee-infinite {
  animation: marqueeInfinite 25s linear infinite;
}
</style>
