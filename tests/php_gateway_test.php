<?php

// Gateway/failure-policy tests which do not require a licensed XenForo runtime.
// The stubs implement only the XF options and HTTP-client methods used by the
// add-on, allowing malformed and failing classifier responses to be exercised.

class XF
{
    public static $testOptions;
    public static $testApp;

    public static function options()
    {
        return self::$testOptions;
    }

    public static function app()
    {
        return self::$testApp;
    }
}

class FakeBody
{
    protected $contents;

    public function __construct(string $contents)
    {
        $this->contents = $contents;
    }

    public function getContents(): string
    {
        return $this->contents;
    }
}

class FakeResponse
{
    protected $status;
    protected $body;

    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body = new FakeBody($body);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getBody(): FakeBody
    {
        return $this->body;
    }
}

class FakeClient
{
    public $response;
    public $throwMessage = '';
    public $lastUrl = '';
    public $lastOptions = [];

    public function post(string $url, array $options)
    {
        $this->lastUrl = $url;
        $this->lastOptions = $options;

        if ($this->throwMessage !== '')
        {
            throw new RuntimeException($this->throwMessage);
        }

        return $this->response;
    }
}

class FakeHttp
{
    protected $client;

    public function __construct(FakeClient $client)
    {
        $this->client = $client;
    }

    public function client(): FakeClient
    {
        return $this->client;
    }
}

class FakeApp
{
    protected $http;

    public function __construct(FakeClient $client)
    {
        $this->http = new FakeHttp($client);
    }

    public function http(): FakeHttp
    {
        return $this->http;
    }
}

require_once __DIR__ . '/../upload/src/addons/Pankaj/MHFSafeguard/Gateway/ClassifierGateway.php';
require_once __DIR__ . '/../upload/src/addons/Pankaj/MHFSafeguard/Pipeline/ResponseInterpreter.php';
require_once __DIR__ . '/../upload/src/addons/Pankaj/MHFSafeguard/Pipeline/PolicyDecision.php';

use Pankaj\MHFSafeguard\Gateway\ClassifierGateway;
use Pankaj\MHFSafeguard\Pipeline\PolicyDecision;
use Pankaj\MHFSafeguard\Pipeline\ResponseInterpreter;

function assertGatewaySame($expected, $actual, string $message): void
{
    if ($expected !== $actual)
    {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function configureGateway(FakeClient $client, bool $failOpen = true): void
{
    XF::$testOptions = (object)[
        'mhfsApiUrl' => 'http://127.0.0.1:8000/api/classify',
        'mhfsApiKey' => 'temporary-test-secret',
        'mhfsTimeout' => 8,
        'mhfsActionMode' => 'moderate',
        'mhfsModerateThreshold' => 85,
        'mhfsReviseThreshold' => 95,
        'mhfsFailOpen' => $failOpen,
    ];
    XF::$testApp = new FakeApp($client);
}

function finalActionFor(array $gatewayResponse, bool $failOpen): string
{
    XF::$testOptions->mhfsFailOpen = $failOpen;
    $result = (new ResponseInterpreter())->interpret($gatewayResponse);
    return (new PolicyDecision())->decide($result);
}

$payload = ['message' => 'synthetic local test'];
$client = new FakeClient();
$client->response = new FakeResponse(200, json_encode([
    'risk_level' => 'none',
    'recommended_action' => 'allow',
    'highest_label' => 'not_harmful',
    'highest_score' => 90,
    'flagged_parts' => [],
]));
configureGateway($client);
$gateway = new ClassifierGateway();
$success = $gateway->classify($payload);
assertGatewaySame(true, $success['ok'], 'Valid 200 classifier response succeeds');
assertGatewaySame(
    'Bearer temporary-test-secret',
    $client->lastOptions['headers']['Authorization'],
    'Configured API key is sent as a bearer token'
);
assertGatewaySame(
    'synthetic local test',
    json_decode($client->lastOptions['body'], true)['message'],
    'Classifier request body remains valid JSON'
);

$client->response = new FakeResponse(200, 'not-json');
$malformed = $gateway->classify($payload);
assertGatewaySame(false, $malformed['ok'], 'Malformed JSON is an API failure');
assertGatewaySame('allow', finalActionFor($malformed, true), 'Malformed JSON respects fail-open');
assertGatewaySame('moderate', finalActionFor($malformed, false), 'Malformed JSON respects fail-closed');

$client->response = new FakeResponse(500, json_encode(['error' => 'Synthetic backend failure']));
$serverError = $gateway->classify($payload);
assertGatewaySame(false, $serverError['ok'], 'HTTP 500 is an API failure');
assertGatewaySame(500, $serverError['status'], 'HTTP 500 status is retained');
assertGatewaySame('moderate', finalActionFor($serverError, false), 'HTTP 500 respects fail-closed');

$client->response = new FakeResponse(401, json_encode(['error' => 'Unauthorized request.']));
$unauthorized = $gateway->classify($payload);
assertGatewaySame(false, $unauthorized['ok'], 'Authentication mismatch is an API failure');
assertGatewaySame(401, $unauthorized['status'], 'Authentication status is retained');

$client->response = new FakeResponse(200, json_encode(['highest_label' => 'not_harmful']));
$missingFields = $gateway->classify($payload);
assertGatewaySame(false, $missingFields['ok'], 'A 200 response missing decision fields fails closed');

$client->throwMessage = 'Synthetic connection failure';
$transportFailure = $gateway->classify($payload);
assertGatewaySame(false, $transportFailure['ok'], 'Transport exception is an API failure');
assertGatewaySame('allow', finalActionFor($transportFailure, true), 'Transport exception respects fail-open');
assertGatewaySame('moderate', finalActionFor($transportFailure, false), 'Transport exception respects fail-closed');

echo "All PHP gateway tests passed.\n";
