<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\Exceptions;

use Exception;

/**
 * Exception für Fehler, die während der Query-Erstellung oder -Ausführung auftreten
 */
class QueryException extends Exception
{
}