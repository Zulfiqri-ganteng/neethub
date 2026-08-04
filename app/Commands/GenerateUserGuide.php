<?php

namespace App\Commands;

use App\Services\PdfService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * One-off generator for docs/PANDUAN_PENGGUNA_NETHUB.pdf from
 * app/Views/pdf/user_guide.php. Not wired into any cron/schedule — run by
 * hand whenever the guide's content needs to be regenerated after a feature
 * change.
 */
class GenerateUserGuide extends BaseCommand
{
    protected $group       = 'docs';
    protected $name        = 'docs:generate-guide';
    protected $description = 'Regenerates docs/PANDUAN_PENGGUNA_NETHUB.pdf from app/Views/pdf/user_guide.php';

    public function run(array $params)
    {
        $html = view('pdf/user_guide');
        $pdf  = (new PdfService())->render($html);

        $path = ROOTPATH . 'docs/PANDUAN_PENGGUNA_NETHUB.pdf';
        file_put_contents($path, $pdf);

        CLI::write('Generated: ' . $path, 'green');
    }
}
