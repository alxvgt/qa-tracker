<?php


namespace App\DataProvider;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use JsonException;

abstract class AbstractJsonPathReducerProvider extends AbstractJsonPathProvider implements ReducerProviderInterface
{
    /**
     * @return float
     * @throws JSONPathException
     * @throws JsonException
     */
    public function fetchData(): float
    {
        $data = json_decode(file_get_contents($this->inputFilePath), true, 512, JSON_THROW_ON_ERROR);
        $jsonFinder = new JSONPath($data);

        $nodes = $jsonFinder->find($this->jsonPathQuery);

        return $this->reduceMethod(iterator_to_array($nodes));
    }
}