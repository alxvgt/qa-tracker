<?php

declare(strict_types=1);

namespace Alxvng\QATracker\DataProvider;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use JsonException;
use RuntimeException;

class JsonPathProvider extends AbstractJsonPathProvider
{
    /**
     * @throws JSONPathException
     * @throws JsonException
     */
    public function fetchData(): float
    {
        $data = json_decode(file_get_contents($this->inputFilePath), true, 512, JSON_THROW_ON_ERROR);
        $jsonFinder = new JSONPath($data);

        $nodes = $jsonFinder->find($this->jsonPathQuery);

        $result = $nodes->first();

        if (!is_numeric((string) $result)) {
            throw new RuntimeException(sprintf('The result of query must be a numeric value, got "%s"', $result));
        }

        return (float) $result;
    }
}
