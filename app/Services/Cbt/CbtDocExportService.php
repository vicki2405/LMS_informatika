<?php

namespace App\Services\Cbt;

use App\Models\CbtBankSoal;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CbtDocExportService
{
    /**
     * Export CBT Bank Soal to Microsoft Word (.doc) format.
     */
    public function export(CbtBankSoal $bankSoal): Response
    {
        $bankSoal->loadMissing([
            'soals' => fn ($q) => $q->orderBy('nomor_urut', 'asc')->orderBy('id', 'asc'),
            'mataPelajaran',
            'kelas',
            'jurusan',
            'guru',
        ]);

        $soals = $bankSoal->soals;
        $jumlahPilihan = (int) ($bankSoal->jumlah_pilihan ?: 5);

        $docContent = $this->generateDocHtml($bankSoal, $soals, $jumlahPilihan);

        $cleanTitle = Str::slug($bankSoal->judul ?: 'soal', '_');
        $cleanKode = Str::slug($bankSoal->kode ?: 'bank', '_');
        $filename = "Soal_{$cleanKode}_{$cleanTitle}.doc";

        return response($docContent, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Build Word-compatible HTML document with standard font, spacing, and question-option structure.
     */
    protected function generateDocHtml(CbtBankSoal $bankSoal, $soals, int $jumlahPilihan): string
    {
        $soalBlocks = '';
        foreach ($soals as $index => $soal) {
            $nomor = $index + 1;
            $pertanyaan = htmlspecialchars($this->cleanText($soal->pertanyaan), ENT_QUOTES, 'UTF-8');
            $opsiA = htmlspecialchars($this->cleanText($soal->opsi_a), ENT_QUOTES, 'UTF-8');
            $opsiB = htmlspecialchars($this->cleanText($soal->opsi_b), ENT_QUOTES, 'UTF-8');
            $opsiC = htmlspecialchars($this->cleanText($soal->opsi_c), ENT_QUOTES, 'UTF-8');
            $opsiD = htmlspecialchars($this->cleanText($soal->opsi_d), ENT_QUOTES, 'UTF-8');
            $opsiE = htmlspecialchars($this->cleanText($soal->opsi_e), ENT_QUOTES, 'UTF-8');
            $kunci = strtoupper(trim((string) $soal->kunci_jawaban));

            $soalBlocks .= "<div class=\"question-item\">\n";
            $soalBlocks .= "  <p class=\"p-soal\">{$nomor}. {$pertanyaan}</p>\n";
            $soalBlocks .= "  <p class=\"p-opsi\">A. {$opsiA}</p>\n";
            $soalBlocks .= "  <p class=\"p-opsi\">B. {$opsiB}</p>\n";
            $soalBlocks .= "  <p class=\"p-opsi\">C. {$opsiC}</p>\n";
            $soalBlocks .= "  <p class=\"p-opsi\">D. {$opsiD}</p>\n";
            if ($jumlahPilihan >= 5 || $opsiE !== '') {
                $soalBlocks .= "  <p class=\"p-opsi\">E. {$opsiE}</p>\n";
            }
            $soalBlocks .= "  <p class=\"p-kunci\">Kunci: {$kunci}</p>\n";
            $soalBlocks .= "  <p class=\"p-spacer\">&nbsp;</p>\n";
            $soalBlocks .= "</div>\n";
        }

        $docTitle = htmlspecialchars($bankSoal->judul ?: 'Bank Soal CBT', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>{$docTitle}</title>
<!--[if gte mso 9]>
<xml>
  <w:WordDocument>
    <w:View>Print</w:View>
    <w:Zoom>100</w:Zoom>
    <w:DoNotOptimizeForBrowser/>
  </w:WordDocument>
</xml>
<![endif]-->
<style>
  @page {
    size: 21.0cm 29.7cm;
    margin: 2.0cm 2.0cm 2.0cm 2.0cm;
    mso-page-orientation: portrait;
  }
  body {
    font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
    font-size: 11pt;
    line-height: 1.35;
    color: #000000;
  }
  p {
    margin: 0 0 2pt 0;
    padding: 0;
  }
  .question-item {
    page-break-inside: avoid;
    margin-bottom: 8pt;
  }
  .p-soal {
    margin-bottom: 2pt;
    font-weight: normal;
  }
  .p-opsi {
    margin-bottom: 2pt;
    font-weight: normal;
  }
  .p-kunci {
    margin-top: 2pt;
    margin-bottom: 4pt;
    font-weight: bold;
  }
  .p-spacer {
    margin: 0 0 6pt 0;
    font-size: 4pt;
  }
</style>
</head>
<body>
{$soalBlocks}
</body>
</html>
HTML;
    }

    /**
     * Clean HTML tags, entity codes, and redundant spacing while preserving clean text.
     */
    protected function cleanText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        // Convert breaks or paragraphs into spaces/clean breaks
        $text = preg_replace('/<br\s*\/?>/i', ' ', $text);
        $text = preg_replace('/<\/p>/i', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize spaces
        $text = preg_replace('/[ \t]+/', ' ', $text);

        return trim($text);
    }
}
