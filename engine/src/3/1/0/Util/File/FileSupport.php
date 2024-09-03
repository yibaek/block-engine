<?php
namespace Ntuple\Synctree\Util\File;

use Ntuple\Synctree\Util\File\Adapter\IAdapter;
use Ntuple\Synctree\Util\File\Exception\FileOpenStreamException;
use Ntuple\Synctree\Util\File\Exception\FilePointerException;
use Ntuple\Synctree\Util\File\Exception\FileReadException;
use Ntuple\Synctree\Util\File\Exception\FileSeekException;
use Ntuple\Synctree\Util\File\Exception\FileWriteException;
use Ntuple\Synctree\Util\File\Exception\UtilFileException;
use SplFileObject;

class FileSupport
{
    private $pointer;
    private $adapter;

    /**
     * FileSupport constructor.
     * @param IAdapter $adapter
     */
    public function __construct(IAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @param IAdapter $adapter
     * @param int $offset
     * @param int|null $length
     * @return string
     */
    public static function readAll(IAdapter $adapter, int $offset = 0, int $length = null): string
    {
        try {
            if ($length === null) {
                $contents = file_get_contents($adapter->getFile(), false, null, $offset);
            } else {
                $contents = file_get_contents($adapter->getFile(), false, null, $offset, $length);
            }

            if ($contents === false) {
                throw new FileReadException('Failed to read file[filename:'.$adapter->getFileName().']');
            }

            return $contents;
        } catch (UtilFileException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new FileReadException('Failed to read file[filename:'.$adapter->getFileName().']');
        }
    }

    /**
     * @return string|null
     */
    public function readLine(): ?string
    {
        try {
            if (!$this->pointer) {
                throw new FilePointerException('Invalid File pointer');
            }

            if (($line=$this->pointer->fgets()) === false) {
                throw new FileReadException('Failed to read file[filename:'.$this->adapter->getFileName().']');
            }

            return $line;
        } catch (UtilFileException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            // for legacy
            if ($this->eof() === true) {
                return null;
            }
            throw new FileReadException('Failed to read file[filename:'.$this->adapter->getFileName().']');
        }
    }

    /**
     * @param int $line
     */
    public function seekLine(int $line): void
    {
        try {
            if (!$this->pointer) {
                throw new FilePointerException('Invalid File pointer');
            }

            $this->seek($line);
        } catch (UtilFileException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new FileSeekException('Failed to seek line[line:'.$line.']');
        }
    }

    /**
     * @param string $contents
     * @param int|null $length
     * @return int
     */
    public function write(string $contents, int $length = null): int
    {
        try {
            if (!$this->pointer) {
                throw new FilePointerException('Invalid File pointer');
            }

            if ($length !== null) {
                $writeBytes = $this->pointer->fwrite($contents, $length);
            } else {
                $writeBytes = $this->pointer->fwrite($contents);
            }

            if (false === $writeBytes) {
                throw new FileWriteException('Failed to write file[filename:'.$this->adapter->getFileName().']');
            }

            return $writeBytes;
        } catch (UtilFileException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new FileWriteException('Failed to write file[filename:'.$this->adapter->getFileName().']');
        }
    }

    /**
     * @return bool
     */
    public function eof(): bool
    {
        try {
            if (!$this->pointer) {
                throw new FilePointerException('Invalid File pointer');
            }

            return $this->pointer->eof();
        } catch (UtilFileException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new FilePointerException('An error occurred during file processing');
        }
    }

    /**
     * @param int|null $flags
     */
    public function setFlags(int $flags = null): void
    {
        try {
            if (!$this->pointer) {
                throw new FilePointerException('Invalid File pointer');
            }

            if ($flags !== null) {
                $this->pointer->setFlags($flags);
            }
        } catch (UtilFileException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new FilePointerException('An error occurred during file processing');
        }
    }

    /**
     * @param string $mode
     * @return FileSupport
     */
    public function createPointer(string $mode = 'rb'): self
    {
        try {
            $this->pointer = new SplFileObject($this->adapter->getFile(), $mode);
            return $this;
        } catch (UtilFileException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            $this->parseExceptionMessage($ex->getMessage());
            throw new FilePointerException('An error occurred during file processing');
        }
    }

    public function closePointer(): void
    {
        try {
            if ($this->pointer) {
                $this->pointer = null;
            }
        } catch (\Exception $ex) {
            return;
        }
    }

    /**
     * @param string $exeptionMessage
     */
    private function parseExceptionMessage(string $exeptionMessage): void
    {
        try {
            if (($pos=strpos($exeptionMessage, 'failed to open stream:')) !== false) {
                throw new FileOpenStreamException(ucfirst(trim(substr($exeptionMessage, $pos))) .'[filename:'.$this->adapter->getFileName().']');
            }
        } catch (UtilFileException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new FileOpenStreamException('Failed to open stream[filename:'.$this->adapter->getFileName().']');
        }
    }

    /**
     * PHP8.0.1 이하 버전에서 offset 1에 대한 버그가 있어서 해당 함수로 대체함.
     * https://bugs.php.net/bug.php?id=46569
     * @param int $line
     */
    private function seek(int $line): void
    {
        if (PHP_VERSION_ID >= 80001 || $line === 0) {
            $this->pointer->seek($line);
        } else {
            if ($line === 1 ) {
                $this->pointer->rewind();
                $this->pointer->fgets();
            } else {
                $this->pointer->seek($line-1);
            }
        }
    }
}