from __future__ import annotations

import hashlib
import os
import sys
from pathlib import Path

import joblib


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def main() -> int:
    default_path = Path(__file__).resolve().parent / "models" / "Suicide_SVM_pipeline.joblib"
    model_path = Path(os.environ.get("MHFS_MODEL_PATH", str(default_path))).expanduser().resolve()

    if not model_path.exists():
        print(f"ERROR: model file does not exist: {model_path}", file=sys.stderr)
        print("Set MHFS_MODEL_PATH to the deployed Suicide_SVM_pipeline.joblib.", file=sys.stderr)
        return 2

    loaded = joblib.load(model_path)

    print(f"Model path : {model_path}")
    print(f"File size  : {model_path.stat().st_size} bytes")
    print(f"SHA-256    : {sha256_file(model_path)}")
    print(f"Object type: {type(loaded).__module__}.{type(loaded).__name__}")

    if hasattr(loaded, "named_steps"):
        steps = list(loaded.named_steps.keys())
        print("Pipeline   : " + " -> ".join(steps))
        classes = getattr(loaded, "classes_", None)
        if classes is None and steps:
            classes = getattr(loaded.named_steps[steps[-1]], "classes_", None)
    elif isinstance(loaded, dict):
        print("Bundle keys: " + ", ".join(sorted(str(key) for key in loaded.keys())))
        classes = loaded.get("classes")
        if classes is None and "model" in loaded:
            classes = getattr(loaded["model"], "classes_", None)
    else:
        classes = getattr(loaded, "classes_", None)

    if classes is not None:
        print("Classes    :")
        for value in list(classes):
            print(f"  - {value}")
    else:
        print("Classes    : unavailable")

    expected = {
        "Not Suicide post",
        "Ideation of Suicide, Self-Harm or Harming Others",
        "Method or action of Suicide, Self-Harm or Harming others",
    }
    actual = {str(value) for value in list(classes)} if classes is not None else set()

    if actual != expected:
        print("WARNING: class labels do not exactly match the final three-class SVM.", file=sys.stderr)
        return 1

    print("OK: artefact exposes the expected final three-class labels.")
    print("Record the SHA-256 above with the deployment so the exact binary can be audited.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
