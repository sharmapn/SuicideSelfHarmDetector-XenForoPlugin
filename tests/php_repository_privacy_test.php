<?php

// Persistence/privacy tests which do not require a licensed XenForo runtime.

class XF
{
    public static $testOptions;
    public static $testDb;
    public static $loggedErrors = [];

    public static function options()
    {
        return self::$testOptions;
    }

    public static function db()
    {
        return self::$testDb;
    }

    public static function logError(string $message): void
    {
        self::$loggedErrors[] = $message;
    }
}

class FakeDb
{
    public $table = '';
    public $row = [];
    public $throwOnInsert = false;

    public function insert(string $table, array $row): void
    {
        if ($this->throwOnInsert)
        {
            throw new RuntimeException('Synthetic database failure');
        }

        $this->table = $table;
        $this->row = $row;
    }
}

require_once __DIR__ . '/../upload/src/addons/Pankaj/MHFSafeguard/Content/ContentContext.php';
require_once __DIR__ . '/../upload/src/addons/Pankaj/MHFSafeguard/Pipeline/PayloadBuilder.php';
require_once __DIR__ . '/../upload/src/addons/Pankaj/MHFSafeguard/Repository/SafeguardScan.php';

use Pankaj\MHFSafeguard\Content\ContentContext;
use Pankaj\MHFSafeguard\Pipeline\PayloadBuilder;
use Pankaj\MHFSafeguard\Repository\SafeguardScan;

function assertPrivacySame($expected, $actual, string $message): void
{
    if ($expected !== $actual)
    {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function configurePrivacy(bool $storeMessage, bool $storeRaw): FakeDb
{
    XF::$testOptions = (object)[
        'boardUrl' => 'http://127.0.0.1/xenforo',
        'mhfsStoreMessage' => $storeMessage,
        'mhfsStoreRawResponse' => $storeRaw,
    ];
    XF::$testDb = new FakeDb();
    return XF::$testDb;
}

$message = 'Synthetic private test text';
$context = new ContentContext([
    'contentType' => 'post',
    'contentId' => 123,
    'threadId' => 45,
    'nodeId' => 7,
    'userId' => 999,
    'username' => 'private-user',
    'title' => 'Synthetic title',
    'message' => $message,
    'isFirstPost' => false,
]);
$result = [
    'api_success' => true,
    'api_status_code' => 200,
    'api_error' => '',
    'risk_level' => 'medium',
    'recommended_action' => 'moderate',
    'highest_label' => 'ideation',
    'highest_score' => 90,
    'flagged_parts' => [[
        'text' => $message,
        'label' => 'ideation',
        'score' => 90,
        'start_offset' => 0,
        'end_offset' => strlen($message),
    ]],
    'raw_response' => json_encode(['sentence_results' => [['text' => $message]]]),
];

$payload = (new PayloadBuilder())->build($context, $message);
assertPrivacySame(false, array_key_exists('user_id', $payload['context']), 'Outbound payload omits user_id');
assertPrivacySame(false, array_key_exists('username', $payload['context']), 'Outbound payload omits username');

$db = configurePrivacy(false, false);
(new SafeguardScan())->log($context, $message, $result, 'moderate');
$storedParts = json_decode($db->row['flagged_parts_json'], true);
assertPrivacySame('xf_mhfs_scan', $db->table, 'Audit row targets xf_mhfs_scan');
assertPrivacySame(null, $db->row['message_text'], 'Default privacy does not store message text');
assertPrivacySame(null, $db->row['api_response_json'], 'Default privacy does not store raw API response');
assertPrivacySame(false, array_key_exists('text', $storedParts[0]), 'Default privacy strips flagged sentence text');
assertPrivacySame(hash('sha256', $message), $db->row['message_hash'], 'Message hash is stored');
assertPrivacySame('ideation', $db->row['highest_label'], 'Classification label is stored');
assertPrivacySame('moderate', $db->row['final_action'], 'Final action is stored');
assertPrivacySame(200, $db->row['api_status_code'], 'API status is stored');

$db = configurePrivacy(false, true);
(new SafeguardScan())->log($context, $message, $result, 'moderate');
assertPrivacySame(
    null,
    $db->row['api_response_json'],
    'Raw response remains disabled unless message storage is also enabled'
);

$db = configurePrivacy(true, true);
(new SafeguardScan())->log($context, $message, $result, 'moderate');
$storedParts = json_decode($db->row['flagged_parts_json'], true);
assertPrivacySame($message, $db->row['message_text'], 'Explicit message storage retains message text');
assertPrivacySame($message, $storedParts[0]['text'], 'Explicit message storage retains flagged text');
assertPrivacySame($result['raw_response'], $db->row['api_response_json'], 'Explicit raw storage retains response');

$db->throwOnInsert = true;
(new SafeguardScan())->log($context, $message, $result, 'moderate');
assertPrivacySame(1, count(XF::$loggedErrors), 'Audit insert failure is logged without becoming fatal');

echo "All PHP repository/privacy tests passed.\n";
