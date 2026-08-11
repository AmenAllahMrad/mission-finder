<?php

namespace App\Scrapers\Contracts;

use App\Models\Source;

interface SourceAwareParserInterface extends SourceParserInterface
{
    public function setSource(Source $source): static;
}