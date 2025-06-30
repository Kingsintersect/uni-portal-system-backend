<?php

namespace App\Services;

use Dompdf\Dompdf;

class PDFService
{
    public function generatePDF($data)
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml(view('pdf.template', compact('data'))->render());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();

        $filename = 'Admission letter for ' . $data['name'] . '.pdf';
        file_put_contents(public_path('storage/' . $filename), $output);

        return public_path('storage/' . $filename);
    }
}
