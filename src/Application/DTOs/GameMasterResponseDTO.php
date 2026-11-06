<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;

final readonly class GameMasterResponseDTO
{
    public function __construct(
        public string $id,
        public string $gameTableId,
        public ?string $userId,
        public string $displayName,
        public GameMasterRole $role,
        public ?string $customTitle,
        public bool $isMain,
        public bool $isNamePublic,
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
            userId: $model->user_id,
            displayName: $model->display_name,
            role: $model->role,
            customTitle: self::title($model->custom_title, $model->role === GameMasterRole::Main ? $defaultTitle : null),
            isMain: $model->role === GameMasterRole::Main,
            isNamePublic: $model->is_name_public,
        );
    }

    private static function title(?string $own, ?string $default): ?string
    {
        foreach ([$own, $default] as $title) {
            if ($title !== null && trim($title) !== '') {
                return $title;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'game_table_id' => $this->gameTableId,
            'user_id' => $this->userId,
            'display_name' => $this->displayName,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'custom_title' => $this->customTitle,
            'is_main' => $this->isMain,
            'is_name_public' => $this->isNamePublic,
        ];
    }
}
