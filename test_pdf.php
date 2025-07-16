<?php
require_once __DIR__ . '/vendor/autoload.php'; // Adjust if needed

use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml('<h1>Hello, Dompdf still works!</h1>');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('test.pdf', ['Attachment' => false]);
