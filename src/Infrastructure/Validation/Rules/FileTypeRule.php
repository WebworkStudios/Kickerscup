<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;
use finfo;

#[Injectable]
class FileTypeRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        // Value sollte ein Datei-Array aus $_FILES sein
        if (!is_array($value) || !isset($value['tmp_name']) || !isset($value['name']) || empty($value['tmp_name'])) {
            return false;
        }

        // Keine erlaubten Dateitypen angegeben
        if (empty($params)) {
            return false;
        }

        // Erlaubte MIME-Typen oder Dateiendungen
        $allowedTypes = $params;

        // Prüfe MIME-Typ über PHP's fileinfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($value['tmp_name']);

        // Prüfe Dateiendung
        $extension = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));

        // Prüfe, ob entweder der MIME-Typ oder die Dateiendung erlaubt ist
        foreach ($allowedTypes as $type) {
            // MIME-Typ Prüfung (z.B. 'image/jpeg')
            if (str_contains($type, '/') && $type === $mimeType) {
                return true;
            }

            // Dateiendungs-Prüfung (z.B. '.jpg', 'jpg')
            $cleanType = ltrim($type, '.');
            if (!str_contains($type, '/') && $cleanType === $extension) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field muss eine Datei eines erlaubten Dateityps sein.";
    }
}