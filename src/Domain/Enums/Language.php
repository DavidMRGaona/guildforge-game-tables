<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum Language: string
{
    case Spanish = 'es';
    case English = 'en';
    case Catalan = 'ca';
    case Basque = 'eu';
    case Galician = 'gl';

    public function label(): string
    {
        return match ($this) {
            self::Spanish => __('game-tables::messages.enums.language.es'),
            self::English => __('game-tables::messages.enums.language.en'),
            self::Catalan => __('game-tables::messages.enums.language.ca'),
            self::Basque => __('game-tables::messages.enums.language.eu'),
            self::Galician => __('game-tables::messages.enums.language.gl'),
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
