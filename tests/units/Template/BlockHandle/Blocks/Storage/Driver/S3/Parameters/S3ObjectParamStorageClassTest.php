<?php declare(strict_types=1);

namespace Tests\units\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters;

use Exception;
use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Storage\Driver\S3\Parameters\S3ObjectParamStorageClass;
use Ntuple\Synctree\Template\BlockHandle\IBlock;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use PHPUnit\Framework\TestCase;

/**
 * @since SRT-201
 */
class S3ObjectParamStorageClassTest extends TestCase
{
    /**
     * @test
     * @testdox 블럭은 (S3 parameter key, 값) 형태의 `array`를 반환한다.
     * @dataProvider mockDataProvider
     * @throws ISynctreeException
     */
    public function block_returns_tuple_contains_param_key_and_value(string $mockValue)
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $value = $this->createStub(IBlock::class);
        $value->method('do')->willReturn($mockValue);

        $sut = new S3ObjectParamStorageClass($storage, $extra, $value);

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(S3ObjectParamStorageClass::PARAM_KEY, $result[0]);
        $this->assertIsString($result[1]);
        $this->assertEquals($mockValue, $result[1]);
    }

    /**
     * @test
     * @testdox 블럭은 `storage class`를 표현할 수 있는 `string`만을 값으로 받는다.
     * @dataProvider valueCaseProvider
     * @throws ISynctreeException
     */
    public function block_accepts_only_string_as_a_value($mockValue, ?string $exception)
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $value = $this->createStub(IBlock::class);
        $value->method('do')->willReturn($mockValue);

        $sut = new S3ObjectParamStorageClass($storage, $extra, $value);

        if ($exception) {
            $this->expectException($exception);
        }

        // act
        $blockStorage = [];
        $result = $sut->do($blockStorage);

        // assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(S3ObjectParamStorageClass::PARAM_KEY, $result[0]);
        $this->assertIsString($result[1]);
    }

    public function mockDataProvider(): iterable
    {
        $ret = ['STANDARD', 'GLACIER'];

        foreach ($ret as $item) {
            yield $item => [$item];
        }
    }

    /**
     * @returns (value, isError)
     * @throws Exception
     */
    public function valueCaseProvider(): iterable
    {
        yield 'string' => [
            'application/'.bin2hex(openssl_random_pseudo_bytes(10)),
            null
        ];
        yield 'integer' => [random_int(PHP_INT_MIN, PHP_INT_MAX), InvalidArgumentException::class];
        yield 'float' => [
            random_int(PHP_INT_MIN, PHP_INT_MAX) / 1.0 / PHP_INT_MAX,
            InvalidArgumentException::class
        ];
        yield 'array' => [[], InvalidArgumentException::class];
        yield 'null' => [null, InvalidArgumentException::class];
    }
}