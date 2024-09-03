<?php declare(strict_types=1);
namespace Tests\units\Template\BlockHandle\Blocks;

use Ntuple\Synctree\Template\BlockHandle\IBlock;

/**
 * 지정된 입력을 그대로 돌려주는 테스트 블럭
 *
 * @since SRT-129
 */
class DummyBlock implements IBlock
{
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }


    public function setData(array $data): IBlock
    {
        return $this;
    }

    public function getTemplate(): array
    {
        return [
            'template' => []
        ];
    }

    public function do(array &$blockStorage)
    {
        return $this->value;
    }

    public function getValue()
    {
        return $this->value;
    }
}