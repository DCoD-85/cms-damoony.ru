<?php

declare(strict_types=1);

namespace Damoony\Core\Http;

interface KernelInterface
{
    public function handle(Request $request): Response;
}
