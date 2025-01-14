<?php
declare(strict_types=1);

namespace FlexHash\Hasher;

/**
 * Uses CRC32 to hash a value into a signed 32bit int address space.
 * Under 32bit PHP this (safely) overflows into negatives ints.
 *
 * @author Paul Annesley
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Crc32Hasher implements HasherInterface
{
    /**
     * @param $string
     * @return int
     */
    public function hash($string)
    {
        return crc32($string);
    }
}
