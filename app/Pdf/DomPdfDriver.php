<?php

namespace App\Pdf;

use Dompdf\Options;
use Spatie\LaravelPdf\Drivers\DomPdfDriver as SpatieDomPdfDriver;

class DomPdfDriver extends SpatieDomPdfDriver
{
    protected function buildOptions(): Options
    {
        $options = parent::buildOptions();
        $options->setIsPhpEnabled(true);

        return $options;
    }
}
