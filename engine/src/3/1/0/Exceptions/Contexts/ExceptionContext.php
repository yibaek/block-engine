<?php declare(strict_types=1);

namespace Ntuple\Synctree\Exceptions\Contexts;

/**
 * {@link SynctreeException} 발생 시, 해당 예외가 발생한 상황을 기술한다.
 * {@link SynctreeException::setContext()}를 통해 에외 객체에 바인딩한다.
 *
 * @since SYN-389
 */
abstract class ExceptionContext
{
    /** @var string 예외 발생 컨텍스트 유형 정보 */
    protected $type;

    public function __construct()
    {
        $this->type = static::class;
    }

    /**
     * @return string 예외 발생 컨텍스트 유형
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 컨텍스트 내용을 Key-Value 형태로 반환한다.
     *
     * @return array
     */
    public function getData(): array
    {
        return [
            'type' => $this->getType(),
        ];
    }

    /**
     * @return string JSON 형식으로 {@link getData()} 내용을 인코딩
     */
    public function __toString(): string
    {
        return json_encode($this->getData());
    }
}