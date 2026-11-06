<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Application\DTOs;

use Modules\GameTables\Application\DTOs\CampaignGameMasterResponseDTO;
use Modules\GameTables\Application\DTOs\GameMasterResponseDTO;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The main game master without a custom title shows the title of the game system («Narrador», «Keeper»…),
 * as the help of the custom title field promises. A co-GM keeps the label of their role, which is what tells
 * them apart; with no title at all, the role's label is used.
 */
final class GameMasterDefaultTitleTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_a_table_game_master_takes_the_system_title_by_default(): void
    {
        $this->assertSame('Narrador', GameMasterResponseDTO::fromModel($this->gm(null), 'table', 'Narrador')->customTitle);
        $this->assertSame('Árbitra', GameMasterResponseDTO::fromModel($this->gm('Árbitra'), 'table', 'Narrador')->customTitle);
        $this->assertNull(GameMasterResponseDTO::fromModel($this->gm(''), 'table')->customTitle);
        $this->assertNull(GameMasterResponseDTO::fromModel($this->gm(null, 'co_gm'), 'table', 'Narrador')->customTitle);
    }

    public function test_a_campaign_game_master_takes_the_system_title_by_default(): void
    {
        $this->assertSame('Narrador', CampaignGameMasterResponseDTO::fromModel($this->gm(''), 'Narrador')->customTitle);
        $this->assertSame('Árbitra', CampaignGameMasterResponseDTO::fromModel($this->gm('Árbitra'), 'Narrador')->customTitle);
        $this->assertNull(CampaignGameMasterResponseDTO::fromModel($this->gm(null))->customTitle);
        $this->assertNull(CampaignGameMasterResponseDTO::fromModel($this->gm(null, 'co_gm'), 'Narrador')->customTitle);
    }

    private function gm(?string $customTitle, string $role = 'main'): GameMasterModel
    {
        $gm = new GameMasterModel;
        $gm->forceFill([
            'id' => 'gm-1',
            'user_id' => null,
            'display_name' => 'Persona de ejemplo',
            'role' => $role,
            'custom_title' => $customTitle,
            'is_name_public' => true,
        ]);

        return $gm;
    }
}
