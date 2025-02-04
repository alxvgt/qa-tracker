<?php

declare(strict_types=1);

namespace Alxvng\QATracker\Tests\DataProvider;

use Alxvng\QATracker\DataProvider\XpathAverageProvider;
use PHPUnit\Framework\TestCase;

class XpathAverageProviderTest extends TestCase
{
    public function testFetchData(): void
    {
        $provider = new XpathAverageProvider(getcwd(), 'tests/resource/log/phploc/log.xml', '/phploc/*[starts-with(name(), \'lloc\')]');
        $result = $provider->fetchData();
        $this->assertIsFloat($result);
        $this->assertEquals(45.2, $result);
    }
}
