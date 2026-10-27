<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { showSuccessAlert, showToast, confirmDelete } from '@/Utils/sweetalert';

const props = defineProps({
  bankSoal: {
    type: Object,
    required: true,
  },
});

function goBack() {
  if (rawText.value.trim() || csvFile.value) {
    confirmDelete({
      title: 'Tinggalkan Halaman?',
      text: 'Data soal yang belum disimpan akan hilang jika Anda kembali.',
      confirmButtonText: 'Ya, Tinggalkan',
      cancelButtonText: 'Tetap di Sini',
    }).then((result) => {
      if (result.isConfirmed) {
        router.visit('/guru/dashboard?tab=cbt-bank-soal');
      }
    });
    return;
  }
  router.visit('/guru/dashboard?tab=cbt-bank-soal');
}

// Mode: 'word' | 'csv'
const activeMethod = ref('word');

// Input state
const rawText = ref('');
const defaultBobot = ref('2,0');
const showFormatGuide = ref(false);
const isSubmitting = ref(false);

// CSV state
const csvFile = ref(null);
const csvFileName = ref('');

// Live statistics of raw text
const rawStats = computed(() => {
  const text = rawText.value.trim();
  if (!text) return { lines: 0, estimatedQuestions: 0 };
  const lines = text.split(/\r?\n/).filter((l) => l.trim().length > 0);
  const qMatches = text.match(/(?:^|\n)\s*(?:(?:soal|no\.?)\s*)?\d+[\.\)]\s*/gi);
  return {
    lines: lines.length,
    estimatedQuestions: qMatches ? qMatches.length : 0,
  };
});

function loadSampleText() {
  rawText.value = `1. Komponen utama pada komputer yang berfungsi sebagai unit pemroses logika dan aritmetika adalah...
A. Random Access Memory (RAM)
B. Central Processing Unit (CPU)
C. Solid State Drive (SSD)
D. Power Supply Unit (PSU)
E. Video Graphics Array (VGA)
Kunci: B

2. Protokol jaringan yang digunakan untuk mentransfer data halaman web secara aman terenkripsi adalah...
A. HTTP
B. FTP
C. HTTPS
D. SMTP
E. DNS
Kunci: C

3. Perhatikan langkah-langkah meracik kopi berikut:
(1) Panaskan air hingga suhu 90 derajat Celcius.
(2) Giling biji kopi dengan tingkat kehalusan medium.
(3) Tuangkan air secara perlahan dengan gerakan memutar.
(4) Tunggu proses ekstraksi selama 3 menit.
Langkah pertama yang dilakukan sebelum menuangkan air adalah...
A. Menunggu ekstraksi
B. Memanaskan air dan menggiling kopi
C. Mendinginkan cangkir
D. Menambahkan susu kental manis
E. Menyaring ampas
Kunci: B`;
  showToast('Contoh format soal berhasil dimuat ke dalam kolom teks.', 'info');
}

// -------------------------------------------------------------
// PARSE WORD TEXT QUESTIONS
// -------------------------------------------------------------
function parseWordText(text, defaultBobotClean) {
  const rawLines = text.split(/\r?\n/);
  const items = [];
  let currentItem = null;

  const questionStartRegex = /^\s*(?:(?:soal|no\.?)\s*)?(\d+)[\.\)]\s*(.*)$/i;
  const explicitQuestionStartRegex = /^\s*(?:soal|no\.?)\s*\d+[\.\)]\s*/i;
  const startOptRegex = /^\s*(?:([A-Ea-e])\s*[\.\-\)\:]|\(([A-Ea-e])\))\s*(.*)$/;
  const keyLineRegex = /^\s*(?:\[|\()? *(?:kunci(?:\s+jawaban)?|jawaban|answer|ans) *[:=\-]+ *(?:opsi\s*|pilihan\s*)?\(?([A-Ea-e])\)? *(?:\]|\))?/i;
  const inlineKeyRegex = /(?:\[|\() *(?:kunci(?:\s+jawaban)?|jawaban|answer|ans) *[:=\-]+ *(?:opsi\s*|pilihan\s*)?([A-Ea-e]) *(?:\]|\))\s*$/i;

  const keyMap = { A: 'opsi_a', B: 'opsi_b', C: 'opsi_c', D: 'opsi_d', E: 'opsi_e' };

  for (let i = 0; i < rawLines.length; i++) {
    let line = rawLines[i].trim();
    if (!line) continue;

    const inlineKeyMatch = line.match(inlineKeyRegex);
    if (inlineKeyMatch && currentItem) {
      currentItem.kunci_jawaban = inlineKeyMatch[1].toUpperCase();
      currentItem.is_key_detected = true;
      line = line.replace(inlineKeyRegex, '').trim();
    }

    const keyMatch = line.match(keyLineRegex);
    if (keyMatch && currentItem) {
      currentItem.kunci_jawaban = keyMatch[1].toUpperCase();
      currentItem.is_key_detected = true;
      continue;
    }

    const optStartMatch = line.match(startOptRegex);
    if (optStartMatch && currentItem && (currentItem.pertanyaan || currentItem.hasStartedOptions)) {
      currentItem.hasStartedOptions = true;
      const firstLetter = (optStartMatch[1] || optStartMatch[2]).toUpperCase();
      const rest = optStartMatch[3] ? optStartMatch[3].trim() : '';

      const inlineOptRegex = /(?:\s{2,}|\t|\s+)(?:([B-Eb-e])\s*[\.\-\)\:]|\(([B-Eb-e])\))\s*/g;
      const subMatches = [];
      let m;
      while ((m = inlineOptRegex.exec(rest)) !== null) {
        subMatches.push({
          index: m.index,
          length: m[0].length,
          letter: (m[1] || m[2]).toUpperCase(),
        });
      }

      if (subMatches.length > 0) {
        const firstText = rest.substring(0, subMatches[0].index).trim();
        if (keyMap[firstLetter]) {
          currentItem[keyMap[firstLetter]] = firstText;
          currentItem.activeOption = keyMap[firstLetter];
        }

        for (let j = 0; j < subMatches.length; j++) {
          const currentM = subMatches[j];
          const nextIndex = j + 1 < subMatches.length ? subMatches[j + 1].index : rest.length;
          const optText = rest.substring(currentM.index + currentM.length, nextIndex).trim();
          if (keyMap[currentM.letter]) {
            currentItem[keyMap[currentM.letter]] = optText;
            currentItem.activeOption = keyMap[currentM.letter];
          }
        }
      } else {
        if (keyMap[firstLetter]) {
          currentItem[keyMap[firstLetter]] = rest;
          currentItem.activeOption = keyMap[firstLetter];
        }
      }
      continue;
    }

    const canStartNewQuestion = !currentItem || currentItem.hasStartedOptions || explicitQuestionStartRegex.test(line);

    if (canStartNewQuestion) {
      const qMatch = line.match(questionStartRegex);
      if (qMatch) {
        const qText = (qMatch[2] !== undefined ? qMatch[2] : '').trim();
        if (currentItem && (currentItem.pertanyaan || currentItem.hasStartedOptions)) {
          items.push(finalizeParsedItem(currentItem, defaultBobotClean));
        }
        currentItem = {
          nomor_urut: items.length + 1,
          pertanyaan: qText,
          opsi_a: '',
          opsi_b: '',
          opsi_c: '',
          opsi_d: '',
          opsi_e: '',
          kunci_jawaban: '',
          is_key_detected: false,
          bobot: defaultBobotClean,
          hasStartedOptions: false,
          activeOption: null,
          posisi_media_soal: 'bawah',
        };
        continue;
      }
    }

    if (currentItem) {
      if (!currentItem.hasStartedOptions) {
        currentItem.pertanyaan += (currentItem.pertanyaan ? '\n' : '') + line;
      } else if (currentItem.activeOption) {
        currentItem[currentItem.activeOption] += (currentItem[currentItem.activeOption] ? '\n' : '') + line;
      }
    }
  }

  if (currentItem && (currentItem.pertanyaan || currentItem.hasStartedOptions)) {
    items.push(finalizeParsedItem(currentItem, defaultBobotClean));
  }

  return items;
}

function finalizeParsedItem(item, defaultBobotVal) {
  return {
    nomor_urut: item.nomor_urut,
    pertanyaan: (item.pertanyaan || '').trim(),
    opsi_a: (item.opsi_a || '').trim(),
    opsi_b: (item.opsi_b || '').trim(),
    opsi_c: (item.opsi_c || '').trim(),
    opsi_d: (item.opsi_d || '').trim(),
    opsi_e: (item.opsi_e || '').trim(),
    kunci_jawaban: item.kunci_jawaban || '',
    bobot: item.bobot || defaultBobotVal,
    posisi_media_soal: item.posisi_media_soal || 'bawah',
  };
}

// -------------------------------------------------------------
// CSV TEMPLATE & PARSER
// -------------------------------------------------------------
function downloadCsvTemplate() {
  const headers = ['No', 'Pertanyaan', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E', 'Kunci Jawaban', 'Bobot'];
  const sampleRows = [
    ['1', 'Ibukota negara Indonesia saat ini adalah...', 'Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang', 'A', '2.0'],
    ['2', 'Organ pernapasan utama pada manusia adalah...', 'Jantung', 'Paru-paru', 'Hati', 'Ginjal', 'Lambung', 'B', '2.0'],
    ['3', 'Satuan besaran arus listrik menurut SI adalah...', 'Volt', 'Watt', 'Ampere', 'Ohm', 'Joule', 'C', '2.0'],
  ];

  const csvContent =
    '\uFEFF' +
    [
      headers.map((h) => `"${h}"`).join(','),
      ...sampleRows.map((row) => row.map((cell) => `"${cell.replace(/"/g, '""')}"`).join(',')),
    ].join('\r\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.setAttribute('download', `Template_Soal_${props.bankSoal.kode || 'CBT'}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function handleCsvFileSelect(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  csvFile.value = file;
  csvFileName.value = file.name;
}

function parseCsvFilePromise(file, cleanDefaultBobot) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (evt) => {
      try {
        const text = evt.target.result;
        const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
        if (lines.length < 2) {
          reject(new Error('File CSV kosong atau tidak memiliki baris data.'));
          return;
        }

        const delimiter = detectDelimiter(lines[0]);
        const headerCells = parseCsvLine(lines[0], delimiter).map((c) => c.toLowerCase().trim());

        let colQ = headerCells.findIndex((c) => /pertanyaan|soal|question/i.test(c));
        let colA = headerCells.findIndex((c) => /opsi\s*a|pilihan\s*a|option\s*a/i.test(c));
        let colB = headerCells.findIndex((c) => /opsi\s*b|pilihan\s*b|option\s*b/i.test(c));
        let colC = headerCells.findIndex((c) => /opsi\s*c|pilihan\s*c|option\s*c/i.test(c));
        let colD = headerCells.findIndex((c) => /opsi\s*d|pilihan\s*d|option\s*d/i.test(c));
        let colE = headerCells.findIndex((c) => /opsi\s*e|pilihan\s*e|option\s*e/i.test(c));
        let colKey = headerCells.findIndex((c) => /kunci|jawaban|answer|key/i.test(c));
        let colBobot = headerCells.findIndex((c) => /bobot|poin|nilai|score/i.test(c));

        if (colQ === -1) colQ = headerCells.length > 8 ? 1 : 0;
        if (colA === -1) colA = colQ + 1;
        if (colB === -1) colB = colQ + 2;
        if (colC === -1) colC = colQ + 3;
        if (colD === -1) colD = colQ + 4;
        if (colE === -1) colE = colQ + 5;
        if (colKey === -1) colKey = colQ + 6;
        if (colBobot === -1) colBobot = colQ + 7;

        const items = [];
        for (let i = 1; i < lines.length; i++) {
          const rawLine = lines[i].trim();
          if (!rawLine) continue;

          const cells = parseCsvLine(rawLine, delimiter);
          const qText = (cells[colQ] || '').trim();
          if (!qText) continue;

          const optA = (cells[colA] || '').trim();
          const optB = (cells[colB] || '').trim();
          const optC = (cells[colC] || '').trim();
          const optD = (cells[colD] || '').trim();
          const optE = (cells[colE] || '').trim();

          let keyRaw = (cells[colKey] || '').trim().toUpperCase();
          const keyMatch = keyRaw.match(/([A-E])/);
          const validKey = keyMatch ? keyMatch[1] : '';

          const bobotCell = (cells[colBobot] || '').trim();
          const bobotVal = bobotCell ? bobotCell.replace(',', '.') : cleanDefaultBobot;

          items.push({
            nomor_urut: items.length + 1,
            pertanyaan: qText,
            opsi_a: optA,
            opsi_b: optB,
            opsi_c: optC,
            opsi_d: optD,
            opsi_e: optE,
            kunci_jawaban: validKey,
            bobot: bobotVal,
            posisi_media_soal: 'bawah',
          });
        }
        resolve(items);
      } catch (err) {
        reject(err);
      }
    };
    reader.onerror = () => reject(new Error('Gagal membaca file CSV.'));
    reader.readAsText(file);
  });
}

function detectDelimiter(firstLine) {
  const countComma = (firstLine.match(/,/g) || []).length;
  const countSemicolon = (firstLine.match(/;/g) || []).length;
  const countTab = (firstLine.match(/\t/g) || []).length;
  if (countSemicolon > countComma && countSemicolon > countTab) return ';';
  if (countTab > countComma && countTab > countSemicolon) return '\t';
  return ',';
}

function parseCsvLine(line, delimiter) {
  const cells = [];
  let current = '';
  let inQuotes = false;

  for (let i = 0; i < line.length; i++) {
    const char = line[i];
    if (char === '"') {
      if (inQuotes && line[i + 1] === '"') {
        current += '"';
        i++;
      } else {
        inQuotes = !inQuotes;
      }
    } else if (char === delimiter && !inQuotes) {
      cells.push(current.trim());
      current = '';
    } else {
      current += char;
    }
  }
  cells.push(current.trim());
  return cells;
}

// -------------------------------------------------------------
// SINGLE ACTION: SIMPAN & IMPORT LANGSUNG KE BANK SOAL
// -------------------------------------------------------------
async function handleImportAndSave() {
  const cleanBobot = defaultBobot.value ? String(defaultBobot.value).trim() : '2,0';
  let questions = [];

  if (activeMethod.value === 'word') {
    const text = rawText.value.trim();
    if (!text) {
      showToast('Silakan tempel teks soal terlebih dahulu!', 'error');
      return;
    }
    questions = parseWordText(text, cleanBobot);
  } else {
    if (!csvFile.value) {
      showToast('Silakan pilih file CSV yang ingin diimpor!', 'error');
      return;
    }
    try {
      questions = await parseCsvFilePromise(csvFile.value, cleanBobot);
    } catch (e) {
      showToast(e.message || 'Gagal memproses file CSV.', 'error');
      return;
    }
  }

  if (!questions || questions.length === 0) {
    showToast('Tidak ada butir soal yang berhasil dikenali. Pastikan penomoran diawali angka (contoh: 1. ...) dan opsi A-E.', 'error');
    return;
  }

  // Validate each question
  for (let i = 0; i < questions.length; i++) {
    const q = questions[i];
    if (!q.pertanyaan || !q.pertanyaan.trim()) {
      showToast(`Soal nomor ${q.nomor_urut} tidak memiliki teks pertanyaan!`, 'error');
      return;
    }
    if (!q.opsi_a || !q.opsi_a.trim() || !q.opsi_b || !q.opsi_b.trim() || !q.opsi_c || !q.opsi_c.trim() || !q.opsi_d || !q.opsi_d.trim()) {
      showToast(`Soal nomor ${q.nomor_urut} harus memiliki minimal 4 pilihan jawaban (A, B, C, D)!`, 'error');
      return;
    }
    if (!q.kunci_jawaban) {
      showToast(`Soal nomor ${q.nomor_urut} belum memiliki kunci jawaban (Kunci: A/B/C/D/E)! Harap lengkapi kunci jawaban pada soal tersebut.`, 'error');
      return;
    }
  }

  // Submit directly to backend and redirect to dashboard tab=cbt-bank-soal
  isSubmitting.value = true;
  const payload = {
    bank_soal_id: props.bankSoal.id,
    redirect_to: '/guru/dashboard?tab=cbt-bank-soal',
    soals: questions.map((q) => ({
      pertanyaan: q.pertanyaan,
      opsi_a: q.opsi_a,
      opsi_b: q.opsi_b,
      opsi_c: q.opsi_c,
      opsi_d: q.opsi_d,
      opsi_e: q.opsi_e || '-',
      kunci_jawaban: q.kunci_jawaban,
      bobot: q.bobot ? String(q.bobot).replace(',', '.') : '1.0',
      posisi_media_soal: q.posisi_media_soal || 'bawah',
    })),
  };

  router.post(route('guru.cbt.soal.import-bulk'), payload, {
    preserveScroll: false,
    onSuccess: () => {
      isSubmitting.value = false;
      showSuccessAlert(
        'Import Soal Berhasil!',
        `Sebanyak ${payload.soals.length} butir soal telah berhasil disimpan ke dalam ${props.bankSoal.judul}.`
      );
    },
    onError: (err) => {
      isSubmitting.value = false;
      const msg = Object.values(err)[0] || 'Gagal mengimpor soal. Periksa kembali kelengkapan data.';
      showToast(msg, 'error');
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}
</script>

<template>
  <Head :title="`Import Butir Soal: ${bankSoal.judul} — SMKS Muhammadiyah 2 Genteng`" />

  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans pb-20">
    <!-- TOP NAVBAR -->
    <header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-white/10">
      <div class="w-full max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-4">
        <!-- Back & Title -->
        <div class="flex items-center gap-3 min-w-0">
          <button
            @click="goBack"
            type="button"
            class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white border border-white/10 text-xs font-semibold transition-all flex items-center gap-1.5 min-h-[38px] shrink-0"
            title="Kembali ke Bank Soal"
          >
            <span>←</span>
            <span>Kembali</span>
          </button>

          <div class="min-w-0">
            <div class="flex items-center gap-2 text-[11px] text-gray-400">
              <span class="hover:underline cursor-pointer" @click="goBack">CBT</span>
              <span>/</span>
              <span class="hover:underline cursor-pointer" @click="goBack">Bank Soal</span>
              <span>/</span>
              <span class="text-teal-400 font-medium">Import Massal</span>
            </div>
            <h1 class="text-sm sm:text-base font-bold text-white truncate">
              Import Butir Soal: {{ bankSoal.judul }}
            </h1>
          </div>
        </div>
      </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="w-full max-w-6xl mx-auto px-4 sm:px-6 pt-6 space-y-6">
      
      <!-- BANK SOAL CONTEXT STRIP -->
      <div class="p-4 rounded-2xl bg-slate-900/80 border border-white/10 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-lg">
        <div class="space-y-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="px-2.5 py-0.5 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 font-mono text-xs font-bold">
              {{ bankSoal.kode }}
            </span>
            <span class="font-bold text-white text-sm sm:text-base">{{ bankSoal.judul }}</span>
          </div>
          <div class="flex items-center gap-3 text-xs text-gray-400 flex-wrap">
            <span>📖 <strong class="text-teal-300">{{ bankSoal.mataPelajaran || 'Umum' }}</strong></span>
            <span>•</span>
            <span>Tingkat: <strong class="text-indigo-300">{{ bankSoal.tingkat || '-' }}</strong></span>
            <span v-if="bankSoal.jurusan">• Jurusan: <strong class="text-gray-300">{{ bankSoal.jurusan }}</strong></span>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-xs text-gray-300 font-semibold">
            Soal Tersimpan Saat Ini: <strong class="text-emerald-400 ml-1">{{ bankSoal.existingCount }} Butir</strong>
          </span>
        </div>
      </div>

      <!-- WORKSPACE CARD -->
      <div class="p-5 sm:p-7 rounded-2xl bg-slate-900/70 border border-white/10 space-y-6 shadow-xl">
        
        <!-- Header & Method Selector -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/10 pb-4">
          <div>
            <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
              <span>Masukkan Data Soal</span>
            </h2>
            <p class="text-xs text-gray-400 mt-0.5">
              Tempel teks langsung dari Word/PDF atau unggah file berformat CSV.
            </p>
          </div>

          <!-- Method Switcher (Word vs CSV) -->
          <div class="inline-flex p-1 rounded-xl bg-slate-950/70 border border-white/10 shrink-0">
            <button
              type="button"
              @click="activeMethod = 'word'"
              class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
              :class="activeMethod === 'word' ? 'bg-cyan-500 text-slate-950 shadow' : 'text-gray-400 hover:text-white'"
            >
              <span>📝 Teks Word</span>
            </button>
            <button
              type="button"
              @click="activeMethod = 'csv'"
              class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
              :class="activeMethod === 'csv' ? 'bg-cyan-500 text-slate-950 shadow' : 'text-gray-400 hover:text-white'"
            >
              <span>📊 File CSV</span>
            </button>
          </div>
        </div>

        <!-- METHOD A: WORD TEXTAREA -->
        <div v-if="activeMethod === 'word'" class="space-y-4">
          <!-- Settings row -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <label class="text-xs text-gray-300 font-semibold flex items-center gap-1.5">
                <span>Default Bobot per Soal:</span>
                <input
                  v-model="defaultBobot"
                  type="text"
                  class="w-20 px-2.5 py-1.5 rounded-lg bg-slate-950 border border-white/20 text-teal-300 font-mono text-xs font-bold text-center focus:outline-none focus:border-teal-400"
                  placeholder="2,0"
                />
              </label>

              <button
                type="button"
                @click="showFormatGuide = !showFormatGuide"
                class="text-xs text-cyan-400 hover:underline flex items-center gap-1"
              >
                <span>{{ showFormatGuide ? 'Sembunyikan Panduan Format' : 'Lihat Panduan Format' }}</span>
              </button>
            </div>

            <button
              type="button"
              @click="loadSampleText"
              class="text-xs text-gray-400 hover:text-cyan-300 transition-colors flex items-center gap-1 self-start sm:self-auto"
            >
              <span>⚡ Muat Contoh Format Soal</span>
            </button>
          </div>

          <!-- Format Guide Drawer -->
          <div v-if="showFormatGuide" class="p-4 rounded-xl bg-slate-950/80 border border-cyan-500/20 text-xs text-gray-300 space-y-2">
            <p class="font-bold text-cyan-300">Format Penulisan Soal yang Didukung:</p>
            <ul class="list-disc list-inside space-y-1 text-gray-400 text-[11.5px]">
              <li>Awali setiap soal dengan nomor urut (contoh: <code class="text-teal-300">1.</code>, <code class="text-teal-300">2)</code>, atau <code class="text-teal-300">Soal 3.</code>).</li>
              <li>Langkah bernomor seperti <code class="text-teal-300">(1)</code>, <code class="text-teal-300">(2)</code> dalam teks soal tetap terjaga sebagai satu pertanyaan.</li>
              <li>Pilihan ganda diawali huruf <code class="text-teal-300">A.</code> sampai <code class="text-teal-300">E.</code> (minimal 4 pilihan: A, B, C, D).</li>
              <li>Kunci jawaban ditulis pada baris terpisah dengan format <code class="text-teal-300">Kunci: B</code> atau di akhir opsi <code class="text-teal-300">[Kunci: B]</code>.</li>
            </ul>
          </div>

          <!-- Textarea -->
          <div class="relative">
            <textarea
              v-model="rawText"
              rows="13"
              placeholder="Tempelkan butir-butir soal Anda di sini...&#10;&#10;Contoh:&#10;1. Ibu kota negara Indonesia saat ini adalah...&#10;A. Jakarta&#10;B. Bandung&#10;C. Surabaya&#10;D. Medan&#10;E. Semarang&#10;Kunci: A"
              class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-white/20 text-gray-100 placeholder-gray-500 text-xs sm:text-sm font-mono leading-relaxed focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400"
            ></textarea>
          </div>

          <!-- Live Indicator & Clear -->
          <div class="flex items-center justify-between text-xs text-gray-400 pt-1">
            <div class="flex items-center gap-2">
              <span>{{ rawStats.lines }} baris terdeteksi</span>
              <span>•</span>
              <span>Estimasi: <strong class="text-cyan-300 font-mono">~{{ rawStats.estimatedQuestions }} butir soal</strong></span>
            </div>
            <button
              v-if="rawText.trim()"
              type="button"
              @click="rawText = ''"
              class="text-rose-400 hover:underline text-xs"
            >
              Kosongkan Teks
            </button>
          </div>

          <!-- Single Primary Action Button -->
          <div class="pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-gray-400">
              💡 Soal akan langsung diekstrak, divalidasi, dan disimpan ke database Bank Soal.
            </p>

            <button
              type="button"
              @click="handleImportAndSave"
              :disabled="isSubmitting || !rawText.trim()"
              class="w-full sm:w-auto px-7 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 disabled:opacity-40 disabled:cursor-not-allowed text-slate-950 font-black text-sm shadow-xl shadow-emerald-500/20 active:scale-95 transition-all flex items-center justify-center gap-2 min-h-[46px]"
            >
              <span v-if="isSubmitting" class="animate-spin text-base">⏳</span>
              <span v-else>💾</span>
              <span>{{ isSubmitting ? 'Menyimpan ke Bank Soal...' : 'Simpan & Import ke Bank Soal' }}</span>
            </button>
          </div>
        </div>

        <!-- METHOD B: CSV UPLOAD -->
        <div v-else class="space-y-5">
          <div class="p-8 rounded-2xl border-2 border-dashed border-white/20 hover:border-cyan-400/50 bg-slate-950/60 transition-colors text-center space-y-4">
            <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 text-cyan-400 mx-auto flex items-center justify-center text-xl">
              📊
            </div>
            <div class="space-y-1">
              <h3 class="text-sm font-bold text-white">Unggah File CSV Soal CBT</h3>
              <p class="text-xs text-gray-400">
                Gunakan template CSV resmi agar urutan kolom soal, opsi pilihan ganda, dan kunci jawaban terbaca sempurna.
              </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-2 flex-wrap">
              <label class="px-5 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs cursor-pointer transition-all shadow min-h-[38px] inline-flex items-center gap-1.5">
                <span>📁 {{ csvFileName ? 'Ganti File CSV' : 'Pilih File CSV' }}</span>
                <input
                  type="file"
                  accept=".csv,text/csv"
                  class="hidden"
                  @change="handleCsvFileSelect"
                />
              </label>

              <button
                type="button"
                @click="downloadCsvTemplate"
                class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 border border-white/10 text-xs font-semibold transition-all min-h-[38px] inline-flex items-center gap-1.5"
              >
                <span>📥 Unduh Template CSV</span>
              </button>
            </div>

            <!-- Selected File Badge -->
            <div v-if="csvFileName" class="pt-2">
              <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-semibold">
                <span>📄 File Terpilih: {{ csvFileName }}</span>
              </span>
            </div>
          </div>

          <!-- Single Primary Action Button for CSV -->
          <div class="pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-gray-400">
              💡 File CSV akan dibaca dan butir-butir soal langsung tersimpan ke Bank Soal.
            </p>

            <button
              type="button"
              @click="handleImportAndSave"
              :disabled="isSubmitting || !csvFile"
              class="w-full sm:w-auto px-7 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 disabled:opacity-40 disabled:cursor-not-allowed text-slate-950 font-black text-sm shadow-xl shadow-emerald-500/20 active:scale-95 transition-all flex items-center justify-center gap-2 min-h-[46px]"
            >
              <span v-if="isSubmitting" class="animate-spin text-base">⏳</span>
              <span v-else>💾</span>
              <span>{{ isSubmitting ? 'Menyimpan ke Bank Soal...' : 'Simpan & Import ke Bank Soal' }}</span>
            </button>
          </div>
        </div>

      </div>

    </main>
  </div>
</template>
