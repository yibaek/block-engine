<?php declare(strict_types=1);
namespace Tests\units\Template\BlockHandle\Blocks\Miscellaneous;

use Ntuple\Synctree\Exceptions\InvalidArgumentException;
use Ntuple\Synctree\Exceptions\ISynctreeException;
use Ntuple\Synctree\Plan\PlanStorage;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Miscellaneous\S3OptionStorageClass;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use PHPUnit\Framework\TestCase;
use Tests\libraries\RandomUtility;

/**
 * @since SRT-186
 */
class S3OptionStorageClassTest extends TestCase
{
    /**
     * @test
     * @testdox 블럭은 입력에 지정된 문자열을 출력으로서 그대로 반환한다.
     * @dataProvider valueProvider
     */
    public function block_returns_input_string_directly_as_output($value, $error)
    {
        // arrange
        $storage = $this->createStub(PlanStorage::class);
        $extra = $this->createStub(ExtraManager::class);

        $sut = new S3OptionStorageClass($storage, $extra, $value);

        if ($error) {
            $this->expectException($error);
        }

        $blockStorage = [];
        $result = $sut->do($blockStorage);

        $this->assertEquals($value, $result);
    }

    public function valueProvider(): iterable
    {
        foreach (S3OptionStorageClass::OPTIONS as $option) {
            yield $option => [$option, null];
        }
    }
}