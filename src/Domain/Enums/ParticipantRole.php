<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum ParticipantRole: string
{
    case GameMaster = 'game_master';
    case CoGm = 'co_gm';
    case Player = 'player';
    case Spectator = 'spectator';

    public function label(): string
    {
        return match ($this) {
            self::GameMaster => __('game-tables::messages.enums.participant_role.game_master'),
            self::CoGm => __('game-tables::messages.enums.participant_role.co_gm'),
            self::Player => __('game-tables::messages.enums.participant_role.player'),
            self::Spectator => __('game-tables::messages.enums.participant_role.spectator'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GameMaster => 'heroicon-o-star',
            self::CoGm => 'heroicon-o-user-plus',
            self::Player => 'heroicon-o-user',
            self::Spectator => 'heroicon-o-eye',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::GameMaster => 'warning',
            self::CoGm => 'info',
            self::Player => 'success',
            self::Spectator => 'gray',
        };
    }

    public function isGameMaster(): bool
    {
        return in_array($this, [self::GameMaster, self::CoGm], true);
    }

    public function isPlayer(): bool
    {
        return $this === self::Player;
    }

    public function isSpectator(): bool
    {
        return $this === self::Spectator;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case): string => $case->label(), self::cases()),
        );
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options for participant selection (excludes GM roles).
     *
     * @return array<string, string>
     */
    public static function participantOptions(): array
    {
        return [
            self::Player->value => self::Player->label(),
            self::Spectator->value => self::Spectator->label(),
        ];
    }
}
