<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum Genre: string
{
    case Fantasy = 'fantasy';
    case Horror = 'horror';
    case SciFi = 'sci_fi';
    case PostApocalyptic = 'post_apocalyptic';
    case Cyberpunk = 'cyberpunk';
    case Steampunk = 'steampunk';
    case Historical = 'historical';
    case Modern = 'modern';
    case Superhero = 'superhero';
    case Mystery = 'mystery';
    case Western = 'western';
    case Comedy = 'comedy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fantasy => __('game-tables::messages.enums.genre.fantasy'),
            self::Horror => __('game-tables::messages.enums.genre.horror'),
            self::SciFi => __('game-tables::messages.enums.genre.sci_fi'),
            self::PostApocalyptic => __('game-tables::messages.enums.genre.post_apocalyptic'),
            self::Cyberpunk => __('game-tables::messages.enums.genre.cyberpunk'),
            self::Steampunk => __('game-tables::messages.enums.genre.steampunk'),
            self::Historical => __('game-tables::messages.enums.genre.historical'),
            self::Modern => __('game-tables::messages.enums.genre.modern'),
            self::Superhero => __('game-tables::messages.enums.genre.superhero'),
            self::Mystery => __('game-tables::messages.enums.genre.mystery'),
            self::Western => __('game-tables::messages.enums.genre.western'),
            self::Comedy => __('game-tables::messages.enums.genre.comedy'),
            self::Other => __('game-tables::messages.enums.genre.other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fantasy => 'primary',
            self::Horror => 'danger',
            self::SciFi => 'info',
            self::PostApocalyptic => 'warning',
            self::Cyberpunk => 'success',
            self::Steampunk => 'warning',
            self::Historical => 'gray',
            self::Modern => 'info',
            self::Superhero => 'primary',
            self::Mystery => 'gray',
            self::Western => 'warning',
            self::Comedy => 'success',
            self::Other => 'gray',
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
