# Classifier API backend

This directory contains the reference HTTP adapter used by the XenForo add-on.

## Model used

The adapter is designed for the **final Linear SVM inference pipeline** used by the research workflow:

```text
Suicide_SVM_pipeline.joblib
```

The complete publication-facing ML run preserved in the companion repository produced this artefact from the final actual/augmented training construction and evaluated it on the 957,154-sentence actual-only held-out test set. The current `training12_py314.py` retains the same SVM export logic and can regenerate the pipeline when traditional ML is enabled.

The artefact is an sklearn `Pipeline` containing the fitted `CountVectorizer`, `TfidfTransformer`, and `LinearSVC`, so raw sentences can be passed directly to `pipeline.predict(...)`.

The model file is intentionally **not committed** to this repository. Copy the trained artefact to a protected server location and set `MHFS_MODEL_PATH` to it. Therefore, the filename alone does not prove that a deployment is using the final binary.

The adapter also supports `Suicide_SVM_with_vectorizer_bundle.joblib`, although the complete pipeline is preferred.

### Verify the deployed model

After setting `MHFS_MODEL_PATH`, run:

```powershell
python verify_model.py
```

The verifier prints the model path, file size, SHA-256 digest, pipeline steps, and class labels. The final three-class artefact must expose exactly:

```text
Not Suicide post
Ideation of Suicide, Self-Harm or Harming Others
Method or action of Suicide, Self-Harm or Harming others
```

Record the SHA-256 digest with the deployment. This provides an auditable way to prove which model binary the API is actually loading.

## Important deployment behaviour

The research classifier was trained at **sentence level**. The API therefore:

1. receives the full cleaned XenForo message;
2. splits it into sentences while retaining offsets;
3. classifies each sentence with the saved SVM pipeline;
4. aggregates the result, prioritising Method/action over Ideation over Not harmful;
5. recommends human moderation when a harmful category is detected.

`LinearSVC` does not provide calibrated class probabilities. `highest_score` is therefore a softmax-normalised decision-function **proxy score**, not a probability. The default moderation recommendation is label-driven and does not treat this value as a clinical probability.

## Setup

Create a virtual environment and install the small API dependency set:

```powershell
py -3.14 -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install -r requirements.txt
```

For the most reproducible model loading, use the same Python/scikit-learn environment that created the saved SVM artefact.

Set the model path and an API key:

```powershell
$env:MHFS_MODEL_PATH="C:\path\to\Suicide_SVM_pipeline.joblib"
$env:MHFS_API_KEY="replace-with-a-long-random-secret"
$env:MHFS_HOST="127.0.0.1"
$env:MHFS_PORT="8000"
```

Verify the artefact before starting the API:

```powershell
python verify_model.py
```

For a local development test:

```powershell
python server.py
```

For a persistent Windows deployment, use a production WSGI server rather than Flask's development server, for example:

```powershell
waitress-serve --listen=127.0.0.1:8000 server:app
```

Place a reverse proxy with HTTPS in front of the service if XenForo connects over a network. Keep the API key out of source control.

## Endpoints

- `GET /health` — confirms the service and model loaded.
- `POST /api/classify` — classifier endpoint used by XenForo.

Example request shape:

```json
{
  "message": "Cleaned XenForo message",
  "context": {
    "content_type": "post",
    "thread_id": 1,
    "node_id": 2
  }
}
```

If `MHFS_API_KEY` is configured, send it as:

```text
Authorization: Bearer <key>
```

## Privacy

The API does not write forum messages to disk. Server/proxy request-body logging should also be disabled. The XenForo add-on defaults should be kept configured not to store raw response bodies or cleaned message text unless specifically required for controlled debugging.
