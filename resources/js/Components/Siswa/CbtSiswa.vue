<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { confirmDelete, showSuccessAlert, showToast } from '@/Utils/sweetalert';
import Swal from 'sweetalert2';

const props = defineProps({
  siswaList: { type: Array, default: () => [] },
  cbtJadwalList: { type: Array, default: () => [] },
  cbtNilaiList: { type: Array, default: () => [] },
  cbtSesiList: { type: Array, default: () => [] },
  siswaObj: { type: Object, default: () => ({}) },
});

// View mode:
// 'list'         -> Daftar Jadwal Ujian Aktif & Riwayat Nilai
// 'waiting_room' -> Terminal / Ruang Tunggu CBT Peserta (menunggu token & verifikasi identitas)
// 'exam_room'    -> Ruang Pengerjaan Ujian Live dengan Anti-Cheat Lock Screen
const currentMode = ref('list');

// Active Tab in list mode: 'aktif' | 'riwayat'
const activeTab = ref('aktif');

// Search & Filter in list view
const searchQuery = ref('');
const filterSiswaHistory = ref('');

// Waiting Room & Identity Selection State (Single Unified Combobox)
const showIdentityModal = ref(false);
const selectedJadwal = ref(null);
const selectedSiswaId = ref('');
const studentSearchText = ref('');
const isDropdownOpen = ref(false);

const inputToken = ref('');
const tokenError = ref('');

// Active Exam & Student State
const activeExam = ref(null);
const activeStudent = ref(null); // { id, nama, nis }
const currentQuestionIndex = ref(0);
const preparedQuestions = ref([]); // Processed & shuffled questions
const answers = ref({}); // { soalId: 'A' | 'B' | ... }
const raguRagu = ref({}); // { soalId: true | false }
const examStartTime = ref(null);
const remainingSeconds = ref(0);
let timerInterval = null;
let heartbeatInterval = null;

// Fisher-Yates array shuffle helper
function shuffleArray(array) {
  const arr = [...array];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

// Anti-Cheat & Lock Screen State
const violationCount = ref(0);
const showViolationAlert = ref(false);
const violationAlertMessage = ref('');
const isExamLocked = ref(false);
const isExamSubmitted = ref(false);
const showMobilePalette = ref(false);

// Lightbox modal state for viewing images in full size
const selectedLightboxImage = ref(null);
function openImageLightbox(url) {
  selectedLightboxImage.value = url;
}
function closeImageLightbox() {
  selectedLightboxImage.value = null;
}

// Question text & [gambar] tag helpers
function hasImageTag(text) {
  return Boolean(text && text.match(/\[(?:gambar|img|image)\]|\((?:gambar|img|image)\)/i));
}

function getQuestionSplitParts(text) {
  if (!text) return ['', ''];
  const parts = text.split(/\[(?:gambar|img|image)\]|\((?:gambar|img|image)\)/i);
  return [parts[0] || '', parts.slice(1).join('\n') || ''];
}

// Filtered Siswa in Combobox Dropdown
const filteredSiswaDropdown = computed(() => {
  if (!studentSearchText.value.trim()) return props.siswaList;
  const q = studentSearchText.value.toLowerCase().trim();
  return props.siswaList.filter((s) => {
    return s.nama.toLowerCase().includes(q) || String(s.nis).includes(q);
  });
});

// Selected Student Object
const selectedSiswaObj = computed(() => {
  return props.siswaList.find((s) => String(s.id) === String(selectedSiswaId.value)) || null;
});

function selectStudent(student) {
  selectedSiswaId.value = student.id;
  studentSearchText.value = `${student.nis} — ${student.nama}`;
  isDropdownOpen.value = false;
  try {
    localStorage.setItem('siswacloud_active_siswa_id', String(student.id));
  } catch (e) {}
}

function clearStudentSelection() {
  selectedSiswaId.value = '';
  studentSearchText.value = '';
  isDropdownOpen.value = true;
}

function onStudentInputFocus() {
  isDropdownOpen.value = true;
}

function onStudentInputBlur() {
  setTimeout(() => {
    isDropdownOpen.value = false;
  }, 250);
}

function onStudentInputEnter() {
  if (filteredSiswaDropdown.value.length > 0) {
    selectStudent(filteredSiswaDropdown.value[0]);
  }
}

// Check if selected student already has a result for this exam
const existingResultForSelectedSiswa = computed(() => {
  if (!selectedJadwal.value || !selectedSiswaId.value) return null;
  return props.cbtNilaiList.find((n) => {
    return String(n.jadwalId) === String(selectedJadwal.value.id) && String(n.siswaId) === String(selectedSiswaId.value);
  }) || null;
});

// Classmates who are in the same waiting room / exam session
const classmatesInSession = computed(() => {
  if (!selectedJadwal.value) return [];
  return props.cbtSesiList.filter((s) => String(s.jadwalId) === String(selectedJadwal.value.id));
});

// Filtered Active Jadwals
const filteredJadwals = computed(() => {
  if (!searchQuery.value.trim()) return props.cbtJadwalList;
  const q = searchQuery.value.toLowerCase();
  return props.cbtJadwalList.filter((j) => {
    return j.judulUjian.toLowerCase().includes(q) ||
      j.mapelNama.toLowerCase().includes(q) ||
      j.guruNama.toLowerCase().includes(q);
  });
});

// Filtered History
const filteredNilais = computed(() => {
  return props.cbtNilaiList.filter((n) => {
    const matchSearch = !searchQuery.value.trim() ||
      n.judulUjian.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      n.mapelNama.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      n.namaSiswa.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      n.nis.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      n.guruNama.toLowerCase().includes(searchQuery.value.toLowerCase());

    const matchSiswa = !filterSiswaHistory.value || String(n.siswaId) === String(filterSiswaHistory.value);

    return matchSearch && matchSiswa;
  });
});

// Rank Calculation per Jadwal for Riwayat & Nilai
const rankMap = computed(() => {
  const map = {};
  const groups = {};

  props.cbtNilaiList.forEach((n) => {
    if (!groups[n.jadwalId]) groups[n.jadwalId] = [];
    groups[n.jadwalId].push(n);
  });

  Object.keys(groups).forEach((jId) => {
    const list = [...groups[jId]].sort((a, b) => {
      const scoreDiff = (parseFloat(b.nilai) || 0) - (parseFloat(a.nilai) || 0);
      if (scoreDiff !== 0) return scoreDiff;
      const benarDiff = (b.jumlahBenar || 0) - (a.jumlahBenar || 0);
      if (benarDiff !== 0) return benarDiff;
      return a.id - b.id;
    });

    list.forEach((item, idx) => {
      map[item.id] = {
        rank: idx + 1,
        totalInJadwal: list.length,
      };
    });
  });

  return map;
});

function getRank(nilaiId) {
  return rankMap.value[nilaiId]?.rank || '-';
}

function getTotalInJadwal(nilaiId) {
  return rankMap.value[nilaiId]?.totalInJadwal || 0;
}

const sortedFilteredNilais = computed(() => {
  return [...filteredNilais.value].sort((a, b) => {
    const rA = rankMap.value[a.id]?.rank ?? 999;
    const rB = rankMap.value[b.id]?.rank ?? 999;
    if (a.jadwalId !== b.jadwalId) {
      return b.jadwalId - a.jadwalId;
    }
    return rA - rB;
  });
});

// Live Leaderboard & Live Ranking during Exam
const showLiveLeaderboardModal = ref(false);

const liveCorrectAnswersCount = computed(() => {
  let count = 0;
  currentQuestions.value.forEach((q) => {
    const ans = answers.value[q.id];
    if (ans && q.kunciJawaban) {
      if (String(ans).trim().toUpperCase() === String(q.kunciJawaban).trim().toUpperCase()) {
        count++;
      }
    }
  });
  return count;
});

const liveRankingInfo = computed(() => {
  if (!activeExam.value || !activeStudent.value) {
    return { rank: 1, total: 1, score: 0, motivationText: '', leaderboard: [] };
  }

  const jId = activeExam.value.id;
  const peers = props.cbtNilaiList
    .filter(n => String(n.jadwalId) === String(jId) && String(n.siswaId) !== String(activeStudent.value.id))
    .map(n => ({
      id: n.id,
      nama: n.namaSiswa,
      nis: n.nis,
      nilai: parseFloat(n.nilai) || 0,
      isSelf: false,
    }));

  const currentScore = isGameMode.value
    ? (currentQuestions.value.length ? Math.round((liveCorrectAnswersCount.value / currentQuestions.value.length) * 100) : 0)
    : (currentQuestions.value.length ? Math.round((totalAnswered.value / currentQuestions.value.length) * 100) : 0);

  const allParticipants = [
    ...peers,
    {
      id: 'self',
      nama: activeStudent.value.nama,
      nis: activeStudent.value.nis,
      nilai: currentScore,
      isSelf: true,
    }
  ].sort((a, b) => b.nilai - a.nilai);

  const myIndex = allParticipants.findIndex(p => p.isSelf);
  const myRank = myIndex !== -1 ? myIndex + 1 : 1;
  const totalParticipants = allParticipants.length;

  let motivationText = '';
  if (myRank === 1) {
    if (totalParticipants > 1) {
      const runnerUp = allParticipants[1];
      const lead = currentScore - runnerUp.nilai;
      motivationText = `👑 Tahta Juara #1 Sementara! Unggul ${lead >= 0 ? lead : 0} poin dari pesaing terdekat!`;
    } else {
      motivationText = `👑 Memimpin di Peringkat #1 Sementara! Raih nilai sempurna 100!`;
    }
  } else {
    const personAhead = allParticipants[myIndex - 1];
    const diff = personAhead ? Math.max(0, personAhead.nilai - currentScore) : 0;
    if (myRank === 2) {
      motivationText = `🥈 Posisi #2 Sementara! Butuh +${diff} poin lagi untuk merebut Juara 1! 🚀`;
    } else if (myRank === 3) {
      motivationText = `🥉 Posisi #3 Besar Sementara! Pertahankan podium juaramu! 🔥`;
    } else {
      motivationText = `⚡ Posisi #${myRank} dari ${totalParticipants} siswa. Terus berjuang untuk tembus 3 Besar! 💪`;
    }
  }

  return {
    rank: myRank,
    total: totalParticipants,
    score: currentScore,
    motivationText,
    leaderboard: allParticipants,
  };
});

// Questions in active exam (supports Soal Acak)
const currentQuestions = computed(() => {
  if (preparedQuestions.value.length > 0) {
    return preparedQuestions.value;
  }
  return activeExam.value?.soals || [];
});

const currentQuestion = computed(() => {
  return currentQuestions.value[currentQuestionIndex.value] || null;
});

// Options for current question (supports Opsi Acak)
const currentDisplayOptions = computed(() => {
  if (!currentQuestion.value) return [];
  if (currentQuestion.value.displayOptions && currentQuestion.value.displayOptions.length > 0) {
    return currentQuestion.value.displayOptions;
  }
  const letters = ['A', 'B', 'C', 'D', 'E'];
  return letters
    .filter((l) => currentQuestion.value['opsi' + l] && String(currentQuestion.value['opsi' + l]).trim() !== '')
    .map((l) => ({
      displayKey: l,
      originalKey: l,
      text: currentQuestion.value['opsi' + l],
    }));
});

function getAnswerDisplayKey(q) {
  const ans = answers.value[q.id];
  if (!ans) return '';
  if (q.tipeSoal === 'match') {
    return '🧩';
  } else if (q.tipeSoal === 'reorder') {
    return '🔢';
  } else if (q.tipeSoal === 'categorize') {
    return '📂';
  }
  if (q.displayOptions && q.displayOptions.length > 0) {
    const found = q.displayOptions.find((o) => o.originalKey === ans);
    if (found) return found.displayKey;
  }
  return typeof ans === 'string' ? ans : '✓';
}

const totalAnswered = computed(() => {
  return Object.keys(answers.value).filter((k) => {
    const v = answers.value[k];
    if (v === null || v === undefined || v === '') return false;
    if (typeof v === 'object') {
      if (Array.isArray(v)) return v.length > 0;
      return Object.keys(v).length > 0;
    }
    return true;
  }).length;
});

// Formatted Timer: MM:SS
const formattedTime = computed(() => {
  const m = Math.floor(remainingSeconds.value / 60);
  const s = remainingSeconds.value % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

// -----------------------------------------------------------------------------
// WEB AUDIO API SOUND SYNTHESIZER (ZERO EXTERNAL MP3 ASSETS)
// -----------------------------------------------------------------------------
const sfxEnabled = ref(true);

function getAudioContext() {
  if (typeof window === 'undefined') return null;
  const AudioCtx = window.AudioContext || window.webkitAudioContext;
  if (!AudioCtx) return null;
  if (!window.__cbtAudioCtx) {
    window.__cbtAudioCtx = new AudioCtx();
  }
  if (window.__cbtAudioCtx.state === 'suspended') {
    window.__cbtAudioCtx.resume();
  }
  return window.__cbtAudioCtx;
}

function playTapSound() {
  if (!sfxEnabled.value) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(580, ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.08);
    gain.gain.setValueAtTime(0.12, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.08);
  } catch (e) {}
}

function playWhooshSound() {
  if (!sfxEnabled.value) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(320, ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.12);
    gain.gain.setValueAtTime(0.08, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.12);
  } catch (e) {}
}

function playPairMatchSound() {
  if (!sfxEnabled.value) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;
    const now = ctx.currentTime;
    [523.25, 659.25, 783.99].forEach((freq, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, now + i * 0.06);
      gain.gain.setValueAtTime(0.1, now + i * 0.06);
      gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.06 + 0.15);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(now + i * 0.06);
      osc.stop(now + i * 0.06 + 0.15);
    });
  } catch (e) {}
}

function playVictorySound() {
  if (!sfxEnabled.value) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;
    const notes = [523.25, 659.25, 783.99, 1046.5];
    const now = ctx.currentTime;
    notes.forEach((freq, idx) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'triangle';
      osc.frequency.setValueAtTime(freq, now + idx * 0.1);
      gain.gain.setValueAtTime(0.15, now + idx * 0.1);
      gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.1 + 0.35);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(now + idx * 0.1);
      osc.stop(now + idx * 0.1 + 0.35);
    });
  } catch (e) {}
}

function playBoomSound() {
  if (!sfxEnabled.value) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;
    const now = ctx.currentTime;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    const filter = ctx.createBiquadFilter();

    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(260, now);
    osc.frequency.exponentialRampToValueAtTime(35, now + 0.35);

    filter.type = 'lowpass';
    filter.frequency.setValueAtTime(550, now);
    filter.frequency.exponentialRampToValueAtTime(70, now + 0.35);

    gain.gain.setValueAtTime(0.22, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

    osc.connect(filter);
    filter.connect(gain);
    gain.connect(ctx.destination);

    osc.start(now);
    osc.stop(now + 0.35);
  } catch (e) {}
}

function playCorrectChime() {
  if (!sfxEnabled.value) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;
    const now = ctx.currentTime;
    const freqs = [783.99, 1046.50, 1318.51, 1567.98];
    freqs.forEach((freq, idx) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, now + idx * 0.05);
      gain.gain.setValueAtTime(0.12, now + idx * 0.05);
      gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.05 + 0.25);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(now + idx * 0.05);
      osc.stop(now + idx * 0.05 + 0.25);
    });
  } catch (e) {}
}

// -----------------------------------------------------------------------------
// GAMIFICATION, QUEST & CONFETTI STATE
// -----------------------------------------------------------------------------
const isGameMode = computed(() => activeExam.value?.modeUjian === 'game');

// -----------------------------------------------------------------------------
// 5 RANDOM ANIMATION THEMES FOR CORRECT ANSWERS (JAWABAN BENAR)
// -----------------------------------------------------------------------------
const correctAnimationVariants = [
  {
    id: 'trophy',
    tag: '🏆 PIALA JUARA',
    title: 'BENAR! JAWABAN TEPAT 🎯',
    borderClass: 'border-amber-400/70 shadow-[0_0_55px_rgba(251,191,36,0.4)]',
    titleGradient: 'from-yellow-300 via-amber-200 to-yellow-400',
    quotes: [
      'Luar biasa! Jawabanmu tepat sekali tanpa ragu! 🎯',
      'Pilihan jitu! Poin bertambah pesat, pertahankan! 🏆',
      'Cerdas! Kamu melaju mulus di jalur juara! ✨',
    ],
  },
  {
    id: 'crown',
    tag: '👑 MAHKOTA RAJA',
    title: 'SULTAN JUARA! KELAS RAJA 👑',
    borderClass: 'border-yellow-400/70 shadow-[0_0_55px_rgba(234,179,8,0.45)]',
    titleGradient: 'from-amber-200 via-yellow-100 to-amber-300',
    quotes: [
      'Mental juara sejati! Mahkota kehormatan milikmu! 👑',
      'Level kepintaran tingkat dewa! Tak tertandingi!',
      'Gaya santai tapi jawaban tetap sempurna! Mantap!',
    ],
  },
  {
    id: 'rocket',
    tag: '🚀 TURBO ROCKET',
    title: 'MELESAT TINGGI! SPEED 🚀',
    borderClass: 'border-cyan-400/70 shadow-[0_0_55px_rgba(6,182,212,0.45)]',
    titleGradient: 'from-cyan-300 via-teal-200 to-emerald-300',
    quotes: [
      'Wusss! Skor melesat bagai roket tanpa ampun! 🚀',
      'Kecepatan dan ketepatanmu luar biasa spektakuler! 🔥',
      'Melesat menembus stratosfer menuju nilai 100!',
    ],
  },
  {
    id: 'treasure',
    tag: '💎 HARTA KARUN',
    title: 'JACKPOT HARTA KARUN! 💎',
    borderClass: 'border-emerald-400/70 shadow-[0_0_55px_rgba(16,185,129,0.45)]',
    titleGradient: 'from-emerald-300 via-teal-100 to-cyan-300',
    quotes: [
      'Jackpot ilmu! Semua rahasia soal berhasil dibongkar! 💎',
      'Dapat berlian berharga! Gudang pengetahuanmu luas!',
      'Peti emas terbuka lebar menyambut kecerdasanmu! ✨',
    ],
  },
  {
    id: 'shield',
    tag: '🛡️ HERO SHIELD',
    title: 'PAHLAWAN SAKTI! PERISAI JUARA 🛡️',
    borderClass: 'border-purple-400/70 shadow-[0_0_55px_rgba(168,85,247,0.45)]',
    titleGradient: 'from-purple-300 via-pink-200 to-amber-300',
    quotes: [
      'Pertahanan logika yang tak tertembus sedikit pun! 🛡️',
      'Pahlawan kelas sejati telah beraksi dengan gagah!',
      'Pedang dan perisai pengetahuanmu tajam luar biasa! ⚔️',
    ],
  },
];

// -----------------------------------------------------------------------------
// 5 RANDOM ANIMATION THEMES FOR WRONG ANSWERS (JAWABAN SALAH & CANDAAN)
// -----------------------------------------------------------------------------
const wrongAnimationVariants = [
  {
    id: 'bomb',
    tag: '💥 KABOOM',
    title: 'BOOM! BELUM TEPAT 💥',
    borderClass: 'border-rose-500/70 shadow-[0_0_55px_rgba(244,63,94,0.45)]',
    titleGradient: 'from-rose-400 via-orange-300 to-amber-400',
    quotes: [
      'BOOM! 💥 Ups meleset sedikit! Santai, balas di nomor depan!',
      'KABOOM! 💣 Kena jebakan betmen! Tetap semangat kamu pasti bisa!',
      'Dinamit meledak! Ayo atur ulang napas dan fokus kembali!',
    ],
  },
  {
    id: 'dizzy',
    tag: '💫 LINGLUNG',
    title: 'OOPSIE! PUSING TUJUH KELILING 💫',
    borderClass: 'border-amber-500/70 shadow-[0_0_55px_rgba(245,158,11,0.45)]',
    titleGradient: 'from-amber-300 via-yellow-200 to-orange-400',
    quotes: [
      'Waduh muter-muter sampai linglung! Tarik napas dulu yuk! 💫',
      'Otak nge-lag sesaat! Tenang, segarkan pikiran di nomor berikutnya!',
      'Bintang-bintang berputar di kepala! Ayo bangkit lagi!',
    ],
  },
  {
    id: 'banana',
    tag: '🍌 TERPELESET',
    title: 'TERPELESET KULIT PISANG! 🍌',
    borderClass: 'border-yellow-500/70 shadow-[0_0_55px_rgba(234,179,8,0.45)]',
    titleGradient: 'from-yellow-300 via-amber-200 to-yellow-500',
    quotes: [
      'Sreeeg... Gubrak! 🍌 Terpeleset sedikit, ayo segera berdiri!',
      'Jalannya licin banget! Hati-hati jebakan soal berikutnya!',
      'Sepatu melayang komik! Jangan menyerah, buktikan kemampuanmu!',
    ],
  },
  {
    id: 'lightning',
    tag: '⚡ KILAT PETIR',
    title: 'TERSENGAT KILAT PETIR! ⚡',
    borderClass: 'border-violet-500/70 shadow-[0_0_55px_rgba(139,92,246,0.45)]',
    titleGradient: 'from-violet-300 via-fuchsia-200 to-pink-400',
    quotes: [
      'Zzzztt! ⚡ Kena sengatan kejutan! Ayo kumpulkan energimu!',
      'Gosong dikit gak ngaruh, pahlawan pantang berputus asa! 🔥',
      'Awan mendung menyambar! Jadikan pemicu kobaran semangat baru!',
    ],
  },
  {
    id: 'balloon',
    tag: '🎈 POP BURST',
    title: 'DUARRR! BALON MELETUS 🎈',
    borderClass: 'border-pink-500/70 shadow-[0_0_55px_rgba(236,72,153,0.45)]',
    titleGradient: 'from-pink-300 via-rose-200 to-purple-400',
    quotes: [
      'Duaarr! 🎈 Meletus bagai balon hijau! Tarik napas & tetap tenang!',
      'Kaget dikit boleh, patah arang jangan! Balas di butir selanjutnya!',
      'Tertusuk jarum jebakan! Ayo lebih teliti membaca soal berikutnya!',
    ],
  },
];

const gameFeedback = ref({
  show: false,
  isCorrect: false,
  variantId: 'trophy',
  tag: '🏆 PIALA JUARA',
  title: '',
  message: '',
  borderClass: '',
  titleGradient: '',
  xpGained: 0,
});
let feedbackTimer = null;

function triggerGameFeedback(isCorrect) {
  if (!isGameMode.value) return;

  if (feedbackTimer) {
    clearTimeout(feedbackTimer);
  }

  // Pilih acak 1 dari 5 variasi animasi (0 sampai 4)
  const randomIdx = Math.floor(Math.random() * 5);

  if (isCorrect) {
    playCorrectChime();
    const variant = correctAnimationVariants[randomIdx];
    const quote = variant.quotes[Math.floor(Math.random() * variant.quotes.length)];
    gameFeedback.value = {
      show: true,
      isCorrect: true,
      variantId: variant.id,
      tag: variant.tag,
      title: variant.title,
      message: quote,
      borderClass: variant.borderClass,
      titleGradient: variant.titleGradient,
      xpGained: 100,
    };
  } else {
    playBoomSound();
    const variant = wrongAnimationVariants[randomIdx];
    const quote = variant.quotes[Math.floor(Math.random() * variant.quotes.length)];
    gameFeedback.value = {
      show: true,
      isCorrect: false,
      variantId: variant.id,
      tag: variant.tag,
      title: variant.title,
      message: quote,
      borderClass: variant.borderClass,
      titleGradient: variant.titleGradient,
      xpGained: 0,
    };
  }

  feedbackTimer = setTimeout(() => {
    gameFeedback.value.show = false;
  }, 1900);
}

function dismissGameFeedback() {
  if (feedbackTimer) {
    clearTimeout(feedbackTimer);
  }
  gameFeedback.value.show = false;
}

const isCelebratingConfetti = ref(false);
const hasCelebrated100 = ref(false);

function triggerCelebrationConfetti() {
  isCelebratingConfetti.value = true;
  setTimeout(() => {
    isCelebratingConfetti.value = false;
  }, 4000);
}

watch(totalAnswered, (newCount) => {
  if (
    currentQuestions.value.length > 0 &&
    newCount === currentQuestions.value.length &&
    !hasCelebrated100.value &&
    currentMode.value === 'exam_room'
  ) {
    hasCelebrated100.value = true;
    playVictorySound();
    triggerCelebrationConfetti();
  }
});

const questProgressPercentage = computed(() => {
  if (!currentQuestions.value.length) return 0;
  return Math.round((totalAnswered.value / currentQuestions.value.length) * 100);
});

const currentQuestStage = computed(() => {
  const pct = questProgressPercentage.value;
  if (pct >= 100) return { title: 'Zona Juara! Tuntas Sempurna', icon: '👑', color: 'from-amber-400 to-yellow-500' };
  if (pct >= 70) return { title: 'Tantangan Puncak', icon: '🔥', color: 'from-purple-500 to-pink-500' };
  if (pct >= 40) return { title: 'Eksplorasi Materi', icon: '⚡', color: 'from-cyan-400 to-teal-400' };
  return { title: 'Pemanasan Awal', icon: '🌱', color: 'from-emerald-400 to-teal-400' };
});

// -----------------------------------------------------------------------------
// INTERACTIVE QUESTION LOGIC (MATCH, REORDER, CATEGORIZE, PASSAGE)
// -----------------------------------------------------------------------------
const isPassageExpanded = ref(true);

// 1. MATCH (Menjodohkan) Logic: Tap-to-Pair
const selectedMatchLeftId = ref(null);

function selectMatchLeft(leftId) {
  if (isGameMode.value && getMatchedRightText(leftId)) {
    showToast('Pada Mode Game, pasangan kartu ini telah terkunci!', 'info');
    return;
  }
  playTapSound();
  if (selectedMatchLeftId.value === leftId) {
    selectedMatchLeftId.value = null;
  } else {
    selectedMatchLeftId.value = leftId;
  }
}

function selectMatchRight(rightText) {
  if (!selectedMatchLeftId.value) {
    showToast('Pilih salah satu kartu di kolom kiri terlebih dahulu!', 'info');
    return;
  }
  if (isGameMode.value && isRightTextPaired(rightText)) {
    showToast('Pilihan jawaban ini sudah terpasang dan terkunci!', 'info');
    return;
  }
  const qId = currentQuestion.value.id;
  const currentPairs = answers.value[qId] && typeof answers.value[qId] === 'object' && !Array.isArray(answers.value[qId])
    ? { ...answers.value[qId] }
    : {};

  // If this rightText was already assigned to another left, unassign it
  Object.keys(currentPairs).forEach((k) => {
    if (currentPairs[k] === rightText) {
      delete currentPairs[k];
    }
  });

  currentPairs[selectedMatchLeftId.value] = rightText;
  answers.value[qId] = currentPairs;

  if (isGameMode.value && currentQuestion.value?.dataInteraktif?.kunciJawaban) {
    const expected = currentQuestion.value.dataInteraktif.kunciJawaban[selectedMatchLeftId.value];
    const isCorrect = expected && String(expected).trim().toLowerCase() === String(rightText).trim().toLowerCase();
    triggerGameFeedback(isCorrect);
  } else {
    playPairMatchSound();
  }

  selectedMatchLeftId.value = null;
}

function unpairMatch(leftId) {
  if (isGameMode.value) {
    showToast('Pada Mode Game, pasangan kartu yang sudah dijodohkan terkunci!', 'info');
    return;
  }
  playTapSound();
  const qId = currentQuestion.value.id;
  if (answers.value[qId] && typeof answers.value[qId] === 'object') {
    const updated = { ...answers.value[qId] };
    delete updated[leftId];
    answers.value[qId] = updated;
  }
}

function getMatchedRightText(leftId) {
  const qId = currentQuestion.value?.id;
  if (!qId || !answers.value[qId] || typeof answers.value[qId] !== 'object') return null;
  return answers.value[qId][leftId] || null;
}

function isRightTextPaired(rightText) {
  const qId = currentQuestion.value?.id;
  if (!qId || !answers.value[qId] || typeof answers.value[qId] !== 'object') return false;
  return Object.values(answers.value[qId]).includes(rightText);
}

// 2. REORDER (Mengurutkan) Logic: Thumb Up/Down Buttons
function getReorderCurrentList() {
  const q = currentQuestion.value;
  if (!q) return [];
  const rawItems = q.dataInteraktif?.items || [];
  const currentOrderIds = answers.value[q.id];

  if (Array.isArray(currentOrderIds) && currentOrderIds.length === rawItems.length) {
    return currentOrderIds.map((id) => rawItems.find((it) => (it.id) === id) || { id, teks: '' }).filter(Boolean);
  }

  // Initialize with initial shuffled order
  const initIds = rawItems.map((i) => i.id);
  answers.value[q.id] = initIds;
  return rawItems;
}

function moveReorderItem(index, direction) {
  playTapSound();
  const q = currentQuestion.value;
  if (!q) return;
  const rawItems = q.dataInteraktif?.items || [];
  let currentOrderIds = answers.value[q.id];

  if (!Array.isArray(currentOrderIds) || currentOrderIds.length !== rawItems.length) {
    currentOrderIds = rawItems.map((i) => i.id);
  } else {
    currentOrderIds = [...currentOrderIds];
  }

  const targetIndex = index + direction;
  if (targetIndex < 0 || targetIndex >= currentOrderIds.length) return;

  const temp = currentOrderIds[index];
  currentOrderIds[index] = currentOrderIds[targetIndex];
  currentOrderIds[targetIndex] = temp;
  answers.value[q.id] = currentOrderIds;
}

// 3. CATEGORIZE (Mengelompokkan) Logic: Category Selection
function setItemCategory(itemId, categoryName) {
  const qId = currentQuestion.value.id;
  const currentCats = answers.value[qId] && typeof answers.value[qId] === 'object' && !Array.isArray(answers.value[qId])
    ? { ...answers.value[qId] }
    : {};

  if (isGameMode.value && currentCats[itemId]) {
    showToast('Pada Mode Game, kategori item yang sudah dipilih terkunci!', 'info');
    return;
  }
  playTapSound();

  if (currentCats[itemId] === categoryName) {
    delete currentCats[itemId];
  } else {
    currentCats[itemId] = categoryName;
    if (isGameMode.value && currentQuestion.value?.dataInteraktif?.kunciJawaban) {
      const expectedCat = currentQuestion.value.dataInteraktif.kunciJawaban[itemId];
      const isCorrect = expectedCat && String(expectedCat).trim().toLowerCase() === String(categoryName).trim().toLowerCase();
      triggerGameFeedback(isCorrect);
    }
  }
  answers.value[qId] = currentCats;
}

function getItemAssignedCategory(itemId) {
  const qId = currentQuestion.value?.id;
  if (!qId || !answers.value[qId] || typeof answers.value[qId] !== 'object') return null;
  return answers.value[qId][itemId] || null;
}

// -----------------------------------------------------------------------------
// NAVIGATION & WAITING ROOM FLOW
// -----------------------------------------------------------------------------

// Step 1: Click "Masuk Ruang Tunggu CBT"
function promptSelectIdentity(jadwal) {
  if (!jadwal.soals || jadwal.soals.length === 0) {
    showToast('Belum ada butir soal pada ujian ini.', 'warning');
    return;
  }

  selectedJadwal.value = jadwal;
  selectedSiswaId.value = '';
  studentSearchText.value = '';
  isDropdownOpen.value = false;
  inputToken.value = '';
  tokenError.value = '';

  try {
    const savedId = localStorage.getItem('siswacloud_active_siswa_id');
    if (savedId) {
      const match = props.siswaList.find((s) => String(s.id) === String(savedId));
      if (match) {
        selectStudent(match);
      }
    }
  } catch (e) {}

  showIdentityModal.value = true;
}

// Step 2: Confirm identity selection and enter Terminal Ruang Tunggu
async function confirmEnterWaitingRoom() {
  // If user typed without clicking, check if top match exists
  if (!selectedSiswaId.value && studentSearchText.value.trim()) {
    const match = filteredSiswaDropdown.value[0];
    if (match) {
      selectStudent(match);
    }
  }

  if (!selectedSiswaId.value) {
    showToast('Silakan pilih nama Anda terlebih dahulu!', 'warning');
    return;
  }

  const student = selectedSiswaObj.value;
  if (!student) {
    showToast('Identitas siswa tidak valid.', 'error');
    return;
  }

  activeStudent.value = student;
  showIdentityModal.value = false;

  // Immediate UI transition to waiting room (smooth, no blocker)
  currentMode.value = 'waiting_room';
  window.scrollTo({ top: 0, behavior: 'smooth' });
  showToast(`Halo ${student.nama}, Anda telah bergabung di Ruang Tunggu CBT!`, 'success');

  // Register in backend CbtSesi asynchronously
  try {
    await axios.post(route('siswa.cbt.gabung-ruang-tunggu'), {
      jadwal_id: selectedJadwal.value.id,
      siswa_id: student.id,
    });
  } catch (err) {
    console.error('Peringatan gabung ruang tunggu:', err);
  }
}

// Exit Waiting Room (returns to exam list)
async function exitWaitingRoom() {
  if (selectedJadwal.value && activeStudent.value) {
    try {
      await axios.post(route('siswa.cbt.keluar-ruang-tunggu'), {
        jadwal_id: selectedJadwal.value.id,
        siswa_id: activeStudent.value.id,
      });
    } catch (e) {
      console.error(e);
    }
  }
  currentMode.value = 'list';
  activeStudent.value = null;
  inputToken.value = '';
  tokenError.value = '';
  preparedQuestions.value = [];
}

// Step 3: Verify Token & Enter Fullscreen Exam Room
async function startExamFromWaitingRoom() {
  if (!inputToken.value.trim()) {
    tokenError.value = 'Silakan masukkan Token ujian yang diberikan oleh Guru pengawas.';
    return;
  }

  const expectedToken = (selectedJadwal.value.token || '').trim().toUpperCase();
  const enteredToken = inputToken.value.trim().toUpperCase();

  if (expectedToken && enteredToken !== expectedToken) {
    tokenError.value = 'Token tidak cocok! Periksa kembali huruf besar/kecil token dari Guru Anda.';
    return;
  }

  tokenError.value = '';

  // Request browser Fullscreen mode to prevent distraction / cheating
  try {
    if (document.documentElement.requestFullscreen) {
      await document.documentElement.requestFullscreen();
    } else if (document.documentElement.webkitRequestFullscreen) {
      await document.documentElement.webkitRequestFullscreen();
    }
  } catch (err) {
    console.log('Fullscreen request ignored or blocked:', err);
  }

  // Setup Exam State
  activeExam.value = selectedJadwal.value;
  currentQuestionIndex.value = 0;
  answers.value = {};
  raguRagu.value = {};
  violationCount.value = 0;
  isExamLocked.value = false;
  showViolationAlert.value = false;
  hasCelebrated100.value = false;
  examStartTime.value = new Date().toISOString();

  // Prepare & randomize questions (Soal Acak & Opsi Acak)
  let rawQuestions = (selectedJadwal.value.soals || []).map((q) => ({ ...q }));

  // 1. Soal Acak: If acakSoal is true (or default true), shuffle questions
  if (selectedJadwal.value.acakSoal !== false) {
    rawQuestions = shuffleArray(rawQuestions);
  }

  // 2. Opsi Acak: If acakOpsi is true (or default true), shuffle options per question
  const shouldShuffleOptions = selectedJadwal.value.acakOpsi !== false;
  const letters = ['A', 'B', 'C', 'D', 'E'];

  preparedQuestions.value = rawQuestions.map((q) => {
    let rawOptions = letters
      .filter((l) => (q['opsi' + l] && String(q['opsi' + l]).trim() !== '') || q['gambarOpsi' + l])
      .map((l) => ({
        originalKey: l,
        text: q['opsi' + l] || '',
        gambar: q['gambarOpsi' + l] || null,
      }));

    if (shouldShuffleOptions && rawOptions.length > 1) {
      rawOptions = shuffleArray(rawOptions);
    }

    const displayOptions = rawOptions.map((opt, idx) => ({
      displayKey: letters[idx] || opt.originalKey,
      originalKey: opt.originalKey,
      text: opt.text,
      gambar: opt.gambar,
    }));

    return {
      ...q,
      displayOptions,
    };
  });

  // Set timer based on duration
  const durasiMenit = activeExam.value.durasiMenit || 60;
  remainingSeconds.value = durasiMenit * 60;

  startTimer();
  startHeartbeat();
  isExamSubmitted.value = false;
  setupAntiCheatListeners();

  currentMode.value = 'exam_room';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// -----------------------------------------------------------------------------
// TIMER & HEARTBEAT CONTROLS
// -----------------------------------------------------------------------------
function startTimer() {
  stopTimer();
  timerInterval = setInterval(() => {
    if (remainingSeconds.value > 0) {
      remainingSeconds.value--;
    } else {
      stopTimer();
      showToast('Waktu ujian telah habis! Jawaban Anda otomatis dikumpulkan.', 'warning');
      submitExam(true);
    }
  }, 1000);
}

function stopTimer() {
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
  }
}

function startHeartbeat() {
  stopHeartbeat();
  // Immediate ping upon entering exam room to update status instantly for teacher monitor
  const sendPing = async () => {
    if (!activeExam.value || !activeStudent.value || currentMode.value !== 'exam_room') return;
    try {
      const res = await axios.post(route('siswa.cbt.heartbeat'), {
        jadwal_id: activeExam.value.id,
        siswa_id: activeStudent.value.id,
        soal_terjawab: totalAnswered.value,
        sisa_waktu_detik: remainingSeconds.value,
        status: isExamLocked.value ? 'terkunci' : 'mengerjakan',
      });
      if (res.data) {
        if (res.data.status === 'terkunci' || res.data.is_locked) {
          isExamLocked.value = true;
          showViolationAlert.value = false;
        } else if (res.data.status === 'mengerjakan' && isExamLocked.value) {
          // Guru telah membuka kunci ujian (Unlocked by Teacher)
          isExamLocked.value = false;
          violationCount.value = 0;
          showToast('Kunci ujian Anda telah dibuka oleh Guru pengawas. Silakan lanjutkan ujian!', 'success');
        }
      }
    } catch (e) {
      console.error('Heartbeat ping failed:', e);
    }
  };

  sendPing();
  heartbeatInterval = setInterval(sendPing, 10000);
}

function stopHeartbeat() {
  if (heartbeatInterval) {
    clearInterval(heartbeatInterval);
    heartbeatInterval = null;
  }
}

// -----------------------------------------------------------------------------
// ANTI-CHEAT, ACCIDENTAL REFRESH PREVENTION & TOUCH GUARD
// -----------------------------------------------------------------------------
let touchStartY = 0;

function handleTouchStart(e) {
  if (e.touches && e.touches.length === 1) {
    touchStartY = e.touches[0].clientY;
  }
}

function handleTouchMove(e) {
  if (currentMode.value === 'exam_room') {
    const touchY = e.touches[0].clientY;
    const touchDiff = touchY - touchStartY;
    // Jika posisi scroll di puncak atas dan user menarik ke bawah (pull-to-refresh ponsel)
    const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
    if (scrollTop <= 0 && touchDiff > 0) {
      if (e.cancelable) {
        e.preventDefault();
      }
    }
  }
}

function handleBeforeUnload(e) {
  if (currentMode.value === 'exam_room' && !isExamSubmitted.value) {
    e.preventDefault();
    e.returnValue = 'Ujian CBT sedang berlangsung. Jika Anda me-refresh atau meninggalkan halaman, sesi dan jawaban dapat terganggu.';
    return e.returnValue;
  }
}

function handleVisibilityChange() {
  if (currentMode.value === 'exam_room' && document.hidden) {
    recordViolation('Pindah Tab / Browser Diminimize (Meninggalkan Layar Ujian)');
  }
}

function handleWindowBlur() {
  if (currentMode.value === 'exam_room') {
    recordViolation('Beralih Jendela / Membuka Aplikasi Lain (Split Screen / AI)');
  }
}

function handleContextMenu(e) {
  if (currentMode.value === 'exam_room') {
    e.preventDefault();
  }
}

function handleCopyPaste(e) {
  if (currentMode.value === 'exam_room') {
    e.preventDefault();
    showToast('Tindakan Copy / Paste dinonaktifkan demi integritas ujian!', 'warning');
  }
}

function handleKeyDown(e) {
  if (currentMode.value === 'exam_room') {
    // Cegah F5 dan kombinasi refresh Ctrl+R / Cmd+R agar tidak keluar dari ujian tanpa sengaja
    if (e.key === 'F5' || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'r')) {
      e.preventDefault();
      showToast('Fitur refresh halaman dinonaktifkan selama ujian berlangsung demi keamanan sesi!', 'warning');
      return;
    }

    if (
      ((e.ctrlKey || e.metaKey) && ['c', 'v', 'u', 's', 'p', 'a'].includes(e.key.toLowerCase())) ||
      e.key === 'F12'
    ) {
      e.preventDefault();
      showToast('Kombinasi tombol pintas dilarang saat ujian berlangsung!', 'warning');
    }
  }
}

function setupAntiCheatListeners() {
  if (typeof window === 'undefined') return;

  // Lapis 1: Kunci overscroll di body/html agar gestur pull-to-refresh mobile mati
  document.documentElement.style.overscrollBehaviorY = 'contain';
  document.body.style.overscrollBehaviorY = 'contain';
  document.body.style.overscrollBehavior = 'contain';

  // Lapis 2: Guard event sentuhan dan beforeunload
  window.addEventListener('beforeunload', handleBeforeUnload);
  document.addEventListener('touchstart', handleTouchStart, { passive: true });
  document.addEventListener('touchmove', handleTouchMove, { passive: false });

  // Lapis 3: Guard anti-cheat jendela & shortcut
  document.addEventListener('visibilitychange', handleVisibilityChange);
  window.addEventListener('blur', handleWindowBlur);
  document.addEventListener('contextmenu', handleContextMenu);
  document.addEventListener('copy', handleCopyPaste);
  document.addEventListener('cut', handleCopyPaste);
  document.addEventListener('paste', handleCopyPaste);
  window.addEventListener('keydown', handleKeyDown);
}

function removeAntiCheatListeners() {
  if (typeof window === 'undefined') return;

  // Pulihkan overscroll behavior normal
  document.documentElement.style.overscrollBehaviorY = '';
  document.body.style.overscrollBehaviorY = '';
  document.body.style.overscrollBehavior = '';

  window.removeEventListener('beforeunload', handleBeforeUnload);
  document.removeEventListener('touchstart', handleTouchStart);
  document.removeEventListener('touchmove', handleTouchMove);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
  window.removeEventListener('blur', handleWindowBlur);
  document.removeEventListener('contextmenu', handleContextMenu);
  document.removeEventListener('copy', handleCopyPaste);
  document.removeEventListener('cut', handleCopyPaste);
  document.removeEventListener('paste', handleCopyPaste);
  window.removeEventListener('keydown', handleKeyDown);
}

async function recordViolation(reason) {
  if (isExamLocked.value) return;

  violationCount.value++;

  // Bila keluar dari sistem lebih dari 3 kali (> 3 kali), langsung diblokir / dikunci
  if (violationCount.value > 3) {
    isExamLocked.value = true;
    showViolationAlert.value = false;
  } else {
    showViolationAlert.value = true;
    violationAlertMessage.value = `Anda terdeteksi melakukan pelanggaran ke-${violationCount.value} dari batas toleransi 3 kali: "${reason}". Jika keluar dari sistem lebih dari 3 kali, ujian Anda akan otomatis DIBLOKIR dan dikunci oleh sistem!`;
  }

  try {
    const res = await axios.post(route('siswa.cbt.catat-pelanggaran'), {
      jadwal_id: activeExam.value.id,
      siswa_id: activeStudent.value.id,
      jenis: reason,
    });
    if (res.data && (res.data.is_locked || res.data.jumlah_pelanggaran > 3)) {
      isExamLocked.value = true;
      showViolationAlert.value = false;
    }
  } catch (err) {
    console.error('Gagal mencatat pelanggaran:', err);
  }
}

function closeViolationAlert() {
  showViolationAlert.value = false;
  try {
    if (document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen();
    }
  } catch (e) {}
}

onUnmounted(() => {
  stopTimer();
  stopHeartbeat();
  removeAntiCheatListeners();
});

// -----------------------------------------------------------------------------
// QUESTION NAVIGATION & SUBMISSION
// -----------------------------------------------------------------------------
function selectAnswer(optionKey) {
  if (!currentQuestion.value || isExamLocked.value) return;
  if (isGameMode.value && answers.value[currentQuestion.value.id]) {
    showToast('Pada Mode Game, jawaban telah terkunci dan tidak dapat diubah!', 'info');
    return;
  }
  playTapSound();
  answers.value[currentQuestion.value.id] = optionKey;

  // Evaluasi instan jika mode game
  if (isGameMode.value && currentQuestion.value.kunciJawaban) {
    const isCorrect = String(optionKey).toUpperCase() === String(currentQuestion.value.kunciJawaban).toUpperCase();
    triggerGameFeedback(isCorrect);
  }
}

function toggleRaguRagu() {
  if (!currentQuestion.value || isExamLocked.value) return;
  playTapSound();
  const qId = currentQuestion.value.id;
  raguRagu.value[qId] = !raguRagu.value[qId];
}

function nextQuestion() {
  if (currentQuestionIndex.value < currentQuestions.value.length - 1) {
    playWhooshSound();
    currentQuestionIndex.value++;
  }
}

function prevQuestion() {
  if (currentQuestionIndex.value > 0) {
    playWhooshSound();
    currentQuestionIndex.value--;
  }
}

function jumpToQuestion(index) {
  playWhooshSound();
  currentQuestionIndex.value = index;
  showMobilePalette.value = false;
}

function confirmSubmitExam() {
  const total = currentQuestions.value.length;
  const dijawab = totalAnswered.value;
  const belum = total - dijawab;
  const namaPeserta = activeStudent.value?.nama || 'Siswa';

  Swal.fire({
    title: 'Selesaikan Ujian CBT?',
    html: `
      <div class="text-left text-xs sm:text-sm text-gray-300 space-y-3 mt-2">
        <div class="p-3.5 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-300">
          <span class="block text-[10px] uppercase font-bold text-cyan-400">Identitas Peserta Ujian:</span>
          <span class="font-black text-white text-base">${namaPeserta}</span>
          <span class="text-xs text-gray-400 block font-mono">NIS: ${activeStudent.value?.nis || '-'} • Kelas: ${props.siswaObj?.kelas || '-'}</span>
        </div>
        <p>Apakah Anda yakin ingin mengumpulkan seluruh lembar jawaban ujian ini?</p>
        <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1 font-mono">
          <div class="flex justify-between text-emerald-400 font-bold">
            <span>Soal Terjawab:</span>
            <span>${dijawab} dari ${total}</span>
          </div>
          ${belum > 0 ? `
          <div class="flex justify-between text-amber-400 font-bold">
            <span>Belum Terjawab:</span>
            <span>${belum} Soal</span>
          </div>` : ''}
          ${violationCount.value > 0 ? `
          <div class="flex justify-between text-rose-400 font-bold">
            <span>Catatan Pelanggaran:</span>
            <span>${violationCount.value} Kali Terdeteksi</span>
          </div>` : ''}
        </div>
        <p class="text-[11px] text-gray-400">Nilai akan langsung dihitung otomatis dan tersimpan ke buku nilai Guru pengampu.</p>
      </div>
    `,
    icon: belum > 0 ? 'warning' : 'question',
    showCancelButton: true,
    confirmButtonColor: '#06b6d4',
    cancelButtonColor: '#475569',
    confirmButtonText: 'Ya, Kumpulkan Jawaban!',
    cancelButtonText: 'Periksa Kembali',
    background: '#0f172a',
    color: '#fff',
    customClass: {
      popup: 'rounded-3xl border border-white/10 shadow-2xl backdrop-blur-xl',
    },
  }).then((result) => {
    if (result.isConfirmed) {
      submitExam(false);
    }
  });
}

function submitExam(force = false) {
  isExamSubmitted.value = true;
  stopTimer();
  stopHeartbeat();
  removeAntiCheatListeners();

  if (document.fullscreenElement && document.exitFullscreen) {
    document.exitFullscreen().catch(() => {});
  }

  const achievedRank = liveRankingInfo.value.rank;
  const achievedTotal = liveRankingInfo.value.total;

  router.post(route('siswa.cbt.submit'), {
    jadwal_id: activeExam.value.id,
    siswa_id: activeStudent.value?.id,
    answers: answers.value,
    waktu_mulai: examStartTime.value,
  }, {
    preserveScroll: false,
    onSuccess: () => {
      const namaPeserta = activeStudent.value?.nama || 'Siswa';
      currentMode.value = 'list';
      activeTab.value = 'riwayat';
      activeExam.value = null;
      activeStudent.value = null;
      preparedQuestions.value = [];
      if (isGameMode.value) {
        showSuccessAlert(
          'Ujian Berhasil Dikumpulkan! 🏆',
          `Hasil ujian atas nama ${namaPeserta} telah dinilai. Kamu berhasil meraih Peringkat #${achievedRank} dari ${achievedTotal} peserta di kelasmu!`
        );
      } else {
        showSuccessAlert(
          'Ujian Berhasil Dikumpulkan! ✅',
          `Jawaban ujian atas nama ${namaPeserta} telah berhasil diserahkan ke sistem. Silakan periksa tab Riwayat Ujian untuk melihat detail penilaian Anda.`
        );
      }
    },
    onError: () => {
      isExamSubmitted.value = false;
      showToast('Terjadi kendala saat menyimpan jawaban ujian. Silakan coba kirim ulang.', 'error');
    },
  });
}
</script>

<template>
  <div class="space-y-6">

    <!-- ======================================================================= -->
    <!-- MODE 1: DAFTAR JADWAL UJIAN & RIWAYAT (LIST VIEW)                       -->
    <!-- ======================================================================= -->
    <div v-if="currentMode === 'list'" class="space-y-6">
      
      <!-- Hero Banner Card -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-cyan-950/80 via-slate-900 to-teal-950/70 border border-cyan-500/30 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-cyan-500/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-5 relative z-10">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs font-black tracking-wider uppercase mb-2.5">
              <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
              <span>PORTAL CBT KELAS {{ siswaObj?.kelas }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
              Sistem Ujian Berbasis Komputer
            </h1>
            <p class="text-xs sm:text-sm text-gray-300 mt-1.5 max-w-2xl leading-relaxed">
              Akun Kelas Bersama. Setiap siswa memilih nama masing-masing di <strong>Terminal Ruang Tunggu</strong> sebelum memasukkan token ujian untuk mencatat nilai secara akurat di buku nilai guru.
            </p>
          </div>

          <!-- Quick Stats Cyber Pill -->
          <div class="flex items-center gap-3 bg-white/5 border border-white/10 p-3.5 rounded-2xl shrink-0 w-full sm:w-auto justify-around sm:justify-start">
            <div class="text-center px-4 border-r border-white/10">
              <span class="block text-2xl font-black text-cyan-300">{{ cbtJadwalList.length }}</span>
              <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Jadwal Aktif</span>
            </div>
            <div class="text-center px-4">
              <span class="block text-2xl font-black text-emerald-300">{{ cbtNilaiList.length }}</span>
              <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Telah Dikerjakan</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs & Search Controls -->
      <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/80 border border-white/10 backdrop-blur-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg">
        
        <!-- Tab Toggle -->
        <div class="flex items-center p-1 rounded-xl bg-slate-800/80 border border-white/10 self-start sm:self-auto w-full sm:w-auto">
          <button
            @click="activeTab = 'aktif'"
            :class="[
              'flex-1 sm:flex-initial flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-xs sm:text-sm font-bold transition-all min-h-[40px]',
              activeTab === 'aktif'
                ? 'bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 shadow-md font-black'
                : 'text-gray-400 hover:text-white'
            ]"
          >
            <span>📝 Ujian CBT Aktif</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'aktif' ? 'bg-slate-950/25 text-slate-950 font-black' : 'bg-white/10 text-cyan-300'">
              {{ cbtJadwalList.length }}
            </span>
          </button>
          <button
            @click="activeTab = 'riwayat'"
            :class="[
              'flex-1 sm:flex-initial flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-xs sm:text-sm font-bold transition-all min-h-[40px]',
              activeTab === 'riwayat'
                ? 'bg-gradient-to-r from-cyan-500 to-teal-500 text-slate-950 shadow-md font-black'
                : 'text-gray-400 hover:text-white'
            ]"
          >
            <span>📊 Riwayat &amp; Nilai</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'riwayat' ? 'bg-slate-950/25 text-slate-950 font-black' : 'bg-white/10 text-emerald-300'">
              {{ cbtNilaiList.length }}
            </span>
          </button>
        </div>

        <!-- Search and Filter Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full sm:w-auto">
          <!-- Filter Siswa (Only on Riwayat tab) -->
          <div v-if="activeTab === 'riwayat'" class="w-full sm:w-56">
            <select
              v-model="filterSiswaHistory"
              class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs focus:outline-none focus:border-cyan-400 min-h-[40px]"
            >
              <option value="" class="bg-slate-900 text-white">Semua Siswa Kelas</option>
              <option v-for="s in siswaList" :key="'opt-' + s.id" :value="s.id" class="bg-slate-900 text-white">
                {{ s.nis }} - {{ s.nama }}
              </option>
            </select>
          </div>

          <!-- Search Input -->
          <div class="relative w-full sm:w-64">
            <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari siswa, mapel, ujian..."
              class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-cyan-400 min-h-[40px]"
            />
          </div>
        </div>
      </div>

      <!-- TAB 1: DAFTAR UJIAN AKTIF -->
      <div v-if="activeTab === 'aktif'">
        
        <!-- Empty State -->
        <div v-if="filteredJadwals.length === 0" class="text-center py-16 px-4 rounded-3xl bg-slate-900/60 border border-white/10">
          <div class="w-16 h-16 mx-auto rounded-2xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-3xl mb-3">
            📅
          </div>
          <h3 class="text-base font-bold text-white">Tidak Ada Jadwal Ujian Aktif</h3>
          <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
            Saat ini belum ada sesi ujian CBT yang dibuka untuk kelas Anda. Harap tunggu jadwal dari Guru pengampu.
          </p>
        </div>

        <!-- Cards Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="j in filteredJadwals"
            :key="j.id"
            class="rounded-3xl bg-slate-900/85 border border-white/10 hover:border-cyan-500/40 p-5 sm:p-6 space-y-4 shadow-xl backdrop-blur-xl transition-all hover:-translate-y-1 flex flex-col justify-between group"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between gap-2">
                <span class="px-3 py-1 rounded-xl bg-teal-500/15 border border-teal-500/30 text-teal-300 font-black text-[11px] truncate max-w-[170px]">
                  📖 {{ j.mapelNama }}
                </span>
                <span class="px-2.5 py-1 rounded-full bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 font-extrabold text-[11px] flex items-center gap-1.5 shrink-0">
                  <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                  <span>{{ j.totalSelesai || 0 }} Selesai</span>
                </span>
              </div>

              <div>
                <h3 class="text-base font-black text-white line-clamp-2 group-hover:text-cyan-300 transition-colors">{{ j.judulUjian }}</h3>
                <p class="text-xs text-gray-400 mt-1 flex items-center gap-1.5">
                  <span>👨‍🏫</span>
                  <span>{{ j.guruNama }}</span>
                </p>
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                  <span
                    v-if="j.acakSoal"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-300 font-bold text-[10px]"
                  >
                    🔀 Soal Acak
                  </span>
                  <span
                    v-if="j.acakOpsi"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-500/15 border border-purple-500/30 text-purple-300 font-bold text-[10px]"
                  >
                    🔀 Opsi Acak
                  </span>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-300 bg-white/5 p-3 rounded-2xl border border-white/5 font-mono">
                <div>
                  <span class="text-gray-400 block text-[10px] uppercase font-sans">Durasi Ujian:</span>
                  <span class="font-bold text-white">⏱️ {{ j.durasiMenit }} Menit</span>
                </div>
                <div>
                  <span class="text-gray-400 block text-[10px] uppercase font-sans">Jumlah Soal:</span>
                  <span class="font-bold text-cyan-300">📝 {{ j.jumlahSoal }} Butir</span>
                </div>
              </div>
            </div>

            <!-- Action Button: Open Waiting Room Entry -->
            <div class="pt-2">
              <button
                @click="promptSelectIdentity(j)"
                class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-cyan-500/25 active:scale-95 transition-all min-h-[44px]"
              >
                <span>🔑 Masuk Ruang Tunggu CBT</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- TAB 2: RIWAYAT & NILAI HASIL UJIAN -->
      <div v-else-if="activeTab === 'riwayat'">
        
        <!-- Empty State -->
        <div v-if="filteredNilais.length === 0" class="text-center py-16 px-4 rounded-3xl bg-slate-900/60 border border-white/10">
          <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-3xl mb-3">
            📊
          </div>
          <h3 class="text-base font-bold text-white">Belum Ada Riwayat Ujian</h3>
          <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
            Belum ada siswa di kelas ini yang menyelesaikan ujian CBT. Selesaikan ujian untuk melihat hasil penilaian otomatis di sini.
          </p>
        </div>

        <!-- Desktop View Table (hidden md:block) -->
        <div v-if="sortedFilteredNilais.length > 0" class="hidden md:block overflow-hidden rounded-2xl bg-slate-900/80 border border-white/10 shadow-xl backdrop-blur-xl">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-white/10 bg-white/5 text-[11px] font-black text-gray-400 uppercase tracking-wider font-mono">
                <th class="py-4 px-4 text-center">🏆 Peringkat Kelas</th>
                <th class="py-4 px-5">Nama Peserta (Siswa)</th>
                <th class="py-4 px-4">Sesi Ujian</th>
                <th class="py-4 px-4">Mata Pelajaran</th>
                <th class="py-4 px-4 text-center">Jawaban Benar / Salah</th>
                <th class="py-4 px-4 text-center">Skor Nilai</th>
                <th class="py-4 px-5 text-right">Waktu Selesai</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-xs">
              <tr v-for="n in sortedFilteredNilais" :key="n.id" class="hover:bg-white/[0.03] transition-colors">
                <td class="py-4 px-4 text-center">
                  <span v-if="getRank(n.id) === 1" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-gradient-to-r from-amber-500/25 to-yellow-400/25 border border-amber-400/50 text-amber-300 font-black text-xs shadow-md shadow-amber-500/10">
                    <span>🥇</span>
                    <span>#1 Top</span>
                  </span>
                  <span v-else-if="getRank(n.id) === 2" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-400/20 border border-slate-300/40 text-slate-200 font-black text-xs shadow-sm">
                    <span>🥈</span>
                    <span>#2</span>
                  </span>
                  <span v-else-if="getRank(n.id) === 3" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-amber-700/25 border border-amber-600/40 text-amber-300 font-black text-xs shadow-sm">
                    <span>🥉</span>
                    <span>#3</span>
                  </span>
                  <span v-else class="inline-flex items-center justify-center px-2 py-0.5 rounded-lg bg-white/5 border border-white/10 text-gray-300 font-mono font-bold text-xs">
                    #{{ getRank(n.id) }} <span class="text-[10px] text-gray-500 ml-0.5">/{{ getTotalInJadwal(n.id) }}</span>
                  </span>
                </td>
                <td class="py-4 px-5 font-bold text-white">
                  <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-300 flex items-center justify-center text-xs font-bold shrink-0 border border-cyan-500/20">
                      👤
                    </div>
                    <div>
                      <div class="font-black text-white text-xs">{{ n.namaSiswa }}</div>
                      <div class="text-[10px] text-cyan-400 font-mono">NIS: {{ n.nis }}</div>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4 font-bold text-gray-200">
                  {{ n.judulUjian }}
                </td>
                <td class="py-4 px-4">
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-teal-500/10 text-teal-300 border border-teal-500/20 font-semibold">
                    📖 {{ n.mapelNama }}
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span class="inline-flex items-center gap-2 font-mono font-bold">
                    <span class="text-emerald-400">{{ n.jumlahBenar }} Benar</span>
                    <span class="text-gray-500">/</span>
                    <span class="text-rose-400">{{ n.jumlahSalah }} Salah</span>
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span
                    :class="[
                      'inline-flex items-center gap-1 px-3 py-1 rounded-full font-black text-xs border',
                      n.nilai >= 75
                        ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300'
                        : 'bg-rose-500/15 border-rose-500/30 text-rose-300'
                    ]"
                  >
                    <span>{{ n.nilai }}</span>
                    <span class="text-[10px]">({{ n.status }})</span>
                  </span>
                </td>
                <td class="py-4 px-5 text-right text-gray-400 font-mono text-[11px]">
                  {{ n.waktuSelesai }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile View Card List (md:hidden) -->
        <div v-if="sortedFilteredNilais.length > 0" class="md:hidden space-y-4">
          <div
            v-for="n in sortedFilteredNilais"
            :key="n.id"
            class="p-4 rounded-2xl bg-slate-900/90 border border-white/10 space-y-3 shadow-lg"
          >
            <div class="flex items-start justify-between gap-2 border-b border-white/5 pb-2.5">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-300 flex items-center justify-center text-xs font-bold shrink-0 border border-cyan-500/20">
                  👤
                </div>
                <div>
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span v-if="getRank(n.id) === 1" class="px-2 py-0.5 rounded-md bg-amber-400/25 border border-amber-400/50 text-amber-300 text-[10px] font-black">
                      🥇 #1 Top
                    </span>
                    <span v-else-if="getRank(n.id) === 2" class="px-2 py-0.5 rounded-md bg-slate-400/25 border border-slate-300/40 text-slate-200 text-[10px] font-black">
                      🥈 #2
                    </span>
                    <span v-else-if="getRank(n.id) === 3" class="px-2 py-0.5 rounded-md bg-amber-700/30 border border-amber-600/40 text-amber-300 text-[10px] font-black">
                      🥉 #3
                    </span>
                    <span v-else class="px-1.5 py-0.5 rounded bg-white/10 border border-white/10 text-gray-300 font-mono text-[10px] font-bold">
                      #{{ getRank(n.id) }}/{{ getTotalInJadwal(n.id) }}
                    </span>
                    <h4 class="font-black text-white text-sm">{{ n.namaSiswa }}</h4>
                  </div>
                  <span class="text-[10px] text-cyan-400 font-mono">NIS: {{ n.nis }}</span>
                </div>
              </div>
              <span
                :class="[
                  'px-3 py-1 rounded-full font-black text-xs border shrink-0',
                  n.nilai >= 75
                    ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300'
                    : 'bg-rose-500/20 border-rose-500/40 text-rose-300'
                ]"
              >
                {{ n.nilai }}
              </span>
            </div>

            <div class="space-y-1">
              <p class="text-xs font-bold text-white">{{ n.judulUjian }}</p>
              <p class="text-[11px] text-teal-300 font-medium">📖 {{ n.mapelNama }}</p>
            </div>

            <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-300 bg-white/5 p-2.5 rounded-xl font-mono">
              <div>
                <span class="text-gray-400 block text-[10px] uppercase font-sans">Hasil / Status:</span>
                <span :class="n.nilai >= 75 ? 'text-emerald-300 font-bold' : 'text-rose-300 font-bold'">
                  {{ n.status }} ({{ n.jumlahBenar }}B / {{ n.jumlahSalah }}S)
                </span>
              </div>
              <div>
                <span class="text-gray-400 block text-[10px] uppercase font-sans">Waktu Selesai:</span>
                <span class="text-gray-300 text-[10px]">{{ n.waktuSelesai }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>

    <!-- ======================================================================= -->
    <!-- MODE 2: TERMINAL RUANG TUNGGU CBT (WAITING ROOM TERMINAL)                -->
    <!-- ======================================================================= -->
    <div v-else-if="currentMode === 'waiting_room' && selectedJadwal && activeStudent" class="space-y-6">
      
      <!-- Top Status Banner -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 sm:p-5 rounded-3xl bg-slate-900/90 border border-cyan-500/40 shadow-2xl backdrop-blur-2xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 flex items-center justify-center text-xl shrink-0 animate-pulse">
            📡
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
              <span class="text-xs font-black uppercase tracking-wider text-emerald-400 font-mono">STATUS: STANDBY DI RUANG TUNGGU</span>
            </div>
            <h2 class="text-base sm:text-lg font-black text-white">Terminal Persiapan Ujian CBT</h2>
          </div>
        </div>

        <button
          @click="exitWaitingRoom"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 hover:bg-rose-500/20 text-gray-300 hover:text-rose-300 border border-white/10 text-xs font-bold transition-all min-h-[40px]"
        >
          <span>🚪 Keluar Ruang Tunggu / Ganti Siswa</span>
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column: Student Cyber Identity & Token Entry Console (Cols 1-7) -->
        <div class="lg:col-span-7 space-y-6">
          
          <!-- Cyber Student ID Card -->
          <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-cyan-950/40 to-slate-900 border border-cyan-500/40 p-6 sm:p-7 shadow-2xl backdrop-blur-xl space-y-5">
            <div class="absolute -bottom-16 -right-16 w-56 h-56 bg-cyan-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="flex items-center justify-between border-b border-white/10 pb-4">
              <span class="text-[10px] font-mono uppercase tracking-widest text-cyan-400 font-black">
                KARTU PESERTA CBT ONLINE
              </span>
              <span class="px-2.5 py-0.5 rounded-full bg-cyan-500/15 border border-cyan-500/30 text-cyan-300 font-mono text-[10px] font-bold">
                KELAS: {{ props.siswaObj?.kelas || '-' }}
              </span>
            </div>

            <div class="flex items-start gap-4 sm:gap-5">
              <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-cyan-500 to-teal-500 text-slate-950 font-black text-2xl sm:text-3xl flex items-center justify-center shrink-0 shadow-lg shadow-cyan-500/25">
                {{ activeStudent.nama.charAt(0).toUpperCase() }}
              </div>
              <div class="space-y-1 flex-1">
                <span class="text-[10px] text-gray-400 font-mono uppercase tracking-wider block">Nama Lengkap Peserta:</span>
                <h3 class="text-lg sm:text-xl font-black text-white leading-tight">{{ activeStudent.nama }}</h3>
                <div class="flex flex-wrap items-center gap-2 pt-1 font-mono text-xs text-gray-300">
                  <span class="px-2 py-0.5 rounded-lg bg-white/5 border border-white/10 text-cyan-300 font-bold">NIS: {{ activeStudent.nis }}</span>
                  <span class="px-2 py-0.5 rounded-lg bg-white/5 border border-white/10 text-teal-300 font-bold">ID Siswa: #{{ activeStudent.id }}</span>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-3 text-[11px] font-mono">
              <div class="p-3 rounded-2xl bg-white/5 border border-white/5">
                <span class="text-gray-400 block text-[10px] uppercase font-sans">Mata Pelajaran:</span>
                <span class="font-bold text-white truncate block">{{ selectedJadwal.mapelNama }}</span>
              </div>
              <div class="p-3 rounded-2xl bg-white/5 border border-white/5">
                <span class="text-gray-400 block text-[10px] uppercase font-sans">Durasi:</span>
                <span class="font-bold text-cyan-300">⏱️ {{ selectedJadwal.durasiMenit }} Menit</span>
              </div>
              <div class="p-3 rounded-2xl bg-white/5 border border-white/5 col-span-2 sm:col-span-1">
                <span class="text-gray-400 block text-[10px] uppercase font-sans">Jumlah Soal:</span>
                <span class="font-bold text-emerald-300">📝 {{ selectedJadwal.jumlahSoal }} Butir</span>
              </div>
            </div>
          </div>

          <!-- Token Entry Card -->
          <div class="rounded-3xl bg-slate-900/90 border border-white/10 p-6 sm:p-7 space-y-4 shadow-2xl backdrop-blur-xl">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-lg">🔑</span>
                <h4 class="text-base font-black text-white">Masukkan Token Akses Ujian</h4>
              </div>
              <p class="text-xs text-gray-400">
                Token akan diumumkan secara langsung oleh Guru pengawas saat seluruh siswa telah siap di terminal ini.
              </p>
            </div>

            <!-- Notice if student already took this test -->
            <div
              v-if="existingResultForSelectedSiswa"
              class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs space-y-1 font-mono"
            >
              <div class="flex items-center gap-1.5 font-bold">
                <span>⚠️</span>
                <span>Perhatian: Siswa Sudah Pernah Mengerjakan!</span>
              </div>
              <p class="text-[11px] text-gray-300 font-sans">
                <strong>{{ activeStudent.nama }}</strong> sebelumnya sudah pernah menyelesaikan ujian ini dengan skor <strong>{{ existingResultForSelectedSiswa.nilai }}</strong> (Status: {{ existingResultForSelectedSiswa.status }}). Memulai pengerjaan ulang akan memperbarui skor terbaru.
              </p>
            </div>

            <div>
              <input
                v-model="inputToken"
                @keyup.enter="startExamFromWaitingRoom"
                type="text"
                placeholder="TOKEN DARI GURU (Contoh: 2COIGB)"
                class="w-full px-5 py-4 rounded-2xl bg-slate-950 border border-cyan-500/40 text-cyan-300 font-mono font-black text-center text-xl sm:text-2xl uppercase tracking-widest placeholder:text-gray-600 focus:outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 min-h-[56px]"
              />
              <p v-if="tokenError" class="text-xs text-rose-400 mt-2 flex items-center gap-1 font-medium bg-rose-500/10 p-2.5 rounded-xl border border-rose-500/20">
                <span>⚠️</span>
                <span>{{ tokenError }}</span>
              </p>
            </div>

            <button
              @click="startExamFromWaitingRoom"
              class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-slate-950 font-black text-sm sm:text-base shadow-xl shadow-cyan-500/25 active:scale-95 transition-all flex items-center justify-center gap-2.5 min-h-[50px]"
            >
              <span>🚀 Masuk Ruang Ujian &amp; Mulai Mengerjakan</span>
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </button>
            <p class="text-[10px] text-gray-500 text-center">
              * Mode layar penuh (Fullscreen) akan otomatis aktif saat ujian dimulai demi mencegah kecurangan.
            </p>
          </div>

        </div>

        <!-- Right Column: Anti-Cheat Rules & Classmate Activity (Cols 8-12) -->
        <div class="lg:col-span-5 space-y-6">
          
          <!-- Anti-Cheating Protocol Box -->
          <div class="rounded-3xl bg-slate-900/90 border border-rose-500/30 p-6 space-y-4 shadow-xl backdrop-blur-xl">
            <div class="flex items-center gap-2.5 text-rose-400">
              <span class="text-xl">🛡️</span>
              <h4 class="text-sm font-black uppercase tracking-wider text-white">Protokol Keamanan &amp; Anti-Cheat</h4>
            </div>
            
            <div class="space-y-2.5 text-xs text-gray-300">
              <div class="flex items-start gap-2.5 p-2.5 rounded-xl bg-white/5 border border-white/5">
                <span class="text-rose-400 shrink-0 font-bold">🚫</span>
                <span><strong>Dilarang Keluar Tab:</strong> Membuka tab baru, browser lain, atau aplikasi AI (ChatGPT/Copilot) akan langsung memicu alarm pelanggaran.</span>
              </div>
              <div class="flex items-start gap-2.5 p-2.5 rounded-xl bg-white/5 border border-white/5">
                <span class="text-amber-400 shrink-0 font-bold">🔒</span>
                <span><strong>Layar Terkunci (Lock Screen):</strong> Beralih jendela atau meminimize layar ujian akan memunculkan peringatan darurat dan mengunci pengerjaan.</span>
              </div>
              <div class="flex items-start gap-2.5 p-2.5 rounded-xl bg-white/5 border border-white/5">
                <span class="text-cyan-400 shrink-0 font-bold">📡</span>
                <span><strong>Pemantauan Real-Time:</strong> Guru pengawas dapat melihat langsung di layarnya berapa kali Anda berpindah jendela dan dapat mengunci ujian Anda.</span>
              </div>
            </div>
          </div>

          <!-- Classmates in the same Waiting Room -->
          <div class="rounded-3xl bg-slate-900/90 border border-white/10 p-6 space-y-4 shadow-xl backdrop-blur-xl">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
              <h4 class="text-sm font-black text-white flex items-center gap-2">
                <span>👥</span>
                <span>Teman Sekelas di Ruang Ini</span>
              </h4>
              <span class="text-xs font-mono text-cyan-300 font-bold">
                {{ classmatesInSession.length }} Terhubung
              </span>
            </div>

            <div v-if="classmatesInSession.length === 0" class="text-center py-6 text-xs text-gray-400">
              Belum ada teman sekelas lain yang bergabung di sesi ini.
            </div>

            <div v-else class="space-y-2 max-h-64 overflow-y-auto pr-1">
              <div
                v-for="c in classmatesInSession"
                :key="c.id"
                class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/5 text-xs"
              >
                <div class="flex items-center gap-2">
                  <div class="w-6 h-6 rounded-lg bg-cyan-500/20 text-cyan-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                    👤
                  </div>
                  <span class="font-bold text-gray-200 truncate max-w-[160px]">{{ c.namaSiswa }}</span>
                </div>
                <span
                  :class="[
                    'px-2 py-0.5 rounded-full text-[10px] font-mono font-bold',
                    c.status === 'mengerjakan' ? 'bg-amber-500/20 text-amber-300' :
                    c.status === 'selesai' ? 'bg-emerald-500/20 text-emerald-300' :
                    c.status === 'terkunci' ? 'bg-rose-500/20 text-rose-300' :
                    'bg-cyan-500/20 text-cyan-300'
                  ]"
                >
                  {{ c.status === 'mengerjakan' ? '✍️ Ujian' : c.status === 'selesai' ? '✅ Selesai' : c.status === 'terkunci' ? '🔒 Dikunci' : '⏳ Standby' }}
                </span>
              </div>
            </div>
          </div>

        </div>

      </div>

    </div>

    <!-- ======================================================================= -->
    <!-- MODE 3: RUANG UJIAN CBT LIVE (EXAM ROOM)                                 -->
    <!-- ======================================================================= -->
    <div v-else-if="currentMode === 'exam_room' && activeExam" class="space-y-6 select-none exam-room-container">
      
      <!-- Top Exam Sticky Header -->
      <div class="sticky top-16 z-30 p-4 sm:p-5 rounded-3xl bg-slate-900/95 border border-cyan-500/40 shadow-2xl backdrop-blur-2xl space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <!-- Active Student & Exam Badge -->
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-cyan-500/20 to-blue-500/20 border border-cyan-500/40 text-cyan-300 flex items-center justify-center text-lg font-black shrink-0 shadow-inner">
              👤
            </div>
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-black text-white">{{ activeStudent?.nama }}</span>
                <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-cyan-500/15 text-cyan-300 border border-cyan-500/30">NIS: {{ activeStudent?.nis }}</span>
                
                <!-- Badge Mode Ujian -->
                <span
                  :class="[
                    'text-[10px] font-mono px-2.5 py-0.5 rounded-full font-black flex items-center gap-1 border shadow-sm',
                    isGameMode
                      ? 'bg-gradient-to-r from-amber-500/25 to-pink-500/25 text-amber-300 border-amber-500/40 shadow-amber-500/20 animate-pulse'
                      : 'bg-white/10 text-gray-300 border-white/20'
                  ]"
                >
                  <span>{{ isGameMode ? '🎮 MODE GAME' : '🏛️ CBT FORMAL' }}</span>
                </span>

                <span v-if="totalAnswered > 0" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 font-black animate-pulse flex items-center gap-1">
                  <span>🔥</span>
                  <span>{{ totalAnswered }} Soal Terjawab</span>
                </span>
                <span v-if="violationCount > 0" class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse font-bold">
                  ⚠️ {{ violationCount }} Pelanggaran
                </span>

                <!-- Live Ranking Status Badge -->
                <span
                  class="text-[10px] font-mono px-2.5 py-0.5 rounded-full font-black flex items-center gap-1.5 border shadow-sm bg-amber-500/15 text-amber-300 border-amber-400/30 select-none"
                  title="Posisi klasemen sementara kamu di kelas"
                >
                  <span v-if="liveRankingInfo.rank === 1">🥇</span>
                  <span v-else-if="liveRankingInfo.rank === 2">🥈</span>
                  <span v-else-if="liveRankingInfo.rank === 3">🥉</span>
                  <span v-else>🏆</span>
                  <span>Posisi #{{ liveRankingInfo.rank }}/{{ liveRankingInfo.total }}</span>
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                </span>
              </div>
              <div class="flex items-center gap-1.5 text-xs text-gray-400 mt-0.5 font-mono">
                <span class="text-teal-300 font-bold">{{ activeExam.mapelNama }}</span>
                <span>•</span>
                <span>Soal {{ currentQuestionIndex + 1 }} / {{ currentQuestions.length }}</span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 sm:gap-2.5 shrink-0 flex-wrap justify-end">
            <!-- Sound FX Toggle Button -->
            <button
              type="button"
              @click="sfxEnabled = !sfxEnabled"
              :class="[
                'px-3 py-2 rounded-2xl border text-xs font-bold font-mono flex items-center gap-1.5 transition-all min-h-[42px]',
                sfxEnabled
                  ? 'bg-cyan-500/15 border-cyan-500/30 text-cyan-300'
                  : 'bg-white/5 border-white/10 text-gray-400'
              ]"
              :title="sfxEnabled ? 'Matikan Efek Suara (Mute)' : 'Nyalakan Efek Suara (SFX)'"
            >
              <span>{{ sfxEnabled ? '🔊 SFX' : '🔇 Mute' }}</span>
            </button>

            <!-- Mobile Toggle Palette Button -->
            <button
              @click="showMobilePalette = !showMobilePalette"
              class="lg:hidden px-3 py-2 rounded-2xl bg-white/5 border border-white/10 text-cyan-300 font-mono font-bold text-xs flex items-center gap-1.5 min-h-[42px]"
            >
              <span>📑 Palet ({{ totalAnswered }}/{{ currentQuestions.length }})</span>
            </button>

            <!-- Live Countdown Timer -->
            <div
              :class="[
                'px-3.5 sm:px-4 py-2 rounded-2xl border font-mono font-black text-sm sm:text-base flex items-center gap-2 shadow-lg transition-colors min-h-[42px]',
                remainingSeconds < 300
                  ? 'bg-rose-500/20 border-rose-500 text-rose-300 animate-pulse'
                  : 'bg-cyan-500/20 border-cyan-500/40 text-cyan-300'
              ]"
            >
              <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>{{ formattedTime }}</span>
            </div>

            <!-- Selesaikan Ujian Button -->
            <button
              @click="confirmSubmitExam"
              class="px-4 sm:px-5 py-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-emerald-500/20 active:scale-95 transition-all min-h-[42px]"
            >
              ✓ Selesai
            </button>
          </div>
        </div>

        <!-- Motivation Banner & Acuan Rangking Real-Time -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3.5 py-2 rounded-2xl bg-gradient-to-r from-slate-950/80 via-amber-950/30 to-slate-950/80 border border-amber-500/30 text-xs">
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="text-base shrink-0">🎯</span>
            <p class="font-bold text-amber-200 truncate text-[11px] sm:text-xs">
              {{ liveRankingInfo.motivationText }}
            </p>
          </div>
          <button
            type="button"
            @click="showLiveLeaderboardModal = true"
            class="self-start sm:self-auto shrink-0 px-2.5 py-1 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 font-mono text-[10px] font-black transition-colors flex items-center gap-1 cursor-pointer"
          >
            <span>🏆 Intip Klasemen</span>
            <span class="text-xs">»</span>
          </button>
        </div>

        <!-- Quest / Level Progress Gaming Bar -->
        <div class="pt-2 border-t border-white/10">
          <div class="flex items-center justify-between text-[11px] font-mono pb-1.5">
            <span class="text-gray-300 flex items-center gap-1.5 font-bold">
              <span>{{ currentQuestStage.icon }}</span>
              <span>Tahap: {{ currentQuestStage.title }}</span>
            </span>
            <span class="text-cyan-300 font-bold">
              {{ questProgressPercentage }}% ({{ totalAnswered }}/{{ currentQuestions.length }} Butir)
            </span>
          </div>
          <div class="w-full h-2 rounded-full bg-white/10 overflow-hidden relative">
            <div
              :class="['h-full rounded-full transition-all duration-500 bg-gradient-to-r', currentQuestStage.color]"
              :style="{ width: questProgressPercentage + '%' }"
            ></div>
          </div>
        </div>
      </div>

      <!-- Main Question & Palette Layout -->
      <div v-if="currentQuestion" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Question Work Area (Cols 1-8) -->
        <div class="lg:col-span-8 space-y-5">
          <div class="p-6 sm:p-8 rounded-3xl bg-slate-900/85 border border-white/10 shadow-2xl backdrop-blur-xl space-y-6">
            
            <!-- Question Meta Header -->
            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-4">
              <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-cyan-500 text-slate-950 font-black flex items-center justify-center text-sm shadow-lg shadow-cyan-500/30">
                  {{ currentQuestionIndex + 1 }}
                </span>
                <span class="text-xs font-bold text-gray-300 font-mono">
                  Poin Bobot: {{ currentQuestion.bobot || 1 }}
                </span>
              </div>

              <!-- Ragu-ragu Checkbox / Button -->
              <button
                @click="toggleRaguRagu"
                :class="[
                  'inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all border min-h-[38px]',
                  raguRagu[currentQuestion.id]
                    ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 font-black'
                    : 'bg-white/5 text-gray-400 border-white/10 hover:text-white'
                ]"
              >
                <span>⚠️</span>
                <span>{{ raguRagu[currentQuestion.id] ? 'Ditandai Ragu-ragu' : 'Tandai Ragu-ragu' }}</span>
              </button>
            </div>

            <!-- PASSAGE / WACANA STIMULUS ACCORDION (Jika soal memiliki stimulus teks wacana) -->
            <div v-if="currentQuestion.stimulusTeks" class="rounded-2xl border border-cyan-500/30 bg-cyan-950/20 overflow-hidden transition-all">
              <button
                type="button"
                @click="isPassageExpanded = !isPassageExpanded"
                class="w-full px-4 py-3 bg-cyan-500/10 hover:bg-cyan-500/15 flex items-center justify-between text-left transition-colors cursor-pointer select-none min-h-[44px]"
              >
                <div class="flex items-center gap-2.5">
                  <span class="text-base">📜</span>
                  <span class="text-xs sm:text-sm font-black text-cyan-300">Wacana / Teks Bacaan Terpadu</span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/20 text-cyan-400 font-mono font-bold">Stimulus</span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-cyan-400 font-medium">
                  <span>{{ isPassageExpanded ? 'Sembunyikan' : 'Baca Wacana' }}</span>
                  <svg
                    :class="['w-4 h-4 transition-transform duration-200', isPassageExpanded ? 'rotate-180' : '']"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </button>
              <div v-show="isPassageExpanded" class="p-4 sm:p-5 text-xs sm:text-sm text-gray-200 leading-relaxed max-h-64 sm:max-h-80 overflow-y-auto whitespace-pre-line border-t border-cyan-500/20 bg-black/20 font-serif">
                {{ currentQuestion.stimulusTeks }}
              </div>
            </div>

            <!-- ========================================================= -->
            <!-- QUESTION PRESENTATION (WITH FLEXIBLE IMAGE POSITIONING)   -->
            <!-- ========================================================= -->

            <!-- KASUS A: Jika Teks Mengandung Tag [gambar] (Inline Split) -->
            <div v-if="hasImageTag(currentQuestion.pertanyaan)" class="space-y-4">
              <!-- Bagian Teks Sebelum [gambar] -->
              <div v-if="getQuestionSplitParts(currentQuestion.pertanyaan)[0]" class="text-sm sm:text-base text-gray-100 font-medium leading-relaxed whitespace-pre-line select-none">
                {{ getQuestionSplitParts(currentQuestion.pertanyaan)[0] }}
              </div>

              <!-- Media Gambar di Posisi Tag -->
              <div v-if="currentQuestion.mediaSoal && currentQuestion.tipeMediaSoal === 'image'" class="overflow-hidden rounded-2xl border border-white/15 bg-black/40 p-2 inline-block max-w-full">
                <img
                  :src="currentQuestion.mediaSoal"
                  alt="Lampiran Soal"
                  class="max-h-80 w-auto rounded-xl object-contain cursor-zoom-in hover:opacity-95 transition-opacity"
                  @click="openImageLightbox(currentQuestion.mediaSoal)"
                />
                <p class="text-[11px] text-gray-400 mt-1.5 flex items-center gap-1.5 font-medium">
                  <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                  <span>Klik gambar untuk memperbesar tampilan</span>
                </p>
              </div>

              <!-- Bagian Teks Setelah [gambar] -->
              <div v-if="getQuestionSplitParts(currentQuestion.pertanyaan)[1]" class="text-sm sm:text-base text-gray-100 font-medium leading-relaxed whitespace-pre-line select-none">
                {{ getQuestionSplitParts(currentQuestion.pertanyaan)[1] }}
              </div>
            </div>

            <!-- KASUS B: Posisi Samping (2 Kolom Layout) -->
            <div v-else-if="currentQuestion.mediaSoal && currentQuestion.tipeMediaSoal === 'image' && currentQuestion.posisiMediaSoal === 'samping'" class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
              <div class="md:col-span-7 text-sm sm:text-base text-gray-100 font-medium leading-relaxed whitespace-pre-line select-none">
                {{ currentQuestion.pertanyaan }}
              </div>
              <div class="md:col-span-5 overflow-hidden rounded-2xl border border-white/15 bg-black/40 p-2 inline-block w-full">
                <img
                  :src="currentQuestion.mediaSoal"
                  alt="Lampiran Soal"
                  class="max-h-80 w-auto mx-auto rounded-xl object-contain cursor-zoom-in hover:opacity-95 transition-opacity"
                  @click="openImageLightbox(currentQuestion.mediaSoal)"
                />
                <p class="text-[11px] text-gray-400 mt-1.5 flex items-center justify-center gap-1.5 font-medium text-center">
                  <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                  <span>Klik gambar untuk memperbesar</span>
                </p>
              </div>
            </div>

            <!-- KASUS C: Posisi Atas (Gambar Dulu Baru Teks) -->
            <div v-else-if="currentQuestion.mediaSoal && currentQuestion.tipeMediaSoal === 'image' && currentQuestion.posisiMediaSoal === 'atas'" class="space-y-4">
              <div class="overflow-hidden rounded-2xl border border-white/15 bg-black/40 p-2 inline-block max-w-full">
                <img
                  :src="currentQuestion.mediaSoal"
                  alt="Lampiran Soal"
                  class="max-h-80 w-auto rounded-xl object-contain cursor-zoom-in hover:opacity-95 transition-opacity"
                  @click="openImageLightbox(currentQuestion.mediaSoal)"
                />
                <p class="text-[11px] text-gray-400 mt-1.5 flex items-center gap-1.5 font-medium">
                  <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                  <span>Klik gambar untuk memperbesar tampilan</span>
                </p>
              </div>

              <div class="text-sm sm:text-base text-gray-100 font-medium leading-relaxed whitespace-pre-line select-none">
                {{ currentQuestion.pertanyaan }}
              </div>
            </div>

            <!-- KASUS D: Posisi Bawah (Default) atau Audio Listening -->
            <div v-else class="space-y-4">
              <div class="text-sm sm:text-base text-gray-100 font-medium leading-relaxed whitespace-pre-line select-none">
                {{ currentQuestion.pertanyaan }}
              </div>

              <!-- Media Soal (Image / Audio) -->
              <div v-if="currentQuestion.mediaSoal" class="pt-1">
                <!-- Image -->
                <div v-if="currentQuestion.tipeMediaSoal === 'image'" class="overflow-hidden rounded-2xl border border-white/15 bg-black/40 p-2 inline-block max-w-full">
                  <img
                    :src="currentQuestion.mediaSoal"
                    alt="Lampiran Soal"
                    class="max-h-80 w-auto rounded-xl object-contain cursor-zoom-in hover:opacity-95 transition-opacity"
                    @click="openImageLightbox(currentQuestion.mediaSoal)"
                  />
                  <p class="text-[11px] text-gray-400 mt-1.5 flex items-center gap-1.5 font-medium">
                    <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                    <span>Klik gambar untuk memperbesar tampilan</span>
                  </p>
                </div>

                <!-- Audio Listening Player -->
                <div v-else-if="currentQuestion.tipeMediaSoal === 'audio'" class="p-4 rounded-2xl bg-gradient-to-r from-purple-950/50 to-slate-900 border border-purple-500/30 space-y-2.5 max-w-lg">
                  <div class="flex items-center gap-2 text-purple-300 font-black text-xs">
                    <span class="text-base">🎙️</span>
                    <span>Audio Listening Ujian</span>
                  </div>
                  <audio controls controlsList="nodownload" :src="currentQuestion.mediaSoal" class="w-full h-10 rounded-xl"></audio>
                  <p class="text-[10.5px] text-gray-400">Dengarkan audio di atas dengan seksama untuk menjawab pertanyaan.</p>
                </div>
              </div>
            </div>

            <!-- Options A-E (Supports Opsi Acak & Gambar Opsi) -->
            <div v-if="!currentQuestion.tipeSoal || currentQuestion.tipeSoal === 'pilihan_ganda'" class="space-y-3 pt-2">
              <div
                v-for="opt in currentDisplayOptions"
                :key="opt.displayKey"
                @click="selectAnswer(opt.originalKey)"
                :class="[
                  'group flex items-start gap-4 p-4 rounded-2xl border transition-all select-none min-h-[52px]',
                  isGameMode && answers[currentQuestion.id]
                    ? (answers[currentQuestion.id] === opt.originalKey ? 'cursor-default' : 'opacity-40 cursor-not-allowed pointer-events-none')
                    : 'cursor-pointer',
                  answers[currentQuestion.id] === opt.originalKey
                    ? (isGameMode && currentQuestion.kunciJawaban
                        ? (opt.originalKey === currentQuestion.kunciJawaban
                            ? 'bg-gradient-to-r from-emerald-500/25 to-teal-500/25 border-emerald-400 text-white shadow-lg shadow-emerald-500/20 ring-2 ring-emerald-400/50'
                            : 'bg-gradient-to-r from-rose-500/20 to-red-500/20 border-rose-500 text-white shadow-lg shadow-rose-500/15 animate-wobble ring-2 ring-rose-500/40')
                        : 'bg-gradient-to-r from-cyan-500/20 to-teal-500/20 border-cyan-400 text-white shadow-lg shadow-cyan-500/10')
                    : 'bg-white/5 border-white/10 hover:border-white/20 hover:bg-white/[0.08] text-gray-200'
                ]"
              >
                <!-- Option Key Pill (A, B, C, D, E) with Game Mode Icons -->
                <span
                  :class="[
                    'w-9 h-9 rounded-xl flex items-center justify-center font-black text-sm shrink-0 font-mono transition-all',
                    answers[currentQuestion.id] === opt.originalKey
                      ? (isGameMode && currentQuestion.kunciJawaban
                          ? (opt.originalKey === currentQuestion.kunciJawaban
                              ? 'bg-gradient-to-r from-emerald-400 to-teal-400 text-slate-950 scale-105 shadow-md shadow-emerald-400/40 font-black'
                              : 'bg-gradient-to-r from-rose-500 to-red-500 text-white scale-105 shadow-md shadow-rose-500/30 font-black')
                          : 'bg-gradient-to-r from-cyan-400 to-teal-400 text-slate-950 scale-105 shadow-md shadow-cyan-400/30')
                      : 'bg-white/10 text-gray-300 group-hover:bg-white/20 group-hover:text-white'
                  ]"
                >
                  <span v-if="isGameMode && answers[currentQuestion.id] === opt.originalKey">
                    {{ opt.originalKey === currentQuestion.kunciJawaban ? '🏆' : '💥' }}
                  </span>
                  <span v-else>
                    {{ opt.displayKey }}
                  </span>
                </span>

                <!-- Option Text & Image Content -->
                <div class="flex-1 space-y-2">
                  <span v-if="opt.text" class="text-xs sm:text-sm pt-1.5 leading-relaxed block">
                    {{ opt.text }}
                  </span>

                  <!-- Game Mode Direct Feedback Pill -->
                  <div v-if="isGameMode && answers[currentQuestion.id] === opt.originalKey" class="pt-0.5">
                    <span
                      :class="[
                        'text-[10px] font-black px-2.5 py-0.5 rounded-full inline-flex items-center gap-1 font-mono border',
                        opt.originalKey === currentQuestion.kunciJawaban
                          ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-sm shadow-emerald-500/10'
                          : 'bg-rose-500/20 text-rose-300 border-rose-500/40 shadow-sm shadow-rose-500/10'
                      ]"
                    >
                      <span v-if="opt.originalKey === currentQuestion.kunciJawaban">✓ TERKUNCI (BENAR • +100 XP)</span>
                      <span v-else>💥 TERKUNCI (SALAH • +0 XP)</span>
                    </span>
                  </div>
                  <!-- Gambar Opsi jika ada -->
                  <div v-if="opt.gambar" class="mt-2">
                    <img
                      :src="opt.gambar"
                      :alt="'Pilihan ' + opt.displayKey"
                      class="max-h-48 rounded-xl border border-white/10 object-contain cursor-zoom-in bg-black/30 hover:opacity-90 transition-opacity"
                      @click.stop="openImageLightbox(opt.gambar)"
                    />
                    <span class="text-[10px] text-gray-400 block mt-1">🔍 Klik untuk memperbesar gambar opsi</span>
                  </div>
                </div>
              </div>

              <!-- Game Mode Locked Notice -->
              <div
                v-if="isGameMode && answers[currentQuestion.id]"
                class="p-3.5 rounded-2xl bg-slate-900/90 border border-amber-500/25 flex items-center gap-2.5 text-xs text-amber-200/90 font-mono shadow-md"
              >
                <span class="text-base">🔒</span>
                <span>Jawaban Anda telah terkunci untuk butir soal ini (Mode Game 1x kesempatan). Silakan lanjut ke nomor berikutnya.</span>
              </div>
            </div>

            <!-- ========================================================= -->
            <!-- 1. INTERACTIVE QUESTION: MATCH (Menjodohkan)              -->
            <!-- ========================================================= -->
            <div v-else-if="currentQuestion.tipeSoal === 'match'" class="space-y-5 pt-2">
              <div class="p-3.5 rounded-2xl bg-cyan-950/30 border border-cyan-500/30 flex items-start gap-3">
                <span class="text-lg shrink-0">🧩</span>
                <div class="text-xs leading-relaxed text-gray-300">
                  <span class="font-bold text-cyan-300 block mb-0.5">Petunjuk Menjodohkan (Tap-to-Pair):</span>
                  Ketuk 1 kartu di kolom kiri (akan menyala), lalu ketuk pasangannya di pilihan kanan. Untuk membatalkan pasangan, ketuk tanda silang (✕) pada kartu.
                </div>
              </div>

              <!-- Match Container (2 Columns on Tablet/Desktop, Stack on Mobile) -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                
                <!-- Left Column: Item Kiri (Target Pasangan) -->
                <div class="space-y-2.5">
                  <h5 class="text-xs font-black uppercase tracking-wider text-cyan-400 flex items-center gap-1.5">
                    <span>1️⃣</span>
                    <span>Kolom Soal / Konsep:</span>
                  </h5>

                  <div
                    v-for="pair in (currentQuestion.dataInteraktif?.pasanganKiri || [])"
                    :key="pair.id"
                    @click="selectMatchLeft(pair.id)"
                    :class="[
                      'p-3.5 sm:p-4 rounded-2xl border transition-all cursor-pointer select-none relative',
                      selectedMatchLeftId === pair.id
                        ? 'border-cyan-400 bg-cyan-500/20 shadow-lg shadow-cyan-500/20 ring-2 ring-cyan-400/50'
                        : getMatchedRightText(pair.id)
                          ? 'border-emerald-500/50 bg-emerald-950/20 hover:border-emerald-400'
                          : 'border-white/10 bg-white/5 hover:border-white/20'
                    ]"
                  >
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex-1">
                        <span class="text-xs sm:text-sm font-semibold text-white block leading-snug">
                          {{ pair.teks }}
                        </span>

                        <!-- Matched Connection Badge -->
                        <div v-if="getMatchedRightText(pair.id)" class="mt-2.5 pt-2 border-t border-emerald-500/20 flex items-center justify-between gap-2">
                          <span class="text-[11px] font-bold text-emerald-300 flex items-center gap-1.5 truncate">
                            <span class="text-xs">🔗</span>
                            <span class="truncate">{{ getMatchedRightText(pair.id) }}</span>
                          </span>
                          <button
                            v-if="!isGameMode"
                            type="button"
                            @click.stop="unpairMatch(pair.id)"
                            class="p-1 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-[10px] font-bold shrink-0 min-w-[28px] min-h-[28px] flex items-center justify-center transition-colors"
                            title="Lepas Pasangan"
                          >
                            ✕
                          </button>
                          <span
                            v-else
                            class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30"
                            title="Terkunci di Mode Game"
                          >
                            🔒 Terkunci
                          </span>
                        </div>
                      </div>

                      <span
                        :class="[
                          'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-transform',
                          selectedMatchLeftId === pair.id
                            ? 'bg-cyan-400 text-slate-950 scale-110 font-black'
                            : getMatchedRightText(pair.id)
                              ? 'bg-emerald-500 text-slate-950'
                              : 'bg-white/10 text-gray-400'
                        ]"
                      >
                        {{ getMatchedRightText(pair.id) ? '✓' : (selectedMatchLeftId === pair.id ? '👉' : '○') }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Right Column: Pilihan Kanan -->
                <div class="space-y-2.5">
                  <h5 class="text-xs font-black uppercase tracking-wider text-teal-400 flex items-center gap-1.5">
                    <span>2️⃣</span>
                    <span>Pilihan Jawaban:</span>
                  </h5>

                  <div
                    v-for="opt in (currentQuestion.dataInteraktif?.pilihanKanan || [])"
                    :key="opt.id"
                    @click="selectMatchRight(opt.teks)"
                    :class="[
                      'p-3.5 sm:p-4 rounded-2xl border transition-all cursor-pointer select-none',
                      isRightTextPaired(opt.teks)
                        ? 'opacity-60 bg-white/[0.02] border-white/10'
                        : selectedMatchLeftId
                          ? 'border-teal-400/60 bg-teal-500/10 hover:bg-teal-500/20 hover:border-teal-400 shadow-md shadow-teal-500/10 animate-pulse'
                          : 'border-white/10 bg-white/5 hover:border-white/20'
                    ]"
                  >
                    <div class="flex items-center justify-between gap-3">
                      <span class="text-xs sm:text-sm text-gray-200 font-medium leading-snug">
                        {{ opt.teks }}
                      </span>
                      <span
                        v-if="isRightTextPaired(opt.teks)"
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 shrink-0 font-mono"
                      >
                        Terpasang
                      </span>
                      <span
                        v-else-if="selectedMatchLeftId"
                        class="text-[11px] font-bold text-teal-300 shrink-0"
                      >
                        Ketuk ➔
                      </span>
                    </div>
                  </div>
                </div>

              </div>
            </div>

            <!-- ========================================================= -->
            <!-- 2. INTERACTIVE QUESTION: REORDER (Mengurutkan)            -->
            <!-- ========================================================= -->
            <div v-else-if="currentQuestion.tipeSoal === 'reorder'" class="space-y-4 pt-2">
              <div class="p-3.5 rounded-2xl bg-purple-950/30 border border-purple-500/30 flex items-start gap-3">
                <span class="text-lg shrink-0">🔢</span>
                <div class="text-xs leading-relaxed text-gray-300">
                  <span class="font-bold text-purple-300 block mb-0.5">Petunjuk Mengurutkan (Touch Order):</span>
                  Urutkan tahapan/langkah berikut dari urutan paling atas ke paling bawah dengan menekan tombol panah <b>Naik (▲)</b> atau <b>Turun (▼)</b>.
                </div>
              </div>

              <div class="space-y-2.5">
                <div
                  v-for="(item, idx) in getReorderCurrentList()"
                  :key="item.id"
                  class="p-3.5 sm:p-4 rounded-2xl border border-white/10 bg-white/5 hover:border-purple-500/30 transition-all flex items-center justify-between gap-3"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="w-8 h-8 rounded-xl bg-purple-500/20 border border-purple-500/30 text-purple-300 font-black font-mono text-xs flex items-center justify-center shrink-0">
                      {{ idx + 1 }}
                    </span>
                    <span class="text-xs sm:text-sm font-medium text-gray-100 leading-snug break-words">
                      {{ item.teks }}
                    </span>
                  </div>

                  <!-- Mobile Thumb Friendly Action Buttons (min-h 40px) -->
                  <div class="flex items-center gap-1.5 shrink-0">
                    <button
                      type="button"
                      @click="moveReorderItem(idx, -1)"
                      :disabled="idx === 0"
                      class="px-2.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 disabled:opacity-20 disabled:cursor-not-allowed text-white text-xs font-bold transition-all min-h-[38px] flex items-center gap-1 active:scale-95"
                      title="Geser Naik"
                    >
                      <span>▲</span>
                      <span class="hidden sm:inline text-[10px]">Naik</span>
                    </button>
                    <button
                      type="button"
                      @click="moveReorderItem(idx, 1)"
                      :disabled="idx === getReorderCurrentList().length - 1"
                      class="px-2.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 disabled:opacity-20 disabled:cursor-not-allowed text-white text-xs font-bold transition-all min-h-[38px] flex items-center gap-1 active:scale-95"
                      title="Geser Turun"
                    >
                      <span>▼</span>
                      <span class="hidden sm:inline text-[10px]">Turun</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- ========================================================= -->
            <!-- 3. INTERACTIVE QUESTION: CATEGORIZE (Mengelompokkan)      -->
            <!-- ========================================================= -->
            <div v-else-if="currentQuestion.tipeSoal === 'categorize'" class="space-y-4 pt-2">
              <div class="p-3.5 rounded-2xl bg-amber-950/30 border border-amber-500/30 flex items-start gap-3">
                <span class="text-lg shrink-0">📂</span>
                <div class="text-xs leading-relaxed text-gray-300">
                  <span class="font-bold text-amber-300 block mb-0.5">Petunjuk Mengelompokkan (Category Selector):</span>
                  Untuk setiap pernyataan/objek di bawah, tentukan kelompok kategori yang tepat dengan mengetuk tombol kategori yang tersedia.
                </div>
              </div>

              <div class="space-y-3">
                <div
                  v-for="item in (currentQuestion.dataInteraktif?.items || [])"
                  :key="item.id"
                  class="p-4 rounded-2xl border border-white/10 bg-white/5 space-y-3"
                >
                  <div class="flex items-start justify-between gap-3">
                    <span class="text-xs sm:text-sm font-semibold text-white leading-snug">
                      {{ item.teks }}
                    </span>
                    <span
                      v-if="getItemAssignedCategory(item.id)"
                      class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 shrink-0"
                    >
                      {{ getItemAssignedCategory(item.id) }}
                    </span>
                  </div>

                  <!-- Category Pills Selection (Thumb Touch >= 40px) -->
                  <div class="flex flex-wrap gap-2 pt-1 border-t border-white/5">
                    <button
                      v-for="cat in (currentQuestion.dataInteraktif?.kategori || [])"
                      :key="cat"
                      type="button"
                      :disabled="isGameMode && Boolean(getItemAssignedCategory(item.id))"
                      @click="setItemCategory(item.id, cat)"
                      :class="[
                        'px-3.5 py-2 rounded-xl text-xs font-bold transition-all min-h-[38px] flex items-center gap-1.5 active:scale-95',
                        getItemAssignedCategory(item.id) === cat
                          ? 'bg-gradient-to-r from-amber-500 to-yellow-500 text-slate-950 shadow-md shadow-amber-500/20 font-black'
                          : (isGameMode && getItemAssignedCategory(item.id)
                              ? 'opacity-30 cursor-not-allowed bg-white/5 text-gray-500'
                              : 'bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white')
                      ]"
                    >
                      <span>{{ getItemAssignedCategory(item.id) === cat ? '✓' : '○' }}</span>
                      <span>{{ cat }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Navigation Buttons Bottom -->
            <div class="flex items-center justify-between gap-3 pt-6 border-t border-white/10">
              <button
                @click="prevQuestion"
                :disabled="currentQuestionIndex === 0"
                class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed text-white font-bold text-xs sm:text-sm flex items-center gap-2 min-h-[44px]"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                <span>Sebelumnya</span>
              </button>

              <button
                v-if="currentQuestionIndex < currentQuestions.length - 1"
                @click="nextQuestion"
                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm flex items-center gap-2 shadow-md shadow-cyan-500/20 active:scale-95 min-h-[44px]"
              >
                <span>Selanjutnya</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </button>

              <button
                v-else
                @click="confirmSubmitExam"
                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm flex items-center gap-2 shadow-lg shadow-emerald-500/25 active:scale-95 min-h-[44px]"
              >
                <span>✓ Selesai &amp; Kumpulkan</span>
              </button>
            </div>

          </div>
        </div>

        <!-- Question Palette Sidebar Desktop (Cols 9-12) -->
        <div class="hidden lg:block lg:col-span-4">
          <div class="p-6 rounded-3xl bg-slate-900/85 border border-white/10 shadow-2xl backdrop-blur-xl sticky top-40 space-y-4">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
              <h4 class="font-black text-white text-sm flex items-center gap-2">
                <span>📑</span>
                <span>Palet Nomor Soal</span>
              </h4>
              <span class="text-xs font-mono text-cyan-300 font-bold">
                {{ totalAnswered }} / {{ currentQuestions.length }}
              </span>
            </div>

            <!-- Palette Grid Numbers -->
            <div class="grid grid-cols-5 gap-2 max-h-72 overflow-y-auto pr-1">
              <button
                v-for="(q, idx) in currentQuestions"
                :key="q.id"
                @click="jumpToQuestion(idx)"
                :class="[
                  'h-10 rounded-xl font-bold font-mono text-xs transition-all relative flex items-center justify-center select-none min-h-[40px]',
                  currentQuestionIndex === idx
                    ? 'ring-2 ring-cyan-400 scale-105 z-10'
                    : '',
                  raguRagu[q.id]
                    ? 'bg-amber-500 text-slate-950 font-black shadow-md shadow-amber-500/20'
                    : answers[q.id]
                      ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/20'
                      : 'bg-white/5 hover:bg-white/10 text-gray-300 border border-white/10'
                ]"
              >
                <span>{{ idx + 1 }}</span>
                <span
                  v-if="answers[q.id]"
                  class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-slate-950 text-white border border-white/20 text-[9px] font-mono flex items-center justify-center font-bold"
                >
                  {{ getAnswerDisplayKey(q) }}
                </span>
              </button>
            </div>

            <!-- Legend -->
            <div class="pt-3 border-t border-white/10 space-y-2 text-[11px] text-gray-400 font-medium">
              <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded bg-emerald-500"></span>
                <span>Sudah Dijawab</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded bg-amber-500"></span>
                <span>Ragu-ragu</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded bg-white/5 border border-white/20"></span>
                <span>Belum Dijawab</span>
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>

    <!-- ======================================================================= -->
    <!-- MOBILE QUESTION PALETTE DRAWER (DRAWER ON MOBILE)                       -->
    <!-- ======================================================================= -->
    <div
      v-if="showMobilePalette && currentMode === 'exam_room'"
      class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/80 backdrop-blur-md animate-in fade-in"
    >
      <div class="w-full max-w-md rounded-t-3xl sm:rounded-3xl bg-slate-900 border border-cyan-500/40 p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-base">📑</span>
            <h4 class="font-black text-white text-base">Pilih Nomor Soal</h4>
          </div>
          <button @click="showMobilePalette = false" class="p-1 text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="grid grid-cols-5 gap-2.5 max-h-72 overflow-y-auto pr-1">
          <button
            v-for="(q, idx) in currentQuestions"
            :key="'mob-' + q.id"
            @click="jumpToQuestion(idx)"
            :class="[
              'h-11 rounded-xl font-bold font-mono text-sm transition-all relative flex items-center justify-center min-h-[44px]',
              currentQuestionIndex === idx
                ? 'ring-2 ring-cyan-400 scale-105 z-10'
                : '',
              raguRagu[q.id]
                ? 'bg-amber-500 text-slate-950 font-black'
                : answers[q.id]
                  ? 'bg-emerald-500 text-slate-950 font-black'
                  : 'bg-white/5 text-gray-300 border border-white/10'
            ]"
          >
            <span>{{ idx + 1 }}</span>
            <span
              v-if="answers[q.id]"
              class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-slate-950 text-white border border-white/20 text-[9px] font-mono flex items-center justify-center font-bold"
            >
              {{ getAnswerDisplayKey(q) }}
            </span>
          </button>
        </div>

        <div class="pt-2">
          <button
            @click="showMobilePalette = false"
            class="w-full py-3 rounded-xl bg-white/10 text-white font-bold text-xs min-h-[44px]"
          >
            Tutup Palet
          </button>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- DIALOG MODAL: PILIH IDENTITAS SISWA (SINGLE UNIFIED SEARCHABLE COMBOBOX) -->
    <!-- ======================================================================= -->
    <div
      v-if="showIdentityModal && selectedJadwal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in duration-200"
    >
      <div class="w-full max-w-lg rounded-3xl bg-slate-900 border border-cyan-500/40 p-6 sm:p-7 shadow-2xl space-y-5">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/15 text-cyan-400 border border-cyan-500/30 flex items-center justify-center text-xl shadow-inner">
              👤
            </div>
            <div>
              <h3 class="text-base font-black text-white">Konfirmasi Identitas Peserta Ujian</h3>
              <p class="text-xs text-gray-400">Akun Kelas: {{ siswaObj?.kelas || 'Kelas Siswa' }}</p>
            </div>
          </div>
          <button @click="showIdentityModal = false" class="p-1 rounded-lg text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Exam Information Card -->
        <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1">
          <span class="text-[10px] text-teal-300 uppercase font-black tracking-wider block">Mata Pelajaran:</span>
          <p class="text-sm font-black text-white">{{ selectedJadwal.judulUjian }}</p>
          <p class="text-xs text-gray-300">📖 {{ selectedJadwal.mapelNama }} ({{ selectedJadwal.jumlahSoal }} Butir Soal • {{ selectedJadwal.durasiMenit }} Menit)</p>
        </div>

        <!-- SINGLE UNIFIED SEARCHABLE STUDENT SELECTOR (1 FORM DUA FUNGSI) -->
        <div class="space-y-3">
          <label class="flex items-center justify-between text-xs font-bold text-gray-200 uppercase tracking-wider">
            <span>Pilih Nama Siswa (Peserta Ujian) *</span>
            <span class="text-[10px] text-cyan-400 font-mono">{{ siswaList.length }} Siswa di Kelas</span>
          </label>

          <!-- CASE 1: Student is already selected -->
          <div
            v-if="selectedSiswaId && selectedSiswaObj"
            class="space-y-3"
          >
            <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-500/15 via-teal-500/10 to-slate-800 border border-emerald-500/40 flex items-center justify-between gap-3 shadow-lg">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center justify-center font-black text-base shrink-0">
                  ✓
                </div>
                <div>
                  <span class="text-[10px] text-emerald-400 font-mono font-bold uppercase tracking-wider block">Nama Anda Terpilih:</span>
                  <h4 class="font-black text-white text-sm sm:text-base leading-tight">{{ selectedSiswaObj.nama }}</h4>
                  <span class="text-xs text-cyan-300 font-mono">NIS: {{ selectedSiswaObj.nis }}</span>
                </div>
              </div>
              <button
                type="button"
                @click="clearStudentSelection"
                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-rose-500/20 text-gray-300 hover:text-rose-300 border border-white/10 text-xs font-bold transition-all shrink-0 min-h-[36px]"
                title="Ganti Nama Siswa"
              >
                Ganti Siswa ✕
              </button>
            </div>

            <!-- Notice if student already took this test -->
            <div
              v-if="existingResultForSelectedSiswa"
              class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs space-y-1 font-mono"
            >
              <div class="flex items-center gap-1.5 font-bold">
                <span>⚠️</span>
                <span>Perhatian: Anda Sudah Pernah Mengerjakan!</span>
              </div>
              <p class="text-[11px] text-gray-300 font-sans">
                <strong>{{ selectedSiswaObj.nama }}</strong> sebelumnya sudah pernah menyelesaikan ujian ini dengan skor <strong>{{ existingResultForSelectedSiswa.nilai }}</strong> ({{ existingResultForSelectedSiswa.status }}). Memulai pengerjaan ulang akan memperbarui skor terbaru.
              </p>
            </div>
          </div>

          <!-- CASE 2: Single unified Combobox: Search or Click to choose from dropdown -->
          <div v-else class="relative">
            <div class="relative">
              <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-cyan-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input
                v-model="studentSearchText"
                @focus="onStudentInputFocus"
                @click="onStudentInputFocus"
                @blur="onStudentInputBlur"
                @keyup.enter="onStudentInputEnter"
                type="text"
                placeholder="Ketik nama / NIS Anda atau klik panah untuk memilih..."
                class="w-full pl-10 pr-10 py-3.5 rounded-2xl bg-slate-800/90 border border-white/15 text-white text-xs sm:text-sm placeholder-gray-400 focus:outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20 min-h-[48px]"
              />
              <button
                type="button"
                @mousedown.prevent="isDropdownOpen = !isDropdownOpen"
                @click="isDropdownOpen = !isDropdownOpen"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white p-1"
                title="Buka / Tutup Daftar Siswa"
              >
                <svg class="w-4 h-4 transition-transform duration-200" :class="isDropdownOpen ? 'rotate-180 text-cyan-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
            </div>

            <!-- Floating Filtered Dropdown Panel -->
            <div
              v-show="isDropdownOpen"
              class="absolute left-0 right-0 top-full mt-2 z-50 rounded-2xl bg-slate-900 border border-cyan-500/40 shadow-2xl overflow-hidden max-h-60 overflow-y-auto backdrop-blur-2xl divide-y divide-white/5"
            >
              <div v-if="filteredSiswaDropdown.length === 0" class="p-4 text-center text-xs text-gray-400 font-sans">
                Nama atau NIS tidak ditemukan dalam kelas ini. Periksa kembali ejaan nama Anda.
              </div>

              <div
                v-for="s in filteredSiswaDropdown"
                :key="'sel-' + s.id"
                @mousedown.prevent="selectStudent(s)"
                @click="selectStudent(s)"
                class="p-3 hover:bg-cyan-500/15 cursor-pointer flex items-center justify-between gap-3 transition-colors group"
              >
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-white/5 group-hover:bg-cyan-500/20 text-gray-300 group-hover:text-cyan-300 font-bold text-xs flex items-center justify-center shrink-0">
                    {{ s.nama.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <div class="font-bold text-white text-xs group-hover:text-cyan-300 transition-colors">{{ s.nama }}</div>
                    <div class="text-[10px] text-gray-400 font-mono">NIS: {{ s.nis }}</div>
                  </div>
                </div>
                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-white/5 text-gray-400 group-hover:bg-cyan-500/20 group-hover:text-cyan-300">
                  Pilih &rarr;
                </span>
              </div>
            </div>

            <p class="text-[10px] text-gray-400 mt-1.5">
              Cukup ketik beberapa huruf nama Anda atau klik panah untuk memilih langsung dari daftar.
            </p>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
          <button
            type="button"
            @click="showIdentityModal = false"
            class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 font-bold text-xs sm:text-sm min-h-[42px]"
          >
            Batal
          </button>
          <button
            type="button"
            @click="confirmEnterWaitingRoom"
            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-400 hover:to-teal-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-cyan-500/20 active:scale-95 transition-all min-h-[42px]"
          >
            Masuk Ruang Tunggu CBT &rarr;
          </button>
        </div>

      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- EMERGENCY FULLSCREEN VIOLATION ALERT MODAL (LOCK ON TAB SWITCH / AI)    -->
    <!-- ======================================================================= -->
    <div
      v-if="showViolationAlert && !isExamLocked"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-2xl animate-in zoom-in-95 duration-200"
    >
      <div class="w-full max-w-lg rounded-3xl bg-slate-950 border-2 border-rose-500 p-6 sm:p-8 text-center space-y-5 shadow-[0_0_50px_rgba(244,63,94,0.35)]">
        <div class="w-20 h-20 mx-auto rounded-3xl bg-rose-500/20 text-rose-500 border-2 border-rose-500/40 flex items-center justify-center text-4xl animate-bounce">
          🚨
        </div>

        <div class="space-y-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/40 text-xs font-mono font-black tracking-widest uppercase">
            PELANGGARAN KE-{{ violationCount }} DICATAT!
          </span>
          <h3 class="text-xl sm:text-2xl font-black text-white">
            Peringatan Keras Pelanggaran CBT!
          </h3>
          <p class="text-xs sm:text-sm text-gray-300 leading-relaxed font-sans pt-1">
            {{ violationAlertMessage }}
          </p>
        </div>

        <div class="p-3.5 rounded-2xl bg-rose-950/30 border border-rose-500/30 text-rose-300 text-xs text-left font-mono space-y-1">
          <div>⚠️ Sistem otomatis mendeteksi perpindahan fokus layar / split screen / tab switch.</div>
          <div>⚠️ Guru pengawas dapat membatalkan atau mengunci ujian Anda jika pelanggaran terus berulang.</div>
        </div>

        <div class="pt-2">
          <button
            @click="closeViolationAlert"
            class="w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-400 hover:to-amber-400 text-slate-950 font-black text-sm shadow-xl shadow-rose-500/30 active:scale-95 transition-all min-h-[46px]"
          >
            Saya Mengerti &amp; Kembali ke Ujian
          </button>
        </div>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- PERMANENT LOCK SCREEN: UJIAN DIKUNCI OLEH GURU PENGAWAS                   -->
    <!-- ======================================================================= -->
    <div
      v-if="isExamLocked"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/95 backdrop-blur-2xl"
    >
      <div class="w-full max-w-md rounded-3xl bg-slate-950 border-2 border-rose-600 p-8 text-center space-y-5 shadow-[0_0_60px_rgba(225,29,72,0.4)]">
        <div class="w-20 h-20 mx-auto rounded-3xl bg-rose-600/20 text-rose-500 border-2 border-rose-600 flex items-center justify-center text-4xl">
          🔒
        </div>

        <div class="space-y-2">
          <h3 class="text-xl sm:text-2xl font-black text-white">
            Ujian Telah Dikunci Pengawas!
          </h3>
          <p class="text-xs sm:text-sm text-gray-300 leading-relaxed">
            Sesi ujian Anda telah dikunci oleh Guru pengawas karena terdeteksi melakukan pelanggaran integritas atau kendala teknis.
          </p>
        </div>

        <div class="p-4 rounded-2xl bg-white/5 border border-white/10 text-xs text-gray-400 font-mono text-left space-y-1.5">
          <div>• Peserta: {{ activeStudent?.nama }}</div>
          <div>• NIS: {{ activeStudent?.nis }}</div>
          <div>• Total Pelanggaran: {{ violationCount }} Kali</div>
        </div>

        <p class="text-xs text-amber-400 font-bold">
          Silakan melapor langsung ke Guru pengawas di kelas / laboratorium untuk membuka kembali kunci ujian Anda.
        </p>
      </div>
    </div>

    <!-- Lightbox Zoom Preview Modal for Students -->
    <div
      v-if="selectedLightboxImage"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
      @click="closeImageLightbox"
    >
      <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" @click.stop>
        <button
          type="button"
          @click="closeImageLightbox"
          class="absolute -top-12 right-0 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors min-h-[40px] min-w-[40px] flex items-center justify-center"
          title="Tutup Preview"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <img
          :src="selectedLightboxImage"
          alt="Preview Gambar Soal"
          class="max-h-[85vh] max-w-full rounded-2xl object-contain border border-white/20 shadow-2xl"
        />
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- GAMING CONFETTI CELEBRATION OVERLAY                                    -->
    <!-- ======================================================================= -->
    <div
      v-if="isCelebratingConfetti"
      class="fixed inset-0 pointer-events-none z-[120] overflow-hidden flex items-center justify-center"
    >
      <div class="absolute inset-0 bg-cyan-950/20 backdrop-blur-[2px]"></div>
      <div class="relative text-center p-6 rounded-3xl bg-slate-900/90 border border-amber-400/40 shadow-2xl backdrop-blur-xl animate-bounce max-w-sm mx-4">
        <div class="text-5xl mb-2">🎉 👑 ⚡</div>
        <h3 class="text-lg sm:text-xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-300 via-yellow-200 to-amber-400">
          Luar Biasa! Semua Soal Telah Terjawab!
        </h3>
        <p class="text-xs text-gray-300 mt-1.5 font-medium">
          Periksa kembali jawabanmu sebelum menekan tombol Selesai Ujian.
        </p>
      </div>
    </div>

    <!-- ======================================================================= -->
    <!-- GAME INTERACTIVE ANIMATION MODAL: PIALA (BENAR) & BOOM (SALAH)          -->
    <!-- ======================================================================= -->
    <div
      v-if="gameFeedback.show"
      @click="dismissGameFeedback"
      class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm cursor-pointer select-none animate-in fade-in duration-200"
    >
      <!-- JAWABAN BENAR: 5 VARIASI ANIMASI ACAK (TROPHY, CROWN, ROCKET, TREASURE, SHIELD) -->
      <div
        v-if="gameFeedback.isCorrect"
        :class="['relative w-full max-w-sm p-6 sm:p-7 rounded-3xl bg-slate-900/95 border-2 text-center space-y-4 animate-bounce transition-all duration-300', gameFeedback.borderClass]"
        @click.stop="dismissGameFeedback"
      >
        <!-- Floating Gold/Emerald Sparkles / Glow -->
        <div class="absolute -top-6 -right-6 w-20 h-20 bg-amber-400/20 rounded-full blur-xl pointer-events-none"></div>
        <div class="absolute -bottom-6 -left-6 w-20 h-20 bg-emerald-400/20 rounded-full blur-xl pointer-events-none"></div>

        <!-- 1. TROPHY (PIALA EMAS JUARA) -->
        <div v-if="gameFeedback.variantId === 'trophy'" class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-amber-500/30 to-yellow-300/30 animate-ping opacity-40"></div>
          <svg class="w-24 h-24 drop-shadow-[0_0_15px_rgba(251,191,36,0.6)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M35 85H65M50 68V85M40 92H60" stroke="#F59E0B" stroke-width="6" stroke-linecap="round"/>
            <path d="M42 68H58L54 52H46L42 68Z" fill="#D97706"/>
            <path d="M28 20C28 20 28 52 50 52C72 52 72 20 72 20H28Z" fill="url(#gold_grad)" stroke="#F59E0B" stroke-width="2"/>
            <ellipse cx="50" cy="20" rx="22" ry="4" fill="#FDE68A"/>
            <path d="M28 26C18 26 16 38 28 44" stroke="#F59E0B" stroke-width="4" stroke-linecap="round"/>
            <path d="M72 26C82 26 84 38 72 44" stroke="#F59E0B" stroke-width="4" stroke-linecap="round"/>
            <path d="M50 28L52.5 33.5L58.5 34.2L54 38.3L55.2 44.2L50 41.2L44.8 44.2L46 38.3L41.5 34.2L47.5 33.5L50 28Z" fill="#FFFBEB"/>
            <defs>
              <linearGradient id="gold_grad" x1="28" y1="20" x2="72" y2="52" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FDE047"/>
                <stop offset="0.5" stop-color="#F59E0B"/>
                <stop offset="1" stop-color="#D97706"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-1 right-2 text-xl animate-spin">✨</span>
          <span class="absolute top-4 -left-2 text-lg">⭐</span>
        </div>

        <!-- 2. CROWN (MAHKOTA RAJA SULTAN) -->
        <div v-else-if="gameFeedback.variantId === 'crown'" class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-yellow-500/30 to-amber-300/30 animate-ping opacity-40"></div>
          <svg class="w-24 h-24 drop-shadow-[0_0_18px_rgba(245,158,11,0.6)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 68C22 68 35 74 50 74C65 74 78 68 78 68L75 75C75 75 63 80 50 80C37 80 25 75 25 75L22 68Z" fill="#BE123C"/>
            <path d="M20 62C20 62 35 67 50 67C65 67 80 62 80 62L78 70C78 70 65 75 50 75C35 75 22 70 22 70L20 62Z" fill="url(#crown_band)" stroke="#D97706" stroke-width="1.5"/>
            <circle cx="32" cy="67" r="2.5" fill="#38BDF8"/>
            <circle cx="50" cy="70" r="3.5" fill="#EF4444"/>
            <circle cx="68" cy="67" r="2.5" fill="#10B981"/>
            <path d="M20 62L22 36L36 50L50 25L64 50L78 36L80 62C80 62 65 67 50 67C35 67 20 62 20 62Z" fill="url(#crown_gold)" stroke="#D97706" stroke-width="2"/>
            <circle cx="22" cy="36" r="3.5" fill="#38BDF8" stroke="#0284C7" stroke-width="1"/>
            <circle cx="50" cy="25" r="4.5" fill="#F43F5E" stroke="#BE123C" stroke-width="1.5"/>
            <circle cx="78" cy="36" r="3.5" fill="#38BDF8" stroke="#0284C7" stroke-width="1"/>
            <path d="M50 16V21M47.5 18.5H52.5" stroke="#FDE047" stroke-width="2" stroke-linecap="round"/>
            <defs>
              <linearGradient id="crown_gold" x1="20" y1="25" x2="80" y2="67" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FEF08A"/>
                <stop offset="0.4" stop-color="#F59E0B"/>
                <stop offset="1" stop-color="#B45309"/>
              </linearGradient>
              <linearGradient id="crown_band" x1="20" y1="62" x2="80" y2="75" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FBBF24"/>
                <stop offset="1" stop-color="#D97706"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-1 left-2 text-xl animate-bounce">👑</span>
          <span class="absolute -bottom-1 right-2 text-lg">💎</span>
        </div>

        <!-- 3. ROCKET (TURBO ROKET MELESAT) -->
        <div v-else-if="gameFeedback.variantId === 'rocket'" class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-cyan-500/30 to-blue-400/30 animate-ping opacity-40"></div>
          <svg class="w-24 h-24 drop-shadow-[0_0_18px_rgba(6,182,212,0.6)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M43 70C43 70 38 85 45 92C48 95 50 87 50 87C50 87 52 95 55 92C62 85 57 70 57 70H43Z" fill="url(#flame_outer)"/>
            <path d="M46 70C46 70 44 80 48 85C49 86 50 82 50 82C50 82 51 86 52 85C56 80 54 70 54 70H46Z" fill="#FEF08A"/>
            <path d="M36 52L24 66C24 66 26 72 34 70L38 64" fill="#0284C7" stroke="#0369A1" stroke-width="1.5"/>
            <path d="M64 52L76 66C76 66 74 72 66 70L62 64" fill="#0284C7" stroke="#0369A1" stroke-width="1.5"/>
            <path d="M50 12C41 26 36 45 36 68H64C64 45 59 26 50 12Z" fill="url(#rocket_body)" stroke="#0284C7" stroke-width="2"/>
            <path d="M50 12C46 20 44 26 43 32H57C56 26 54 20 50 12Z" fill="#EF4444"/>
            <circle cx="50" cy="44" r="8" fill="#38BDF8" stroke="#0284C7" stroke-width="2.5"/>
            <circle cx="48" cy="42" r="3" fill="#FFFFFF" opacity="0.8"/>
            <rect x="42" y="68" width="16" height="4" rx="2" fill="#64748B"/>
            <defs>
              <linearGradient id="rocket_body" x1="36" y1="12" x2="64" y2="68" gradientUnits="userSpaceOnUse">
                <stop stop-color="#F8FAFC"/>
                <stop offset="0.7" stop-color="#E2E8F0"/>
                <stop offset="1" stop-color="#94A3B8"/>
              </linearGradient>
              <linearGradient id="flame_outer" x1="43" y1="70" x2="57" y2="92" gradientUnits="userSpaceOnUse">
                <stop stop-color="#F97316"/>
                <stop offset="0.6" stop-color="#EF4444"/>
                <stop offset="1" stop-color="#DC2626"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-1 -right-1 text-xl animate-bounce">🚀</span>
          <span class="absolute bottom-2 -left-1 text-base">🔥</span>
        </div>

        <!-- 4. TREASURE (PETI HARTA KARUN EMAS) -->
        <div v-else-if="gameFeedback.variantId === 'treasure'" class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-emerald-500/30 to-teal-300/30 animate-ping opacity-40"></div>
          <svg class="w-24 h-24 drop-shadow-[0_0_18px_rgba(16,185,129,0.6)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M45 42L32 18M50 40L50 14M55 42L68 18M38 42L20 28M62 42L80 28" stroke="#34D399" stroke-width="2" stroke-linecap="round" opacity="0.6"/>
            <circle cx="42" cy="38" r="4" fill="#38BDF8"/>
            <circle cx="58" cy="36" r="3.5" fill="#F43F5E"/>
            <circle cx="50" cy="32" r="4.5" fill="#FBBF24"/>
            <path d="M22 42C22 34 32 28 50 28C68 28 78 34 78 42L72 34C72 28 62 24 50 24C38 24 28 28 28 34L22 42Z" fill="#B45309" stroke="#F59E0B" stroke-width="1.5"/>
            <path d="M20 44L26 30C26 30 35 24 50 24C65 24 74 30 74 30L80 44" stroke="#F59E0B" stroke-width="2"/>
            <path d="M24 48H76L72 78H28L24 48Z" fill="url(#chest_wood)" stroke="#78350F" stroke-width="2"/>
            <rect x="33" y="48" width="6" height="30" fill="#F59E0B"/>
            <rect x="61" y="48" width="6" height="30" fill="#F59E0B"/>
            <rect x="46" y="46" width="8" height="10" rx="2" fill="#FDE047" stroke="#D97706" stroke-width="1.5"/>
            <circle cx="50" cy="50" r="1.5" fill="#78350F"/>
            <defs>
              <linearGradient id="chest_wood" x1="24" y1="48" x2="76" y2="78" gradientUnits="userSpaceOnUse">
                <stop stop-color="#92400E"/>
                <stop offset="0.5" stop-color="#78350F"/>
                <stop offset="1" stop-color="#451A03"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-1 left-1 text-xl animate-spin">💎</span>
          <span class="absolute top-3 -right-2 text-lg">💰</span>
        </div>

        <!-- 5. SHIELD (PERISAI PAHLAWAN & SAYAP JUARA) -->
        <div v-else class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-purple-500/30 to-indigo-300/30 animate-ping opacity-40"></div>
          <svg class="w-24 h-24 drop-shadow-[0_0_18px_rgba(168,85,247,0.6)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 34C16 42 16 58 26 68M78 34C84 42 84 58 74 68" stroke="#FBBF24" stroke-width="3" stroke-linecap="round" stroke-dasharray="2 4"/>
            <path d="M50 16L74 26C74 54 62 72 50 82C38 72 26 54 26 26L50 16Z" fill="url(#shield_metal)" stroke="#F59E0B" stroke-width="2.5"/>
            <path d="M50 22L68 30C68 52 58 66 50 74C42 66 32 52 32 30L50 22Z" fill="url(#shield_core)"/>
            <path d="M50 36L52.5 42.5L59 43.2L54 47.3L55.5 53.5L50 50.2L44.5 53.5L46 47.3L41 43.2L47.5 42.5L50 36Z" fill="#FDE047" stroke="#F59E0B" stroke-width="1"/>
            <path d="M22 18L30 26M78 18L70 26" stroke="#C084FC" stroke-width="2" stroke-linecap="round"/>
            <defs>
              <linearGradient id="shield_metal" x1="26" y1="16" x2="74" y2="82" gradientUnits="userSpaceOnUse">
                <stop stop-color="#8B5CF6"/>
                <stop offset="0.5" stop-color="#6D28D9"/>
                <stop offset="1" stop-color="#4C1D95"/>
              </linearGradient>
              <linearGradient id="shield_core" x1="32" y1="22" x2="68" y2="74" gradientUnits="userSpaceOnUse">
                <stop stop-color="#A78BFA"/>
                <stop offset="1" stop-color="#5B21B6"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-1 -right-1 text-xl animate-bounce">🛡️</span>
          <span class="absolute bottom-2 -left-1 text-base">⚔️</span>
        </div>

        <!-- Text Feedback -->
        <div class="space-y-1.5">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-mono text-xs font-black">
            <span>+100 XP</span>
            <span>•</span>
            <span>{{ gameFeedback.tag }}</span>
          </div>
          <h4 :class="['text-xl font-black text-transparent bg-clip-text bg-gradient-to-r', gameFeedback.titleGradient]">
            {{ gameFeedback.title }}
          </h4>
          <p class="text-xs sm:text-sm text-gray-200 font-medium leading-relaxed">
            {{ gameFeedback.message }}
          </p>
        </div>

        <p class="text-[10px] text-amber-300/70 font-mono">
          * Ketuk di mana saja untuk lanjut
        </p>
      </div>

      <!-- JAWABAN SALAH: 5 VARIASI ANIMASI ACAK (BOMB, DIZZY, BANANA, LIGHTNING, BALLOON) -->
      <div
        v-else
        :class="['relative w-full max-w-sm p-6 sm:p-7 rounded-3xl bg-slate-900/95 border-2 text-center space-y-4 animate-wobble transition-all duration-300', gameFeedback.borderClass]"
        @click.stop="dismissGameFeedback"
      >
        <!-- Floating Red/Orange Fire Glow -->
        <div class="absolute -top-6 -right-6 w-20 h-20 bg-rose-500/25 rounded-full blur-xl pointer-events-none"></div>
        <div class="absolute -bottom-6 -left-6 w-20 h-20 bg-orange-500/25 rounded-full blur-xl pointer-events-none"></div>

        <!-- 1. BOMB (BOOM KARTUN DINAMIT) -->
        <div v-if="gameFeedback.variantId === 'bomb'" class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <svg class="w-24 h-24 drop-shadow-[0_0_20px_rgba(244,63,94,0.7)]" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M50 10L58 24L74 16L72 32L88 36L78 48L92 60L76 66L80 82L64 78L58 92L50 80L42 92L36 78L20 82L24 66L8 60L22 48L12 36L28 32L26 16L42 24L50 10Z" fill="url(#boom_grad)" stroke="#EF4444" stroke-width="2"/>
            <path d="M50 22L55 32L66 26L64 38L76 42L68 50L78 58L66 62L68 74L56 70L50 80L44 70L32 74L34 62L22 58L32 50L24 42L36 38L34 26L45 32L50 22Z" fill="#FDE047"/>
            <circle cx="43" cy="48" r="4" fill="#0F172A"/>
            <circle cx="57" cy="48" r="4" fill="#0F172A"/>
            <path d="M41 43L46 45M59 45L54 43" stroke="#0F172A" stroke-width="2" stroke-linecap="round"/>
            <path d="M44 58C47 55 53 55 56 58" stroke="#0F172A" stroke-width="2.5" stroke-linecap="round"/>
            <defs>
              <linearGradient id="boom_grad" x1="12" y1="10" x2="88" y2="92" gradientUnits="userSpaceOnUse">
                <stop stop-color="#F97316"/>
                <stop offset="0.6" stop-color="#EF4444"/>
                <stop offset="1" stop-color="#991B1B"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-2 left-2 text-2xl animate-ping">💥</span>
          <span class="absolute -bottom-2 right-2 text-xl">💣</span>
        </div>

        <!-- 2. DIZZY (LINGLUNG PUSING BINTANG) -->
        <div v-else-if="gameFeedback.variantId === 'dizzy'" class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <svg class="w-24 h-24 drop-shadow-[0_0_20px_rgba(245,158,11,0.6)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="50" cy="20" rx="32" ry="8" stroke="#FBBF24" stroke-width="1.5" stroke-dasharray="3 3" opacity="0.7"/>
            <circle cx="28" cy="18" r="3" fill="#FBBF24"/>
            <circle cx="72" cy="22" r="3" fill="#F59E0B"/>
            <circle cx="50" cy="54" r="32" fill="url(#dizzy_face)" stroke="#D97706" stroke-width="2"/>
            <path d="M38 48C38 45 42 45 42 48C42 51 36 51 36 47C36 43 44 43 44 48" stroke="#1E293B" stroke-width="2" stroke-linecap="round"/>
            <path d="M62 48C62 45 66 45 66 48C66 51 60 51 60 47C60 43 68 43 68 48" stroke="#1E293B" stroke-width="2" stroke-linecap="round"/>
            <path d="M42 66C44 63 48 68 52 64C56 67 59 63 60 65" stroke="#1E293B" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M76 42C76 42 79 46 79 48C79 50 77.5 51 76 51C74.5 51 73 50 73 48C73 46 76 42 76 42Z" fill="#38BDF8"/>
            <defs>
              <linearGradient id="dizzy_face" x1="20" y1="24" x2="80" y2="84" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FEF08A"/>
                <stop offset="0.7" stop-color="#FBBF24"/>
                <stop offset="1" stop-color="#F59E0B"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-1 left-2 text-xl animate-spin">💫</span>
          <span class="absolute -bottom-1 -left-1 text-lg">🌀</span>
        </div>

        <!-- 3. BANANA (TERPELESET KULIT PISANG) -->
        <div v-else-if="gameFeedback.variantId === 'banana'" class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <svg class="w-24 h-24 drop-shadow-[0_0_20px_rgba(234,179,8,0.6)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M14 78H36M18 85H42M26 92H52" stroke="#FDE047" stroke-width="3" stroke-linecap="round" opacity="0.8"/>
            <path d="M68 32L71 38L78 39L73 44L74 50L68 47L63 50L64 44L59 39L66 38L68 32Z" fill="#FBBF24"/>
            <path d="M50 48C48 35 44 26 40 22C46 25 54 32 54 48" fill="#FACC15" stroke="#CA8A04" stroke-width="1.5"/>
            <path d="M46 54C46 44 50 40 50 40C50 40 54 44 54 54L50 68Z" fill="#FEF9C3"/>
            <path d="M46 56C36 56 25 64 22 74C28 72 38 68 46 66" fill="#FACC15" stroke="#CA8A04" stroke-width="2"/>
            <path d="M22 74C20 74 18 76 22 77C28 78 38 72 46 66" fill="#713F12"/>
            <path d="M54 56C64 56 75 64 78 74C72 72 62 68 54 66" fill="#FACC15" stroke="#CA8A04" stroke-width="2"/>
            <path d="M78 74C80 74 82 76 78 77C72 78 62 72 54 66" fill="#713F12"/>
            <ellipse cx="50" cy="68" rx="7" ry="4" fill="#EAB308" stroke="#CA8A04" stroke-width="1.5"/>
            <circle cx="50" cy="70" r="2" fill="#713F12"/>
          </svg>
          <span class="absolute -top-1 -right-1 text-2xl animate-bounce">🍌</span>
          <span class="absolute bottom-1 -left-1 text-xl">💨</span>
        </div>

        <!-- 4. LIGHTNING (AWAN PETIR TERSENGAT) -->
        <div v-else-if="gameFeedback.variantId === 'lightning'" class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <svg class="w-24 h-24 drop-shadow-[0_0_20px_rgba(139,92,246,0.7)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M28 44C24 44 20 48 20 53C20 58 24 62 29 62H71C76 62 80 58 80 53C80 48 76 44 72 44C72 36 65 30 57 30C52 30 47 33 45 37C42 34 38 33 34 35C30 37 28 40 28 44Z" fill="url(#cloud_dark)" stroke="#A78BFA" stroke-width="2"/>
            <circle cx="42" cy="48" r="3" fill="#FFFFFF"/>
            <circle cx="42" cy="48" r="1.5" fill="#0F172A"/>
            <circle cx="58" cy="48" r="3" fill="#FFFFFF"/>
            <circle cx="58" cy="48" r="1.5" fill="#0F172A"/>
            <ellipse cx="50" cy="55" rx="3.5" ry="2" fill="#0F172A"/>
            <path d="M52 56L42 70H51L45 88L62 68H52L58 56H52Z" fill="url(#bolt_grad)" stroke="#F59E0B" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M38 74L34 78M66 74L70 78M42 86L37 90M56 86L61 90" stroke="#FDE047" stroke-width="2" stroke-linecap="round"/>
            <defs>
              <linearGradient id="cloud_dark" x1="20" y1="30" x2="80" y2="62" gradientUnits="userSpaceOnUse">
                <stop stop-color="#475569"/>
                <stop offset="0.6" stop-color="#334155"/>
                <stop offset="1" stop-color="#1E293B"/>
              </linearGradient>
              <linearGradient id="bolt_grad" x1="42" y1="56" x2="62" y2="88" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FEF08A"/>
                <stop offset="0.5" stop-color="#FBBF24"/>
                <stop offset="1" stop-color="#F59E0B"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-1 -right-2 text-2xl animate-bounce">⚡</span>
          <span class="absolute bottom-0 -left-1 text-xl">🌩️</span>
        </div>

        <!-- 5. BALLOON (BALON MELETUS POP BURST) -->
        <div v-else class="relative w-28 h-28 mx-auto flex items-center justify-center">
          <svg class="w-24 h-24 drop-shadow-[0_0_20px_rgba(236,72,153,0.7)] animate-pulse" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M50 18L54 28L64 22L61 32L72 32L65 40L75 46L64 50L70 60L58 58L60 70L50 62L40 70L42 58L30 60L36 50L25 46L35 40L28 32L39 32L36 22L46 28L50 18Z" fill="#F43F5E" opacity="0.25"/>
            <path d="M32 38C26 44 26 56 34 62C36 55 35 46 32 38Z" fill="url(#balloon_grad)" stroke="#E11D48" stroke-width="1.5"/>
            <path d="M68 38C74 44 74 56 66 62C64 55 65 46 68 38Z" fill="url(#balloon_grad)" stroke="#E11D48" stroke-width="1.5"/>
            <path d="M42 26C47 22 53 22 58 26C55 32 45 32 42 26Z" fill="url(#balloon_grad)" stroke="#E11D48" stroke-width="1.5"/>
            <path d="M46 72L54 72L52 76L48 76L46 72Z" fill="#BE123C"/>
            <path d="M50 76C48 82 54 86 50 92" stroke="#CBD5E1" stroke-width="2" stroke-linecap="round" fill="none"/>
            <path d="M78 20L58 38" stroke="#E2E8F0" stroke-width="3" stroke-linecap="round"/>
            <path d="M58 38L54 42" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round"/>
            <circle cx="80" cy="18" r="4" fill="#EF4444"/>
            <defs>
              <linearGradient id="balloon_grad" x1="26" y1="22" x2="74" y2="66" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FB7185"/>
                <stop offset="0.6" stop-color="#F43F5E"/>
                <stop offset="1" stop-color="#BE123C"/>
              </linearGradient>
            </defs>
          </svg>
          <span class="absolute -top-1 left-2 text-2xl animate-bounce">🎈</span>
          <span class="absolute -bottom-1 right-1 text-xl">📌</span>
        </div>

        <!-- Text Feedback -->
        <div class="space-y-1.5">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-500/20 border border-rose-500/40 text-rose-300 font-mono text-xs font-black">
            <span>{{ gameFeedback.tag }}</span>
            <span>•</span>
            <span>+0 XP</span>
          </div>
          <h4 :class="['text-xl font-black text-transparent bg-clip-text bg-gradient-to-r', gameFeedback.titleGradient]">
            {{ gameFeedback.title }}
          </h4>
          <p class="text-xs sm:text-sm text-gray-200 font-medium leading-relaxed">
            {{ gameFeedback.message }}
          </p>
        </div>

        <p class="text-[10px] text-rose-300/70 font-mono">
          * Ketuk di mana saja untuk lanjut
        </p>
      </div>
    </div>
    <!-- ======================================================================= -->
    <!-- LIVE LEADERBOARD / KLASEMEN SEMENTARA UJIAN MODAL                       -->
    <!-- ======================================================================= -->
    <div
      v-if="showLiveLeaderboardModal"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-in fade-in duration-200"
      @click.self="showLiveLeaderboardModal = false"
    >
      <div class="relative w-full max-w-lg p-5 sm:p-7 rounded-3xl bg-slate-900 border border-amber-500/40 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-start justify-between gap-3 border-b border-white/10 pb-3 shrink-0">
          <div class="flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-300 flex items-center justify-center text-xl shrink-0 border border-amber-500/30">
              🏆
            </div>
            <div>
              <h3 class="text-base sm:text-lg font-black text-white">Klasemen Sementara Ujian</h3>
              <p class="text-xs text-cyan-300 font-medium line-clamp-1">{{ activeExam?.judulUjian }}</p>
            </div>
          </div>
          <button
            @click="showLiveLeaderboardModal = false"
            class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
          >
            ✕
          </button>
        </div>

        <!-- Motivation Pill inside modal -->
        <div class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-200 text-xs font-medium shrink-0 flex items-center gap-2">
          <span class="text-lg">⚡</span>
          <span>{{ liveRankingInfo.motivationText }}</span>
        </div>

        <!-- Scrollable Leaderboard List -->
        <div class="overflow-y-auto space-y-2 pr-1 flex-1 min-h-0 divide-y divide-white/5">
          <div
            v-for="(p, idx) in liveRankingInfo.leaderboard"
            :key="'live-rank-' + idx"
            :class="[
              'p-3 rounded-2xl flex items-center justify-between gap-3 transition-all',
              p.isSelf
                ? 'bg-gradient-to-r from-cyan-950/80 to-blue-950/80 border-2 border-cyan-400/80 shadow-lg shadow-cyan-500/20'
                : 'bg-white/[0.02] border border-white/5'
            ]"
          >
            <div class="flex items-center gap-3 min-w-0">
              <!-- Rank Medal / Number -->
              <div
                class="w-8 h-8 rounded-xl flex items-center justify-center font-black text-xs shrink-0 font-mono"
                :class="[
                  idx === 0 ? 'bg-amber-400/25 border border-amber-400/50 text-amber-300 text-sm' :
                  idx === 1 ? 'bg-slate-400/25 border border-slate-300/40 text-slate-200 text-sm' :
                  idx === 2 ? 'bg-amber-700/30 border border-amber-600/40 text-amber-300 text-sm' :
                  'bg-white/5 border border-white/10 text-gray-400'
                ]"
              >
                <span v-if="idx === 0">🥇</span>
                <span v-else-if="idx === 1">🥈</span>
                <span v-else-if="idx === 2">🥉</span>
                <span v-else>#{{ idx + 1 }}</span>
              </div>

              <!-- Student Name & Badge -->
              <div class="min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="font-black text-xs sm:text-sm text-white truncate">{{ p.nama }}</span>
                  <span v-if="p.isSelf" class="px-2 py-0.5 rounded-full bg-cyan-500/25 text-cyan-300 border border-cyan-400/40 text-[9px] font-black uppercase">
                    Kamu
                  </span>
                </div>
                <span class="text-[10px] text-gray-400 font-mono">NIS: {{ p.nis }}</span>
              </div>
            </div>

            <!-- Score / Status -->
            <div class="text-right shrink-0">
              <span class="block font-mono font-black text-sm" :class="p.isSelf ? 'text-cyan-300' : 'text-amber-300'">
                {{ p.nilai }} Poin
              </span>
              <span class="text-[10px] font-mono text-gray-400">
                {{ p.isSelf ? (isGameMode ? 'Skor Saat Ini' : 'Progres') : 'Selesai' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="pt-3 border-t border-white/10 shrink-0">
          <button
            @click="showLiveLeaderboardModal = false"
            class="w-full py-3 px-4 rounded-2xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-amber-500/20 active:scale-95 transition-all min-h-[44px]"
          >
            Lanjut Mengerjakan Soal 🚀
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.exam-room-container {
  overscroll-behavior-y: contain;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}

@keyframes wobble {
  0%, 100% { transform: translateX(0) rotate(0deg); }
  15% { transform: translateX(-6px) rotate(-4deg); }
  30% { transform: translateX(5px) rotate(4deg); }
  45% { transform: translateX(-4px) rotate(-2deg); }
  60% { transform: translateX(3px) rotate(2deg); }
  75% { transform: translateX(-1px) rotate(-1deg); }
}

.animate-wobble {
  animation: wobble 0.6s ease-in-out;
}
</style>
