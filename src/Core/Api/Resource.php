<?php
declare(strict_types=1);

namespace App\Core\Api;

interface Resource
{
    /**
     * Transformiert ein Modell in eine Ressource
     *
     * @param mixed $model Zu transformierendes Modell
     * @return array Transformierte Ressource
     */
    public function toArray(mixed $model): array;
}