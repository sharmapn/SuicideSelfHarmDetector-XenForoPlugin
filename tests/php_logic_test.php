<?php

// Lightweight logic tests which do not require a licensed XenForo runtime.
// They stub only XF::options(), then exercise pure add-on policy/context code.

class XF
{
    public static $testOptions;

    public static function options()
    {
        return self::$testOptions;
    }
}

require_once __DIR__ . '/../upload/src/addons/Pankaj/MHFSafeguard/Pipeline/PolicyDecision.php';
require_once __DIR__ . '/../upload/src/addons/Pankaj/MHFSafeguard/Content/ContentContext.php';

use Pankaj\MHFSafeguard\Pipeline\PolicyDecision;
use Pankaj\MHFSafeguard\Content\ContentContext;

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual)
    {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function configure(string $mode = 'moderate', bool $failOpen = true): void
{
    XF::$testOptions = (object)[
        'mhfsActionMode' => $mode,
        'mhfsModerateThreshold' => 85,
        'mhfsReviseThreshold' => 95,
        'mhfsFailOpen' => $failOpen,
    ];
}

$decision = new PolicyDecision();

configure('moderate');
assertSameValue('allow', $decision->decide([
    'api_success' => true,
    'highest_label' => 'not_harmful',
    'highest_score' => 99.9,
    'recommended_action' => 'allow',
]), 'Confident safe SVM result is not threshold-moderated');

assertSameValue('moderate', $decision->decide([
    'api_success' => true,
    'highest_label' => 'ideation',
    'highest_score' => 60,
    'recommended_action' => 'moderate',
]), 'Ideation is sent to moderation');

assertSameValue('moderate', $decision->decide([
    'api_success' => true,
    'highest_label' => 'method_or_action',
    'highest_score' => 60,
    'recommended_action' => 'moderate',
]), 'Method/action is moderated in moderate mode');

configure('revise');
assertSameValue('revise', $decision->decide([
    'api_success' => true,
    'highest_label' => 'method_or_action',
    'highest_score' => 60,
    'recommended_action' => 'moderate',
]), 'Method/action requests revision in revise mode');

assertSameValue('moderate', $decision->decide([
    'api_success' => true,
    'highest_label' => 'ideation',
    'highest_score' => 99,
    'recommended_action' => 'moderate',
]), 'Ideation remains a human moderation case in revise mode');

configure('log');
assertSameValue('allow', $decision->decide([
    'api_success' => true,
    'highest_label' => 'method_or_action',
    'highest_score' => 99,
    'recommended_action' => 'moderate',
]), 'Log mode does not alter publication state');

configure('moderate', true);
assertSameValue('allow', $decision->decide([
    'api_success' => false,
]), 'Fail-open permits content when API fails');

configure('moderate', false);
assertSameValue('moderate', $decision->decide([
    'api_success' => false,
]), 'Fail-closed sends content to moderation when API fails');

$context = new ContentContext([
    'contentType' => 'post',
    'contentId' => 123,
    'threadId' => 45,
    'nodeId' => 7,
    'userId' => 999,
    'username' => 'private-user',
    'title' => 'Example',
    'message' => 'Example message',
    'isFirstPost' => false,
]);

$payload = $context->toPayloadArray();
assertSameValue(false, array_key_exists('user_id', $payload), 'Outbound context omits user_id');
assertSameValue(false, array_key_exists('username', $payload), 'Outbound context omits username');
assertSameValue(999, $context->getUserId(), 'Local context retains user_id for audit logging');
assertSameValue('private-user', $context->getUsername(), 'Local context retains username for audit logging');

echo "All PHP logic tests passed.\n";
