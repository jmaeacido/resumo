<?php

namespace Resumo;

use RuntimeException;
use Smalot\PdfParser\Parser;
use ZipArchive;

final class DocumentExtractor
{
    public static function extract(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Resume upload failed.');
        }

        $name = strtolower($file['name'] ?? '');
        $path = $file['tmp_name'];
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return match ($extension) {
            'txt' => trim((string)file_get_contents($path)),
            'pdf' => self::pdf($path),
            'docx' => self::docx($path),
            default => throw new RuntimeException('Supported resume files are TXT, PDF, and DOCX.'),
        };
    }

    private static function pdf(string $path): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        return trim($pdf->getText());
    }

    private static function docx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open DOCX file.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            throw new RuntimeException('Unable to read DOCX document text.');
        }

        $xml = preg_replace('/<w:tab\/>/', ' ', $xml);
        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }
}
