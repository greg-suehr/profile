<?php

namespace App\Katzen\Service\Delete;

final class DeleteReport
{
    public function __construct(
        public bool $ok,
        /** @var array<string,mixed> */
        public array $facts = [],  # total counts, ids
        /** @var string[] */
        public array $reasons = [] # user facing error messages, warnings
    ) {}
}

?>
