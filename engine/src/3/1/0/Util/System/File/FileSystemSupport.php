<?php
namespace Ntuple\Synctree\Util\System\File;

use Ntuple\Synctree\Util\System\Exception\FileSystemException;
use Ntuple\Synctree\Util\System\Exception\UtilSystemException;
use Symfony\Component\Filesystem\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class FileSystemSupport
{
    private $fileSystem;

    public function __construct()
    {
        $this->fileSystem = new Filesystem();
    }

    /**
     * @param string $origin
     * @param string $target
     * @param bool $overwrite
     */
    public function move(string $origin, string $target, bool $overwrite = false): void
    {
        try {
            $this->fileSystem->rename((new Path($origin))->getFile(), (new Path($target))->getFile(), $overwrite);
        } catch (IOException|InvalidArgumentException $ex) {
            throw new FileSystemException($ex->getMessage());
        } catch (UtilSystemException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new FileSystemException('An error occurred during file system processing');
        }
    }
}