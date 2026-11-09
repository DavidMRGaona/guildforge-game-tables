<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * Who a mail goes to: an account (userId set), which the core recognises and whose mail
 * choices it applies, or a plain address (a guest, an external game master, a table's mailbox).
 */
final readonly class MailRecipientDTO
{
    public function __construct(
        public string $email,
        public string $name = '',
        public ?string $userId = null,
    ) {}

    public static function account(string $userId, string $email, string $name = ''): self
    {
        return new self($email, $name, $userId);
    }

    public static function address(string $email, string $name = ''): self
    {
        return new self($email, $name);
    }

    public function isAccount(): bool
    {
        return $this->userId !== null;
    }
}
