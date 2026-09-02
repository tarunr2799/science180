<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once S180BR_PLUGIN_DIR . 'vendor/setasign/fpdf/fpdf.php';
require_once S180BR_PLUGIN_DIR . 'vendor/setasign/fpdi/autoload.php';
require_once S180BR_PLUGIN_DIR . 'vendor/setasign/fpdi-protection/autoload.php';

use setasign\FpdiProtection\FpdiProtection;

class S180BR_Protected_PDF extends FpdiProtection
{
    private $angle = 0;

    public function rotate($angle, $x = -1, $y = -1)
    {
        if ($x === -1) {
            $x = $this->x;
        }
        if ($y === -1) {
            $y = $this->y;
        }
        if ($this->angle !== 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle !== 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.5F %.5F cm 1 0 0 1 %.5F %.5F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    protected function _endpage()
    {
        if ($this->angle !== 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}

class S180BR_PDF
{
    public static function generate($source, $destination, $recipient, $message, $color, $position, $personalized = true, $margin_font_size = 7, $footer_font_size = 8)
    {
        if (!is_readable($source)) {
            throw new RuntimeException(__('The source PDF cannot be read.', 'science180-book-review'));
        }

        // Use the library's native ARCFOUR fallback so this also works on OpenSSL 3 hosts
        // where legacy RC4 providers are disabled.
        $pdf = new S180BR_Protected_PDF('P', 'mm', 'A4', true);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $page_count = $pdf->setSourceFile($source);
        $rgb = self::hex_to_rgb($color);
        $margin_font_size = max(5, min(24, (int) $margin_font_size));
        $footer_font_size = max(5, min(24, (int) $footer_font_size));

        for ($page_number = 1; $page_number <= $page_count; $page_number++) {
            $template = $pdf->importPage($page_number);
            $size = $pdf->getTemplateSize($template);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, array($size['width'], $size['height']));
            $pdf->useTemplate($template, 0, 0, $size['width'], $size['height'], true);
            $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);

            if ($personalized) {
                $footer = trim($recipient['name'] . ' - ' . $recipient['email']);
                $pdf->SetFont('Arial', '', $footer_font_size);
                $pdf->SetXY(8, max(0, $size['height'] - 8));
                $pdf->Cell(max(10, $size['width'] - 16), 4, self::pdf_text($footer), 0, 0, 'C');
            }

            if (trim($message) !== '') {
                self::write_margin_message($pdf, $message, $position, $size['width'], $size['height'], $margin_font_size);
            }
        }

        // Permit printing for review use, while disallowing copying and modification in compliant readers.
        $pdf->setProtection(S180BR_Protected_PDF::PERM_PRINT, '', wp_generate_password(32, true, true), 3);
        $pdf->Output('F', $destination);

        if (!is_readable($destination) || filesize($destination) < 100) {
            throw new RuntimeException(__('The protected PDF could not be generated.', 'science180-book-review'));
        }
        return $page_count;
    }

    private static function write_margin_message($pdf, $message, $position, $width, $height, $font_size)
    {
        $message = self::pdf_text(wp_strip_all_tags($message));
        $pdf->SetFont('Arial', '', $font_size);

        if ($position === 'left') {
            $pdf->rotate(90, 4, $height - 8);
            $pdf->SetXY(4, $height - 8);
            $pdf->Cell(max(10, $height - 16), 4, $message, 0, 0, 'C');
            $pdf->rotate(0);
        } elseif ($position === 'right') {
            $pdf->rotate(-90, $width - 4, 8);
            $pdf->SetXY($width - 4, 8);
            $pdf->Cell(max(10, $height - 16), 4, $message, 0, 0, 'C');
            $pdf->rotate(0);
        } else {
            $pdf->SetXY(8, 3);
            $pdf->Cell(max(10, $width - 16), 4, $message, 0, 0, 'C');
        }
    }

    private static function hex_to_rgb($hex)
    {
        $hex = ltrim((string) $hex, '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '7030A0';
        }
        return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    private static function pdf_text($text)
    {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string) $text);
            if ($converted !== false) {
                return $converted;
            }
        }
        return preg_replace('/[^\x20-\x7E]/', '?', (string) $text);
    }
}
