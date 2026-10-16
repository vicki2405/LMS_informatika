<script setup>
import { ref, computed } from 'vue';
import { showToast } from '@/Utils/sweetalert';

const props = defineProps({
  userAccountsList: { type: Array, default: () => [] },
  guruList: { type: Array, default: () => [] },
  siswaList: { type: Array, default: () => [] },
  mapelList: { type: Array, default: () => [] },
  kelasList: { type: Array, default: () => [] }
});

const emit = defineEmits(['save-user', 'reset-password', 'delete-user']);

// Active Sub-Tab: 'all' | 'admin' | 'guru' | 'siswa'
const activeUserTab = ref('all');
const searchUser = ref('');

// Modals State
const showEditUserModal = ref(false);
const editingUser = ref(null);

const userForm = ref({
  id: null,
  username: '',
  name: '',
  email: '',
  nip: '',
  telepon: '',
  mapel_utama: '',
  guru_id: null,
  kelas_id: null,
  roles: ['guru'],
  password: '',
  password_confirmation: '',
  status: 'Aktif'
});

const showResetPasswordModal = ref(false);
const resetUserTarget = ref(null);
const newPasswordForm = ref({ newPassword: '', confirmPassword: '' });

const filteredUsers = computed(() => {
  let list = [...props.userAccountsList];

  // 1. Filter based on active sub-tab
  if (activeUserTab.value === 'admin') {
    list = list.filter(u => u.role === 'admin' || (Array.isArray(u.roles) && u.roles.includes('admin')));
  } else if (activeUserTab.value === 'guru') {
    list = list.filter(u => 
      u.role === 'guru' || u.role === 'wali_kelas' || 
      (Array.isArray(u.roles) && (u.roles.includes('guru') || u.roles.includes('wali_kelas')))
    );
  } else if (activeUserTab.value === 'siswa') {
    list = list.filter(u => u.role === 'murid' || u.role === 'siswa' || (Array.isArray(u.roles) && u.roles.includes('murid')));
  }

  // 2. Filter based on search query
  if (searchUser.value.trim()) {
    const q = searchUser.value.toLowerCase();
    list = list.filter(u =>
      u.username.toLowerCase().includes(q) ||
      u.email.toLowerCase().includes(q) ||
      (u.name && u.name.toLowerCase().includes(q))
    );
  }

  return list;
});

const groupedKelasList = computed(() => {
  const list = [...(props.kelasList || [])];
  const groups = {};
  list.forEach(k => {
    const t = k.tingkat || 'X';
    if (!groups[t]) {
      groups[t] = [];
    }
    groups[t].push(k);
  });
  const result = [];
  ['X', 'XI', 'XII'].forEach(t => {
    if (groups[t] && groups[t].length > 0) {
      result.push({
        tingkat: t,
        classes: groups[t].sort((a, b) => (a?.namaKelas || a?.nama || a || '').toString().localeCompare((b?.namaKelas || b?.nama || b || '').toString(), undefined, { numeric: true, sensitivity: 'base' }))
      });
    }
  });
  return result;
});

function openAddUserModal() {
  editingUser.value = null;
  let initialRole = ['guru'];
  if (activeUserTab.value === 'admin') initialRole = ['admin'];
  if (activeUserTab.value === 'siswa') initialRole = ['murid'];

  userForm.value = {
    id: null,
    username: '',
    name: '',
    email: '',
    nip: '',
    telepon: '',
    mapel_utama: '',
    guru_id: null,
    kelas_id: null,
    roles: initialRole,
    password: '',
    password_confirmation: '',
    status: 'Aktif'
  };
  showEditUserModal.value = true;
}

function onSelectMasterGuru(e) {
  const guruId = parseInt(e.target.value);
  if (!guruId) {
    userForm.value.guru_id = null;
    return;
  }
  const guru = props.guruList.find(g => g.id === guruId);
  if (guru) {
    userForm.value.guru_id = guru.id;
    userForm.value.name = guru.nama;
    userForm.value.username = guru.nama.toLowerCase().replace(/[^a-z0-9]/g, '').substring(0, 12);
    userForm.value.email = guru.email && guru.email !== '-' ? guru.email : `${userForm.value.username}@sekolah.sch.id`;
    userForm.value.nip = guru.nip || '';
    userForm.value.telepon = guru.telepon || '';
    userForm.value.mapel_utama = guru.mapelUtama || '';
  }
}

function onSelectKelasForSharedSiswa(e) {
  const kelasId = parseInt(e.target.value);
  if (!kelasId) {
    userForm.value.kelas_id = null;
    return;
  }
  const k = props.kelasList.find(item => item.id === kelasId);
  if (k) {
    const cleanKelas = k.namaKelas.toLowerCase().replace(/[^a-z0-9]/g, '');
    userForm.value.kelas_id = k.id;
    userForm.value.name = `Siswa Kelas ${k.namaKelas}`;
    userForm.value.username = `siswa_${cleanKelas}`;
    userForm.value.email = `siswa.${cleanKelas}@sekolah.sch.id`;
  }
}

function openEditUserModal(u) {
  editingUser.value = u;
  const currentRoles = u.roles && u.roles.length ? [...u.roles] : [u.role || 'guru'];
  userForm.value = {
    id: u.id,
    username: u.username || '',
    name: u.name || '',
    email: u.email || '',
    nip: u.nip || '',
    telepon: u.telepon || '',
    mapel_utama: u.mapelUtama || u.mapel_utama || '',
    guru_id: u.guru_id || null,
    kelas_id: u.kelasId || u.kelas_id || null,
    roles: currentRoles,
    password: '',
    password_confirmation: '',
    status: u.status || 'Aktif'
  };
  showEditUserModal.value = true;
}

function toggleRole(roleName) {
  const idx = userForm.value.roles.indexOf(roleName);
  if (idx !== -1) {
    if (userForm.value.roles.length > 1) {
      userForm.value.roles.splice(idx, 1);
    }
  } else {
    userForm.value.roles.push(roleName);
  }
}

function handleSaveUser() {
  if (!userForm.value.username || !userForm.value.email || !userForm.value.roles.length) return;

  if (userForm.value.password && userForm.value.password_confirmation && userForm.value.password !== userForm.value.password_confirmation) {
    showToast('Konfirmasi kata sandi baru tidak cocok!', 'warning');
    return;
  }

  emit('save-user', { editing: editingUser.value, data: { ...userForm.value } });
  showEditUserModal.value = false;
}

// Reset Password Handlers
function openResetPasswordModal(u) {
  resetUserTarget.value = u;
  newPasswordForm.value = { newPassword: '', confirmPassword: '' };
  showResetPasswordModal.value = true;
}

function handleResetPassword() {
  if (!newPasswordForm.value.newPassword) {
    showToast('Masukkan kata sandi baru!', 'warning');
    return;
  }
  if (newPasswordForm.value.newPassword !== newPasswordForm.value.confirmPassword) {
    showToast('Konfirmasi kata sandi tidak cocok!', 'warning');
    return;
  }
  emit('reset-password', { userId: resetUserTarget.value.id, newPassword: newPasswordForm.value.newPassword });
  showResetPasswordModal.value = false;
}
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-300">
    
    <!-- Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight flex items-center gap-2">
          <span>Manajemen Akun User &amp; Password Control Center</span>
          <span class="text-purple-400 text-base">🔐</span>
        </h1>
        <p class="text-gray-400 text-xs sm:text-sm mt-1 font-medium">
          Pengelolaan kredensial login terpusat &amp; Penugasan Hak Akses Peran (Multi-Role Spatie)
        </p>
      </div>

      <button
        @click="openAddUserModal"
        class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-purple-500 via-indigo-600 to-cyan-500 text-white px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 hover:scale-105 active:scale-95 transition-all duration-200 shrink-0 min-h-[44px]"
      >
        <span class="text-base">+</span>
        <span>Buat Akun User Baru</span>
      </button>
    </div>

    <!-- 4 Sub-Tabs Navigation Bar -->
    <div class="flex items-center gap-2 border-b border-white/10 pb-3 overflow-x-auto custom-scrollbar">
      <button
        @click="activeUserTab = 'all'"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 shrink-0 min-h-[44px]',
          activeUserTab === 'all'
            ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/25 border border-purple-500/40'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        <span>👥</span>
        <span>Semua Akun User</span>
      </button>

      <button
        @click="activeUserTab = 'admin'"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 shrink-0 min-h-[44px]',
          activeUserTab === 'admin'
            ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/25 border border-purple-500/40'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        <span>🛡️</span>
        <span>Akun Admin</span>
      </button>

      <button
        @click="activeUserTab = 'guru'"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 shrink-0 min-h-[44px]',
          activeUserTab === 'guru'
            ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/25 border border-purple-500/40'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        <span>👨‍🏫</span>
        <span>Akun Guru &amp; Wali Kelas</span>
      </button>

      <button
        @click="activeUserTab = 'siswa'"
        :class="[
          'px-5 py-3 rounded-2xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center gap-2 shrink-0 min-h-[44px]',
          activeUserTab === 'siswa'
            ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/25 border border-purple-500/40'
            : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-transparent'
        ]"
      >
        <span>🎓</span>
        <span>Akun Kelas Siswa (Shared Per Rombel)</span>
      </button>
    </div>

    <!-- Tab Info Banner for Siswa -->
    <div v-if="activeUserTab === 'siswa'" class="p-4 rounded-3xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3 shadow-xl">
      <span class="text-xl shrink-0">ℹ️</span>
      <div class="text-xs leading-relaxed font-medium text-emerald-200">
        <span class="font-extrabold text-white block mb-0.5">Sistem Akun Login Siswa Shared per Rombel/Kelas</span>
        Untuk efisiensi database &amp; kemudahan akses, seluruh siswa dalam 1 kelas menggunakan <strong>1 Akun Shared per Kelas</strong> (contoh: <code>siswa_x_tkr1</code>). Seluruh siswa rombel tersebut dapat login bersamaan.
      </div>
    </div>

    <!-- Search Bar -->
    <div class="relative">
      <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>
      <input
        v-model="searchUser"
        type="text"
        placeholder="Cari Username, Nama, atau Email..."
        class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-white/10 rounded-2xl text-xs sm:text-sm text-white placeholder-gray-400 focus:border-purple-400 focus:ring-2 focus:ring-purple-400/20 outline-none transition-all duration-200 min-h-[44px]"
      />
      <button v-if="searchUser" @click="searchUser = ''" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs text-gray-400 hover:text-white">✕</button>
    </div>

    <!-- Datatable View -->
    <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-xs sm:text-sm text-gray-300">
          <thead class="bg-slate-950/60 text-gray-400 uppercase text-[11px] font-extrabold tracking-wider border-b border-white/10">
            <tr>
              <th class="px-6 py-4 w-12 text-center">No.</th>
              <th class="px-6 py-4">Username &amp; Nama User</th>
              <th class="px-6 py-4">Email Terdaftar</th>
              <th class="px-6 py-4">Peran &amp; Hak Akses (Roles)</th>
              <th class="px-6 py-4">Penugasan / Kelas</th>
              <th class="px-6 py-4">Status Akun</th>
              <th class="px-6 py-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 font-medium">
            <tr
              v-for="(u, idx) in filteredUsers"
              :key="u.id"
              class="hover:bg-white/5 transition-colors duration-150"
            >
              <td class="px-6 py-4 text-center font-mono text-xs text-purple-300 font-bold">{{ idx + 1 }}</td>
              <td class="px-6 py-4 font-bold text-white">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl text-white font-black text-xs flex items-center justify-center shadow-md shrink-0 bg-gradient-to-br from-purple-500 to-indigo-600">
                    {{ u.username.substring(0, 2).toUpperCase() }}
                  </div>
                  <div>
                    <span class="text-sm font-extrabold text-white block">{{ u.username }}</span>
                    <span v-if="u.name" class="text-[11px] text-gray-400 font-medium block">{{ u.name }}</span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 text-gray-300 font-mono text-xs">{{ u.email }}</td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="r in (u.roles || [u.role])"
                    :key="r"
                    :class="[
                      'px-2.5 py-1 rounded-full text-[10px] font-extrabold border',
                      r === 'admin' ? 'bg-purple-500/20 text-purple-300 border-purple-500/30' :
                      r === 'wali_kelas' ? 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30' :
                      r === 'guru' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' :
                      'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                    ]"
                  >
                    {{ r }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 text-xs text-gray-300">
                <span v-if="u.namaKelas || u.kelasId" class="bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 px-2.5 py-1 rounded-full font-bold">
                  🏫 Kelas {{ u.namaKelas || u.kelasId }}
                </span>
                <span v-else-if="u.mapelUtama" class="text-gray-400">
                  📚 {{ u.mapelUtama }}
                </span>
                <span v-else class="text-gray-500">-</span>
              </td>
              <td class="px-6 py-4"><span class="text-emerald-400 text-xs font-bold">✓ Aktif</span></td>
              <td class="px-6 py-4 text-right space-x-2">
                <button @click="openResetPasswordModal(u)" class="text-amber-300 hover:text-white font-bold text-xs bg-amber-500/15 hover:bg-amber-500/30 px-3 py-1.5 rounded-xl border border-amber-500/30 active:scale-95 transition-all">🔑 Reset</button>
                <button @click="openEditUserModal(u)" class="text-cyan-300 hover:text-white font-bold text-xs bg-cyan-500/15 hover:bg-cyan-500/30 px-3 py-1.5 rounded-xl border border-cyan-500/30 active:scale-95 transition-all">✏️ Edit</button>
                <button @click="$emit('delete-user', u.id)" class="text-red-400 hover:text-red-300 font-bold text-xs bg-red-500/15 hover:bg-red-500/30 px-3 py-1.5 rounded-xl border border-red-500/30 active:scale-95 transition-all">🗑️ Hapus</button>
              </td>
            </tr>
            <tr v-if="filteredUsers.length === 0">
              <td colspan="7" class="px-6 py-12 text-center text-gray-400 font-medium">
                Belum ada data akun user terdaftar pada kategori ini.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card View -->
      <div class="md:hidden divide-y divide-white/5">
        <div
          v-for="(u, idx) in filteredUsers"
          :key="'mobile-' + u.id"
          class="p-4 space-y-3"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl text-white font-black text-sm flex items-center justify-center shadow-md shrink-0 bg-gradient-to-br from-purple-500 to-indigo-600">
              {{ u.username.substring(0, 2).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-sm font-extrabold text-white block truncate">{{ u.username }}</span>
              <span v-if="u.name" class="text-[11px] text-gray-400 font-medium block truncate">{{ u.name }}</span>
            </div>
            <span class="text-emerald-400 text-[10px] font-bold shrink-0">✓ Aktif</span>
          </div>

          <div class="text-[11px] text-gray-300 font-mono truncate">{{ u.email }}</div>

          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="r in (u.roles || [u.role])"
              :key="r"
              :class="[
                'px-2 py-0.5 rounded-full text-[10px] font-extrabold border',
                r === 'admin' ? 'bg-purple-500/20 text-purple-300 border-purple-500/30' :
                r === 'wali_kelas' ? 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30' :
                r === 'guru' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' :
                'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
              ]"
            >
              {{ r }}
            </span>
          </div>

          <div v-if="u.namaKelas || u.kelasId" class="text-[11px]">
            <span class="bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 px-2 py-0.5 rounded-full font-bold">
              🏫 Kelas {{ u.namaKelas || u.kelasId }}
            </span>
          </div>
          <div v-else-if="u.mapelUtama" class="text-[11px] text-gray-400">
            📚 {{ u.mapelUtama }}
          </div>

          <div class="flex items-center gap-2 pt-2 border-t border-white/5">
            <button @click="openResetPasswordModal(u)" class="flex-1 text-amber-300 font-bold text-[11px] bg-amber-500/15 hover:bg-amber-500/30 px-3 py-2 rounded-xl border border-amber-500/30 active:scale-95 transition-all text-center">🔑 Reset</button>
            <button @click="openEditUserModal(u)" class="flex-1 text-cyan-300 font-bold text-[11px] bg-cyan-500/15 hover:bg-cyan-500/30 px-3 py-2 rounded-xl border border-cyan-500/30 active:scale-95 transition-all text-center">✏️ Edit</button>
            <button @click="$emit('delete-user', u.id)" class="flex-1 text-red-400 font-bold text-[11px] bg-red-500/15 hover:bg-red-500/30 px-3 py-2 rounded-xl border border-red-500/30 active:scale-95 transition-all text-center">🗑️ Hapus</button>
          </div>
        </div>

        <div v-if="filteredUsers.length === 0" class="p-8 text-center text-gray-400 font-medium text-sm">
          Belum ada data akun user terdaftar pada kategori ini.
        </div>
      </div>
    </div>

    <!-- Modal Form Akun User Baru / Edit -->
    <div v-if="showEditUserModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl max-w-lg w-full max-h-[90vh] sm:max-h-[85vh] overflow-y-auto p-6 shadow-2xl space-y-5 animate-in slide-in-from-bottom duration-200 sm:animate-in sm:zoom-in-95 pb-24 sm:pb-6">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="text-lg font-black text-white tracking-tight">
            {{ editingUser ? 'Edit Kredensial Akun User' : 'Buat Akun User Baru' }}
          </h3>
          <button @click="showEditUserModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="handleSaveUser" class="space-y-4">
          <!-- Dropdown Pilih Data Master Guru (jika role Guru / Wali Kelas) -->
          <div v-if="!editingUser && (userForm.roles.includes('guru') || userForm.roles.includes('wali_kelas'))" class="bg-purple-950/30 border border-purple-500/30 p-3 rounded-2xl space-y-2">
            <label class="block text-[11px] font-extrabold text-purple-300 uppercase tracking-wider">Auto-Fill dari Master Guru</label>
            <select @change="onSelectMasterGuru" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:border-purple-400 outline-none">
              <option value="">-- Pilih Guru dari Master Data --</option>
              <option v-for="g in guruList" :key="g.id" :value="g.id">{{ g.nama }} ({{ g.nip || 'No NIP' }})</option>
            </select>
          </div>

          <!-- Dropdown Auto-Fill Rombel/Kelas untuk Akun Siswa Shared -->
          <div v-if="!editingUser && userForm.roles.includes('murid')" class="bg-emerald-950/30 border border-emerald-500/30 p-3 rounded-2xl space-y-2">
            <label class="block text-[11px] font-extrabold text-emerald-300 uppercase tracking-wider">Auto-Fill Akun Shared 1 Kelas / Rombel</label>
            <select @change="onSelectKelasForSharedSiswa" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:border-emerald-400 outline-none">
              <option value="">-- Pilih Kelas untuk Pembuatan Akun Shared Siswa --</option>
              <optgroup v-for="group in groupedKelasList" :key="group.tingkat" :label="'Tingkat ' + group.tingkat">
                <option v-for="k in group.classes" :key="k.id" :value="k.id">Kelas {{ k.namaKelas }} ({{ k.jurusan || 'SMK' }})</option>
              </optgroup>
            </select>
          </div>

          <div>
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">Username Login <span class="text-red-400">*</span></label>
            <input v-model="userForm.username" required type="text" placeholder="Contoh: siswa_x_tkr1..." class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
          </div>

          <div>
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">Email <span class="text-red-400">*</span></label>
            <input v-model="userForm.email" required type="email" placeholder="user@sekolah.sch.id" class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
          </div>

          <div>
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">Nama Tampilan / Nama Akun</label>
            <input v-model="userForm.name" type="text" placeholder="Contoh: Siswa Kelas X TKR 1..." class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
          </div>

          <!-- Select Kelas jika Wali Kelas atau Murid -->
          <div v-if="userForm.roles.includes('wali_kelas') || userForm.roles.includes('murid')">
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">
              {{ userForm.roles.includes('wali_kelas') ? 'Penugasan Kelas Binaan (Wali Kelas)' : 'Rombel / Kelas Terkait' }}
            </label>
            <select v-model="userForm.kelas_id" class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none">
              <option :value="null">-- Pilih Kelas --</option>
              <optgroup v-for="group in groupedKelasList" :key="group.tingkat" :label="'Tingkat ' + group.tingkat">
                <option v-for="k in group.classes" :key="k.id" :value="k.id">Kelas {{ k.namaKelas }} ({{ k.jurusan || 'SMK' }})</option>
              </optgroup>
            </select>
          </div>

          <!-- Roles Multi-Select (Hanya untuk Admin / Guru / Wali Kelas, disembunyikan untuk Akun Siswa) -->
          <div v-if="activeUserTab !== 'siswa' && !userForm.roles.includes('murid')">
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-2">Penugasan Peran &amp; Hak Akses (Roles Multi-Select)</label>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                @click="toggleRole('admin')"
                :class="['px-3 py-1.5 rounded-xl text-xs font-bold border transition-all', userForm.roles.includes('admin') ? 'bg-purple-500 text-white border-purple-400' : 'bg-white/5 text-gray-400 border-white/10']"
              >
                🛡️ Admin
              </button>
              <button
                type="button"
                @click="toggleRole('guru')"
                :class="['px-3 py-1.5 rounded-xl text-xs font-bold border transition-all', userForm.roles.includes('guru') ? 'bg-cyan-500 text-white border-cyan-400' : 'bg-white/5 text-gray-400 border-white/10']"
              >
                👨‍🏫 Guru
              </button>
              <button
                type="button"
                @click="toggleRole('wali_kelas')"
                :class="['px-3 py-1.5 rounded-xl text-xs font-bold border transition-all', userForm.roles.includes('wali_kelas') ? 'bg-indigo-500 text-white border-indigo-400' : 'bg-white/5 text-gray-400 border-white/10']"
              >
                📋 Wali Kelas
              </button>
            </div>
          </div>
          <div v-else class="bg-emerald-950/20 border border-emerald-500/30 p-3 rounded-2xl flex items-center justify-between">
            <span class="text-xs font-extrabold text-gray-300 uppercase tracking-wider">Peran Akun User</span>
            <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-3 py-1 rounded-full text-xs font-extrabold flex items-center gap-1.5">
              🎓 Murid / Siswa Kelas
            </span>
          </div>

          <div v-if="!editingUser" class="grid sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">Password Login</label>
              <input v-model="userForm.password" type="password" placeholder="Kosongkan (default: password123)" class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
            </div>
            <div>
              <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">Konfirmasi Password</label>
              <input v-model="userForm.password_confirmation" type="password" placeholder="Ulangi password..." class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
            <button type="button" @click="showEditUserModal = false" class="px-5 py-2.5 rounded-2xl text-xs font-extrabold text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-all">Batal</button>
            <button type="submit" class="px-5 py-2.5 rounded-2xl text-xs font-extrabold text-white bg-gradient-to-r from-purple-500 to-indigo-600 shadow-lg shadow-purple-500/25 hover:scale-105 active:scale-95 transition-all">Simpan Akun User</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Reset Password -->
    <div v-if="showResetPasswordModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
      <div class="bg-slate-900 border border-white/10 rounded-t-3xl sm:rounded-3xl max-w-md w-full max-h-[90vh] sm:max-h-[85vh] overflow-y-auto p-6 shadow-2xl space-y-5 animate-in slide-in-from-bottom duration-200 sm:animate-in sm:zoom-in-95 pb-24 sm:pb-6">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="text-lg font-black text-white tracking-tight">Reset Password Akun</h3>
          <button @click="showResetPasswordModal = false" class="text-gray-400 hover:text-white text-lg font-bold">✕</button>
        </div>

        <p class="text-xs text-gray-300">
          Reset kata sandi untuk username: <strong class="text-purple-300 font-mono">{{ resetUserTarget?.username }}</strong>
        </p>

        <form @submit.prevent="handleResetPassword" class="space-y-4">
          <div>
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">Password Baru</label>
            <input v-model="newPasswordForm.newPassword" required type="password" placeholder="Masukkan password baru..." class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
          </div>

          <div>
            <label class="block text-xs font-extrabold text-gray-300 uppercase tracking-wider mb-1">Konfirmasi Password Baru</label>
            <input v-model="newPasswordForm.confirmPassword" required type="password" placeholder="Ulangi password baru..." class="w-full bg-slate-950 border border-white/10 rounded-2xl px-4 py-2.5 text-xs text-white focus:border-purple-400 outline-none" />
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
            <button type="button" @click="showResetPasswordModal = false" class="px-5 py-2.5 rounded-2xl text-xs font-extrabold text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 transition-all">Batal</button>
            <button type="submit" class="px-5 py-2.5 rounded-2xl text-xs font-extrabold text-white bg-gradient-to-r from-amber-500 to-amber-600 shadow-lg shadow-amber-500/25 hover:scale-105 active:scale-95 transition-all">Update Password</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
