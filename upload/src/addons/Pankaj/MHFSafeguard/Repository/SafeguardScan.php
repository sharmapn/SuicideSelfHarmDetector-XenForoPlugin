<?php

namespace Pankaj\MHFSafeguard\Repository;

use Pankaj\MHFSafeguard\Content\ContentContext;

class SafeguardScan
{
    public function log(ContentContext $context, string $cleanMessage, array $result, string $finalAction): void
    {
        $options = \XF::options();
        $storeMessage = (bool)$options->mhfsStoreMessage;
        $storeRaw = (bool)$options->mhfsStoreRawResponse;

        // The API may return sentence text inside flagged_parts. If message
        // storage is disabled, strip that text before writing the audit record.
        $flaggedParts = $this->sanitiseFlaggedParts(
            (array)($result['flagged_parts'] ?? []),
            $storeMessage
        );

        try
        {
            \XF::db()->insert('xf_mhfs_scan', [
                'content_type' => $context->getContentType(),
                'content_id' => $context->getContentId(),
                'thread_id' => $context->getThreadId(),
                'node_id' => $context->getNodeId(),
                'user_id' => $context->getUserId(),
                'username' => $context->getUsername(),
                'message_hash' => hash('sha256', $cleanMessage),
                'message_text' => $storeMessage ? $cleanMessage : null,
                'risk_level' => (string)($result['risk_level'] ?? 'none'),
                'recommended_action' => (string)($result['recommended_action'] ?? 'allow'),
                'final_action' => $finalAction,
                'highest_label' => (string)($result['highest_label'] ?? ''),
                'highest_score' => (float)($result['highest_score'] ?? 0),
                'api_success' => !empty($result['api_success']) ? 1 : 0,
                'api_status_code' => (int)($result['api_status_code'] ?? 0),
                'api_error' => (string)($result['api_error'] ?? ''),
                'flagged_parts_json' => json_encode($flaggedParts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                // Raw API responses can include sentence text. Only retain them
                // when both explicit raw-response and message storage are on.
                'api_response_json' => ($storeRaw && $storeMessage)
                    ? (string)($result['raw_response'] ?? '')
                    : null,
                'scan_date' => time()
            ]);
        }
        catch (\Throwable $e)
        {
            \XF::logError('MHF Safeguard scan log failed: ' . $e->getMessage());
        }
    }

    protected function sanitiseFlaggedParts(array $parts, bool $includeText): array
    {
        $safe = [];

        foreach ($parts as $part)
        {
            if (!is_array($part))
            {
                continue;
            }

            $item = [
                'label' => (string)($part['label'] ?? ''),
                'score' => (float)($part['score'] ?? 0),
                'start_offset' => (int)($part['start_offset'] ?? 0),
                'end_offset' => (int)($part['end_offset'] ?? 0)
            ];

            if ($includeText && isset($part['text']))
            {
                $item['text'] = (string)$part['text'];
            }

            $safe[] = $item;
        }

        return $safe;
    }
}
