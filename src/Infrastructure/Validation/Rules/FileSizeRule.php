<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class FileSizeRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        // Value sollte ein Datei-Array aus $_FILES sein
        if (!is_array($value) || !isset($value['tmp_name']) || !isset($value['size']) || empty($value['tmp_name'])) {
            return false;
        }

        // Parameter: [maxSize(KB|MB|GB|TB), optional: minSize(KB|MB|GB|TB)]
        if (empty($params)) {
            return false;
        }

        $maxSizeStr = $params[0];
        $minSizeStr = $params[1] ?? null;

        $maxSize = $this->parseSize($maxSizeStr);
        $minSize = $minSizeStr ? $this->parseSize($minSizeStr) : 0;

        $fileSize = $value['size']; // in Bytes

        return $fileSize <= $maxSize && $fileSize >= $minSize;
    }

    /**
     * Parst eine Größenangabe wie '5MB' oder '2G' in Bytes
     */
    private function parseSize(string $sizeStr): int
    {
        $sizeStr = strtoupper($sizeStr);

        // Extrahiere numerischen Wert und Einheit
        if (!preg_match('/^(\d+)([KMGT]B?)?$/', $sizeStr, $matches)) {
            return (int)$sizeStr; // Falls keine Einheit, nimm die Zahl als Bytes
        }

        $size = (int)$matches[1];
        $unit = $matches[2] ?? '';

        return match ($unit) {
            'K', 'KB' => $size * 1024,
            'M', 'MB' => $size * 1024 * 1024,
            'G', 'GB' => $size * 1024 * 1024 * 1024,
            'T', 'TB' => $size * 1024 * 1024 * 1024 * 1024,
            default => $size,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field muss eine Datei mit gültiger Größe sein.";
    }
}