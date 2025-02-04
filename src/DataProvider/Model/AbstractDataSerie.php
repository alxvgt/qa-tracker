<?php

declare(strict_types=1);

namespace Alxvng\QATracker\DataProvider\Model;

use DateTimeImmutable;
use JsonException;
use RuntimeException;

use const JSON_THROW_ON_ERROR;

use function is_array;

abstract class AbstractDataSerie
{
    public const PROVIDERS_DIR = 'providers-data';
    public const DATE_FORMAT = 'YmdHis';

    protected string $storageFilePath;
    protected string $id;
    protected string $slug;
    protected array $data = [];

    public static function isStandard(array $providerConfig): bool
    {
        return isset($providerConfig['class'], $providerConfig['arguments']);
    }

    public static function isPercent(array $providerConfig): bool
    {
        return isset($providerConfig['provider'], $providerConfig['totalPercentProvider']);
    }

    public function addData($value, DateTimeImmutable $trackDate): void
    {
        $this->data[$trackDate->format(static::DATE_FORMAT)] = round($value, 2);
        $data = $this->data;
        ksort($data);
        $this->data = $data;
    }

    /**
     * @throws JsonException
     */
    public function save(): void
    {
        $dir = dirname($this->getStorageFilePath());
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($this->getStorageFilePath(), json_encode($this->data, JSON_THROW_ON_ERROR, 512));
    }

    public function reset(): void
    {
        $dir = dirname($this->getStorageFilePath());
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        file_put_contents($this->getStorageFilePath(), '');
        $this->data = [];
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getStorageFilePath(): string
    {
        return $this->storageFilePath;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getData(): array
    {
        return $this->data;
    }

    abstract public function collect(DateTimeImmutable $trackDate, bool $reset): void;

    /**
     * @throws JsonException
     */
    protected function load(): void
    {
        if (!file_exists($this->getStorageFilePath())) {
            return;
        }

        $data = json_decode(file_get_contents($this->getStorageFilePath()), true);
        $this->data = is_array($data) ? $data : [];
    }
}
