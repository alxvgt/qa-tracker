<?php

namespace Alxvng\QATracker\DataProvider\Reducer;

trait AverageReducerTrait
{
    public function reduceMethod(array $nodes): float
    {
        return round(\array_sum($nodes) / count($nodes), 2);
    }
}
