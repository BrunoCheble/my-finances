<?php

namespace App\Statements;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    public function extract(string $path): string
    {
        return $this->document($path)->getText();
    }

    public function document(string $path): Document
    {
        $pdf = file_get_contents($path);
        $config = new Config();

        // banks protect statements against copy/print without a password
        if (PdfDecryptor::isEncrypted($pdf)) {
            $pdf = (new PdfDecryptor())->decrypt($pdf);
            $config->setIgnoreEncryption(true);
        }

        return (new Parser([], $config))->parseContent($pdf);
    }
}
