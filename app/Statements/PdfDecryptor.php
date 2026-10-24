<?php

namespace App\Statements;

use InvalidArgumentException;

/**
 * Removes the "standard security handler" encryption (RC4 / AES-128, revisions 2-4)
 * from PDFs that open without a password, as banks do to restrict copy/print.
 *
 * Streams are decrypted in place and padded to their original size, so every
 * xref offset stays valid and the regular parser can read the result.
 * Strings are left encrypted: text extraction only needs the streams.
 */
class PdfDecryptor
{
    private const PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
        . "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    private string $key;
    private bool $aes;
    private bool $encryptMetadata;
    private bool $encryptStreams;

    public static function isEncrypted(string $pdf): bool
    {
        return (bool) preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/', $pdf);
    }

    public function decrypt(string $pdf): string
    {
        $this->readEncryptDictionary($pdf);

        $offset = 0;
        while (preg_match('/(\d+)\s+(\d+)\s+obj\b/', $pdf, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $this->decryptObject($pdf, (int) $match[1][0], (int) $match[2][0], $match[0][1] + strlen($match[0][0]));
        }

        return $pdf;
    }

    private function readEncryptDictionary(string $pdf): void
    {
        preg_match('/\/Encrypt\s+(\d+)\s+(\d+)\s+R/', $pdf, $ref);
        // up to "endobj": the binary O/U strings may contain ">>"
        if (!preg_match('/\b' . $ref[1] . '\s+' . $ref[2] . '\s+obj\s*<<(.*?)endobj/s', $pdf, $dict)) {
            throw new InvalidArgumentException(__('Unsupported PDF protection.'));
        }
        $dict = $dict[1];

        $filter = self::name($dict, 'Filter');
        $version = (int) self::number($dict, 'V');
        $revision = (int) self::number($dict, 'R');
        if ($filter !== 'Standard' || $version > 4 || $revision > 4) {
            throw new InvalidArgumentException(__('Unsupported PDF protection.'));
        }

        // the crypt filter sub-dictionary has its own /Length (in bytes)
        $topLevel = preg_replace('/\/CF\s*<<.*?>>\s*>>/s', '', $dict);
        $length = match (true) {
            $version === 1 => 5,
            $version === 4 => intdiv((int) (self::number($topLevel, 'Length') ?: 128), 8),
            default => intdiv((int) (self::number($topLevel, 'Length') ?: 40), 8),
        };
        $owner = substr(self::string($dict, 'O'), 0, 32);
        $user = self::string($dict, 'U');
        $permissions = (int) self::number($topLevel, 'P');

        $this->encryptMetadata = !preg_match('/\/EncryptMetadata\s+false/', $dict);
        $this->aes = $version === 4 && str_contains($dict, '/AESV2');
        $this->encryptStreams = !($version === 4 && self::name($dict, 'StmF') === 'Identity');

        // incremental updates add trailers; the newest /ID comes last
        foreach (self::ids($pdf) as $id) {
            // Algorithm 2 (ISO 32000-1, 7.6.3.3) with an empty user password
            $hash = md5(self::PADDING . $owner . pack('V', $permissions & 0xFFFFFFFF) . $id
                . ($revision >= 4 && !$this->encryptMetadata ? "\xFF\xFF\xFF\xFF" : ''), true);
            if ($revision >= 3) {
                for ($i = 0; $i < 50; $i++) {
                    $hash = md5(substr($hash, 0, $length), true);
                }
            }
            $this->key = substr($hash, 0, $length);

            if ($this->userPasswordIsEmpty($revision, $user, $id)) {
                return;
            }
        }

        throw new InvalidArgumentException(__('This PDF requires a password.'));
    }

    /**
     * Algorithms 4/5: recompute the U entry with the derived key.
     */
    private function userPasswordIsEmpty(int $revision, string $user, string $id): bool
    {
        if ($revision === 2) {
            return self::rc4($this->key, self::PADDING) === substr($user, 0, 32);
        }

        $value = self::rc4($this->key, md5(self::PADDING . $id, true));
        for ($i = 1; $i <= 19; $i++) {
            $value = self::rc4($this->key ^ str_repeat(chr($i), strlen($this->key)), $value);
        }
        return $value === substr($user, 0, 16);
    }

    /**
     * Decrypts the object's stream (if any) and returns the offset after it.
     */
    private function decryptObject(string &$pdf, int $number, int $generation, int $start): int
    {
        $end = strpos($pdf, 'endobj', $start);
        if ($end === false) {
            return strlen($pdf);
        }

        if (!preg_match('/\bstream\r?\n/', $pdf, $streamMatch, PREG_OFFSET_CAPTURE, $start)
            || $streamMatch[0][1] > $end) {
            return $end;
        }

        $dictionary = substr($pdf, $start, $streamMatch[0][1] - $start);
        $dataStart = $streamMatch[0][1] + strlen($streamMatch[0][0]);
        $length = $this->streamLength($pdf, $dictionary, $dataStart);
        $afterStream = $dataStart + $length;
        $resume = strpos($pdf, 'endobj', $afterStream) ?: $afterStream;

        if (!$this->encryptStreams
            || preg_match('/\/Type\s*\/XRef\b/', $dictionary)
            || (!$this->encryptMetadata && preg_match('/\/Type\s*\/Metadata\b/', $dictionary))) {
            return $resume;
        }

        $plain = $this->decryptData(substr($pdf, $dataStart, $length), $number, $generation);
        if ($plain === null) {
            return $resume;
        }

        $pdf = substr_replace($pdf, str_pad($plain, $length, ' '), $dataStart, $length);
        $this->rewriteLength($pdf, $start, $dictionary, strlen($plain));

        return $resume;
    }

    private function streamLength(string $pdf, string $dictionary, int $dataStart): int
    {
        if (preg_match('/\/Length\s+(\d+)\s+(\d+)\s+R/', $dictionary, $ref)
            && preg_match('/\b' . $ref[1] . '\s+' . $ref[2] . '\s+obj\s*(\d+)\s*endobj/', $pdf, $value)) {
            return (int) $value[1];
        }
        if (preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $dictionary, $direct)) {
            return (int) $direct[1];
        }

        // unknown length: up to the end-of-line before "endstream"
        $end = strpos($pdf, 'endstream', $dataStart);
        return rtrim(substr($pdf, $dataStart, $end - $dataStart), "\r\n") === ''
            ? 0
            : strlen(rtrim(substr($pdf, $dataStart, $end - $dataStart), "\r\n"));
    }

    /**
     * Writes the decrypted length over the /Length value without changing the
     * file size (an indirect "47 0 R" becomes a direct number plus spaces).
     */
    private function rewriteLength(string &$pdf, int $dictionaryStart, string $dictionary, int $length): void
    {
        if (!preg_match('/\/Length\s+((\d+)\s+(\d+)\s+R|\d+)/', $dictionary, $match, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $old = $match[1][0];
        if (strlen((string) $length) > strlen($old)) {
            return;
        }
        $pdf = substr_replace($pdf, str_pad((string) $length, strlen($old), ' '), $dictionaryStart + $match[1][1], strlen($old));
    }

    private function decryptData(string $data, int $number, int $generation): ?string
    {
        $objectKey = md5($this->key
            . substr(pack('V', $number), 0, 3)
            . substr(pack('V', $generation), 0, 2)
            . ($this->aes ? 'sAlT' : ''), true);
        $objectKey = substr($objectKey, 0, min(strlen($this->key) + 5, 16));

        if (!$this->aes) {
            return self::rc4($objectKey, $data);
        }

        if (strlen($data) < 32 || strlen($data) % 16 !== 0) {
            return null;
        }
        $plain = openssl_decrypt(substr($data, 16), 'aes-128-cbc', $objectKey, OPENSSL_RAW_DATA, substr($data, 0, 16));

        return $plain === false ? null : $plain;
    }

    private static function rc4(string $key, string $data): string
    {
        $state = range(0, 255);
        $keyLength = strlen($key);
        for ($i = 0, $j = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) % 256;
            [$state[$i], $state[$j]] = [$state[$j], $state[$i]];
        }

        $output = '';
        for ($n = 0, $i = 0, $j = 0, $len = strlen($data); $n < $len; $n++) {
            $i = ($i + 1) % 256;
            $j = ($j + $state[$i]) % 256;
            [$state[$i], $state[$j]] = [$state[$j], $state[$i]];
            $output .= chr(ord($data[$n]) ^ $state[($state[$i] + $state[$j]) % 256]);
        }
        return $output;
    }

    /**
     * First element of every /ID array, newest first.
     */
    private static function ids(string $pdf): array
    {
        $ids = [];
        preg_match_all('/\/ID\s*\[\s*(<([0-9A-Fa-f\s]*)>|\()/', $pdf, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $ids[] = $match[1][0] === '('
                ? self::literalAt($pdf, $match[1][1])
                : hex2bin(preg_replace('/\s+/', '', $match[2][0]));
        }

        return array_values(array_unique(array_reverse($ids) ?: ['']));
    }

    private static function name(string $dict, string $key): ?string
    {
        return preg_match('/\/' . $key . '\s*\/(\w+)/', $dict, $m) ? $m[1] : null;
    }

    private static function number(string $dict, string $key): ?string
    {
        return preg_match('/\/' . $key . '\s+(-?\d+)/', $dict, $m) ? $m[1] : null;
    }

    private static function string(string $dict, string $key): string
    {
        if (preg_match('/\/' . $key . '\s*<([0-9A-Fa-f\s]*)>/', $dict, $hex)) {
            return hex2bin(preg_replace('/\s+/', '', $hex[1]));
        }
        if (preg_match('/\/' . $key . '\s*\(/', $dict, $literal, PREG_OFFSET_CAPTURE)) {
            return self::literalAt($dict, $literal[0][1] + strlen($literal[0][0]) - 1);
        }
        return '';
    }

    /**
     * Decodes a literal string "(...)" starting at $offset (escapes and nesting).
     */
    private static function literalAt(string $data, int $offset): string
    {
        $escapes = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C", '(' => '(', ')' => ')', '\\' => '\\'];
        $output = '';
        $depth = 0;

        for ($i = $offset + 1, $len = strlen($data); $i < $len; $i++) {
            $char = $data[$i];
            if ($char === '\\') {
                $next = $data[++$i] ?? '';
                if (isset($escapes[$next])) {
                    $output .= $escapes[$next];
                } elseif (ctype_digit($next) && $next < '8') {
                    $octal = $next;
                    while (strlen($octal) < 3 && isset($data[$i + 1]) && ctype_digit($data[$i + 1]) && $data[$i + 1] < '8') {
                        $octal .= $data[++$i];
                    }
                    $output .= chr(octdec($octal) & 0xFF);
                } elseif ($next === "\r") {
                    if (($data[$i + 1] ?? '') === "\n") {
                        $i++;
                    }
                } elseif ($next !== "\n") {
                    $output .= $next;
                }
            } elseif ($char === '(') {
                $depth++;
                $output .= $char;
            } elseif ($char === ')') {
                if ($depth === 0) {
                    break;
                }
                $depth--;
                $output .= $char;
            } else {
                $output .= $char;
            }
        }

        return $output;
    }
}
