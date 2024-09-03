<?php declare(strict_types=1);

namespace Tests\libraries;

use Ntuple\Synctree\Template\BlockHandle\IBlock;

/**
 * Block unit test helper
 *
 * @since SRT-142
 */
trait BlockTestTrait
{
    /**
     * 블럭은 지정된 템플릿 구조를 갖는다.
     *
     * @param array $result {@link IBlock::getTemplate()} 호출 결과
     * @param array $slotNames 하위 템플릿에 포함될 슬롯 이름 배열
     */
    public function assertTemplateIsValid(array $result, array $slotNames = [])
    {
        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result, 'Template must have TYPE');
        $this->assertArrayHasKey('action', $result, 'Template must have ACTION');
        $this->assertArrayHasKey('template', $result, 'The template must have child template for slots');

        foreach ($slotNames as $name) {
            $this->assertArrayHasKey($name, $result['template'], "Template must have '{$name}' slot");
        }
    }
}