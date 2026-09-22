<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Classes;

class EscSvg {
    
    public function resizeQrCodeSvg(string $svg): string
    {
        $document = new DOMDocument();
        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS)) {
                return $svg;
            }

            $root = $document->documentElement;
            if (!$root instanceof DOMElement || $root->localName !== 'svg') {
                return $svg;
            }

            $parts = [];
            foreach ($root->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'svg') {
                    $parts[] = $child;
                }
            }

            if (count($parts) < 2) {
                return $svg;
            }

            [$qrCode, $logo] = $parts;
            $logoAspectRatio = $this->svgAspectRatio($logo);
            if ($logoAspectRatio === null) {
                return $svg;
            }

            $qrCodeSize = $this->config->qrCodeRenderSize;
            $logoWidth = $qrCodeSize * $logoAspectRatio;
            $totalWidth = $qrCodeSize + $logoWidth;
            $formattedQrCodeSize = $this->svgNumber($qrCodeSize);
            $formattedLogoWidth = $this->svgNumber($logoWidth);
            $formattedTotalWidth = $this->svgNumber($totalWidth);

            $root->setAttribute('width', $formattedTotalWidth);
            $root->setAttribute('height', $formattedQrCodeSize);
            $root->setAttribute('viewBox', "0 0 {$formattedTotalWidth} {$formattedQrCodeSize}");
            $root->setAttribute('preserveAspectRatio', 'xMinYMin meet');

            $qrCode->setAttribute('x', '0');
            $qrCode->setAttribute('y', '0');
            $qrCode->setAttribute('width', $formattedQrCodeSize);
            $qrCode->setAttribute('height', $formattedQrCodeSize);

            $logo->setAttribute('x', $formattedQrCodeSize);
            $logo->setAttribute('y', '0');
            $logo->setAttribute('width', $formattedLogoWidth);
            $logo->setAttribute('height', $formattedQrCodeSize);

            $resizedSvg = $document->saveXML($root);

            return is_string($resizedSvg) ? $resizedSvg : $svg;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }
    }

    private function svgAspectRatio(DOMElement $element): ?float
    {
        $viewBox = preg_split('/\s+/', trim($element->getAttribute('viewBox')));
        if (is_array($viewBox) && count($viewBox) === 4) {
            $width = (float) $viewBox[2];
            $height = (float) $viewBox[3];
        } else {
            $width = (float) $element->getAttribute('width');
            $height = (float) $element->getAttribute('height');
        }

        return $width > 0.0 && $height > 0.0 ? $width / $height : null;
    }

    private function svgNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }
}
