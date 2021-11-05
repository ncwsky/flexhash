<?php
declare(strict_types=1);

namespace FlexHash\Tests\Hasher;

use FlexHash\Hasher\Crc32Hasher;
use FlexHash\Hasher\Md5Hasher;

/**
 * @author Paul Annesley
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class HasherTest extends \PHPUnit\Framework\TestCase
{
    public function testCrc32Hash(): void
    {
        $hasher = new Crc32Hasher();
        $result1 = $hasher->hash('test');
        $result2 = $hasher->hash('test');
        $result3 = $hasher->hash('different');

        $this->assertEquals($result1, $result2);
        $this->assertNotEquals($result1, $result3); // fragile but worthwhile
    }

    public function testMd5Hash(): void
    {
        $hasher = new Md5Hasher();
        $result1 = $hasher->hash('test');
        $result2 = $hasher->hash('test');
        $result3 = $hasher->hash('different');

        $this->assertEquals($result1, $result2);
        $this->assertNotEquals($result1, $result3); // fragile but worthwhile
    }
}
