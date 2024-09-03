<?php declare(strict_types=1);

namespace Ntuple\Synctree\Exceptions\Contexts;

/**
 * 블럭에 설정된 값에 오류가 있음을 서술한다.
 *
 * @since SYN-389
 */
class InvalidArgumentExceptionContext extends ExceptionContext
{
    /** @var mixed */
    private $expected;

    /** @var mixed */
    private $actual;


    public function setExpected($expected): self
    {
        $this->expected = $expected;
        return $this;
    }

    /**
     * @return mixed 요구되었던 값 또는 그 형식
     */
    public function getExpected()
    {
        return $this->expected;
    }

    public function setActual($actual): self
    {
        $this->actual = $actual;
        return $this;
    }


    /**
     * @return mixed 예외를 발생시킨 값
     */
    public function getActual()
    {
        return $this->actual;
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        return [
            'type' => $this->getType(),
            'expected' => $this->getExpected(),
            'actual' => $this->getActual()
        ];
    }
}