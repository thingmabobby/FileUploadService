<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Enum;

use FileUploadService\Enum\CollisionStrategyEnum;
use PHPUnit\Framework\TestCase;

class CollisionStrategyEnumTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('increment', CollisionStrategyEnum::INCREMENT->value);
        $this->assertSame('uuid', CollisionStrategyEnum::UUID->value);
        $this->assertSame('timestamp', CollisionStrategyEnum::TIMESTAMP->value);
    }


    public function testGetLabel(): void
    {
        $this->assertSame('Increment', CollisionStrategyEnum::INCREMENT->getLabel());
        $this->assertSame('UUID', CollisionStrategyEnum::UUID->getLabel());
        $this->assertSame('Timestamp', CollisionStrategyEnum::TIMESTAMP->getLabel());
    }


    public function testGetDescription(): void
    {
        $this->assertSame('Appends incremental numbers (filename_1, filename_2)', CollisionStrategyEnum::INCREMENT->getDescription());
        $this->assertSame('Appends UUID for uniqueness (filename_a1b2c3d4)', CollisionStrategyEnum::UUID->getDescription());
        $this->assertSame('Appends timestamp (filename_1234567890)', CollisionStrategyEnum::TIMESTAMP->getDescription());
    }


    public function testIsHighPerformance(): void
    {
        $this->assertFalse(CollisionStrategyEnum::INCREMENT->isHighPerformance());
        $this->assertTrue(CollisionStrategyEnum::UUID->isHighPerformance());
        $this->assertFalse(CollisionStrategyEnum::TIMESTAMP->isHighPerformance());
    }


    public function testTryFromValidValue(): void
    {
        $this->assertSame(CollisionStrategyEnum::INCREMENT, CollisionStrategyEnum::tryFrom('increment'));
        $this->assertSame(CollisionStrategyEnum::UUID, CollisionStrategyEnum::tryFrom('uuid'));
        $this->assertSame(CollisionStrategyEnum::TIMESTAMP, CollisionStrategyEnum::tryFrom('timestamp'));
    }


    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(CollisionStrategyEnum::tryFrom('invalid'));
        $this->assertNull(CollisionStrategyEnum::tryFrom(''));
        $this->assertNull(CollisionStrategyEnum::tryFrom('INCREMENT'));
    }


    public function testFromValidValue(): void
    {
        $this->assertSame(CollisionStrategyEnum::INCREMENT, CollisionStrategyEnum::from('increment'));
        $this->assertSame(CollisionStrategyEnum::UUID, CollisionStrategyEnum::from('uuid'));
    }


    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        CollisionStrategyEnum::from('invalid');
    }


    public function testEnumCases(): void
    {
        $cases = CollisionStrategyEnum::cases();

        $this->assertIsArray($cases);
        $this->assertCount(3, $cases);

        foreach ($cases as $case) {
            $this->assertInstanceOf(CollisionStrategyEnum::class, $case);
        }
    }
}
