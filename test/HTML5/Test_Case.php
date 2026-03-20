<?php

declare(strict_types=1);

namespace Masterminds\HTML5\Tests;

use Masterminds\HTML5;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    public const DOC_OPEN = '<!DOCTYPE html><html><head><title>test</title></head><body>';

    public const DOC_CLOSE = '</body></html>';

    public function getInstance(array $options = [])
    {
        return new HTML5($options);
    }

    protected function wrap($fragment)
    {
        return self::DOC_OPEN . $fragment . self::DOC_CLOSE;
    }
}
