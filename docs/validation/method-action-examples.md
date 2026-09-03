# Explicit method/action example: observed local behavior

Date: 2026-09-04. Environment: local XenForo 2.3.12, MHF Safeguard 0.2.0, and the current candidate Linear SVM pipeline. All text is synthetic and non-instructional.

## Classifier probes

The three probes sent only a `message` field through the configured authenticated classifier gateway. The category title was not part of any probe.

| Synthetic sentence | HTTP status | Observed label | Interpretation |
| --- | --- | --- | --- |
| I attempted suicide yesterday. | 200 | `ideation` | Category mismatch: the sentence describes an action, but the model returned ideation. |
| I made an attempt to harm myself yesterday; this was an act of self harm. | 200 | `method_or_action` | Used for the explicit action demonstration. |
| I want to slit my wrists tonight. | 200 | `method_or_action` | Exact user-supplied synthetic method/action example; names a method and time frame. |

These are observed demonstration probes, not a held-out benchmark or a new evaluation of sensitivity/specificity. The first result is retained here rather than omitted in favor of the successful example. No classifier code, model weights, labels, or thresholds were changed to obtain the demonstration.

## Earlier XenForo action demonstration

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

## User-supplied explicit method demonstration

Title: **Suicide or self harm method or action - synthetic test**

Exact body:

> I want to slit my wrists tonight.

The message-only gateway probe returned `ok=true`, HTTP `200`, `highest_label=method_or_action`, and `recommended_action=moderate`. This label was not inferred from the category title.

In `moderate` mode, the real XenForo thread-creation service returned:

- one new thread (local ID 16), one first post (local ID 19), and one scan row (local ID 20);
- `highest_label=method_or_action`, `risk_level=high`, `final_action=moderate`, and API status `200`;
- `discussion_state=moderated`, with the first post's `message_state=visible`; the enclosing thread remained non-public;
- a 64-character message hash, with message text and raw response null and flagged text omitted from the scan row.

The actual browser page displayed **“Awaiting approval before being displayed publicly.”** The screenshot is [10-explicit-method-moderated.png](../screenshots/light/10-explicit-method-moderated.png).

The same exact body and title were then entered through the browser and submitted in `revise` mode. XenForo displayed its native revision warning. Counts taken immediately before and after that browser submission were unchanged:

| Persisted rows | Before revision submission | After revision submission |
| --- | --- | --- |
| Threads | 16 | 16 |
| Posts | 19 | 19 |
| Scan rows | 20 | 20 |

The rejected submission therefore created no additional thread, post, or scan row. This also means the revision screenshot is UI evidence, not a persisted audit record of that rejected attempt. See the [exact input](../screenshots/light/09-explicit-method-input.png) and [revision warning](../screenshots/light/11-explicit-method-revision.png). The moderation and revision demonstrations used different local test accounts (synthetic audit member and local administrator, respectively), as visible in the captures.

The latest three images were recaptured in XenForo's Light appearance with a 1920 × 1080 desktop viewport after the user requested full-size browser screenshots. The input and moderated-thread PNGs are 1905 × 1072; the revision overlay PNG is 1920 × 1080. These are webpage captures without the browser address bar or window frame. The existing moderated thread was reused; no duplicate moderation fixture was created. Repeating the revision submission again left counts unchanged at 16 threads, 19 posts, and 20 scans, and `log` mode was restored afterward.

No screenshot recoloring, upscaling, fabricated interface, model retraining, or threshold adjustment was used. Both examples remain documented; replaced narrow screenshot versions remain in Git history.

## Full-width configuration and gallery refresh

The remaining nine images (configuration, privacy/failure controls, installed add-on, forum overview, approval queue, normal publication, and the earlier action example's three screens) were subsequently retaken with the same 1920 × 1080 desktop viewport. All 12 gallery images now use the desktop layout. The configuration token was masked in an unsaved form before capture; the stored token was not replaced. Existing thread fixtures were reused, and no approve/delete/spam decisions were applied.

The earlier action example was resubmitted only in `revise` mode to reproduce its warning at desktop width. Counts remained at 16 threads, 19 posts, and 20 scans before and after that submission. Log-only mode was restored, with fail-open enabled, an eight-second timeout, message/raw-response storage off, and no excluded forums. The refreshed forum screenshots show 12 queued items; older screenshots with 10 or 11 are retained in Git history.

## Consequence of the mismatch

For “I attempted suicide yesterday.”, an `ideation` result means both `moderate` and `revise` modes would route the message to human moderation, rather than request revision in `revise` mode. In `log` mode, the add-on does not change normal publication. This illustrates a classifier limitation even though human review remains part of the enforced workflow.

After capture, the local add-on was restored to `log` mode with fail-open enabled and message/raw-response storage off. These observations do not establish clinical validity or production readiness.
