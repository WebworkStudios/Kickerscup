<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class ImageDimensionsRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        // Value sollte ein Datei-Array aus $_FILES sein
        if (!is_array($value) || !isset($value['tmp_name']) || empty($value['tmp_name'])) {
            return false;
        }

        // Prüfe, ob es sich um ein Bild handelt
        if (!$this->isImage($value['tmp_name'])) {
            return false;
        }

        // Hole Bildabmessungen
        $dimensions = getimagesize($value['tmp_name']);
        if ($dimensions === false) {
            return false;
        }

        [$width, $height] = $dimensions;

        // Parameter-Format: [min_width, min_height, max_width, max_height]
        // Alle Parameter sind optional
        $minWidth = $params[0] ?? 0;
        $minHeight = $params[1] ?? 0;
        $maxWidth = $params[2] ?? PHP_INT_MAX;
        $maxHeight = $params[3] ?? PHP_INT_MAX;

        return $width >= $minWidth && $width <= $maxWidth &&
            $height >= $minHeight && $height <= $maxHeight;
    }

    /**
     * Prüft, ob eine Datei ein Bild ist
     */
    private function isImage(string $path): bool
    {
        // Prüfe MIME-Typ über PHP's fileinfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/webp',
            'image/svg+xml'
        ];

        return in_array($mimeType, $allowedTypes, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field muss ein Bild mit gültigen Abmessungen sein.";
    }
}