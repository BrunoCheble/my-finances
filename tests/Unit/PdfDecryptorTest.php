<?php

use App\Statements\PdfDecryptor;
use App\Statements\PdfTextExtractor;

// error messages are translated, so the app must be booted
uses(Tests\TestCase::class);

const PDF_PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
    . "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

function rc4(string $key, string $data): string
{
    $s = range(0, 255);
    for ($i = 0, $j = 0; $i < 256; $i++) {
        $j = ($j + $s[$i] + ord($key[$i % strlen($key)])) % 256;
        [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
    }
    $out = '';
    for ($n = 0, $i = 0, $j = 0; $n < strlen($data); $n++) {
        $i = ($i + 1) % 256;
        $j = ($j + $s[$i]) % 256;
        [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        $out .= chr(ord($data[$n]) ^ $s[($s[$i] + $s[$j]) % 256]);
    }
    return $out;
}

/**
 * Builds a one-page PDF encrypted with AES-128 (V4/R4) and an empty user
 * password, like the bank statements.
 */
function encryptedPdf(string $text, bool $validUserEntry = true): string
{
    $id = random_bytes(16);
    $owner = random_bytes(32);
    $permissions = -3084;

    $key = md5(PDF_PADDING . $owner . pack('V', $permissions & 0xFFFFFFFF) . $id, true);
    for ($i = 0; $i < 50; $i++) {
        $key = md5($key, true);
    }
    $user = rc4($key, md5(PDF_PADDING . $id, true));
    for ($i = 1; $i <= 19; $i++) {
        $user = rc4($key ^ str_repeat(chr($i), 16), $user);
    }
    $user = $validUserEntry ? $user . random_bytes(16) : random_bytes(32);

    $content = gzcompress("BT /F1 12 Tf 72 700 Td ($text) Tj ET");
    $objectKey = md5($key . "\x04\x00\x00\x00\x00sAlT", true);
    $iv = random_bytes(16);
    $stream = $iv . openssl_encrypt($content, 'aes-128-cbc', $objectKey, OPENSSL_RAW_DATA, $iv);

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        4 => '<< /Length ' . strlen($stream) . " /Filter /FlateDecode >>\nstream\n" . $stream . "\nendstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        6 => '<< /Filter /Standard /V 4 /R 4 /Length 128 /P ' . $permissions
            . ' /O <' . bin2hex($owner) . '> /U <' . bin2hex($user) . '>'
            . ' /CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV2 /Length 16 >> >> /StmF /StdCF /StrF /StdCF >>',
    ];

    $pdf = "%PDF-1.6\n";
    $offsets = [];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "$number 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 7\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $hexId = bin2hex($id);
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R /Encrypt 6 0 R /ID [<$hexId><$hexId>] >>\nstartxref\n$xref\n%%EOF\n";

    return $pdf;
}

function extractFromString(string $pdf): string
{
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, $pdf);
    try {
        return (new PdfTextExtractor())->extract($path);
    } finally {
        unlink($path);
    }
}

it('reads text from an AES protected PDF without password', function () {
    $pdf = encryptedPdf('Extrato de teste');

    expect(PdfDecryptor::isEncrypted($pdf))->toBeTrue()
        ->and(extractFromString($pdf))->toContain('Extrato de teste');
});

it('keeps the file size so xref offsets stay valid', function () {
    $pdf = encryptedPdf('Extrato de teste');

    expect(strlen((new PdfDecryptor())->decrypt($pdf)))->toBe(strlen($pdf));
});

it('refuses PDFs that need a password', function () {
    extractFromString(encryptedPdf('Segredo', validUserEntry: false));
})->throws(InvalidArgumentException::class);
