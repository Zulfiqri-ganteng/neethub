<?php

namespace App\Services;

use App\Models\PlatformSettingModel;

/**
 * Manages the Android release APK, which is stored as a STATIC file under
 * public/projectneethub/apk/ (uploaded via FTP directly — the file is far
 * too large for PHP's shared-hosting upload limits) rather than through this
 * backend. Only the small metadata (version/build/notes) gets registered
 * through the API, via register() below.
 *
 * Same design as the sibling "Galajuara" project's ApkManager — proven in
 * production there, ported here rather than reinvented.
 */
class ApkService
{
    /** Folder the FTP account is chrooted to — public/projectneethub/apk/. */
    public static function dir(): string
    {
        return FCPATH . 'projectneethub/apk/';
    }

    /** .apk filenames present in the folder, newest first. */
    public static function listApks(): array
    {
        $files = glob(self::dir() . '*.apk') ?: [];
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map('basename', $files);
    }

    /**
     * Reads versionCode/versionName straight out of the APK's own
     * AndroidManifest.xml (binary AXML format) — avoids trusting a
     * hand-typed build number, which would otherwise risk "update available"
     * never clearing for users once it drifts out of sync with the real
     * file.
     *
     * @return array{version_code:int, version_name:string}|null
     */
    public static function readApkInfo(string $path): ?array
    {
        if (! class_exists('ZipArchive') || ! is_file($path)) {
            return null;
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return null;
            }
            $axml = $zip->getFromName('AndroidManifest.xml');
            $zip->close();

            if ($axml === false || strlen($axml) < 8) {
                return null;
            }

            return self::parseManifestAxml($axml);
        } catch (\Throwable $e) {
            log_message('error', '[APK INFO] gagal baca: ' . $e->getMessage());

            return null;
        }
    }

    private static function parseManifestAxml(string $d): ?array
    {
        $len = strlen($d);
        if ($len < 8 || (ord($d[0]) | (ord($d[1]) << 8)) !== 0x0003) {
            return null; // bukan RES_XML
        }

        $u16 = static fn (int $o) => ord($d[$o]) | (ord($d[$o + 1]) << 8);
        $u32 = static fn (int $o) => ord($d[$o]) | (ord($d[$o + 1]) << 8) | (ord($d[$o + 2]) << 16) | (ord($d[$o + 3]) << 24);

        $strings = [];
        $off     = 8;

        while ($off + 8 <= $len) {
            $type  = $u16($off);
            $csize = $u32($off + 4);
            if ($csize < 8 || $off + $csize > $len) {
                break;
            }

            if ($type === 0x0001) { // STRING POOL
                $strings = self::readAxmlStrings($d, $off, $u16, $u32);
            } elseif ($type === 0x0102) { // START ELEMENT
                $nameIdx = $u32($off + 20);
                $name    = $strings[$nameIdx] ?? '';
                if ($name === 'manifest') {
                    $attrStart = $u16($off + 24);
                    $attrSize  = $u16($off + 26) ?: 20;
                    $attrCount = $u16($off + 28);
                    $base      = $off + 16 + $attrStart;

                    $vCode = null;
                    $vName = null;
                    for ($i = 0; $i < $attrCount; $i++) {
                        $a = $base + $i * $attrSize;
                        if ($a + 20 > $len) {
                            break;
                        }
                        $aName   = $strings[$u32($a + 4)] ?? '';
                        $rawVal  = $u32($a + 8);
                        $dataTyp = ord($d[$a + 15]);
                        $data    = $u32($a + 16);

                        if ($aName === 'versionCode') {
                            $vCode = $data;
                        } elseif ($aName === 'versionName') {
                            if ($rawVal !== 0xFFFFFFFF && isset($strings[$rawVal])) {
                                $vName = $strings[$rawVal];
                            } elseif ($dataTyp === 0x03 && isset($strings[$data])) {
                                $vName = $strings[$data];
                            }
                        }
                    }

                    if ($vCode !== null && $vCode >= 1) {
                        return ['version_code' => (int) $vCode, 'version_name' => (string) ($vName ?? '')];
                    }

                    return null;
                }
            }
            $off += $csize;
        }

        return null;
    }

    private static function readAxmlStrings(string $d, int $poolOff, callable $u16, callable $u32): array
    {
        $headerSize   = $u16($poolOff + 2);
        $stringCount  = $u32($poolOff + 8);
        $flags        = $u32($poolOff + 16);
        $stringsStart = $u32($poolOff + 20);
        $isUtf8       = ($flags & 0x100) !== 0;
        $offsetsBase  = $poolOff + $headerSize;
        $dataBase     = $poolOff + $stringsStart;
        $len          = strlen($d);

        $out = [];
        for ($i = 0; $i < $stringCount; $i++) {
            $oPos = $offsetsBase + $i * 4;
            if ($oPos + 4 > $len) {
                break;
            }
            $p = $dataBase + $u32($oPos);
            if ($p >= $len) {
                $out[$i] = '';

                continue;
            }

            if ($isUtf8) {
                $b = ord($d[$p]);
                $p++;
                if ($b & 0x80) {
                    $p++;
                }
                $bl = ord($d[$p]);
                $p++;
                if ($bl & 0x80) {
                    $bl = (($bl & 0x7F) << 8) | ord($d[$p]);
                    $p++;
                }
                $out[$i] = substr($d, $p, $bl);
            } else {
                $cl = $u16($p);
                $p += 2;
                if ($cl & 0x8000) {
                    $cl = (($cl & 0x7FFF) << 16) | $u16($p);
                    $p += 2;
                }
                $raw     = substr($d, $p, $cl * 2);
                $out[$i] = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
            }
        }

        return $out;
    }

    /** ZIP signature + AndroidManifest.xml present — the minimum bar for "this is really an APK". */
    public static function validateApk(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }
        $fh    = @fopen($path, 'rb');
        $magic = $fh ? fread($fh, 4) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($magic !== "PK\x03\x04") {
            return false;
        }
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return false;
            }
            $hasManifest = $zip->locateName('AndroidManifest.xml') !== false;
            $zip->close();

            return $hasManifest;
        }

        return true;
    }

    /**
     * Registers an APK already sitting in projectneethub/apk/ (uploaded via
     * FTP) — sets platform_settings' mobile_apk_* columns from it. Deletes
     * every other .apk in the folder so only the registered one remains
     * (shared hosting disk is limited, and an old lingering file could
     * otherwise be served by guessing its URL).
     *
     * @return array{ok:bool, code?:string, message:string, data?:array}
     */
    public static function register(?string $filename, string $version, int $build, string $notes = '', ?int $minBuild = null): array
    {
        $dir      = self::dir();
        $filename = $filename ? basename($filename) : (self::listApks()[0] ?? '');
        if ($filename === '' || ! preg_match('/\.apk$/i', $filename)) {
            return [
                'ok'      => false,
                'code'    => 'invalid_filename',
                'message' => 'Tidak ada file .apk di folder. Upload dulu ke public/projectneethub/apk/ via FTP.',
            ];
        }

        $path = $dir . $filename;
        if (! is_file($path)) {
            return ['ok' => false, 'code' => 'apk_not_found', 'message' => "File {$filename} tidak ditemukan di projectneethub/apk/."];
        }
        if (! self::validateApk($path)) {
            return ['ok' => false, 'code' => 'invalid_apk', 'message' => 'File bukan APK valid (perlu signature ZIP + AndroidManifest.xml).'];
        }

        $info = self::readApkInfo($path);
        if ($info) {
            $build   = $info['version_code'];
            $version = $info['version_name'] !== '' ? $info['version_name'] : $version;
        }

        if (! preg_match('/^\d+\.\d+(\.\d+)?(\+\d+)?$/', $version)) {
            return ['ok' => false, 'code' => 'invalid_version', 'message' => 'Versi harus format semver dasar (mis. 1.0.1).'];
        }
        if ($build < 1) {
            return ['ok' => false, 'code' => 'invalid_build', 'message' => 'Build harus bilangan bulat > 0.'];
        }

        foreach (glob($dir . '*.apk') ?: [] as $other) {
            if (basename($other) !== $filename) {
                @unlink($other);
            }
        }

        $size  = @filesize($path) ?: 0;
        $model = new PlatformSettingModel();

        $kv = [
            'mobile_apk_file'        => $filename,
            'mobile_apk_version'     => mb_substr($version, 0, 20),
            'mobile_apk_build'       => $build,
            'mobile_apk_size'        => $size,
            'mobile_apk_uploaded_at' => date('Y-m-d H:i:s'),
            'mobile_apk_notes'       => mb_substr($notes, 0, 500),
        ];
        $minBuildOut = (int) ($model->current()['mobile_min_build'] ?? 1);
        if ($minBuild !== null && $minBuild >= 1) {
            $kv['mobile_min_build'] = $minBuild;
            $minBuildOut            = $minBuild;
        }
        $model->update(1, $kv);

        log_message('info', "[APK REGISTER] {$filename} v{$version} build {$build} ({$size} bytes)");

        return ['ok' => true, 'message' => 'APK terdaftar.', 'data' => [
            'apk_url'       => base_url('projectneethub/apk/' . $filename),
            'apk_version'   => $version,
            'apk_build'     => $build,
            'apk_size'      => $size,
            'min_build'     => $minBuildOut,
            'filename'      => $filename,
            'auto_detected' => $info !== null,
        ]];
    }
}
