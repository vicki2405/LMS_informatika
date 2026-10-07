export const materiData = [
  {
    id: 1,
    judul: 'Perangkat Keras & Komputer (Hardware)',
    kategori: 'Teknologi Komputer',
    ikon: '💻',
    warna: 'from-blue-500 to-cyan-500',
    deskripsiSingkat: 'Memahami arsitektur komputer, fungsi komponen internal seperti CPU, RAM, Motherboard, serta perangkat Input & Output.',
    progress: 0,
    subMateri: [
      {
        id: '1-1',
        judul: '1. Pengenalan Komponen Komputer',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Pengenalan Komponen Komputer</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Komputer secara garis besar terdiri dari komponen fisik yang disebut <strong>Hardware</strong>. Komponen ini saling bekerja sama untuk mengolah data masukan (Input) menjadi informasi keluaran (Output).</p>
          <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded-r-xl">
            <h4 class="font-bold text-blue-800 mb-1">Empat Fungsi Utama Komputer (IPOPS):</h4>
            <ul class="list-disc list-inside text-blue-700 space-y-1 text-sm">
              <li><strong>Input</strong>: Memasukkan data (Keyboard, Mouse)</li>
              <li><strong>Processing</strong>: Mengolah data (CPU/Processor)</li>
              <li><strong>Output</strong>: Menampilkan hasil (Monitor, Printer)</li>
              <li><strong>Storage</strong>: Menyimpan data (SSD, Harddisk)</li>
            </ul>
          </div>
        `
      },
      {
        id: '1-2',
        judul: '2. CPU dan Memory (RAM & ROM)',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Central Processing Unit (CPU) & Memori</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">CPU atau sering disebut sebagai "otak" komputer bertanggung jawab untuk mengeksekusi instruksi program dan mengolah data.</p>
          <p class="text-gray-600 mb-4 leading-relaxed"><strong>RAM (Random Access Memory)</strong> berfungsi sebagai memori kerja sementara yang bersifat <em>volatile</em> (data hilang saat listrik mati), sedangkan <strong>ROM</strong> menyimpan instruksi dasar booting (BIOS/UEFI).</p>
        `
      },
      {
        id: '1-3',
        judul: '3. Perangkat Penyimpanan Data (Storage)',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Storage Devices (HDD vs SSD)</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Perangkat penyimpanan digunakan untuk menyimpan data sistem operasi, aplikasi, dan berkas pengguna secara permanen (non-volatile).</p>
          <p class="text-gray-600 mb-4 leading-relaxed"><strong>SSD (Solid State Drive)</strong> menggunakan memori flash NAND yang jauh lebih cepat dibanding <strong>HDD (Hard Disk Drive)</strong> piringan mekanis.</p>
        `
      }
    ]
  },
  {
    id: 2,
    judul: 'Jaringan Komputer & Internet',
    kategori: 'Konektivitas',
    ikon: '🌐',
    warna: 'from-cyan-500 to-teal-500',
    deskripsiSingkat: 'Mempelajari cara kerja jaringan LAN, WAN, protokol TCP/IP, IP Address, serta konsep komunikasi data internet.',
    progress: 0,
    subMateri: [
      {
        id: '2-1',
        judul: '1. Dasar Jaringan Komputer',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Konsep Dasar Jaringan Komputer</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Jaringan komputer adalah sistem yang menghubungkan dua atau lebih komputer untuk saling berbagi data, sumber daya (seperti printer), dan koneksi internet.</p>
        `
      },
      {
        id: '2-2',
        judul: '2. Pengalamatan IP (IP Address & Subnetting)',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">IP Address (IPv4 & IPv6)</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">IP Address adalah identitas unik berupa deretan angka yang diberikan ke setiap perangkat yang terhubung ke jaringan agar dapat saling berkomunikasi.</p>
        `
      },
      {
        id: '2-3',
        judul: '3. Layanan Internet & Web Browser',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Cara Kerja World Wide Web (WWW)</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Web browser mengirim permintaan HTTP/HTTPS ke server web melalui protokol TCP/IP untuk menampilkan halaman situs web kepada pengguna.</p>
        `
      }
    ]
  },
  {
    id: 3,
    judul: 'Keamanan Komputer & Etika Digital',
    kategori: 'Keamanan',
    ikon: '🔒',
    warna: 'from-amber-500 to-orange-500',
    deskripsiSingkat: 'Proteksi data diri, bahaya Malware/Phishing, pentingnya enkripsi, serta etika berinteraksi di dunia digital.',
    progress: 0,
    subMateri: [
      {
        id: '3-1',
        judul: '1. Mengenal Jenis Ancaman Cyber (Malware & Phishing)',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Ancaman Siber di Era Digital</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Pahami berbagai jenis perangkat lunak berbahaya (Malware) seperti Virus, Trojan, Ransomware, serta teknik rekayasa sosial seperti Phishing.</p>
        `
      },
      {
        id: '3-2',
        judul: '2. Melindungi Akun & Data Pribadi',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Autentikasi & Keamanan Kata Sandi</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Gunakan kombinasi kata sandi yang kuat dan aktifkan Two-Factor Authentication (2FA) untuk mengamankan akun pribadi dari pembajakan.</p>
        `
      },
      {
        id: '3-3',
        judul: '3. Etika Komunikasi Digital (Cyberbullying & UU ITE)',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Kewargaan Digital (Digital Citizenship)</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Berinteraksi secara bijak dan sopan di media sosial serta memahami batasan aturan hukum dan privasi orang lain.</p>
        `
      }
    ]
  },
  {
    id: 4,
    judul: 'Algoritma & Pemrograman Dasar',
    kategori: 'Programming',
    ikon: '🧮',
    warna: 'from-indigo-500 to-purple-500',
    deskripsiSingkat: 'Mengembangkan logika berpikir komputasional, struktur flowchart, variabel, percabangan if-else, dan perulangan.',
    progress: 0,
    subMateri: [
      {
        id: '4-1',
        judul: '1. Pengenalan Berpikir Komputasional',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Computational Thinking</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Metode pemecahan masalah dengan 4 pilar: Dekomposisi, Pengenalan Pola, Abstraksi, dan Perancangan Algoritma.</p>
        `
      },
      {
        id: '4-2',
        judul: '2. Flowchart dan Pseudocode',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Visualisasi Logika Program</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Menulis urutan langkah penyelesaian masalah menggunakan diagram alir (Flowchart) dan bahasa semu (Pseudocode).</p>
        `
      },
      {
        id: '4-3',
        judul: '3. Variabel, Tipe Data, dan Percabangan',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Dasar Bahasa Pemrograman</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Memahami tempat penyimpanan data sementara (Variabel), tipe data (String, Integer, Boolean), serta instruksi kondisi (If-Else).</p>
        `
      }
    ]
  },
  {
    id: 5,
    judul: 'Analisis Data & Spreadsheet',
    kategori: 'Data Science',
    ikon: '📊',
    warna: 'from-emerald-500 to-green-500',
    deskripsiSingkat: 'Mengolah data tabel, menggunakan rumus statistik dasar (SUM, AVERAGE, VLOOKUP), dan membuat grafik data.',
    progress: 0,
    subMateri: [
      {
        id: '5-1',
        judul: '1. Dasar Pengolahan Lembar Kerja',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Navigasi Sel dan Format Data</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Mengatur baris, kolom, dan tipe format data pada aplikasi spreadsheet seperti Microsoft Excel atau Google Sheets.</p>
        `
      },
      {
        id: '5-2',
        judul: '2. Fungsi dan Rumus Statistik Dasar',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Formulasi Matematika & Otomasi</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Menggunakan fungsi matematika otomatis: SUM, AVERAGE, COUNT, MAX, MIN untuk analisis data numerik.</p>
        `
      },
      {
        id: '5-3',
        judul: '3. Visualisasi Data dengan Grafik',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Grafik Batang, Garis, dan Pai</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Mengubah kumpulan data mentah menjadi diagram visual yang mudah dipahami oleh pembaca.</p>
        `
      }
    ]
  },
  {
    id: 6,
    judul: 'Dampak Sosial Informatika (DSI)',
    kategori: 'Sosial & Karir',
    ikon: '🚀',
    warna: 'from-violet-500 to-pink-500',
    deskripsiSingkat: 'Perkembangan teknologi bagi dunia kerja, profesi di bidang IT, dan transformasi digital di Indonesia.',
    progress: 0,
    subMateri: [
      {
        id: '6-1',
        judul: '1. Rintisan Karir Bidang IT untuk SMK',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Peluang Karir Lulusan SMK Informatika</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Mengenal profesi populer: Network Engineer, Web Developer, System Administrator, UI/UX Designer, dan IT Support.</p>
        `
      },
      {
        id: '6-2',
        judul: '2. Transformasi Digital dan Ekonomi Kreatif',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Pemanfaatan Teknologi untuk Bisnis</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Peran e-commerce, startup digital, serta pembayaran cashless dalam kehidupan bermasyarakat.</p>
        `
      },
      {
        id: '6-3',
        judul: '3. Hak Kekayaan Intelektual (HKI) & Lisensi Software',
        konten: `
          <h3 class="text-xl font-bold text-gray-800 mb-4">Lisensi Open Source vs Proprietary</h3>
          <p class="text-gray-600 mb-4 leading-relaxed">Memahami hak cipta karya digital, bahaya software bajakan, dan jenis lisensi perangkat lunak.</p>
        `
      }
    ]
  }
];

export const siswaDemo = [
  { nis: '1001', nama: 'Ahmad Rizky', kelas: 'X RPL 1', password: 'siswa123' },
  { nis: '1002', nama: 'Siti Nurhaliza', kelas: 'X TKJ 2', password: 'siswa123' },
  { nis: '1003', nama: 'Budi Santoso', kelas: 'X DKV 1', password: 'siswa123' }
];
