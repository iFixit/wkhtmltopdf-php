# wkhtmltopdf-php #
A very simple interface to the super-useful PDF generation library wkhtmltopdf.

Check the source for the rest of the public API.

## Example ##

    $pdf = new PDFGenerator();
    $pdf->setInputHTML($htmlContent);
    $pdf->setPageSize('57.15mm', '12.7mm');
    $pdf->setMargins('5mm');
    $pdf->streamToClient();
    exit();
