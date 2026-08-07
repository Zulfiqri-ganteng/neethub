<?php

namespace App\Controllers\Api;

use App\Models\PlatformSettingModel;
use App\Services\ApkService;

/**
 * Android update-checker. Both endpoints are public (no jwtauth) — version
 * must be checkable before the app has a session, and registerApk
 * authenticates itself via a release token instead (see below).
 */
class AppController extends BaseApiController
{
    /**
     * GET /api/app/version — called by the app on every launch. `min_build`
     * is the force-update floor (installed build below this = blocking
     * dialog); `apk_build` is just "latest available" (below this but above
     * min_build = optional/dismissible banner).
     */
    public function version()
    {
        $settings = (new PlatformSettingModel())->current();

        $apkFile = basename((string) ($settings['mobile_apk_file'] ?? ''));
        $apkAda  = $apkFile !== '' && is_file(ApkService::dir() . $apkFile);

        return $this->ok([
            'min_build'           => (int) ($settings['mobile_min_build'] ?? 1),
            'apk_available'       => $apkAda,
            'apk_url'             => $apkAda ? base_url('projectneethub/apk/' . $apkFile) : null,
            'apk_version'         => $settings['mobile_apk_version'] ?? null,
            'apk_build'           => (int) ($settings['mobile_apk_build'] ?? 0),
            'apk_size'            => (int) ($settings['mobile_apk_size'] ?? 0),
            'apk_notes'           => $settings['mobile_apk_notes'] ?? null,
            'maintenance'         => (bool) ($settings['mobile_maintenance'] ?? false),
            'maintenance_message' => $settings['mobile_maintenance_msg'] ?? 'Aplikasi sedang pemeliharaan. Coba lagi nanti.',
        ]);
    }

    /**
     * POST /api/app/apk/register — metadata-only (no file in the request
     * body, the .apk itself is already sitting in projectneethub/apk/ via
     * FTP). Auth: X-Apk-Token header, constant-time compared against
     * APK_UPLOAD_TOKEN in .env — deliberately not a user/session token,
     * since this is meant to be called by a build/deploy script.
     *
     * Body JSON: {version, build, min_build?, notes?, filename?}. Without
     * filename, the newest .apk already in the folder is used.
     */
    public function registerApk()
    {
        $expected = (string) env('APK_UPLOAD_TOKEN', '');
        $given    = trim($this->request->getHeaderLine('X-Apk-Token'));
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return $this->fail('Token rilis tidak valid.', 401);
        }

        $in = $this->request->getJSON(true);
        if (! is_array($in) || ! $in) {
            $in = $this->request->getPost() ?: [];
        }

        $filename = isset($in['filename']) && $in['filename'] !== '' ? (string) $in['filename'] : null;
        $version  = trim((string) ($in['version'] ?? ''));
        $build    = (int) ($in['build'] ?? 0);
        $notes    = trim((string) ($in['notes'] ?? ''));
        $minBuild = (isset($in['min_build']) && $in['min_build'] !== '') ? (int) $in['min_build'] : null;

        $result = ApkService::register($filename, $version, $build, $notes, $minBuild);
        if (! $result['ok']) {
            $status = ($result['code'] ?? '') === 'apk_not_found' ? 404 : 422;

            return $this->fail($result['message'], $status);
        }

        return $this->ok($result['data'], $result['message']);
    }

    /**
     * POST /api/app/apk/publish — PUBLIC (auth via the same X-Apk-Token as
     * registerApk above, not a session). Accepts the .apk FILE directly over
     * HTTPS — a single connection, so unlike FTP it works from networks
     * (mobile hotspots, this session's own sandboxed environment) where
     * FTP's separate data channel gets blocked. Only viable while the
     * upload fits under this host's real upload_max_filesize/post_max_size
     * — if it doesn't, PHP silently empties $_POST/$_FILES, which this
     * detects via CONTENT_LENGTH and reports honestly rather than as a
     * misleading validation error.
     *
     * Multipart body: apk(file), version, build, min_build?, notes?
     */
    public function publishApk()
    {
        $expected = (string) env('APK_UPLOAD_TOKEN', '');
        $given    = trim($this->request->getHeaderLine('X-Apk-Token'));
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return $this->fail('Token rilis tidak valid.', 401);
        }

        $contentLength = (int) ($this->request->getServer('CONTENT_LENGTH') ?? 0);
        if (empty($_POST) && empty($_FILES) && $contentLength > 0) {
            return $this->fail('File terlalu besar untuk batas upload server.', 413, [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'       => ini_get('post_max_size'),
                'content_length'      => $contentLength,
            ]);
        }

        $version  = trim((string) $this->request->getPost('version'));
        $build    = (int) $this->request->getPost('build');
        $notes    = trim((string) $this->request->getPost('notes'));
        $minBuildRaw = $this->request->getPost('min_build');
        $minBuild = ($minBuildRaw !== null && $minBuildRaw !== '') ? (int) $minBuildRaw : null;

        $file = $this->request->getFile('apk');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->fail('File apk wajib dikirim.', 422);
        }
        if ($file->getError() !== UPLOAD_ERR_OK || ! $file->isValid() || $file->hasMoved()) {
            return $this->fail('Upload APK gagal (kemungkinan melebihi batas upload server).', 422);
        }
        if (strtolower((string) $file->getClientExtension()) !== 'apk') {
            return $this->fail('File harus berekstensi .apk.', 422);
        }

        $dir = ApkService::dir();
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            return $this->fail('Gagal menyiapkan folder penyimpanan.', 500);
        }

        $filename = 'nethub-owner-' . $build . '.apk';
        try {
            $file->move($dir, $filename, true);
        } catch (\Throwable $e) {
            log_message('error', '[APK PUBLISH] ' . $e->getMessage());

            return $this->fail('Gagal menyimpan APK. Coba lagi.', 500);
        }

        $result = ApkService::register($filename, $version, $build, $notes, $minBuild);
        if (! $result['ok']) {
            return $this->fail($result['message'], 422);
        }

        return $this->ok($result['data'], 'APK berhasil dipublikasikan.');
    }
}
