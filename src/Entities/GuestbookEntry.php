<?php


declare(strict_types=1);

namespace App\Entities;

readonly class GuestbookEntry
{
    public function __construct(
        public ?int   $id,
        public string $name,
        public string $email,
        public string $message,
        public string $createdAt
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['name'],
            $data['email'],
            $data['message'],
            $data['created_at'] ?? date('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'message' => $this->message,
            'created_at' => $this->createdAt
        ];
    }
}