<?php
declare(strict_types=1);

const BASE_PATH = __DIR__ . '/..';
const CONFIG_PATH = BASE_PATH . '/config';

function loadJsonConfig(string $filename): array
{
    $path = CONFIG_PATH . '/' . $filename;
    if (!is_file($path)) {
        throw new RuntimeException("Configuration file not found: {$filename}");
    }
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}
