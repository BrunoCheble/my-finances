<?php

namespace App\Statements;

use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    public function extract(string $path): string
    {
        return (new Parser())->parseFile($path)->getText();
    }
}
