<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Thin wrapper around Dompdf so every PDF in the app (invoices, report
 * exports) goes through the same, safely-configured instance rather than
 * each caller configuring Dompdf itself. Remote resources are disabled —
 * nothing here should ever fetch an external URL while rendering.
 */
class PdfService
{
    public function render(string $html, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }
}
