<?php

declare(strict_types=1);

namespace Alxvng\QATracker\DataProvider;

use SimpleXMLElement;

abstract class AbstractXpathReducerProvider extends AbstractXpathProvider implements ReducerProviderInterface
{
    public function fetchData(): float
    {
        $xml = new SimpleXMLElement(file_get_contents($this->inputFilePath));

        $nodes = $xml->xpath($this->xpathQuery);

        return $this->reduceMethod($nodes);
    }
}
