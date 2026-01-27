<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum CharacterCreation: string
{
    case PreGenerated = 'pre_generated';
    case BringOwn = 'bring_own';
    case CreateAtTable = 'create_at_table';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::PreGenerated => __('game-tables::messages.enums.character_creation.pre_generated'),
            self::BringOwn => __('game-tables::messages.enums.character_creation.bring_own'),
            self::CreateAtTable => __('game-tables::messages.enums.character_creation.create_at_table'),
            self::Any => __('game-tables::messages.enums.character_creation.any'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PreGenerated => 'heroicon-o-document-text',
            self::BringOwn => 'heroicon-o-arrow-up-tray',
            self::CreateAtTable => 'heroicon-o-pencil-square',
            self::Any => 'heroicon-o-check-circle',
        };
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
}
