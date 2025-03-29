<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class PhoneNumberRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Bereinige die Telefonnummer von nicht-numerischen Zeichen,
        // außer Plus-Zeichen am Anfang für internationale Nummern
        $cleanedNumber = preg_replace('/[^\d+]/', '', $value);

        // Parameter: [countryCode] oder [format]
        // Wenn kein Parameter angegeben ist, nutze einen allgemeinen Regex-Check
        if (empty($params)) {
            // Allgemeine Telefonnummer-Validierung: mindestens 7 Ziffern, maximal 15 Ziffern
            // (gemäß ITU-T E.164 Standard)
            $pattern = '/^\+?[1-9]\d{6,14}$/';
            return preg_match($pattern, $cleanedNumber) === 1;
        }

        $format = $params[0];

        // Format kann ein Ländercode (z.B. 'DE', 'US') oder ein benutzerdefiniertes Format sein
        if (strlen($format) === 2) {
            // Länderspezifische Validierung
            return $this->validateCountryPhoneNumber($cleanedNumber, $format);
        }

        // Benutzerdefiniertes Format-Muster
        return $this->validateCustomFormat($value, $format);
    }

    /**
     * Validiert eine Telefonnummer nach Ländercode
     */
    private function validateCountryPhoneNumber(string $number, string $countryCode): bool
    {
        return match (strtoupper($countryCode)) {
            'DE' => preg_match('/^(?:\+49|0)[1-9]\d{8,11}$/', $number) === 1,
            'US' => preg_match('/^(?:\+1|1)?[2-9]\d{9}$/', $number) === 1,
            'GB' => preg_match('/^(?:\+44|0)[1-9]\d{8,9}$/', $number) === 1,
            'FR' => preg_match('/^(?:\+33|0)[1-9]\d{8}$/', $number) === 1,
            'IT' => preg_match('/^(?:\+39)?[0-9]\d{8,10}$/', $number) === 1,
            'ES' => preg_match('/^(?:\+34)?[6789]\d{8}$/', $number) === 1,
            'CH' => preg_match('/^(?:\+41|0)[1-9]\d{8}$/', $number) === 1,
            'AT' => preg_match('/^(?:\+43|0)[1-9]\d{3,12}$/', $number) === 1,
            // Weitere Länder könnten hier hinzugefügt werden
            default => $this->validateInternationalPhoneNumber($number),
        };
    }

    /**
     * Validiert eine Telefonnummer mit einem benutzerdefinierten Format
     * X = beliebige Ziffer, * = beliebige Anzahl von Ziffern
     */
    private function validateCustomFormat(string $number, string $format): bool
    {
        // Ersetze X durch \d und wandle das Format in einen regulären Ausdruck um
        $pattern = str_replace(['X', '*'], ['\d', '\d*'], $format);
        $pattern = '/^' . $pattern . '$/';

        return preg_match($pattern, $number) === 1;
    }

    /**
     * Internationale Telefonnummer-Validierung nach ITU-T E.164
     */
    private function validateInternationalPhoneNumber(string $number): bool
    {
        // Nach E.164: + gefolgt von 1-15 Ziffern
        return preg_match('/^\+[1-9]\d{1,14}$/', $number) === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field muss eine gültige Telefonnummer sein.";
    }
}