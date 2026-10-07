import Swal from 'sweetalert2';

/**
 * Custom Dark Mode Styled SweetAlert2 Confirmation Modal for Deletion.
 */
export function confirmDelete({
  title = 'Apakah Anda yakin?',
  text = 'Data yang dihapus dari database tidak dapat dikembalikan!',
  confirmButtonText = 'Ya, Hapus Data!',
  cancelButtonText = 'Batal'
} = {}) {
  return Swal.fire({
    title: title,
    text: text,
    icon: 'warning',
    iconColor: '#ef4444',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#475569',
    confirmButtonText: `<span class="font-extrabold px-2">${confirmButtonText}</span>`,
    cancelButtonText: `<span class="font-semibold">${cancelButtonText}</span>`,
    background: '#0f172a',
    color: '#f8fafc',
    customClass: {
      popup: 'rounded-3xl border border-white/10 shadow-2xl backdrop-blur-xl',
      title: 'text-lg font-black text-white',
      htmlContainer: 'text-xs text-gray-300 font-medium',
      confirmButton: 'rounded-2xl px-5 py-2.5 text-xs shadow-lg shadow-red-500/30 font-bold',
      cancelButton: 'rounded-2xl px-5 py-2.5 text-xs font-bold'
    }
  });
}

/**
 * Custom Prominent Center Success Modal with Animated Green Checkmark.
 */
export function showSuccessAlert(title = 'Berhasil!', text = 'Data berhasil diproses ke database.') {
  return Swal.fire({
    title: title,
    text: text,
    icon: 'success',
    iconColor: '#10b981', // Emerald-500
    confirmButtonColor: '#10b981',
    confirmButtonText: '<span class="font-extrabold px-3">OK, Terimakasih!</span>',
    timer: 3000,
    timerProgressBar: true,
    background: '#0f172a',
    color: '#f8fafc',
    customClass: {
      popup: 'rounded-3xl border border-emerald-500/40 shadow-2xl backdrop-blur-xl animate-in zoom-in-95',
      title: 'text-xl font-black text-white',
      htmlContainer: 'text-xs text-emerald-200 font-medium',
      confirmButton: 'rounded-2xl px-6 py-2.5 text-xs font-extrabold shadow-lg shadow-emerald-500/30'
    }
  });
}

/**
 * Custom Dark Mode Styled SweetAlert2 Toast Notification.
 */
export function showToast(title, icon = 'success') {
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    background: '#0f172a',
    color: '#ffffff',
    customClass: {
      popup: 'rounded-2xl border border-white/10 shadow-2xl backdrop-blur-xl text-xs font-bold'
    },
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
  });

  return Toast.fire({
    icon: icon,
    title: title
  });
}

export default Swal;
