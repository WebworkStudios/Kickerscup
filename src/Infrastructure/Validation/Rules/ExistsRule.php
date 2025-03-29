<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Database\Contracts\QueryBuilderInterface;
use App\Infrastructure\Validation\ValidationException;
use Throwable;

#[Injectable]
readonly class ExistsRule implements ValidationRuleInterface
{
    public function __construct(
        private ?QueryBuilderInterface $database = null
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        if ($this->database === null) {
            throw new ValidationException("Datenbankverbindung für 'exists'-Validierung nicht verfügbar");
        }

        if (count($params) < 2) {
            throw new ValidationException("Die 'exists'-Regel benötigt mindestens zwei Parameter: Tabelle und Spalte");
        }

        $table = $params[0];
        $column = $params[1];

        try {
            $result = $this->database->table($table)
                ->select('COUNT(*) as count')
                ->where($column, '=', $value)
                ->first();

            return ($result['count'] ?? 0) > 0;
        } catch (Throwable $e) {
            throw new ValidationException("Datenbankfehler bei 'exists'-Validierung: " . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field existiert nicht in der Datenbank.";
    }
}