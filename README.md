# SuicideSelfHarmDetector-XenForoPlugin

A XenForo add-on for integrating a self-managed suicide and self-harm text classifier into an online mental-health community moderation workflow.

This repository contains the **XenForo plugin only**. The machine-learning, deep-learning and transformer research scripts, model evaluation and aggregate experimental results are maintained separately in the [MHFSafeguard research repository](https://github.com/sharmapn/MHFSafeguard).

## Purpose

The add-on intercepts new threads, replies and edited posts, normalises the submitted text, sends the message to a configured classifier API, interprets the returned risk assessment, and applies the configured moderation policy.

The classifier is intended to distinguish three research categories:

1. **Not Ideation or method or action**
2. **Suicide or Self Harm Ideation**
3. **Method or action of Suicide, Self-Harm or Harming others**

The plugin is designed as a **human-supervised moderation support tool**, not an autonomous replacement for moderators.

## Workflow

```text
User submits thread/reply/edit
        |
        v
XenForo plugin intercepts message
        |
        v
Text normalisation
        |
        v
Classifier API request
        |
        v
Risk label + score + recommended action
        |
        v
Allow / log / moderate / request revision
        |
        v
Store scan record for moderator review
```

## Repository structure

```text
upload/
└── src/
    └── addons/
        └── Pankaj/
            └── MHFSafeguard/
                ├── addon.json
                ├── Setup.php
                ├── Content/
                ├── Gateway/
                ├── Pipeline/
                ├── Repository/
                ├── XF/
                │   └── Service/
                │       ├── Post/
                │       └── Thread/
                └── _data/
```

The `upload/` directory mirrors the structure expected by a XenForo installation.

## API contract

The plugin sends a cleaned message and relevant XenForo context to a self-managed classifier endpoint. A typical request contains:

```json
{
  "platform": "xenforo",
  "source": "mhf_safeguard_plugin",
  "context": {
    "content_type": "post",
    "content_id": 12345,
    "thread_id": 456,
    "node_id": 12,
    "user_id": 99,
    "username": "example_user",
    "title": "Thread title",
    "is_first_post": false
  },
  "message": "Cleaned message text",
  "message_hash": "sha256_hash",
  "return_spans": true,
  "return_sentences": true
}
```

The classifier API should return fields such as:

```json
{
  "risk_level": "high",
  "recommended_action": "moderate",
  "highest_label": "method_or_action",
  "highest_score": 94,
  "flagged_parts": []
}
```

## Plugin actions

| Action | Behaviour |
|---|---|
| `allow` | Publish normally |
| `log` | Record classifier result without interrupting publication |
| `moderate` | Place content into the XenForo moderation workflow |
| `revise` | Ask the user to revise the message before resubmitting |

## Configuration

The add-on includes XenForo options for:

- enabling/disabling scanning;
- classifier API URL;
- optional bearer-token API key;
- moderation and revision thresholds;
- API timeout;
- fail-open behaviour;
- storage of message text or raw responses;
- forums excluded from classification.

For initial testing, use logging or moderation mode with conservative settings and retain human review.

## Installation

Copy the contents of `upload/` into the XenForo installation root so that the add-on is placed at:

```text
src/addons/Pankaj/MHFSafeguard/
```

Then install/enable **MHF Safeguard** through the XenForo Admin Control Panel and configure the classifier API endpoint.

## Research companion

The associated research repository contains the model-development pipeline and evaluation artefacts:

**https://github.com/sharmapn/MHFSafeguard**

The research evaluates traditional machine learning, neural deep learning and transformer models for sentence-level suicide/self-harm classification.

## Privacy and safety

Mental-health forum content can be highly sensitive. Deployments should minimise stored raw text, protect API traffic, use authentication, restrict access to scan logs, and retain human oversight for moderation decisions.

## Status

Research prototype / development version. Validate on a test XenForo installation before production deployment.
