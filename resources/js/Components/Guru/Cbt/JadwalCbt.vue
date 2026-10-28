<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmDelete, showSuccessAlert, showToast } from '@/Utils/sweetalert';

const props = defineProps({
  jadwalList: { type: Array, default: () => [] },
  bankSoalList: { type: Array, default: () => [] },
  masterKelasList: { type: Array, default: () => [] },
  jurusanList: { type: Array, default: () => [] },
});

// Search & Filter
const searchQuery = ref('');
const filterStatus = ref('');
const filterJurusan = ref('');
const filterKelas = ref('');

// Master kelas list filtered by filterJurusan for the top filter bar
const availableKelasForFilter = computed(() => {
  if (!filterJurusan.value) return props.masterKelasList;
  return props.masterKelasList.filter((k) => !k.jurusanId || String(k.jurusanId) === String(filterJurusan.value));
});

// Filtered Schedules
const filteredJadwals = computed(() => {
  return props.jadwalList.filter((j) => {
    const matchSearch = !searchQuery.value ||
      j.judulUjian.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      j.bankSoalJudul.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (j.token && j.token.toLowerCase().includes(searchQuery.value.toLowerCase()));

    const matchStatus = !filterStatus.value || j.status === filterStatus.value;
    const matchJurusan = !filterJurusan.value || String(j.jurusanId) === String(filterJurusan.value);
    const matchKelas = !filterKelas.value || String(j.kelasId) === String(filterKelas.value);

    return matchSearch && matchStatus && matchJurusan && matchKelas;
  });
});

function openCreateModal() {
  router.visit(route('guru.cbt.jadwal.create'));
}

function openEditModal(j) {
  router.visit(route('guru.cbt.jadwal.edit', j.id));
}

function toggleStatus(j) {
  router.patch(route('guru.cbt.jadwal.toggle-status', j.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      showToast(
        j.status === 'aktif' ? 'Jadwal CBT telah dinonaktifkan' : 'Jadwal CBT sekarang aktif & dapat dikerjakan siswa',
        'success'
      );
    },
  });
}

function deleteJadwal(id) {
  confirmDelete({
    title: 'Hapus Jadwal CBT?',
    text: 'Jadwal akan dihapus dari daftar aktif. Riwayat nilai siswa yang sudah masuk tetap tersimpan aman di sistem.',
    confirmButtonText: 'Ya, Hapus Jadwal!',
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(route('guru.cbt.jadwal.delete', id), {
        preserveScroll: true,
        onSuccess: () => showSuccessAlert('Berhasil Dihapus', 'Jadwal CBT telah dihapus.'),
      });
    }
  });
}

function copyToken(token) {
  if (!token) return;
  navigator.clipboard.writeText(token);
  showToast(`Token ${token} berhasil disalin!`, 'success');
}

function goToMonitoring(j) {
  router.visit(route('guru.cbt.jadwal.monitoring', j.id));
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-teal-900/60 via-slate-900 to-cyan-950/70 border border-teal-500/20 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
      <div class="absolute -top-24 -right-24 w-72 h-72 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/10 border border-teal-500/30 text-teal-300 text-xs font-black tracking-wider uppercase mb-2">
            <span>⏱️ Pengaturan &amp; Pengawasan CBT</span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            Jadwal Pelaksanaan CBT
          </h1>
          <p class="text-xs sm:text-sm text-gray-400 mt-1 max-w-2xl">
            Atur sesi ujian, token akses, kelas sasaran, serta kontrol <strong>Monitoring Real-Time &amp; Anti-Cheat</strong> peserta secara langsung.
          </p>
        </div>
        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2.5 px-5 py-3 rounded-2xl bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-400 hover:to-cyan-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-teal-500/20 transition-all duration-200 active:scale-95 shrink-0 min-h-[44px]"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
          </svg>
          <span>Tambah Jadwal CBT</span>
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
          placeholder="Cari judul ujian, bank soal, atau token..."
          class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs sm:text-sm placeholder-gray-500 focus:outline-none focus:border-teal-400 transition-colors min-h-[40px]"
        />
      </div>

      <!-- Filter Status -->
      <select
        v-model="filterStatus"
        class="w-full sm:w-44 py-2.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs sm:text-sm focus:outline-none focus:border-teal-400 min-h-[40px]"
      >
        <option value="">Semua Status</option>
        <option value="aktif">🟢 Aktif (Terbuka)</option>
        <option value="nonaktif">⚪ Non-Aktif (Terkunci)</option>
      </select>

      <!-- Filter Jurusan -->
      <select
        v-model="filterJurusan"
        @change="filterKelas = ''"
        class="w-full sm:w-44 py-2.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs sm:text-sm focus:outline-none focus:border-teal-400 min-h-[40px]"
      >
        <option value="">Semua Jurusan</option>
        <option v-for="j in jurusanList" :key="j.id" :value="j.id">{{ j.kode }} - {{ j.nama }}</option>
      </select>

      <!-- Filter Kelas -->
      <select
        v-model="filterKelas"
        class="w-full sm:w-44 py-2.5 px-3 rounded-xl bg-slate-800 border border-white/10 text-gray-200 text-xs sm:text-sm focus:outline-none focus:border-teal-400 min-h-[40px]"
      >
        <option value="">Semua Kelas</option>
        <option v-for="k in availableKelasForFilter" :key="k.id" :value="k.id">{{ k.namaKelas }}</option>
      </select>
    </div>

    <!-- Empty State -->
    <div v-if="filteredJadwals.length === 0" class="text-center py-16 px-4 rounded-3xl bg-slate-900/60 border border-white/10">
      <div class="w-16 h-16 mx-auto rounded-2xl bg-teal-500/10 text-teal-400 flex items-center justify-center text-3xl mb-3">
        📅
      </div>
      <h3 class="text-base font-bold text-white">Belum Ada Jadwal CBT</h3>
      <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
        Belum ada jadwal pelaksanaan ujian yang dibuat. Klik "Tambah Jadwal CBT" untuk membuka sesi ujian bagi siswa.
      </p>
    </div>

    <!-- Desktop View: Table (hidden md:block) -->
    <div v-if="filteredJadwals.length > 0" class="hidden md:block overflow-hidden rounded-2xl bg-slate-900/80 border border-white/10 shadow-xl backdrop-blur-xl">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
            <th class="py-4 px-5">Sesi / Judul Ujian</th>
            <th class="py-4 px-4">Bank Soal &amp; Mapel</th>
            <th class="py-4 px-4">Jurusan &amp; Kelas</th>
            <th class="py-4 px-4 text-center">Token Akses</th>
            <th class="py-4 px-4 text-center">Status Ujian</th>
            <th class="py-4 px-4 text-center">Pengawasan (Live)</th>
            <th class="py-4 px-5 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5 text-xs">
          <tr v-for="j in filteredJadwals" :key="j.id" class="hover:bg-white/[0.03] transition-colors group">
            <td class="py-4 px-5">
              <p class="font-bold text-white text-sm group-hover:text-teal-300 transition-colors">
                {{ j.judulUjian }}
              </p>
              <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                <span>🕒 {{ j.waktuMulai || 'Fleksibel' }}</span>
                <span>s/d</span>
                <span>{{ j.waktuSelesai || 'Selesai' }}</span>
              </div>
              <div class="flex flex-wrap items-center gap-1.5 mt-2">
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-bold text-[10px] border',
                    j.modeUjian === 'game'
                      ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-sm shadow-amber-500/10'
                      : 'bg-cyan-500/10 text-cyan-300 border-cyan-500/20'
                  ]"
                >
                  {{ j.modeUjian === 'game' ? '🎮 Mode Game' : '🏛️ CBT Formal' }}
                </span>

                <span
                  v-if="j.acakSoal"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-300 font-bold text-[10px]"
                  title="Urutan soal diacak untuk setiap siswa"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  Soal Acak
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-400 font-medium text-[10px]"
                >
                  Soal Urut
                </span>

                <span
                  v-if="j.acakOpsi"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30 text-purple-300 font-bold text-[10px]"
                  title="Pilihan ganda A-E diacak per butir soal"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                  Opsi Acak
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-400 font-medium text-[10px]"
                >
                  Opsi Urut
                </span>
              </div>
            </td>
            <td class="py-4 px-4">
              <p class="font-semibold text-gray-200">{{ j.bankSoalJudul }}</p>
              <span class="inline-flex items-center gap-1 text-[11px] text-teal-400 mt-0.5">
                📖 {{ j.mapelNama }}
              </span>
            </td>
            <td class="py-4 px-4">
              <div class="space-y-1">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-cyan-500/10 border border-cyan-500/20 text-cyan-300 font-bold text-[11px]">
                  🏢 {{ j.jurusanNama || 'Semua Jurusan' }}
                </span>
                <div class="text-[11px] text-gray-300 flex items-center gap-1 font-semibold">
                  <span>🏫</span>
                  <span>{{ j.kelasNama || 'Semua Kelas' }}</span>
                </div>
                <p class="text-[10px] text-gray-400">⏱️ {{ j.durasiMenit }} Menit</p>
              </div>
            </td>
            <td class="py-4 px-4 text-center">
              <button
                @click="copyToken(j.token)"
                title="Klik untuk salin token"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 font-mono font-black text-cyan-300 text-xs transition-colors"
              >
                <span>{{ j.token }}</span>
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              </button>
            </td>
            <td class="py-4 px-4 text-center">
              <!-- Quick Toggle Status Switch -->
              <button
                @click="toggleStatus(j)"
                :title="j.status === 'aktif' ? 'Klik untuk Non-Aktifkan Soal' : 'Klik untuk Aktifkan Soal'"
                :class="[
                  'inline-flex items-center gap-2 px-3 py-1.5 rounded-full font-extrabold text-xs transition-all active:scale-95 border min-h-[36px]',
                  j.status === 'aktif'
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-lg shadow-emerald-500/10 hover:bg-emerald-500/30'
                    : 'bg-rose-500/15 text-rose-300 border-rose-500/30 hover:bg-rose-500/25'
                ]"
              >
                <span class="w-2 h-2 rounded-full" :class="j.status === 'aktif' ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                <span>{{ j.status === 'aktif' ? 'AKTIF' : 'NON-AKTIF' }}</span>
              </button>
            </td>
            <!-- LIVE MONITORING RADAR BUTTON -->
            <td class="py-4 px-4 text-center">
              <button
                @click="goToMonitoring(j)"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-cyan-500/20 to-teal-500/20 hover:from-cyan-500/30 hover:to-teal-500/30 text-cyan-300 border border-cyan-500/40 font-black text-xs shadow-md shadow-cyan-500/10 active:scale-95 transition-all min-h-[36px]"
              >
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                <span>📡 Pantau Live</span>
              </button>
            </td>
            <td class="py-4 px-5 text-right">
              <div class="inline-flex items-center gap-2">
                <button
                  @click="openEditModal(j)"
                  title="Edit Jadwal"
                  class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button
                  @click="deleteJadwal(j.id)"
                  title="Hapus Jadwal"
                  class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile View: Card List (md:hidden) -->
    <div v-if="filteredJadwals.length > 0" class="md:hidden space-y-4">
      <div
        v-for="j in filteredJadwals"
        :key="j.id"
        class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 space-y-3 shadow-lg"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <h4 class="font-black text-white text-sm">{{ j.judulUjian }}</h4>
            <p class="text-xs text-teal-400 mt-0.5">📖 {{ j.bankSoalJudul }} ({{ j.mapelNama }})</p>
          </div>
          <button
            @click="copyToken(j.token)"
            class="px-2.5 py-1 rounded bg-white/5 border border-white/10 text-cyan-300 font-mono font-bold text-xs shrink-0"
          >
            🔑 {{ j.token }}
          </button>
        </div>

        <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-300 bg-white/5 p-2.5 rounded-xl font-mono">
          <div>
            <span class="text-gray-400 block text-[10px] uppercase font-sans">Jurusan &amp; Kelas:</span>
            <span class="font-bold text-cyan-300 truncate block">🏢 {{ j.jurusanKode || 'ALL' }} - {{ j.kelasNama }}</span>
          </div>
          <div>
            <span class="text-gray-400 block text-[10px] uppercase font-sans">Durasi Ujian:</span>
            <span class="font-bold text-teal-300">⏱️ {{ j.durasiMenit }} Menit</span>
          </div>
        </div>

        <!-- Badges Acak Soal, Opsi & Mode Mobile -->
        <div class="flex flex-wrap items-center gap-1.5">
          <span
            :class="[
              'inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-bold text-[10px] border',
              j.modeUjian === 'game'
                ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                : 'bg-cyan-500/10 text-cyan-300 border-cyan-500/20'
            ]"
          >
            {{ j.modeUjian === 'game' ? '🎮 Mode Game' : '🏛️ CBT Formal' }}
          </span>

          <span
            v-if="j.acakSoal"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-300 font-bold text-[10px]"
          >
            🔀 Soal Acak
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-400 font-medium text-[10px]"
          >
            Soal Urut
          </span>
          <span
            v-if="j.acakOpsi"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30 text-purple-300 font-bold text-[10px]"
          >
            🔀 Opsi Acak
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-gray-400 font-medium text-[10px]"
          >
            Opsi Urut
          </span>
        </div>

        <!-- Monitoring Button Mobile -->
        <div>
          <button
            @click="goToMonitoring(j)"
            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500/20 to-teal-500/20 hover:from-cyan-500/30 text-cyan-300 border border-cyan-500/40 font-black text-xs flex items-center justify-center gap-2 min-h-[44px]"
          >
            <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
            <span>📡 Pantau Peserta &amp; Pelanggaran (Live)</span>
          </button>
        </div>

        <div class="flex items-center justify-between pt-1 border-t border-white/10">
          <!-- Toggle Status -->
          <button
            @click="toggleStatus(j)"
            :class="[
              'inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-black min-h-[40px]',
              j.status === 'aktif'
                ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                : 'bg-rose-500/15 text-rose-300 border border-rose-500/30'
            ]"
          >
            <span class="w-2 h-2 rounded-full" :class="j.status === 'aktif' ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
            <span>{{ j.status === 'aktif' ? 'AKTIF' : 'NON-AKTIF' }}</span>
          </button>

          <div class="flex items-center gap-2">
            <button
              @click="openEditModal(j)"
              class="p-2 rounded-xl bg-white/5 text-gray-300 border border-white/10 min-h-[40px] min-w-[40px] flex items-center justify-center"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>
            <button
              @click="deleteJadwal(j.id)"
              class="p-2 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 min-h-[40px] min-w-[40px] flex items-center justify-center"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    </div>
</template>
