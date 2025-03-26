<?php


declare(strict_types=1);

namespace App\Domain\Entities;

use DateTime;

class Task
{
    public function __construct(
        public readonly ?int      $id = null,
        public readonly string    $title = '',
        public readonly ?string   $description = null,
        public readonly string    $status = 'pending',
        public readonly ?DateTime $dueDate = null,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            status: $data['status'] ?? 'pending',
            dueDate: isset($data['due_date']) ? new DateTime($data['due_date']) : null,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'due_date' => $this->dueDate?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }
}