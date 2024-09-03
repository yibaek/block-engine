<?php declare(strict_types=1);
namespace Tests\units\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters;

use Exception;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamExpires;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use PHPUnit\Framework\TestCase;
use Tests\libraries\RandomUtility;

/**
 * @since SRT-201
 */
class S3ObjectParamExpiresTest extends TestCase
{
    /**
     * @test
     * @testdox 블럭은 (S3 parameter key, 값) 형태의 `array`를 반환한다.
     * @dataProvider mockDataProvider
     * @throws ISynctreeException
     */
    public function block_returns_tuple_contains_param_key_and_value(int $mockValue, $error)
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $value = $this->createStub(IBlock::class);
        $value->method('do')->willReturn($mockValue);

        $sut = new S3ObjectParamExpires($storage, $extra, $value);

        if ($error !== false) {
            $this->expectException($error);
        }

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(S3ObjectParamExpires::PARAM_KEY, $result[0]);
        $this->assertIsInt($result[1]);
        $this->assertEquals($mockValue, $result[1]);
    }

    /**
     * @test
     * @testdox 블럭은 `unix timestamp`를 표현하는 정수만을 값으로 받는다.
     * @dataProvider valueCaseProvider
     * @throws ISynctreeException
     */
    public function block_accepts_only_integer_as_a_value($mockValue, ?string $exception)
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $value = $this->createStub(IBlock::class);
        $value->method('do')->willReturn($mockValue);

        $sut = new S3ObjectParamExpires($storage, $extra, $value);

        if ($exception) {
            $this->expectException($exception);
        }

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(S3ObjectParamExpires::PARAM_KEY, $result[0]);
        $this->assertIsInt($result[1]);
    }


    /**
     * @throws Exception
     */
    public function mockDataProvider(): iterable
    {
        yield 'negative value -> fail' => [
            random_int(PHP_INT_MIN, -1),
            InvalidArgumentException::class
        ];

        for ($i = 0; $i < 10; ++$i) {
            $val = random_int(-1000000, time());
            $isError = $val < 0 ? InvalidArgumentException::class : false;
            $label = $isError !== false ? 'fail' : 'success';

            yield "$val -> $label" => [$val, $isError];
        }
    }


    /**
     * only integer is successful
     *
     * @returns (value, isError)
     * @throws Exception
     */
    public function valueCaseProvider(): iterable
    {
        $randomArray = (new RandomUtility())->getRandomAssociativeStringArray(5, 6, 10);

        yield 'associative array -> fail' => [$randomArray, InvalidArgumentException::class];
        yield 'empty array -> fail' => [[], InvalidArgumentException::class];
        yield 'string -> fail' => [
            'application/'.bin2hex(openssl_random_pseudo_bytes(10)),
            InvalidArgumentException::class
        ];
        yield 'integer -> success' => [random_int(0, PHP_INT_MAX), null];
        yield 'float -> fail' => [
            random_int(PHP_INT_MIN, PHP_INT_MAX) / 1.0 / PHP_INT_MAX,
            InvalidArgumentException::class
        ];
        yield 'null -> fail' => [null, InvalidArgumentException::class];
    }
}