<?php
namespace Ntuple\Synctree\Util\File\Adapter;

use Ntuple\Synctree\Util\CommonUtil;
use Ntuple\Synctree\Util\File\Exception\FileAllowPathException;

class Local implements IAdapter
{
    private $filename;
    private $filepath;

    /**
     * Local constructor.
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
        $this->filepath = $this->getUserStoragePath();
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function getFile(): string
    {
        $basePath = realpath($this->filepath);
        $checkPath = realpath($this->filepath.'/'.$this->parseFilePath($this->filename));

        if ($checkPath === false || strpos($checkPath, $basePath) !== 0) {
            throw new FileAllowPathException('Not within the allowed path[filename:'.$this->filename.']');
        }

        return $basePath.'/'.$this->filename;
    }

    /**
     * @return string
     */
    private function getUserStoragePath(): string
    {
        return CommonUtil::getUserStoragePath();
    }

    /**
     * @param string $fileName
     * @return false|string
     */
    private function parseFilePath(string $fileName)
    {
        $pathInfo = pathInfo($fileName);
        return substr($fileName, 0, strlen($fileName)-strlen($pathInfo['basename']));
    }
}