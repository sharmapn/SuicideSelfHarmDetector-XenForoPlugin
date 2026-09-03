<?php

namespace Pankaj\MHFSafeguard\Gateway;

class ClassifierGateway
{
    public function classify(array $payload): array
    {
        $options = \XF::options();
        $apiUrl = trim((string)$options->mhfsApiUrl);
        $apiKey = trim((string)$options->mhfsApiKey);
        $timeout = (int)$options->mhfsTimeout;

        if ($timeout <= 0)
        {
            $timeout = 8;
        }

        if ($apiUrl === '')
        {
            return $this->failure(0, 'Classifier API URL is not configured.');
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Pankaj-MHFSafeguard-XenForo/0.2'
        ];

        if ($apiKey !== '')
        {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        try
        {
            $client = \XF::app()->http()->client();
            $response = $client->post($apiUrl, [
                'headers' => $headers,
                'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'timeout' => $timeout,
                'connect_timeout' => min($timeout, 5),
                'http_errors' => false
            ]);

            $status = (int)$response->getStatusCode();
            $raw = (string)$response->getBody()->getContents();
            $httpOk = ($status >= 200 && $status < 300);

            try
            {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (\JsonException $e)
            {
                return $this->failure(
                    $status,
                    'Classifier returned invalid JSON: ' . $e->getMessage(),
                    $raw
                );
            }

            if (!is_array($data))
            {
                return $this->failure($status, 'Classifier response is not a JSON object.', $raw);
            }

            if (!$httpOk)
            {
                $remoteError = isset($data['error']) ? trim((string)$data['error']) : '';
                return $this->failure(
                    $status,
                    $remoteError !== '' ? $remoteError : ('HTTP ' . $status),
                    $raw,
                    $data
                );
            }

            // A successful response must contain the fields needed to make a
            // moderation decision. A 2xx with an empty/malformed object is a
            // backend failure, not an implicit allow decision.
            if (!array_key_exists('highest_label', $data)
                || !array_key_exists('recommended_action', $data)
                || !array_key_exists('highest_score', $data))
            {
                return $this->failure(
                    $status,
                    'Classifier response is missing required fields.',
                    $raw,
                    $data
                );
            }

            return [
                'ok' => true,
                'status' => $status,
                'error' => '',
                'raw' => $raw,
                'data' => $data
            ];
        }
        catch (\Throwable $e)
        {
            return $this->failure(0, $e->getMessage());
        }
    }

    protected function failure(int $status, string $error, string $raw = '', array $data = []): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error,
            'raw' => $raw,
            'data' => $data
        ];
    }
}
