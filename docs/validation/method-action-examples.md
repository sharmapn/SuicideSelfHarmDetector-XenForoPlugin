# Explicit method/action example: observed local behavior

Date: 2026-09-04. Environment: local XenForo 2.3.12, MHF Safeguard 0.2.0, and the current candidate Linear SVM pipeline. All text is synthetic and non-instructional.

## Classifier probes

The two probes sent only a `message` field through the configured authenticated classifier gateway. The category title was not part of either probe.

| Synthetic sentence | HTTP status | Observed label | Interpretation |
| --- | --- | --- | --- |
| I attempted suicide yesterday. | 200 | `ideation` | Category mismatch: the sentence describes an action, but the model returned ideation. |
| I made an attempt to harm myself yesterday; this was an act of self harm. | 200 | `method_or_action` | Used for the explicit action demonstration. |

These are observed demonstration probes, not a held-out benchmark or a new evaluation of sensitivity/specificity. The first result is retained here rather than omitted in favor of the successful example. No classifier code, model weights, labels, or thresholds were changed to obtain the demonstration.

## XenForo demonstration

Title: **Suicide or self harm method or action - synthetic test**

Body:

> I made an attempt to harm myself yesterday; this was an act of self harm.

In `moderate` mode, the real XenForo thread-creation service returned:

- classifier status `200`, label `method_or_action`, and final action `moderate`;
- one created thread, one first post, and one new scan row;
- `discussion_state=moderated` (the thread is not public);
- message/raw-response storage disabled, with flagged text omitted from the scan row.

The first post's `message_state` was `visible`, while the enclosing thread's `discussion_state` was `moderated`. XenForo therefore displayed **“Awaiting approval before being displayed publicly.”**

The same body was then submitted through the browser in `revise` mode. XenForo displayed the configured revision warning. The input and resulting UI are preserved in the [light-theme screenshot gallery](../screenshots/README.md).

## Consequence of the mismatch

For “I attempted suicide yesterday.”, an `ideation` result means both `moderate` and `revise` modes would route the message to human moderation, rather than request revision in `revise` mode. In `log` mode, the add-on does not change normal publication. This illustrates a classifier limitation even though human review remains part of the enforced workflow.

After capture, the local add-on was restored to `log` mode with fail-open enabled and message/raw-response storage off. These observations do not establish clinical validity or production readiness.
