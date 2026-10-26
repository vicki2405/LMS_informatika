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

// Watch when bank soal changes to suggest judul ujian if empty
watch(() => form.value.bank_soal_id, (newVal) => {
  if (newVal && selectedBankSoal.value) {
    if (!form.value.judul_ujian.trim()) {
      form.value.judul_ujian = `Ujian CBT — ${selectedBankSoal.value.judul || selectedBankSoal.value.nama || 'Mata Pelajaran'}`;
    }
    // Auto-fill jurusan/kelas from bank soal if set and form is currently empty
    if (!form.value.jurusan_id && selectedBankSoal.value.jurusanId) {
      form.value.jurusan_id = selectedBankSoal.value.jurusanId;
    }
    if (!form.value.kelas_id && selectedBankSoal.value.kelasId) {
      form.value.kelas_id = selectedBankSoal.value.kelasId;
    }
  }
});

// Filter kelas list based on selected jurusan
const filteredKelasList = computed(() => {
  if (!form.value.jurusan_id) {
    return props.masterKelasList;
  }
  return props.masterKelasList.filter(
    (k) => !k.jurusanId || String(k.jurusanId) === String(form.value.jurusan_id)
  );
});

function onJurusanChange() {
  if (form.value.kelas_id && form.value.jurusan_id) {
    const k = props.masterKelasList.find((x) => String(x.id) === String(form.value.kelas_id));
    if (k && k.jurusanId && String(k.jurusanId) !== String(form.value.jurusan_id)) {
      form.value.kelas_id = '';
    }
  }
}

function onKelasChange() {
  if (form.value.kelas_id) {
    const k = props.masterKelasList.find((x) => String(x.id) === String(form.value.kelas_id));
    if (k && k.jurusanId) {
      form.value.jurusan_id = k.jurusanId;
    }
  }
}

function applyPresetJudul(prefix) {
  const mapel = selectedBankSoal.value?.mapelNama || selectedBankSoal.value?.mataPelajaran?.nama || 'Mata Pelajaran';
  form.value.judul_ujian = `${prefix} — ${mapel}`;
}

function setDurasi(minutes) {
  form.value.durasi_menit = minutes;
}

function goBack() {
  router.visit('/guru/dashboard?tab=cbt-jadwal');
}

function handleSubmit() {
  if (!form.value.judul_ujian.trim()) {
    showToast('Judul Sesi Ujian wajib diisi!', 'error');
    return;
  }
  if (!form.value.bank_soal_id) {
    showToast('Silakan pilih Bank Soal yang akan diujikan!', 'error');
    return;
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

  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans pb-32">
    <!-- TOP STICKY HEADER BAR -->
    <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-white/10 shadow-2xl">
      <div class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
        <!-- Brand & Back Button -->
        <div class="flex items-center gap-3 min-w-0">
          <button
            @click="goBack"
            type="button"
            class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white border border-white/10 text-xs font-bold transition-all flex items-center gap-1.5 min-h-[40px] shrink-0"
            title="Kembali ke Tab Jadwal CBT"
          >
            <span>←</span>
            <span class="hidden sm:inline">Kembali</span>
          </button>

          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
                :class="isEditing ? 'bg-amber-500/15 text-amber-300 border-amber-500/30' : 'bg-teal-500/15 text-teal-300 border-teal-500/30'"
              >
                {{ isEditing ? 'Mode Edit Jadwal' : 'Jadwal Baru' }}
              </span>
              <span class="text-xs text-gray-400 font-mono hidden md:inline">CBT System SMKS Muhammadiyah 2 Genteng</span>
            </div>
            <h1 class="text-sm sm:text-base font-black text-white truncate">
              {{ isEditing ? `Edit: ${form.judul_ujian || 'Jadwal CBT'}` : 'Buat Sesi Ujian CBT Baru' }}
            </h1>
          </div>
        </div>

        <!-- Action Button Top -->
        <div class="flex items-center gap-2">
          <button
            @click="goBack"
            type="button"
            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-gray-300 text-xs font-bold transition-all min-h-[40px]"
          >
            Batal
          </button>
          <button
            @click="handleSubmit"
            :disabled="isSubmitting"
            type="button"
            class="px-5 py-2 rounded-xl bg-gradient-to-r from-teal-500 via-cyan-500 to-indigo-600 hover:from-teal-400 hover:to-cyan-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-teal-500/25 active:scale-95 transition-all flex items-center gap-2 min-h-[40px] disabled:opacity-50"
          >
            <span v-if="isSubmitting">⏳ Menyimpan...</span>
            <span v-else class="flex items-center gap-1.5">
              <span>💾</span>
              <span>{{ isEditing ? 'Simpan Perubahan' : 'Terbitkan Jadwal' }}</span>
            </span>
          </button>
        </div>
      </div>
    </header>

    <!-- MAIN FORM CONTAINER -->
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 pt-6">
      
      <!-- SUBTITLE BANNER -->
      <div class="mb-6 p-4 rounded-3xl bg-gradient-to-r from-slate-900 via-teal-950/20 to-slate-900 border border-white/10 shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-base sm:text-lg font-black text-white flex items-center gap-2">
            <span>📅</span>
            <span>Konfigurasi Pelaksanaan & Pengawasan Ujian CBT</span>
          </h2>
          <p class="text-xs text-gray-400 mt-1">
            Atur butir soal yang diujikan, sasaran siswa/kelas, durasi waktu, token akses, serta mode ujian formal atau game interaktif.
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="px-3.5 py-1.5 rounded-2xl bg-white/5 border border-white/10 text-xs font-mono text-cyan-300">
            Total Bank Soal: <strong>{{ bankSoalList.length }}</strong>
          </div>
        </div>
      </div>

      <!-- TWO COLUMNS GRID LAYOUT -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- KOLOM KIRI (7 SPAN): PENGATURAN UTAMA & SOAL -->
        <div class="lg:col-span-7 space-y-6">

          <!-- KARTU 1: PILIH BANK SOAL & JUDUL SESI -->
          <div class="p-5 sm:p-6 rounded-3xl bg-slate-900/90 border border-white/10 shadow-xl space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-white/10">
              <span class="w-8 h-8 rounded-xl bg-teal-500/20 text-teal-300 flex items-center justify-center text-sm font-bold border border-teal-500/30">
                1
              </span>
              <div>
                <h3 class="text-sm sm:text-base font-black text-white">Bank Soal & Identitas Sesi Ujian</h3>
                <p class="text-[11px] text-gray-400">Pilih paket bank soal aktif yang telah Anda siapkan di menu Bank Soal.</p>
              </div>
            </div>

            <!-- Pilih Bank Soal -->
            <div>
              <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                Pilih Bank Soal <span class="text-rose-400">*</span>
              </label>
              <select
                v-model="form.bank_soal_id"
                class="w-full px-4 py-3 rounded-2xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white text-xs sm:text-sm font-semibold outline-none transition-all min-h-[46px]"
              >
                <option value="" disabled>-- Pilih Bank Soal CBT --</option>
                <option v-for="b in bankSoalList" :key="b.id" :value="b.id">
                  {{ b.kode || 'SOAL' }} — {{ b.judul || b.nama }} ({{ b.mapelNama || b.mataPelajaran?.nama || 'Umum' }} • {{ b.jumlahSoal || (b.soals ? b.soals.length : 0) }} Soal)
                </option>
              </select>

              <!-- Detail info card bank soal terpilih -->
              <div v-if="selectedBankSoal" class="mt-3 p-3.5 rounded-2xl bg-teal-950/30 border border-teal-500/30 text-xs space-y-2">
                <div class="flex items-center justify-between">
                  <span class="font-black text-teal-300 flex items-center gap-1.5">
                    <span>📚</span>
                    <span>{{ selectedBankSoal.judul || selectedBankSoal.nama }}</span>
                  </span>
                  <span class="px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-300 font-mono text-[10px] font-bold">
                    {{ selectedBankSoal.kode }}
                  </span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1 border-t border-teal-500/20 text-[11px]">
                  <div>
                    <span class="text-gray-400 block text-[10px]">Mata Pelajaran:</span>
                    <strong class="text-white">{{ selectedBankSoal.mapelNama || selectedBankSoal.mataPelajaran?.nama || 'Umum' }}</strong>
                  </div>
                  <div>
                    <span class="text-gray-400 block text-[10px]">Tingkat:</span>
                    <strong class="text-white">Kelas {{ selectedBankSoal.tingkat || '-' }}</strong>
                  </div>
                  <div>
                    <span class="text-gray-400 block text-[10px]">Butir Soal:</span>
                    <strong class="text-emerald-300">{{ selectedBankSoal.jumlahSoal || (selectedBankSoal.soals ? selectedBankSoal.soals.length : 0) }} Butir</strong>
                  </div>
                  <div>
                    <span class="text-gray-400 block text-[10px]">Total Bobot:</span>
                    <strong class="text-cyan-300">{{ selectedBankSoal.totalBobot || 100 }} Poin</strong>
                  </div>
                </div>
              </div>
            </div>

            <!-- Input Judul Sesi Ujian -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-bold text-gray-300 uppercase tracking-wider">
                  Judul Sesi Ujian CBT <span class="text-rose-400">*</span>
                </label>
                <!-- Quick Preset Judul -->
                <div class="flex items-center gap-1">
                  <span class="text-[10px] text-gray-400 hidden sm:inline">Contoh Cepat:</span>
                  <button
                    type="button"
                    @click="applyPresetJudul('Sumatif Harian')"
                    class="px-2 py-0.5 rounded-lg bg-white/5 hover:bg-white/10 text-cyan-300 text-[10px] font-bold"
                  >
                    PH / Sumatif
                  </button>
                  <button
                    type="button"
                    @click="applyPresetJudul('STS Ganjil')"
                    class="px-2 py-0.5 rounded-lg bg-white/5 hover:bg-white/10 text-amber-300 text-[10px] font-bold"
                  >
                    STS
                  </button>
                  <button
                    type="button"
                    @click="applyPresetJudul('SAS Gasal')"
                    class="px-2 py-0.5 rounded-lg bg-white/5 hover:bg-white/10 text-emerald-300 text-[10px] font-bold"
                  >
                    SAS
                  </button>
                </div>
              </div>
              <input
                v-model="form.judul_ujian"
                type="text"
                placeholder="Contoh: Penilaian Akhir Semester Ganjil — Informatika X RPL"
                class="w-full px-4 py-3 rounded-2xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white text-xs sm:text-sm font-semibold outline-none transition-all min-h-[46px]"
              />
              <p class="text-[11px] text-gray-400 mt-1.5">
                Judul ini akan menjadi nama sesi ujian yang dilihat siswa pada daftar kartu ujian mereka.
              </p>
            </div>
          </div>

          <!-- KARTU 2: TARGET PESERTA UJIAN (JURUSAN & KELAS) -->
          <div class="p-5 sm:p-6 rounded-3xl bg-slate-900/90 border border-white/10 shadow-xl space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-white/10">
              <span class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-300 flex items-center justify-center text-sm font-bold border border-cyan-500/30">
                2
              </span>
              <div>
                <h3 class="text-sm sm:text-base font-black text-white">Target Sasaran Peserta Ujian</h3>
                <p class="text-[11px] text-gray-400">Batasi sesi ujian untuk jurusan tertentu atau kelas spesifik yang Anda ajar.</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Jurusan Target -->
              <div>
                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                  Konsentrasi Keahlian / Jurusan
                </label>
                <select
                  v-model="form.jurusan_id"
                  @change="onJurusanChange"
                  class="w-full px-4 py-3 rounded-2xl bg-slate-800 border border-white/15 focus:border-cyan-400 text-white text-xs sm:text-sm font-semibold outline-none min-h-[46px]"
                >
                  <option value="">Semua Jurusan (Terbuka Umum)</option>
                  <option v-for="j in jurusanList" :key="j.id" :value="j.id">
                    {{ j.nama }} ({{ j.kode }})
                  </option>
                </select>
                <span class="text-[10px] text-gray-400 mt-1 block">Biarkan kosong jika berlaku lintas jurusan.</span>
              </div>

              <!-- Kelas Target -->
              <div>
                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                  Kelas Spesifik Target
                </label>
                <select
                  v-model="form.kelas_id"
                  @change="onKelasChange"
                  class="w-full px-4 py-3 rounded-2xl bg-slate-800 border border-white/15 focus:border-cyan-400 text-white text-xs sm:text-sm font-semibold outline-none min-h-[46px]"
                >
                  <option value="">Semua Kelas di Jurusan Terpilih</option>
                  <option v-for="k in filteredKelasList" :key="k.id" :value="k.id">
                    {{ k.namaKelas }} (Tingkat {{ k.tingkat }})
                  </option>
                </select>
                <span class="text-[10px] text-gray-400 mt-1 block">Pilih satu rombel jika ujian hanya untuk kelas tersebut.</span>
              </div>
            </div>
          </div>

          <!-- KARTU 3: PENGACAKAN SOAL & PILIHAN GANDA -->
          <div class="p-5 sm:p-6 rounded-3xl bg-slate-900/90 border border-white/10 shadow-xl space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-white/10">
              <span class="w-8 h-8 rounded-xl bg-purple-500/20 text-purple-300 flex items-center justify-center text-sm font-bold border border-purple-500/30">
                3
              </span>
              <div>
                <h3 class="text-sm sm:text-base font-black text-white">Integritas & Pengacakan Butir Soal</h3>
                <p class="text-[11px] text-gray-400">Cegah kecurangan antar siswa yang duduk berdampingan.</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Acak Soal -->
              <div class="p-4 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-between gap-3">
                <div>
                  <span class="text-xs font-bold text-white flex items-center gap-1.5">
                    <span class="text-amber-400 text-sm">🔀</span>
                    <span>Acak Nomor Soal</span>
                  </span>
                  <p class="text-[11px] text-gray-400 mt-1">Urutan nomor butir soal diacak otomatis untuk setiap siswa.</p>
                </div>
                <button
                  type="button"
                  @click="form.acak_soal = !form.acak_soal"
                  class="px-4 py-2 rounded-xl text-xs font-black transition-all shrink-0 min-h-[40px]"
                  :class="form.acak_soal ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20' : 'bg-white/10 text-gray-400 hover:text-white'"
                >
                  {{ form.acak_soal ? '🟢 AKTIF' : '⚪ MATI' }}
                </button>
              </div>

              <!-- Acak Opsi Pilihan Ganda -->
              <div class="p-4 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-between gap-3">
                <div>
                  <span class="text-xs font-bold text-white flex items-center gap-1.5">
                    <span class="text-purple-400 text-sm">🔄</span>
                    <span>Acak Opsi Pilihan (A-E)</span>
                  </span>
                  <p class="text-[11px] text-gray-400 mt-1">Posisi opsi pilihan ganda A, B, C, D, E diacak per siswa.</p>
                </div>
                <button
                  type="button"
                  @click="form.acak_opsi = !form.acak_opsi"
                  class="px-4 py-2 rounded-xl text-xs font-black transition-all shrink-0 min-h-[40px]"
                  :class="form.acak_opsi ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/20' : 'bg-white/10 text-gray-400 hover:text-white'"
                >
                  {{ form.acak_opsi ? '🟢 AKTIF' : '⚪ MATI' }}
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- KOLOM KANAN (5 SPAN): WAKTU, TOKEN, MODE & LIVE PREVIEW -->
        <div class="lg:col-span-5 space-y-6">

          <!-- KARTU 4: WAKTU PELAKSANAAN & DURASI -->
          <div class="p-5 sm:p-6 rounded-3xl bg-slate-900/90 border border-white/10 shadow-xl space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-white/10">
              <span class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-300 flex items-center justify-center text-sm font-bold border border-amber-500/30">
                4
              </span>
              <div>
                <h3 class="text-sm sm:text-base font-black text-white">Jadwal & Batas Waktu</h3>
                <p class="text-[11px] text-gray-400">Atur jendela pengerjaan ujian dan batas durasi waktu.</p>
              </div>
            </div>

            <!-- Waktu Mulai & Waktu Selesai -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                  Waktu Mulai Akses
                </label>
                <input
                  v-model="form.waktu_mulai"
                  type="datetime-local"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-amber-400 text-white text-xs outline-none min-h-[42px]"
                />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                  Waktu Selesai Akses
                </label>
                <input
                  v-model="form.waktu_selesai"
                  type="datetime-local"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-amber-400 text-white text-xs outline-none min-h-[42px]"
                />
              </div>
            </div>

            <!-- Durasi Pengerjaan & Presets -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-bold text-gray-300 uppercase tracking-wider">
                  Durasi Pengerjaan Siswa (Menit)
                </label>
                <span class="text-xs font-black text-amber-400 font-mono">{{ form.durasi_menit }} Menit</span>
              </div>
              <div class="flex items-center gap-3">
                <input
                  v-model.number="form.durasi_menit"
                  type="number"
                  min="5"
                  max="300"
                  class="w-28 px-4 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-amber-400 text-white font-mono font-bold text-sm outline-none min-h-[42px]"
                />
                <div class="flex items-center gap-1.5 flex-wrap">
                  <button
                    v-for="d in [30, 45, 60, 90, 120]"
                    :key="d"
                    type="button"
                    @click="setDurasi(d)"
                    class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all"
                    :class="form.durasi_menit === d ? 'bg-amber-500 text-slate-950 font-black' : 'bg-white/5 hover:bg-white/10 text-gray-300'"
                  >
                    {{ d }}m
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- KARTU 5: TOKEN AKSES & STATUS -->
          <div class="p-5 sm:p-6 rounded-3xl bg-slate-900/90 border border-white/10 shadow-xl space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-white/10">
              <span class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center text-sm font-bold border border-emerald-500/30">
                5
              </span>
              <div>
                <h3 class="text-sm sm:text-base font-black text-white">Token Akses & Status Ujian</h3>
                <p class="text-[11px] text-gray-400">Kode otentikasi masuk ujian dan visibilitas sesi.</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Token Ujian -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-bold text-gray-300 uppercase tracking-wider">Token Masuk</label>
                  <button
                    type="button"
                    @click="generateToken"
                    class="text-[11px] font-bold text-teal-400 hover:text-teal-300 underline"
                  >
                    🎲 Acak Baru
                  </button>
                </div>
                <input
                  v-model="form.token"
                  type="text"
                  placeholder="SMK01X"
                  class="w-full px-4 py-2.5 rounded-xl bg-slate-800 border border-white/15 focus:border-teal-400 text-white text-base font-mono font-black uppercase tracking-widest outline-none min-h-[42px]"
                />
              </div>

              <!-- Status Akses -->
              <div>
                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">
                  Status Ketersediaan
                </label>
                <button
                  type="button"
                  @click="form.status = form.status === 'aktif' ? 'nonaktif' : 'aktif'"
                  class="w-full px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-2 min-h-[42px]"
                  :class="form.status === 'aktif' ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20' : 'bg-slate-800 text-gray-400 border border-white/10'"
                >
                  <span>{{ form.status === 'aktif' ? '🟢 SESI DIAKTIFKAN' : '⚪ DITUTUP / NON-AKTIF' }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- KARTU 6: GAYA PELAKSANAAN UJIAN (FORMAL VS GAME) -->
          <div class="p-5 sm:p-6 rounded-3xl bg-slate-900/90 border border-white/10 shadow-xl space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-white/10">
              <span class="w-8 h-8 rounded-xl bg-pink-500/20 text-pink-300 flex items-center justify-center text-sm font-bold border border-pink-500/30">
                6
              </span>
              <div>
                <h3 class="text-sm sm:text-base font-black text-white">Model Gaya Pelaksanaan Ujian</h3>
                <p class="text-[11px] text-gray-400">Pilih suasana ujian sesuai tujuan penilaian Anda.</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Mode Formal -->
              <button
                type="button"
                @click="form.mode_ujian = 'formal'"
                class="p-4 rounded-2xl border text-left transition-all cursor-pointer space-y-1.5"
                :class="form.mode_ujian === 'formal' ? 'bg-cyan-500/20 border-cyan-400 text-white ring-2 ring-cyan-400/30' : 'bg-slate-800/60 border-white/10 text-gray-400 hover:text-white'"
              >
                <div class="flex items-center gap-2">
                  <span class="text-xl">🏛️</span>
                  <strong class="text-xs font-black" :class="form.mode_ujian === 'formal' ? 'text-cyan-300' : 'text-gray-200'">
                    CBT Formal Standar
                  </strong>
                </div>
                <p class="text-[10.5px] text-gray-300 leading-tight">
                  Format ujian standar resmi, fokus dan hening tanpa spoiler jawaban selama tes.
                </p>
              </button>

              <!-- Mode Game -->
              <button
                type="button"
                @click="form.mode_ujian = 'game'"
                class="p-4 rounded-2xl border text-left transition-all cursor-pointer space-y-1.5"
                :class="form.mode_ujian === 'game' ? 'bg-gradient-to-r from-amber-500/20 to-pink-500/20 border-amber-400 text-white ring-2 ring-amber-400/40' : 'bg-slate-800/60 border-white/10 text-gray-400 hover:text-white'"
              >
                <div class="flex items-center gap-2">
                  <span class="text-xl">🎮</span>
                  <strong class="text-xs font-black" :class="form.mode_ujian === 'game' ? 'text-amber-300' : 'text-gray-200'">
                    Mode Game / Kuis Seru
                  </strong>
                </div>
                <p class="text-[10.5px] text-gray-300 leading-tight">
                  Dilengkapi efek suara, animasi Boom saat salah, serta piala pahlawan saat benar!
                </p>
              </button>
            </div>
          </div>

          <!-- KARTU 7: LIVE PREVIEW TIKET UJIAN SISWA -->
          <div class="p-5 sm:p-6 rounded-3xl bg-gradient-to-br from-slate-900 to-indigo-950/40 border border-indigo-500/25 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-black uppercase tracking-wider text-indigo-300 flex items-center gap-1.5">
                <span>👁️</span> Pratinjau Tiket Ujian Siswa
              </span>
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-200 font-mono">
                Live View
              </span>
            </div>

            <!-- Tiket Card Preview -->
            <div class="p-4 rounded-2xl bg-slate-950/80 border border-white/10 space-y-3">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <span class="text-[10px] font-mono font-bold text-teal-400 uppercase tracking-wider">
                    {{ selectedBankSoal?.mapelNama || selectedBankSoal?.mataPelajaran?.nama || 'Mata Pelajaran' }}
                  </span>
                  <h4 class="text-sm font-black text-white mt-0.5">
                    {{ form.judul_ujian || 'Judul Sesi Ujian CBT' }}
                  </h4>
                </div>
                <span
                  class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase shrink-0"
                  :class="form.status === 'aktif' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-gray-500/20 text-gray-400 border border-gray-500/30'"
                >
                  {{ form.status === 'aktif' ? 'Tersedia' : 'Ditutup' }}
                </span>
              </div>

              <div class="grid grid-cols-3 gap-2 py-2 px-3 rounded-xl bg-white/5 border border-white/5 text-[11px] font-mono">
                <div>
                  <span class="text-gray-400 block text-[9px]">Durasi:</span>
                  <strong class="text-white">{{ form.durasi_menit }} Menit</strong>
                </div>
                <div>
                  <span class="text-gray-400 block text-[9px]">Mode:</span>
                  <strong :class="form.mode_ujian === 'game' ? 'text-amber-300' : 'text-cyan-300'">
                    {{ form.mode_ujian === 'game' ? '🎮 Game' : '🏛️ Formal' }}
                  </strong>
                </div>
                <div>
                  <span class="text-gray-400 block text-[9px]">Token:</span>
                  <strong class="text-emerald-300 tracking-wider">{{ form.token || '-' }}</strong>
                </div>
              </div>

              <div class="flex items-center justify-between text-[10px] text-gray-400 font-sans">
                <span>Sasaran: <strong class="text-gray-200">{{ form.kelas_id ? (masterKelasList.find(k => String(k.id) === String(form.kelas_id))?.namaKelas || 'Kelas Terpilih') : 'Seluruh Rombel' }}</strong></span>
                <span>Soal: <strong class="text-gray-200">{{ selectedBankSoal?.jumlahSoal || 0 }} Butir</strong></span>
              </div>
            </div>
          </div>

        </div>

      </div>

      <!-- BOTTOM STICKY ACTION BAR -->
      <div class="fixed bottom-0 left-0 right-0 z-40 p-4 bg-slate-950/95 backdrop-blur-xl border-t border-white/10 shadow-2xl">
        <div class="w-full max-w-[1600px] mx-auto flex items-center justify-between gap-4">
          <div class="flex items-center gap-2 text-xs text-gray-400 font-mono hidden sm:flex">
            <span>Status:</span>
            <span class="text-emerald-300 font-bold font-sans">{{ form.status === 'aktif' ? '🟢 Siap Diterbitkan Aktif' : '⚪ Draf / Non-Aktif' }}</span>
          </div>

          <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
            <button
              @click="goBack"
              type="button"
              class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 text-xs font-bold transition-all min-h-[44px]"
            >
              Batal
            </button>
            <button
              @click="handleSubmit"
              :disabled="isSubmitting"
              type="button"
              class="px-7 py-2.5 rounded-xl bg-gradient-to-r from-teal-500 via-cyan-500 to-indigo-600 hover:from-teal-400 hover:to-cyan-400 text-slate-950 font-black text-xs sm:text-sm shadow-xl shadow-teal-500/25 active:scale-95 transition-all flex items-center gap-2 min-h-[44px] disabled:opacity-50"
            >
              <span v-if="isSubmitting">⏳ Sedang Menyimpan...</span>
              <span v-else class="flex items-center gap-2">
                <span>💾</span>
                <span>{{ isEditing ? 'Simpan Perubahan Jadwal' : 'Terbitkan Jadwal Ujian CBT' }}</span>
              </span>
            </button>
          </div>
        </div>
      </div>

    </main>
  </div>
</template>
