<?php declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Helper\Helper\Dictionary;
use Ntuple\Synctree\Template\BlockHandle\Blocks\Primitive\CInteger;
use Ntuple\Synctree\Util\Extra\ExtraManager;
use PHPUnit\Framework\TestCase;
use Tests\engine\Models\Rdb\RdbHandlerMock;
use Tests\engine\Models\Rdb\StudioRdbMgrMock;
use Tests\fixtures\BizUnitFixture;

/**
 * @since SYN-672
 */
class DictionaryDataManagerTest extends TestCase
{
    /** @var int dictionary ID */
    const DICT_KEY = 1;

    /**
     * @test
     * @testdox 단일 요청 컨텍스트 안에서의 dictionary 호출은 항상 같은 값을 획득한다.
     * @return void
     * @throws Exception
     */
    public function getDictionaryData_in_single_request_returns_single_value()
    {
        // arrange
        $fixture = new BizUnitFixture();
        $fixture->initializeTransactionManager();
        $plan = $fixture->getPlanStorage();
        $extra = new ExtraManager($plan);
        $rdbMock = new RdbHandlerMock();

        define('EXPECTED_TXT', random_bytes(10));
        $plan->setRdbStudioResource(new StudioRdbMgrMock($rdbMock));

        $rdbMock->setDictionaryStub(self::DICT_KEY, EXPECTED_TXT);
        $blockStorage = [];
        $sut = new Dictionary($plan, $extra, new CInteger($plan, $extra, self::DICT_KEY));

        // act
        $result = $sut->do($blockStorage);

        // assert
        $this->assertEquals(EXPECTED_TXT, $result);

        for ($i = 0, $tries = mt_rand(2, 10); $i < $tries; ++$i) {
            // DB 변경이 있어도 항상 캐싱된 값을 가져온다.
            $rdbMock->setDictionaryStub(self::DICT_KEY, random_bytes(10));
            $blockStorage = [];
            $sut = new Dictionary($plan, $extra, new CInteger($plan, $extra, self::DICT_KEY));

            // act
            $result = $sut->do($blockStorage);

            // assert
            $this->assertEquals(EXPECTED_TXT, $result);
        }

        $this->assertEquals(1, $rdbMock->getDictionaryReadCounter(self::DICT_KEY));
    }
}
