<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum SafetyTool: string
{
    case XCard = 'x_card';
    case LinesAndVeils = 'lines_and_veils';
    case OpenDoor = 'open_door';
    case Stars = 'stars';
    case SupportFlower = 'support_flower';
    case Script = 'script';
    case Roses = 'roses';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::XCard => __('game-tables::messages.enums.safety_tool.x_card'),
            self::LinesAndVeils => __('game-tables::messages.enums.safety_tool.lines_and_veils'),
            self::OpenDoor => __('game-tables::messages.enums.safety_tool.open_door'),
            self::Stars => __('game-tables::messages.enums.safety_tool.stars'),
            self::SupportFlower => __('game-tables::messages.enums.safety_tool.support_flower'),
            self::Script => __('game-tables::messages.enums.safety_tool.script'),
            self::Roses => __('game-tables::messages.enums.safety_tool.roses'),
            self::Other => __('game-tables::messages.enums.safety_tool.other'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::XCard => 'Permite pausar o saltar contenido incomodo sin explicaciones',
            self::LinesAndVeils => 'Define limites absolutos y temas a tratar con sutileza',
            self::OpenDoor => 'Libertad para abandonar la sesion sin dar explicaciones',
            self::Stars => 'Sistema para pedir mas o menos de cierto contenido',
            self::SupportFlower => 'Metodo para indicar necesidad de apoyo emocional',
            self::Script => 'Permite rebobinar, avanzar o pausar la escena',
            self::Roses => 'Feedback positivo y constructivo al final de sesion',
            self::Other => 'Otra herramienta de seguridad',
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
