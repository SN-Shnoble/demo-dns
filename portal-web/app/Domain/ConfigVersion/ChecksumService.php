<?php

namespace App\Domain\ConfigVersion;

final class ChecksumService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function checksum(array $payload): string
    {
        return 'sha256:' . hash('sha256', CanonicalJson::encode($payload));
    }
}
