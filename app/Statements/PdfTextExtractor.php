<?php

namespace App\Statements;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    private const DECODE_LIMIT = 50 * 1024 * 1024;

    public function extract(string $path): string
    {
        return $this->document($path)->getText();
    }

    public function document(string $path): Document
    {
        $pdf = file_get_contents($path);
        $config = new Config();
        // a crafted PDF could expand to gigabytes when decompressed
        $config->setDecodeMemoryLimit(self::DECODE_LIMIT);

        // banks protect statements against copy/print without a password
        if (PdfDecryptor::isEncrypted($pdf)) {
            $pdf = (new PdfDecryptor())->decrypt($pdf);
            $config->setIgnoreEncryption(true);
        }

        return (new Parser([], $config))->parseContent($pdf);
    }
}
