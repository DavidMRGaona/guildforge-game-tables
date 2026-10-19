<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

/**
 * A guest registration whose email belongs to an account: the person must sign in to register.
 */
final class AccountEmailRequiresLoginException extends DomainException
{
    public readonly string $reason;

    private function __construct(string $message)
    {
        parent::__construct($message);
        $this->reason = 'email_has_account';
    }

    public static function forEmail(): self
    {
        return new self(__('game-tables::messages.errors.email_has_account'));
    }
}
