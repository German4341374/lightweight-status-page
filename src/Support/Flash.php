<?php

declare(strict_types=1);

namespace App\Support;

final class Flash
{
    public function add(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public function consume(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        if (!\is_array($messages)) {
            return [];
        }

        $result = [];
        foreach ($messages as $message) {
            if (
                \is_array($message)
                && isset($message['type'], $message['message'])
                && \is_string($message['type'])
                && \is_string($message['message'])
            ) {
                $result[] = ['type' => $message['type'], 'message' => $message['message']];
            }
        }

        return $result;
    }
}
