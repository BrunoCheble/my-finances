<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Smalot\PdfParser\Parser;

/**
 * Shows the structure of a PDF bank statement without exposing its data:
 * every digit becomes 9 and the words passed in --hide are removed.
 * Used to build per-bank parsers from real statements.
 */
class InspectStatement extends Command
{
    protected $signature = 'statements:inspect
                            {file : Caminho do PDF}
                            {--hide=* : Palavras a esconder (nome, morada, NIF...). Pode repetir}
                            {--mask-letters : Também troca todas as letras por x (mantém só a estrutura)}
                            {--positions : Mostra as coordenadas x/y de cada trecho (útil para colunas)}
                            {--pages=2 : Quantidade máxima de páginas}';

    protected $description = 'Extrai o texto de um extrato PDF de forma anonimizada';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!is_file($file)) {
            $this->error("Arquivo não encontrado: $file");
            return self::FAILURE;
        }

        $pdf = (new Parser())->parseFile($file);
        $pages = array_slice($pdf->getPages(), 0, (int) $this->option('pages'));

        if (trim($pdf->getText()) === '') {
            $this->warn('Nenhum texto encontrado: o PDF parece ser escaneado (imagem) e precisaria de OCR.');
            return self::SUCCESS;
        }

        foreach ($pages as $index => $page) {
            $this->line('===== Página ' . ($index + 1) . ' =====');

            if ($this->option('positions')) {
                foreach ($page->getDataTm() as [$matrix, $text]) {
                    $this->line(sprintf('[x=%6.1f y=%6.1f] %s', $matrix[4], $matrix[5], $this->anonymize($text)));
                }
                continue;
            }

            foreach (explode("\n", $page->getText()) as $line) {
                $this->line($this->anonymize($line));
            }
        }

        return self::SUCCESS;
    }

    private function anonymize(string $text): string
    {
        foreach ($this->option('hide') as $word) {
            $text = str_ireplace($word, '[OCULTO]', $text);
        }

        $text = preg_replace('/\S+@\S+/', '[EMAIL]', $text);
        $text = preg_replace('/\d/', '9', $text);

        if ($this->option('mask-letters')) {
            $text = preg_replace('/(?!OCULTO|EMAIL)\b\p{L}+\b/u', 'xxx', $text);
        }

        return $text;
    }
}
