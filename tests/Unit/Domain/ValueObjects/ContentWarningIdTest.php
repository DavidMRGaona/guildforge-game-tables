<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\GameTables\Domain\ValueObjects\ContentWarningId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ContentWarningIdTest extends TestCase
{
    public function test_it_generates_valid_uuid(): void
    {
        $id = ContentWarningId::generate();

        $this->assertInstanceOf(ContentWarningId::class, $id);
        $this->assertTrue(Uuid::isValid($id->value()));
    }

    public function test_it_creates_from_valid_uuid_string(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $id = ContentWarningId::fromString($uuid);

        $this->assertInstanceOf(ContentWarningId::class, $id);
        $this->assertEquals($uuid, $id->value());
    }

    public function test_it_throws_exception_with_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID: not-a-uuid');

        ContentWarningId::fromString('not-a-uuid');
    }

    public function test_it_compares_equality(): void
    {
        $uuid = Uuid::uuid4()->toString();
        $id1 = ContentWarningId::fromString($uuid);
        $id2 = ContentWarningId::fromString($uuid);
        $id3 = ContentWarningId::generate();

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function test_it_converts_to_string(): void
    {
        $uuid = Uuid::uuid4()->toString();
        $id = ContentWarningId::fromString($uuid);

        $this->assertEquals($uuid, (string) $id);
        $this->assertEquals($uuid, $id->__toString());
    }

    public function test_it_returns_value(): void
    {
        $uuid = Uuid::uuid4()->toString();
        $id = ContentWarningId::fromString($uuid);

        $this->assertEquals($uuid, $id->value());
    }
}
