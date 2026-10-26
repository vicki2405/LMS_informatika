<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { showSuccessAlert, showToast } from '@/Utils/sweetalert';

const props = defineProps({
  jadwalProp: { type: Object, default: null },
  bankSoalList: { type: Array, default: () => [] },
  masterKelasList: { type: Array, default: () => [] },
  jurusanList: { type: Array, default: () => [] },
});

const isEditing = computed(() => !!props.jadwalProp?.id);

// Form state
const form = ref({
  id: props.jadwalProp?.id || null,
  bank_soal_id: props.jadwalProp?.bank_soal_id || '',
  jurusan_id: props.jadwalProp?.jurusan_id || '',
  kelas_id: props.jadwalProp?.kelas_id || '',
  judul_ujian: props.jadwalProp?.judul_ujian || '',
  waktu_mulai: props.jadwalProp?.waktu_mulai || '',
  waktu_selesai: props.jadwalProp?.waktu_selesai || '',
  durasi_menit: props.jadwalProp?.durasi_menit || 60,
  token: props.jadwalProp?.token || '',
  acak_soal: props.jadwalProp ? !!props.jadwalProp.acak_soal : true,
  acak_opsi: props.jadwalProp ? !!props.jadwalProp.acak_opsi : true,
  status: props.jadwalProp?.status || 'aktif',
  mode_ujian: props.jadwalProp?.mode_ujian || 'formal',
});

const isSubmitting = ref(false);

// Generate initial token if creating new
if (!form.value.token && !isEditing.value) {
  generateToken();
}

function generateToken() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let res = '';
  for (let i = 0; i < 6; i++) {
    res += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  form.value.token = res;
}

// Selected Bank Soal metadata
const selectedBankSoal = computed(() => {
  return props.bankSoalList.find((b) => String(b.id) === String(form.value.bank_soal_id));
});

const selectedBankSoalMapel = computed(() => {
  return selectedBankSoal.value?.mapelNama || selectedBankSoal.value?.mataPelajaran?.nama || '';
});

// Watch when bank soal changes: automatically sync judul_ujian & kelas_id if defined
watch(() => form.value.bank_soal_id, (newVal) => {
  if (newVal && selectedBankSoal.value) {
    form.value.judul_ujian = selectedBankSoal.value.judul || selectedBankSoal.value.nama || '';
    if (selectedBankSoal.value.kelasId) {
      form.value.kelas_id = selectedBankSoal.value.kelasId;
    }
    if (selectedBankSoal.value.jurusanId) {
      form.value.jurusan_id = selectedBankSoal.value.jurusanId;
    }
  }
});

// Filter target classes directly by mapel of selected Bank Soal
const targetKelasList = computed(() => {
  const list = props.masterKelasList || [];
  const mapelTarget = selectedBankSoalMapel.value?.toLowerCase().trim();
  if (mapelTarget) {
    const matched = list.filter((k) => {
      if (!k.mapels || k.mapels.length === 0) return false;
      return k.mapels.some((m) => {
        const mLower = m.toLowerCase();
        return mLower.includes(mapelTarget) || mapelTarget.includes(mLower);
      });
    });
    if (matched.length > 0) {
      return matched;
    }
  }
  return list;
});

function onKelasChange() {
  if (form.value.kelas_id) {
    const k = props.masterKelasList.find((x) => String(x.id) === String(form.value.kelas_id));
    if (k) {
      form.value.jurusan_id = k.jurusanId || '';
    }
  } else {
    form.value.jurusan_id = '';
  }
}

// Waktu Mulai & Selesai Helper Functions
function setNowAsStartTime() {
  const now = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  form.value.waktu_mulai = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
  calcWaktuSelesai();
}

function calcWaktuSelesai() {
  if (!form.value.waktu_mulai) return;
  const start = new Date(form.value.waktu_mulai);
  if (isNaN(start.getTime())) return;
  const durasi = Number(form.value.durasi_menit) || 60;
  const end = new Date(start.getTime() + durasi * 60 * 1000);
  const pad = (n) => String(n).padStart(2, '0');
  form.value.waktu_selesai = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}T${pad(end.getHours())}:${pad(end.getMinutes())}`;
}

function setDurasi(minutes) {
  form.value.durasi_menit = minutes;
  calcWaktuSelesai();
}

function goBack() {
  router.visit('/guru/dashboard?tab=cbt-jadwal');
}

function handleSubmit() {
  if (!form.value.bank_soal_id) {
    showToast('Silakan pilih Bank Soal yang akan diujikan!', 'error');
    return;
  }
  // Judul ujian otomatis mewarisi judul Bank Soal
  if (!form.value.judul_ujian.trim()) {
    form.value.judul_ujian = selectedBankSoal.value?.judul || selectedBankSoal.value?.nama || 'Ujian CBT';
  }

  isSubmitting.value = true;
  router.post(route('guru.cbt.jadwal.store'), form.value, {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      showSuccessAlert(
        isEditing.value ? 'Jadwal Diperbarui!' : 'Jadwal CBT Berhasil Dibuat!',
        'Pengaturan jadwal ujian CBT telah tersimpan di sistem.'
      );
    },
    onError: (err) => {
      isSubmitting.value = false;
      const firstErr = Object.values(err)[0] || 'Terjadi kesalahan saat menyimpan jadwal.';
      showToast(firstErr, 'error');
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}
</script>

<template>
  <Head :title="isEditing ? 'Edit Jadwal CBT — SMKS Muhammadiyah 2 Genteng' : 'Tambah Jadwal CBT — SMKS Muhammadiyah 2 Genteng'" />

  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans pb-16">
    <!-- TOP NAVBAR -->
    <header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-white/10">
      <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-4">
        <!-- Back & Title -->
        <div class="flex items-center gap-3 min-w-0">
          <button
            @click="goBack"
            type="button"
            class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white border border-white/10 text-xs font-semibold transition-all flex items-center gap-1.5 min-h-[38px] shrink-0"
            title="Kembali ke Jadwal CBT"
          >
            <span>←</span>
            <span class="hidden sm:inline">Kembali</span>
          </button>

          <div class="min-w-0">
            <div class="flex items-center gap-2 text-[11px] text-gray-400">
              <span class="hover:underline cursor-pointer" @click="goBack">CBT</span>
              <span>/</span>
              <span class="hover:underline cursor-pointer" @click="goBack">Jadwal Ujian</span>
              <span>/</span>
              <span class="text-teal-400 font-medium">{{ isEditing ? 'Edit Jadwal' : 'Tambah Baru' }}</span>
            </div>
            <h1 class="text-sm sm:text-base font-bold text-white truncate">
              {{ isEditing ? `Edit: ${form.judul_ujian || 'Jadwal CBT'}` : 'Buat Sesi Jadwal Ujian CBT' }}
            </h1>
          </div>
        </div>

        <!-- Header Action CTA -->
        <div class="flex items-center gap-2">
          <button
            @click="goBack"
            type="button"
            class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 text-xs font-medium transition-all min-h-[38px]"
          >
            Batal
          </button>
          <button
            @click="handleSubmit"
            :disabled="isSubmitting"
            type="button"
            class="px-4 sm:px-5 py-2 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold text-xs sm:text-sm shadow-md shadow-teal-500/20 active:scale-95 transition-all flex items-center gap-1.5 min-h-[38px] disabled:opacity-50"
          >
            <span v-if="isSubmitting">Menyimpan...</span>
            <span v-else>{{ isEditing ? 'Simpan Perubahan' : 'Terbitkan Jadwal' }}</span>
          </button>
        </div>
      </div>
    </header>

    <!-- MAIN FORM CONTAINER -->
    <main class="w-full max-w-7xl mx-auto px-4 sm:px-6 pt-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- KOLOM KIRI (8 COLS): FORM UTAMA -->
        <div class="lg:col-span-8 space-y-5">

          <!-- SECTION 1: PAKET BANK SOAL UJIAN -->
          <div class="p-5 sm:p-6 rounded-2xl bg-slate-900 border border-white/10 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
              <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                <h2 class="text-sm font-bold text-white uppercase tracking-wider">1. Paket Bank Soal Ujian</h2>
              </div>
              <span class="text-xs text-gray-400">{{ bankSoalList.length }} Paket Tersedia</span>
            </div>

            <!-- Pilih Bank Soal -->
            <div>
              <label class="block text-xs font-semibold text-gray-300 mb-1.5">
                Pilih Bank Soal <span class="text-rose-400">*</span>
              </label>
              <select
                v-model="form.bank_soal_id"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white text-xs sm:text-sm font-medium outline-none transition-all min-h-[42px]"
              >
                <option value="" disabled>-- Pilih Paket Bank Soal --</option>
                <option v-for="b in bankSoalList" :key="b.id" :value="b.id">
                  {{ b.kode ? `[${b.kode}] ` : '' }}{{ b.judul || b.nama }} — {{ b.mapelNama || b.mataPelajaran?.nama || 'Umum' }} ({{ b.jumlahSoal || (b.soals ? b.soals.length : 0) }} Butir Soal)
                </option>
              </select>
            </div>

            <!-- Info Detail Paket Terpilih -->
            <div v-if="selectedBankSoal" class="p-4 rounded-xl bg-slate-800/80 border border-teal-500/20 space-y-2">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-xs text-teal-400 font-semibold uppercase tracking-wider">Nama Ujian:</span>
                  <strong class="text-sm font-bold text-white">{{ selectedBankSoal.judul || selectedBankSoal.nama }}</strong>
                </div>
                <span class="px-2 py-0.5 rounded-md bg-teal-500/20 text-teal-300 font-mono text-[11px] font-bold">
                  {{ selectedBankSoal.kode || 'PAKET CBT' }}
                </span>
              </div>
              <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-gray-300 pt-2 border-t border-white/5">
                <div class="flex items-center gap-1.5">
                  <span class="text-gray-400">Mata Pelajaran:</span>
                  <strong class="text-teal-300">{{ selectedBankSoalMapel || 'Umum' }}</strong>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-gray-400">Total Soal:</span>
                  <strong class="text-white">{{ selectedBankSoal.jumlahSoal || (selectedBankSoal.soals ? selectedBankSoal.soals.length : 0) }} Butir</strong>
                </div>
                <div v-if="selectedBankSoal.deskripsi" class="w-full text-[11px] text-gray-400 pt-0.5">
                  {{ selectedBankSoal.deskripsi }}
                </div>
              </div>
            </div>
          </div>

          <!-- SECTION 2: KELAS SASARAN & WAKTU PELAKSANAAN -->
          <div class="p-5 sm:p-6 rounded-2xl bg-slate-900 border border-white/10 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
              <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                <h2 class="text-sm font-bold text-white uppercase tracking-wider">2. Sasaran Siswa & Waktu</h2>
              </div>
              <span v-if="selectedBankSoalMapel" class="text-xs text-teal-400 font-medium">
                Mapel: {{ selectedBankSoalMapel }}
              </span>
            </div>

            <!-- Kelas Sasaran (Disesuaikan langsung dengan mapel Bank Soal yang diajar guru) -->
            <div>
              <label class="block text-xs font-semibold text-gray-300 mb-1.5">
                Kelas Sasaran Ujian
              </label>
              <select
                v-model="form.kelas_id"
                @change="onKelasChange"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white text-xs sm:text-sm font-medium outline-none min-h-[42px]"
              >
                <option value="">-- Semua Kelas --</option>
                <option v-for="k in targetKelasList" :key="k.id" :value="k.id">
                  {{ k.namaKelas }}
                </option>
              </select>
              <p class="text-[11px] text-gray-400 mt-1.5">
                Pilih rombel kelas yang dituju (misal: <strong>X TKR 1</strong>) atau pilih <strong>Semua Kelas</strong>.
              </p>
            </div>

            <!-- Waktu Mulai & Waktu Selesai -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-semibold text-gray-300">
                    Waktu Mulai Akses
                  </label>
                  <button
                    type="button"
                    @click="setNowAsStartTime"
                    class="text-[10px] text-teal-400 hover:text-teal-300 underline font-medium"
                  >
                    ⚡ Mulai Sekarang
                  </button>
                </div>
                <input
                  v-model="form.waktu_mulai"
                  @change="calcWaktuSelesai"
                  type="datetime-local"
                  style="color-scheme: dark;"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white text-xs outline-none min-h-[42px]"
                />
              </div>

              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-semibold text-gray-300">
                    Waktu Selesai Akses
                  </label>
                  <button
                    type="button"
                    @click="calcWaktuSelesai"
                    v-if="form.waktu_mulai"
                    class="text-[10px] text-teal-400 hover:text-teal-300 underline font-medium"
                    title="Hitung otomatis berdasarkan waktu mulai + durasi menit"
                  >
                    ⚡ Hitung Otomatis
                  </button>
                </div>
                <input
                  v-model="form.waktu_selesai"
                  type="datetime-local"
                  style="color-scheme: dark;"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white text-xs outline-none min-h-[42px]"
                />
              </div>
            </div>

            <!-- Durasi Pengerjaan & Presets -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-semibold text-gray-300">
                  Durasi Pengerjaan (Menit)
                </label>
                <span class="text-xs font-bold text-teal-400 font-mono">{{ form.durasi_menit }} Menit</span>
              </div>
              <div class="flex items-center gap-2.5 flex-wrap">
                <input
                  v-model.number="form.durasi_menit"
                  @change="calcWaktuSelesai"
                  type="number"
                  min="5"
                  max="300"
                  class="w-24 px-3 py-2 rounded-xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white font-mono font-bold text-xs outline-none min-h-[40px]"
                />
                <div class="flex items-center gap-1.5 flex-wrap">
                  <button
                    v-for="d in [30, 45, 60, 90, 120]"
                    :key="d"
                    type="button"
                    @click="setDurasi(d)"
                    class="px-2.5 py-2 rounded-lg text-xs font-medium transition-all min-h-[38px]"
                    :class="form.durasi_menit === d ? 'bg-teal-500 text-slate-950 font-bold' : 'bg-white/5 hover:bg-white/10 text-gray-300'"
                  >
                    {{ d }}m
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- SECTION 3: PENGATURAN & KEAMANAN CBT -->
          <div class="p-5 sm:p-6 rounded-2xl bg-slate-900 border border-white/10 shadow-sm space-y-4">
            <div class="flex items-center gap-2.5 pb-3 border-b border-white/10">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <h2 class="text-sm font-bold text-white uppercase tracking-wider">3. Pengaturan & Keamanan CBT</h2>
            </div>

            <!-- Token & Status -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Token Ujian -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-semibold text-gray-300">Token Masuk Siswa</label>
                  <button
                    type="button"
                    @click="generateToken"
                    class="text-[11px] font-medium text-teal-400 hover:text-teal-300 underline"
                  >
                    Acak Baru
                  </button>
                </div>
                <input
                  v-model="form.token"
                  type="text"
                  placeholder="SMK01X"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white text-sm font-mono font-bold uppercase tracking-wider outline-none min-h-[42px]"
                />
              </div>

              <!-- Status Akses -->
              <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1.5">
                  Status Ketersediaan
                </label>
                <button
                  type="button"
                  @click="form.status = form.status === 'aktif' ? 'nonaktif' : 'aktif'"
                  class="w-full px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 min-h-[42px]"
                  :class="form.status === 'aktif' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800 text-gray-400 border border-white/10'"
                >
                  <span class="w-2 h-2 rounded-full" :class="form.status === 'aktif' ? 'bg-emerald-400' : 'bg-gray-400'"></span>
                  <span>{{ form.status === 'aktif' ? 'Aktif (Siswa Dapat Mengakses)' : 'Draf / Non-Aktif' }}</span>
                </button>
              </div>
            </div>

            <!-- Acak Soal & Opsi -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
              <!-- Acak Soal -->
              <div class="p-3.5 rounded-xl bg-slate-800/80 border border-white/10 flex items-center justify-between gap-3">
                <div>
                  <span class="text-xs font-semibold text-white block">Acak Urutan Soal</span>
                  <span class="text-[11px] text-gray-400 block mt-0.5">Nomor butir soal diacak per siswa.</span>
                </div>
                <button
                  type="button"
                  @click="form.acak_soal = !form.acak_soal"
                  class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all shrink-0 min-h-[36px]"
                  :class="form.acak_soal ? 'bg-teal-500 text-slate-950' : 'bg-white/10 text-gray-400 hover:text-white'"
                >
                  {{ form.acak_soal ? 'Aktif' : 'Mati' }}
                </button>
              </div>

              <!-- Acak Opsi -->
              <div class="p-3.5 rounded-xl bg-slate-800/80 border border-white/10 flex items-center justify-between gap-3">
                <div>
                  <span class="text-xs font-semibold text-white block">Acak Opsi Jawaban</span>
                  <span class="text-[11px] text-gray-400 block mt-0.5">Pilihan opsi A-E diacak per butir.</span>
                </div>
                <button
                  type="button"
                  @click="form.acak_opsi = !form.acak_opsi"
                  class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all shrink-0 min-h-[36px]"
                  :class="form.acak_opsi ? 'bg-teal-500 text-slate-950' : 'bg-white/10 text-gray-400 hover:text-white'"
                >
                  {{ form.acak_opsi ? 'Aktif' : 'Mati' }}
                </button>
              </div>
            </div>

            <!-- Mode Pelaksanaan Ujian -->
            <div class="pt-1">
              <label class="block text-xs font-semibold text-gray-300 mb-2">
                Gaya / Mode Pelaksanaan Ujian
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Mode Formal -->
                <button
                  type="button"
                  @click="form.mode_ujian = 'formal'"
                  class="p-3.5 rounded-xl border text-left transition-all cursor-pointer min-h-[44px]"
                  :class="form.mode_ujian === 'formal' ? 'bg-teal-500/15 border-teal-400 text-white' : 'bg-slate-800/60 border-white/10 text-gray-400 hover:text-white'"
                >
                  <strong class="text-xs font-bold block" :class="form.mode_ujian === 'formal' ? 'text-teal-300' : 'text-gray-200'">
                    CBT Formal Standar
                  </strong>
                  <span class="text-[11px] text-gray-400 block mt-0.5">
                    Format ujian resmi tanpa spoiler atau feedback instan saat pengerjaan.
                  </span>
                </button>

                <!-- Mode Game -->
                <button
                  type="button"
                  @click="form.mode_ujian = 'game'"
                  class="p-3.5 rounded-xl border text-left transition-all cursor-pointer min-h-[44px]"
                  :class="form.mode_ujian === 'game' ? 'bg-indigo-500/15 border-indigo-400 text-white' : 'bg-slate-800/60 border-white/10 text-gray-400 hover:text-white'"
                >
                  <strong class="text-xs font-bold block" :class="form.mode_ujian === 'game' ? 'text-indigo-300' : 'text-gray-200'">
                    Mode Interaktif
                  </strong>
                  <span class="text-[11px] text-gray-400 block mt-0.5">
                    Dilengkapi animasi dan audio feedback responsif untuk kuis asesmen formatif.
                  </span>
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- KOLOM KANAN (4 COLS): RINGKASAN STICKY & AKSI -->
        <div class="lg:col-span-4 lg:sticky lg:top-20 space-y-4">
          <div class="p-5 rounded-2xl bg-slate-900 border border-white/10 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
              <h3 class="text-xs font-bold text-white uppercase tracking-wider">Ringkasan Sesi Ujian</h3>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                :class="form.status === 'aktif' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-gray-500/20 text-gray-400 border border-gray-500/30'"
              >
                {{ form.status === 'aktif' ? 'Aktif' : 'Draf' }}
              </span>
            </div>

            <!-- List Ringkasan -->
            <div class="space-y-3 text-xs">
              <div>
                <span class="text-gray-400 text-[11px] block">Paket Bank Soal:</span>
                <strong class="text-white block mt-0.5">
                  {{ selectedBankSoal?.judul || selectedBankSoal?.nama || 'Belum dipilih' }}
                </strong>
                <span v-if="selectedBankSoal" class="text-teal-400 text-[11px] font-medium block mt-0.5">
                  {{ selectedBankSoalMapel || 'Umum' }} • {{ selectedBankSoal?.jumlahSoal || 0 }} Soal
                </span>
              </div>

              <div class="pt-2 border-t border-white/10">
                <span class="text-gray-400 text-[11px] block">Kelas Sasaran:</span>
                <strong class="text-teal-300 block mt-0.5">
                  {{ form.kelas_id ? (masterKelasList.find(k => String(k.id) === String(form.kelas_id))?.namaKelas || 'Kelas Terpilih') : 'Semua Kelas' }}
                </strong>
              </div>

              <div class="grid grid-cols-2 gap-2 pt-2 border-t border-white/10 text-[11px]">
                <div>
                  <span class="text-gray-400 block">Durasi:</span>
                  <strong class="text-white block mt-0.5">{{ form.durasi_menit }} Menit</strong>
                </div>
                <div>
                  <span class="text-gray-400 block">Token:</span>
                  <strong class="text-teal-300 font-mono tracking-wider">{{ form.token || '-' }}</strong>
                </div>
              </div>

              <div class="pt-2 border-t border-white/10 text-[11px]">
                <span class="text-gray-400 block">Mode Pelaksanaan:</span>
                <strong :class="form.mode_ujian === 'game' ? 'text-indigo-300' : 'text-gray-200'" class="block mt-0.5">
                  {{ form.mode_ujian === 'game' ? '🎮 Interaktif' : '📝 Formal Standar' }}
                </strong>
              </div>
            </div>

            <!-- Action Buttons in Summary Card -->
            <div class="pt-3 border-t border-white/10 space-y-2">
              <button
                @click="handleSubmit"
                :disabled="isSubmitting"
                type="button"
                class="w-full py-2.5 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold text-xs sm:text-sm shadow-md shadow-teal-500/20 active:scale-95 transition-all flex items-center justify-center gap-1.5 min-h-[42px] disabled:opacity-50"
              >
                <span v-if="isSubmitting">Sedang Menyimpan...</span>
                <span v-else>{{ isEditing ? 'Simpan Perubahan' : 'Terbitkan Jadwal Ujian' }}</span>
              </button>

              <button
                @click="goBack"
                type="button"
                class="w-full py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 text-xs font-medium transition-all min-h-[40px]"
              >
                Batal
              </button>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>
</template>
