<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

final readonly class MailRecipientDTO
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}
