<?php

namespace FlatRate\WikiContext\Context;

final class ContextWriteException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $httpStatus = 422
    ) {
        parent::__construct($reason);
    }
}
