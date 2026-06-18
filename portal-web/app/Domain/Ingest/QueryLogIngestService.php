<?php

namespace App\Domain\Ingest;

final class QueryLogIngestService
{
    /**
     * @param array<string, mixed> $batch
     * @return array<string, mixed>
     */
    public function accept(array $batch): array
    {
        $itemCount = count($batch['items'] ?? []);
        if ($itemCount < 1 || $itemCount > 1000) {
            throw new \InvalidArgumentException('Batch size is out of bounds.');
        }

        $encoded = json_encode($batch['items'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode log items to JSON: ' . json_last_error_msg());
        }

        $contentSha = 'sha256:' . hash('sha256', $encoded);

        return [
            'accepted' => true,
            'batch_id' => $batch['batch_id'],
            'received_count' => $itemCount,
            'duplicate' => false,
            'content_sha256' => $contentSha,
        ];
    }
}
