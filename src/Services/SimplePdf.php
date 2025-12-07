<?php

declare(strict_types=1);

namespace SistemaVentas\Services;

/**
 * Generador básico de PDF con texto secuencial.
 * No soporta saltos de página ni estilos avanzados, pero permite
 * exportar listados simples sin dependencias externas.
 */
final class SimplePdf
{
    private array $lines = [];

    /**
     * Agrega una fila de texto al documento.
     */
    public function addLine(string $line): void
    {
        $clean = preg_replace("/[\r\n]+/", ' ', $line) ?? '';
        $this->lines[] = $this->encodeText($clean);
    }

    /**
     * Agrega una línea vacía como separador.
     */
    public function addSpacer(): void
    {
        $this->lines[] = '';
    }

    /**
     * Emite el PDF con las cabeceras necesarias.
     */
    public function output(string $filename): void
    {
        if (empty($this->lines)) {
            $this->addLine('Sin datos para mostrar.');
        }

        $contentStream = $this->buildContentStream();
        $objects = $this->buildObjects($contentStream);
        $pdfBinary = $this->assemblePdf($objects);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBinary));

        echo $pdfBinary;
    }

    private function escape(string $text): string
    {
        return strtr($text, [
            '\\' => '\\\\',
            '('  => '\\(',
            ')'  => '\\)',
        ]);
    }

    private function encodeText(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($converted === false) {
            return $text;
        }
        return $converted;
    }

    private function buildContentStream(): string
    {
        $yPosition = 780;
        $lineHeight = 14;
        $chunks = [];

        foreach ($this->lines as $line) {
            $chunks[] = implode("\r\n", [
                'BT',
                '/F1 10 Tf',
                sprintf('1 0 0 1 50 %d Tm', $yPosition),
                '(' . $this->escape($line) . ') Tj',
                'ET',
            ]);
            $yPosition -= $lineHeight;
            if ($yPosition < 40) {
                $yPosition = 780; // reinicia en caso de overflow simple
            }
        }

        return implode("\r\n", $chunks);
    }

    /**
     * @return array<int, string>
     */
    private function buildObjects(string $contentStream): array
    {
        return [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj",
            '4 0 obj << /Length ' . strlen($contentStream) . ' >> stream\r\n' . $contentStream . "\r\nendstream endobj",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj",
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function assemblePdf(array $objects): string
    {
        $eol = "\r\n";
        $pdf = "%PDF-1.4" . $eol;
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . $eol;
        }

        $xrefPosition = strlen($pdf);
        $pdf .= 'xref' . $eol . '0 ' . (count($objects) + 1) . $eol;
        $pdf .= '0000000000 65535 f ' . $eol;
        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n %s', $offset, $eol);
        }

        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . $eol;
        $pdf .= 'startxref' . $eol . $xrefPosition . $eol;
        $pdf .= '%%EOF' . $eol;

        return $pdf;
    }
}
