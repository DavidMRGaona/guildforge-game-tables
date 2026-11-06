<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum RosterAccess: string
{
    case Anyone = 'anyone';
    case MembersOnly = 'members_only';

    /**
     * @param  string|null  $associationRole  visible name of the site's association member role
     *                                        (AssociationRoleNameInterface), named by «members only»
     */
    public function label(?string $associationRole = null): string
    {
        return match ($this) {
            self::Anyone => __('game-tables::messages.enums.roster_access.anyone'),
            self::MembersOnly => __('game-tables::messages.enums.roster_access.members_only', [
                'role' => $associationRole ?? __('game-tables::messages.association_role_fallback'),
            ]),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(?string $associationRole = null): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case): string => $case->label($associationRole), self::cases()),
        );
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
