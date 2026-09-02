# Classifier API backend

This directory contains the reference HTTP adapter used by the XenForo add-on.

## Model used

The current adapter is designed for the **final Linear SVM pipeline** produced by `training12_py314.py` in the companion research repository:

```text
Suicide_SVM_pipeline.joblib
```

That artefact is an sklearn `Pipeline` containing the fitted `CountVectorizer`, `TfidfTransformer`, and `LinearSVC`, so raw sentences can be passed directly to `pipeline.predict(...)`.

The model file is intentionally **not committed** to this repository. Copy the trained artefact to a protected server location and set `MHFS_MODEL_PATH` to it.

The adapter also supports `Suicide_SVM_with_vectorizer_bundle.joblib`, although the complete pipeline is preferred.

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
