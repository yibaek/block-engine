<?php declare(strict_types=1);
namespace Tests\units\Template\BlockHandle\Blocks\Common\Random;

use Exception;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Common\Util\Random\RandomInteger;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use PHPUnit\Framework\TestCase;

/**
 * {@link RandomInteger} 블럭에 대한 unit test
 *
 * @since SRT-129
 */
class RandomIntegerTest extends TestCase
{
    /** @var int test 반복 횟수 */
    const RANDOM_TEST_TRIALS = 1000;

    /**
     * @test
     * @testdox {@link RandomInteger} 블럭은 min, max 블럭을 입력으로 받고 실행 결과로 min, max 사이의 정수를 반환한다.
     * @throws Exception
     * @throws ISynctreeException
     */
    public function block_returns_an_integer_between_min_and_max(): void
    {
        // arrange
        $storageStub = $this->createStub(PlanStorage::class);
        $extraStub = $this->createStub(ExtraManager::class);

        $min = $this->createStub(IBlock::class);
        $min->method('do')->willReturn(random_int(10, 100));
        $max = $this->createStub(IBlock::class);
        $max->method('do')->willReturn(random_int(200, 300));

        $blockStorage = [];
        $sut = new RandomInteger($storageStub, $extraStub, $min, $max);

        // act
        $result = $sut->do($blockStorage);

        // assert
        $blockStorageMin = [];
        $blockStorageMax = [];
        $this->assertGreaterThanOrEqual($min->do($blockStorageMin), $result);
        $this->assertLessThanOrEqual($max->do($blockStorageMax), $result);
    }

    /**
     * @test
     * @testdox 블럭은 min > max 입력에 대해 오류를 발생시킨다.
     * @throws Exception Log error
     * @throws ISynctreeException InvalidArgumentException
     * @dataProvider integerRangeProvider
     */
    public function block_throws_when_given_invalid_range($minValue, $maxValue, $expectedException): void
    {
        // assert
        if (null !== $expectedException) {
            $this->expectException(InvalidArgumentException::class);
        }

        // arrange
        $storageStub = $this->createStub(PlanStorage::class);
        $extraStub = $this->createStub(ExtraManager::class);
        $minBlock = $this->createStub(IBlock::class);
        $minBlock->method('do')->willReturn($minValue);
        $maxBlock = $this->createStub(IBlock::class);
        $maxBlock->method('do')->willReturn($maxValue);

        $sut = new RandomInteger($storageStub, $extraStub, $minBlock, $maxBlock);

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $minStorage = [];
        $maxStorage = [];
        $this->assertGreaterThanOrEqual($minBlock->do($minStorage), $result);
        $this->assertLessThanOrEqual($maxBlock->do($maxStorage), $result);
    }

    /**
     * @test
     * @testdox 블럭은 정수 이외의 타입에 대해 {@link InvalidArgumentException}을 발생시킨다.
     * @dataProvider invalidRangeProvider
     * @throws ISynctreeException
     */
    public function block_throws_when_given_invalid_input_of_type($minValue, $maxValue)
    {
        // assert
        $this->expectException(InvalidArgumentException::class);

        // arrange
        $storageStub = $this->createStub(PlanStorage::class);
        $extraStub = $this->createStub(ExtraManager::class);

        $min = $this->createStub(IBlock::class);
        $min->method('do')->willReturn($minValue);
        $max = $this->createStub(IBlock::class);
        $max->method('do')->willReturn($maxValue);

        $sut = new RandomInteger($storageStub, $extraStub, $min, $max);

        // act
        $blockStorage = [];
        $sut->do($blockStorage);
    }

    /**
     * 무작위로 Int 범위 내 숫자를 생성
     * dummy 데이터를 생성하고 생성된 값에 따라 기대 되는 에러를 추가한다.
     *
     * @throws Exception
     */
    public function integerRangeProvider(): iterable
    {
        for ($i = 0; $i < self::RANDOM_TEST_TRIALS; ++$i) {
            $min = random_int(PHP_INT_MIN, PHP_INT_MAX);
            $max = random_int(PHP_INT_MIN, PHP_INT_MAX);

            yield [$min, $max, ($min > $max) ? InvalidArgumentException::class : null];
        }
    }

    /**
     * 잘못된 입력 데이터 제공
     *
     * @return iterable
     */
    public function invalidRangeProvider(): iterable
    {
        yield 'min is string' => ['a', 1];
        yield 'max is string' => [-1, 'abc'];
        yield 'both are string' => ['both', 'string'];
        yield 'min is float' => [-0.1, 23];
        yield 'max is float' => [4, 5.6];
        yield 'both are float' => [0.1, 11.11];
        yield 'min is null' => [null, 1];
        yield 'max is null' => [0, null];
        yield 'both are null' => [null, null];
    }
}