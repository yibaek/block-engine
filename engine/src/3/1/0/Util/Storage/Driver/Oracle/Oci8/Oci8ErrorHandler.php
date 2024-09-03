<?php
namespace Ntuple\Synctree\Util\Storage\Driver\Oracle\Oci8;

abstract class Oci8ErrorHandler
{
    /**
     * @param string $message
     * @param array $otherPatterns
     * @throw Oci8Exception
     */
    protected function errorHandler(string $message, array $otherPatterns = []): void
    {
        $patterns = ['/ORA-(\d+)/', '/OCI-(\d+)/'];
        foreach ($patterns as $pattern) {
            preg_match($pattern, $message, $matches);
            if (is_array($matches) && array_key_exists(1, $matches)) {
                throw (new Oci8Exception($message))->setData($message, $matches[0] ?? '');
            }
        }

        // handling error message
        foreach ($otherPatterns as $pattern) {
            preg_match($pattern, $message, $matches);
            if (is_array($matches)) {
                throw new Oci8Exception($message);
            }
        }
    }
}