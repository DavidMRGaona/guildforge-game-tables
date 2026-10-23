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

    /**
     * Each language in its own tongue, the same whatever the site locale. It also names codes
     * outside the selectable cases, since the web forms only check the length of the code.
     */
    private const NATIVE_NAMES = [
        'es' => 'Español',
        'en' => 'English',
        'ca' => 'Català',
        'eu' => 'Euskara',
        'gl' => 'Galego',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'pt' => 'Português',
    ];

    public function nativeName(): string
    {
        return self::NATIVE_NAMES[$this->value];
    }

    /**
     * @return array<string, string>
     */
    public static function nativeOptions(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case): string => $case->nativeName(), self::cases()),
        );
    }

    /**
     * The native name of a stored code; an unknown code is returned as it is.
     */
    public static function nativeNameOf(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return self::NATIVE_NAMES[$code] ?? $code;
    }

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
