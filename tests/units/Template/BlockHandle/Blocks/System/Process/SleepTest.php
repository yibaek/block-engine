<?php declare(strict_types=1);
namespace Tests\units\Template\BlockHandle\Blocks\System\Process;

use Exception;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\System\Process\Sleep;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@link Sleep} block
 *
 * @since SRT-127
 */
class SleepTest extends TestCase
{
    const DELAY_MIN = 1;
    const DELAY_MAX = 5;

    /**
     * @test
     * @testdox 블럭은 적어도 지정된 시간 동안 프로세스를 중단한다.
     * @return void
     * @throws Exception
     * @throws ISynctreeException
     */
    public function block_waits_within_duration(): void
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);
        $input = $this->createStub(IBlock::class);

        $randomDelay = random_int(self::DELAY_MIN, self::DELAY_MAX);
        $input->method('do')->willReturn($randomDelay);
        $startTime = microtime(true);

        $sut = new Sleep($storage, $extra, $input);

        // act
        $blockStorage = [];
        $sut->do($blockStorage);

        // assert
        $delay = microtime(true) - $startTime;
        $this->assertGreaterThanOrEqual($randomDelay, $delay);
    }

    /**
     * @test
     * @testdox 블럭은 delay 슬롯의 결과가 양의 정수가 아닌 경우 예외를 발생시킨다.
     * @throws Exception failure of random_int()
     * @throws ISynctreeException
     * @dataProvider inputCases
     */
    public function block_throws_invalid_argument_when_given_duration_is_invalid($delay, $expectedException)
    {
        // assert
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);
        $input = $this->createStub(IBlock::class);
        $input->method('do')->willReturn($delay);

        $sut = new Sleep($storage, $extra, $input);
        $startTime = microtime(true);

        // act
        $blockStorage = [];
        $sut->do($blockStorage);

        // assert
        $elapsedTime = microtime(true) - $startTime;
        $this->assertGreaterThanOrEqual($delay, $elapsedTime);
    }

    /**
     * @throws Exception
     */
    public function inputCases(): iterable
    {
        yield 'positive duration' => [random_int(0, 10), null];
        yield 'negative duration' => [random_int(-10000, -1), InvalidArgumentException::class];
        yield 'duration is null' => [null, InvalidArgumentException::class];
        yield 'duration is string' => [bin2hex(random_bytes(10)), InvalidArgumentException::class];
    }
}