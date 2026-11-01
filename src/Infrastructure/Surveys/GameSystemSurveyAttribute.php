<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Surveys;

use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use App\Application\Surveys\DTOs\SurveySubjectAttributeValueDTO;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;

/**
 * The game system of a table, campaign session or campaign as surveys see it: its name, for the
 * statistics, and its id, so surveys recognise it after a rename (a template per game system).
 * Every game system is listed, inactive ones too, since older tables still play them.
 */
final readonly class GameSystemSurveyAttribute
{
    public const string KEY = 'game_system';

    public function __construct(
        private GameSystemRepositoryInterface $systems,
    ) {}

    public function of(GameSystemId $id): SurveySubjectAttributeDTO
    {
        $system = $this->systems->find($id);

        return $system instanceof GameSystem
            ? new SurveySubjectAttributeDTO(self::KEY, $this->label(), $system->name, $system->id->value)
            : new SurveySubjectAttributeDTO(self::KEY, $this->label(), null);
    }

    /**
     * @return list<SurveySubjectAttributeValueDTO>
     */
    public function values(string $key): array
    {
        if ($key !== self::KEY) {
            return [];
        }

        return array_values(array_map(
            static fn (GameSystem $system): SurveySubjectAttributeValueDTO => new SurveySubjectAttributeValueDTO($system->id->value, $system->name),
            $this->systems->all(),
        ));
    }

    private function label(): string
    {
        return __('game-tables::messages.surveys.attributes.game_system');
    }
}
