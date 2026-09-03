from __future__ import annotations

import importlib
import os
import sys
import tempfile
import unittest
from pathlib import Path

import joblib
from sklearn.feature_extraction.text import CountVectorizer, TfidfTransformer
from sklearn.pipeline import Pipeline
from sklearn.svm import LinearSVC


NOT_LABEL = "Not Suicide post"
IDEATION_LABEL = "Ideation of Suicide, Self-Harm or Harming Others"
METHOD_LABEL = "Method or action of Suicide, Self-Harm or Harming others"


class ApiTestCase(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp_dir = tempfile.TemporaryDirectory()
        model_path = Path(cls.temp_dir.name) / "Suicide_SVM_pipeline.joblib"

        # Harmless synthetic vocabulary: this tests the adapter contract, not
        # classifier quality and does not contain forum-derived text.
        texts = [
            "alpha ordinary neutral",
            "alpha routine neutral",
            "alpha general ordinary",
            "beta ideation concept",
            "beta reflection concept",
            "beta thought concept",
            "gamma method concept",
            "gamma action concept",
            "gamma procedure concept",
        ]
        labels = [
            NOT_LABEL, NOT_LABEL, NOT_LABEL,
            IDEATION_LABEL, IDEATION_LABEL, IDEATION_LABEL,
            METHOD_LABEL, METHOD_LABEL, METHOD_LABEL,
        ]

        pipeline = Pipeline([
            ("count_vectorizer", CountVectorizer(ngram_range=(1, 2))),
            ("tfidf_transformer", TfidfTransformer()),
            ("svm", LinearSVC()),
        ])
        pipeline.fit(texts, labels)
        joblib.dump(pipeline, model_path)

        os.environ["MHFS_MODEL_PATH"] = str(model_path)
        os.environ["MHFS_API_KEY"] = "unit-test-secret"
        os.environ["MHFS_MAX_MESSAGE_CHARS"] = "10000"

        if "server" in sys.modules:
            del sys.modules["server"]

        sys.path.insert(0, str(Path(__file__).resolve().parent))
        cls.server = importlib.import_module("server")
        cls.client = cls.server.app.test_client()

    @classmethod
    def tearDownClass(cls):
        cls.temp_dir.cleanup()

    def auth_headers(self):
        return {"Authorization": "Bearer unit-test-secret"}

    def test_health(self):
        response = self.client.get("/health")
        self.assertEqual(response.status_code, 200)
        data = response.get_json()
        self.assertTrue(data["ok"])
        self.assertEqual(data["model"], "Linear SVM")

    def test_authentication_required(self):
        response = self.client.post("/api/classify", json={"message": "alpha ordinary."})
        self.assertEqual(response.status_code, 401)

    def test_invalid_json(self):
        response = self.client.post(
            "/api/classify",
            data="not-json",
            content_type="application/json",
            headers=self.auth_headers(),
        )
        self.assertEqual(response.status_code, 400)

    def test_safe_sentence(self):
        response = self.client.post(
            "/api/classify",
            json={"message": "alpha ordinary neutral."},
            headers=self.auth_headers(),
        )
        self.assertEqual(response.status_code, 200)
        data = response.get_json()
        self.assertEqual(data["highest_label"], "not_harmful")
        self.assertEqual(data["recommended_action"], "allow")
        self.assertEqual(data["flagged_parts"], [])

    def test_method_outranks_safe_in_multi_sentence_message(self):
        response = self.client.post(
            "/api/classify",
            json={"message": "alpha ordinary neutral. gamma method concept."},
            headers=self.auth_headers(),
        )
        self.assertEqual(response.status_code, 200)
        data = response.get_json()
        self.assertEqual(data["highest_label"], "method_or_action")
        self.assertEqual(data["recommended_action"], "moderate")
        self.assertGreaterEqual(len(data["sentence_results"]), 2)
        self.assertEqual(len(data["flagged_parts"]), 1)
        self.assertIn("start_offset", data["flagged_parts"][0])
        self.assertIn("end_offset", data["flagged_parts"][0])

    def test_ideation_is_moderated(self):
        response = self.client.post(
            "/api/classify",
            json={"message": "beta ideation concept."},
            headers=self.auth_headers(),
        )
        self.assertEqual(response.status_code, 200)
        data = response.get_json()
        self.assertEqual(data["highest_label"], "ideation")
        self.assertEqual(data["recommended_action"], "moderate")

    def test_length_limit(self):
        previous = self.server.MAX_MESSAGE_CHARS
        try:
            self.server.MAX_MESSAGE_CHARS = 5
            response = self.client.post(
                "/api/classify",
                json={"message": "alpha ordinary"},
                headers=self.auth_headers(),
            )
            self.assertEqual(response.status_code, 400)
        finally:
            self.server.MAX_MESSAGE_CHARS = previous


if __name__ == "__main__":
    unittest.main()
