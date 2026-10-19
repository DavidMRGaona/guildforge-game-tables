<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Domain;

use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Tests\Support\Modules\ModuleTestCase;

final class ParticipantStatusLabelTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_expired_is_labelled_caducada(): void
    {
        $this->assertSame('Caducada', ParticipantStatus::Expired->label());
    }
}
