<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;

/**
 * A table director as visitors see them: no account id, and no name unless they chose to show it.
 */
final readonly class GameMasterResponseDTO
{
    /**
     * @param  string  $displayName  The public name, empty when the director chose not to show it
     * @param  string  $cardName  How a card names them: the public name, else the title, else the role's label
     */
    public function __construct(
        public string $id,
        public string $gameTableId,
        public string $displayName,
        public GameMasterRole $role,
        public ?string $customTitle,
        public bool $isMain,
        public bool $isNamePublic,
        public string $cardName,
    ) {}

    /**
     * @param  string|null  $defaultTitle  The game system's title for its game masters, shown for the main game master without one of
     *                                     their own (a co-GM keeps the label of their role, which tells them apart)
     */
    public static function fromModel(GameMasterModel $model, string $gameTableId, ?string $defaultTitle = null): self
    {
        return new self(
            id: $model->id,
            gameTableId: $gameTableId,
            displayName: $model->publicName() ?? '',
            role: $model->role,
            customTitle: $model->shownTitle($defaultTitle),
            isMain: $model->role === GameMasterRole::Main,
            isNamePublic: $model->is_name_public,
            cardName: $model->cardName($defaultTitle),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'game_table_id' => $this->gameTableId,
            'display_name' => $this->displayName,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'custom_title' => $this->customTitle,
            'is_main' => $this->isMain,
            'is_name_public' => $this->isNamePublic,
        ];
    }
}
