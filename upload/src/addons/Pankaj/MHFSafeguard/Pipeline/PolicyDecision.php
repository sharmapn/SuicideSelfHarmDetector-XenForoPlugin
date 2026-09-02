<?php

namespace Pankaj\MHFSafeguard\Pipeline;

class PolicyDecision
{
    public function decide(array $result): string
    {
        $options = \XF::options();
        $mode = strtolower(trim((string)$options->mhfsActionMode));
        $moderateThreshold = (float)$options->mhfsModerateThreshold;
        $reviseThreshold = (float)$options->mhfsReviseThreshold;
        $failOpen = (bool)$options->mhfsFailOpen;

        if (!in_array($mode, ['log', 'moderate', 'revise'], true))
        {
            $mode = 'moderate';
        }

        if (empty($result['api_success']))
        {
            return $failOpen ? 'allow' : 'moderate';
        }

        if ($mode === 'log')
        {
            return 'allow';
        }

        $recommended = strtolower(trim((string)($result['recommended_action'] ?? 'allow')));
        $label = strtolower(trim((string)($result['highest_label'] ?? '')));
        $score = (float)($result['highest_score'] ?? 0);

        // The SVM backend's score is a decision-function ranking proxy, not a
        // calibrated probability. A confident safe prediction can legitimately
        // have a high score, so a known-safe label must never be moderated just
        // because its score exceeds a harmful-content threshold.
        if (in_array($label, ['not_harmful', 'not harmful', 'not suicide post', 'safe', 'none'], true))
        {
            return 'allow';
        }

        // For the three-class research model, use the class itself as the main
        // policy signal. Revision mode is deliberately limited to explicit
        // Method/action content; Ideation remains a human-moderation case.
        if (in_array($label, ['method_or_action', 'method or action'], true))
        {
            return ($mode === 'revise') ? 'revise' : 'moderate';
        }

        if ($label === 'ideation')
        {
            return 'moderate';
        }

        // Fallback support for alternate classifier backends.
        if (in_array($recommended, ['block', 'reject', 'revise', 'revision'], true))
        {
            return ($mode === 'revise') ? 'revise' : 'moderate';
        }

        if (in_array($recommended, ['moderate', 'flag', 'review'], true))
        {
            return 'moderate';
        }

        // Thresholds are secondary only for unknown/non-safe labels from an
        // alternate backend. They are not treated as calibrated SVM probability.
        if ($mode === 'revise' && $reviseThreshold > 0 && $score >= $reviseThreshold)
        {
            return 'revise';
        }

        if ($moderateThreshold > 0 && $score >= $moderateThreshold)
        {
            return 'moderate';
        }

        // Unknown non-safe labels should not silently pass through.
        if ($label !== '')
        {
            return 'moderate';
        }

        return 'allow';
    }
}
