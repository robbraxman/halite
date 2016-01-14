<?php
declare(strict_types=1);
namespace ParagonIE\Halite\Stream;

use \ParagonIE\Halite\Contract\StreamInterface;
use \ParagonIE\Halite\Alerts as CryptoException;
use \ParagonIE\Halite\Util as CryptoUtil;

/**
 * Contrast with ReadOnlyFile: does not prevent race conditions by itself
 */
class MutableFile implements StreamInterface
{
    const CHUNK = 8192; // PHP's fread() buffer is set to 8192 by default
    
    private $fp;
    private $pos;
    private $stat = [];
    
    public function __construct($file)
    {
        if (is_string($file)) {
            $this->fp = \fopen($file, 'wb');
            $this->pos = 0;
            $this->stat = \fstat($this->fp);
        } elseif (is_resource($file)) {
            $this->fp = $file;
            $this->pos = \ftell($this->fp);
            $this->stat = \fstat($this->fp);
        } else {
            throw new \ParagonIE\Halite\Alerts\InvalidType(
                'Argument 1: Expected a filename or resource'
            );
        }
    }
    
    /**
     * Read from a stream; prevent partial reads
     * 
     * @param int $num
     * @return string
     * @throws CryptoException\AccessDenied
     * @throws CryptoException\CannotPerformOperation
     */
    public function readBytes(int $num, bool $skipTests = false): string
    {
        if ($num <= 0) {
            throw new \Exception('num < 0');
        }
        if (($this->pos + $num) > $this->stat['size']) {
            throw new CryptoException\CannotPerformOperation('Out-of-bounds read');
        }
        $buf = '';
        $remaining = $num;
        do {
            if ($remaining <= 0) {
                break;
            }
            $read = \fread($this->fp, $remaining);
            if ($read === false) {
                throw new CryptoException\FileAccessDenied(
                    'Could not read from the file'
                );
            }
            $buf .= $read;
            $readSize = CryptoUtil::safeStrlen($read);
            $this->pos += $readSize;
            $remaining -= $readSize;
        } while ($remaining > 0);
        return $buf;
    }
    
    /**
     * Write to a stream; prevent partial writes
     *
     * @param string $buf
     * @param int $num (number of bytes)
     * @return int
     * @throws CryptoException\FileAccessDenied
     * @throws CryptoException\CannotPerformOperation
     */
    public function writeBytes(string $buf, int $num = null): int
    {
        $bufSize = CryptoUtil::safeStrlen($buf);
        if ($num === null || $num > $bufSize) {
            $num = $bufSize;
        }
        if ($num < 0) {
            throw new CryptoException\CannotPerformOperation('num < 0');
        }
        $remaining = $num;
        do {
            if ($remaining <= 0) {
                break;
            }
            $written = \fwrite($this->fp, $buf, $remaining);
            if ($written === false) {
                throw new CryptoException\FileAccessDenied(
                    'Could not write to the file'
                );
            }
            $buf = CryptoUtil::safeSubstr($buf, $written, null);
            $this->pos += $written;
            $this->stat = \fstat($this->fp);
            $remaining -= $written;
        } while ($remaining > 0);
        return $num;
    }
    
    /**
     * Set the current cursor position to the desired location
     * 
     * @param int $i
     * @return boolean
     * @throws CryptoException\CannotPerformOperation
     */
    public function reset(int $i = 0): bool
    {
        $this->pos = $i;
        if (\fseek($this->fp, $i, SEEK_SET) === 0) {
            return true;
        }
        throw new CryptoException\CannotPerformOperation(
            'fseek() failed'
        );
    }
}
