<?php

namespace App\Scrapers\Contracts;

interface SourceParserInterface
{
    public function fetch(): array;

    public function normaliser(array $rawItem): array;
}