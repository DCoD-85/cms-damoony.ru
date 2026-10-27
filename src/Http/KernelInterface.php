<?php

declare(strict_types=1);

namespace Damoony\Http;

interface KernelInterface
{
    public function handle(Request $request): Response;
}
