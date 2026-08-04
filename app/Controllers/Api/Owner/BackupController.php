<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Services\BackupService;

class BackupController extends BaseApiController
{
    public function export()
    {
        $data = (new BackupService())->export();

        return $this->response->download(
            'nethub_backup_' . date('Ymd_His') . '.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function restore()
    {
        $file = $this->request->getFile('backup_file');

        if (! $file || ! $file->isValid()) {
            return $this->fail('File backup wajib diunggah.', 422);
        }

        $data = json_decode(file_get_contents($file->getTempName()), true);

        if (! is_array($data) || ! isset($data['version'])) {
            return $this->fail('File backup tidak valid atau rusak.', 422);
        }

        $result = (new BackupService())->restore($data);

        return $this->ok($result, 'Restore selesai.');
    }
}
