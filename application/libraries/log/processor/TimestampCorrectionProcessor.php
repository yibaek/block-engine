<?php
namespace libraries\log\processor;

/**
 * context 내용을 정리한다.
 *   - monolog가 기본값으로 넣어주는 timestamp를 실제 로그의 발생 시점으로 덮어씀.
 *   - *_sno 필드가 string인 경우 int로 타입 변경.
 */
class TimestampCorrectionProcessor
{
    /**
     * @param array $record Monolog record
     */
    public function __invoke(array $record): array
    {
        $context = $record['context'];

        if (array_key_exists('__timestamp', $context)) {
            $record['datetime'] = $context['__timestamp'];
            unset($record['context']['__timestamp']);
        }

        if (isset($context['bizunit_sno'])) {
            $record['context']['bizunit_sno'] = (int) $context['bizunit_sno'];
        }

        if (isset($context['revision_sno'])) {
            $record['context']['revision_sno'] = (int) $context['revision_sno'];
        }

        return $record;
    }
}