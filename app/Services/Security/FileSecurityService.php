<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class FileSecurityService
{
    /**
     * Dangerous extensions that must never be uploaded or present in filenames (e.g. double extension).
     */
    protected static array $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'sh', 'bash', 'zsh', 'ksh', 'csh',
        'exe', 'bat', 'cmd', 'com', 'msi', 'vbs', 'ps1',
        'cgi', 'pl', 'py', 'rb', 'jar', 'jsp', 'asp', 'aspx',
        'htaccess', 'htpasswd', 'ini', 'env', 'conf'
    ];

    /**
     * Known PHP executable opening tags and patterns.
     */
    protected static array $dangerousPayloadSignatures = [
        '<?php',
        '<?=',
        '<script language="php"',
        '<script language=\'php\'',
        '__halt_compiler()',
        'eval(',
        'base64_decode(',
        'system(',
        'shell_exec(',
        'passthru(',
        'proc_open(',
        'popen(',
    ];

    /**
     * Validate an uploaded file for web security:
     * 1. Extension and filename sanity (blocks double extensions like shell.php.png).
     * 2. Deep binary and text content scanning for PHP opening tags and executable scripts.
     *
     * @param UploadedFile $file
     * @param array $allowedExtensions
     * @throws ValidationException
     */
    public static function validateUploadedFile(UploadedFile $file, array $allowedExtensions = []): void
    {
        $originalName = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        // 1. Double extension check (e.g., shell.php.png, payload.phtml.jpg)
        $nameParts = explode('.', strtolower($originalName));
        if (count($nameParts) > 2) {
            // Check all middle parts for dangerous extensions
            for ($i = 1; $i < count($nameParts) - 1; $i++) {
                if (in_array($nameParts[$i], self::$dangerousExtensions, true)) {
                    throw ValidationException::withMessages([
                        'file' => "Nama berkas tidak diizinkan karena mengandung ekstensi berbahaya ganda: {$nameParts[$i]}",
                    ]);
                }
            }
        }

        // 2. Direct dangerous extension check
        if (in_array($ext, self::$dangerousExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => "Ekstensi berkas '.{$ext}' dilarang demi keamanan sistem.",
            ]);
        }

        // 3. Whitelist check if provided
        if (!empty($allowedExtensions)) {
            $normalizedWhitelist = array_map('strtolower', $allowedExtensions);
            if (!in_array($ext, $normalizedWhitelist, true)) {
                throw ValidationException::withMessages([
                    'file' => "Tipe berkas '.{$ext}' tidak didukung. Ekstensi yang diizinkan: " . implode(', ', $allowedExtensions),
                ]);
            }
        }

        // 4. Content Scanning for PHP executable signatures
        $realPath = $file->getRealPath();
        if ($realPath && file_exists($realPath) && is_readable($realPath)) {
            $content = file_get_contents($realPath);
            if ($content !== false && self::containsPhpPayload($content)) {
                throw ValidationException::withMessages([
                    'file' => 'Berkas ditolak: Terdeteksi pola skrip PHP atau kode yang dapat dieksekusi di dalam isi berkas.',
                ]);
            }
        }
    }

    /**
     * Validate raw decoded base64 image content before saving to public disk.
     *
     * @param string $decodedContent
     * @param string $extension
     * @throws ValidationException
     */
    public static function validateBase64Image(string $decodedContent, string $extension): void
    {
        $ext = strtolower(trim($extension));

        // SVG is strictly disallowed in direct base64 image imports to eliminate Stored XSS
        if ($ext === 'svg') {
            throw ValidationException::withMessages([
                'file' => 'Format gambar SVG tidak diizinkan untuk diimpor secara langsung demi mencegah risiko Cross-Site Scripting (XSS).',
            ]);
        }

        // Whitelist safe raster formats only
        $safeExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $safeExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => "Format gambar '{$ext}' tidak didukung.",
            ]);
        }

        // Content Scanning for PHP signatures in the decoded binary
        if (self::containsPhpPayload($decodedContent)) {
            throw ValidationException::withMessages([
                'file' => 'Data gambar base64 ditolak: Mengandung penanda skrip PHP atau muatan berbahaya.',
            ]);
        }
    }

    /**
     * Check if a binary or text string contains PHP execution markers.
     *
     * @param string $content
     * @return bool
     */
    public static function containsPhpPayload(string $content): bool
    {
        $lowered = strtolower($content);

        // Immediate check for PHP opening tags
        if (str_contains($lowered, '<?php') || str_contains($lowered, '<?=')) {
            return true;
        }

        // Check for script language=php
        if (preg_match('/<script\s+language\s*=\s*["\']?php["\']?/i', $content)) {
            return true;
        }

        // Check for __halt_compiler()
        if (str_contains($lowered, '__halt_compiler()')) {
            return true;
        }

        return false;
    }
}
