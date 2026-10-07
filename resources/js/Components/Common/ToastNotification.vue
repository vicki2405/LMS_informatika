<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success'); // 'success' | 'danger' | 'warning' | 'info'
let timer = null;

function showToast(msg, msgType = 'success') {
  if (!msg) return;
  message.value = msg;
  type.value = msgType;
  visible.value = true;

  if (timer) clearTimeout(timer);
  timer = setTimeout(() => {
    visible.value = false;
  }, 4000);
}

function dismiss() {
  visible.value = false;
  if (timer) clearTimeout(timer);
}

// Listen to Inertia Flash Messages
watch(
  () => page.props.flash?.message,
  (newMsg) => {
    if (newMsg) {
      const msgType = page.props.flash?.type || (newMsg.toLowerCase().includes('hapus') ? 'danger' : newMsg.toLowerCase().includes('reset') || newMsg.toLowerCase().includes('password') ? 'warning' : 'success');
      showToast(newMsg, msgType);
    }
  },
  { immediate: true }
);

function handleShowToast(e) {
  if (e.detail) {
    showToast(e.detail.message, e.detail.type || 'success');
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('show-toast', handleShowToast);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('show-toast', handleShowToast);
  }
});
</script>

<template>
  <Transition
    enter-active-class="transform transition ease-out duration-300"
    enter-from-class="translate-y-[-100%] opacity-0 scale-95"
    enter-to-class="translate-y-0 opacity-100 scale-100"
    leave-active-class="transition ease-in duration-200"
    leave-from-class="translate-y-0 opacity-100 scale-100"
    leave-to-class="translate-y-[-100%] opacity-0 scale-95"
  >
    <div
      v-if="visible"
      class="fixed top-20 right-4 sm:right-8 z-50 max-w-md w-[calc(100vw-2rem)] sm:w-auto shadow-2xl rounded-2xl border backdrop-blur-xl p-4 flex items-center gap-3.5"
      :class="[
        type === 'success' ? 'bg-emerald-950/90 border-emerald-500/40 text-emerald-100 shadow-emerald-950/50' :
        type === 'danger' ? 'bg-rose-950/90 border-rose-500/40 text-rose-100 shadow-rose-950/50' :
        type === 'warning' ? 'bg-amber-950/90 border-amber-500/40 text-amber-100 shadow-amber-950/50' :
        'bg-cyan-950/90 border-cyan-500/40 text-cyan-100 shadow-cyan-950/50'
      ]"
    >
      <!-- Icon Badge -->
      <div
        class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 font-black text-sm shadow-inner"
        :class="[
          type === 'success' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' :
          type === 'danger' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' :
          type === 'warning' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' :
          'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30'
        ]"
      >
        <span v-if="type === 'success'">✅</span>
        <span v-else-if="type === 'danger'">🗑️</span>
        <span v-else-if="type === 'warning'">🔑</span>
        <span v-else>ℹ️</span>
      </div>

      <!-- Message Content -->
      <div class="flex-1 min-w-0">
        <h4 class="text-xs font-black uppercase tracking-wider text-white">
          {{ type === 'success' ? 'Transaksi Berhasil' : type === 'danger' ? 'Penghapusan Data' : type === 'warning' ? 'Pemberitahuan Sistem' : 'Informasi' }}
        </h4>
        <p class="text-xs font-semibold leading-snug mt-0.5 break-words">
          {{ message }}
        </p>
      </div>

      <!-- Close Button -->
      <button
        @click="dismiss"
        class="p-1.5 rounded-xl hover:bg-white/10 text-gray-400 hover:text-white transition-colors shrink-0"
      >
        ✕
      </button>
    </div>
  </Transition>
</template>
