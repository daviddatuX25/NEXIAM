import hashlib
import json
import os
import tempfile
import threading
import unittest
import unittest.mock
from contextlib import contextmanager
from http.client import HTTPConnection
from http.server import ThreadingHTTPServer
import worker
from worker import (
    InferenceUnavailableError,
    PayloadValidationError,
    TabPFNRequestHandler,
    _INFERENCE_LOCK,
    evaluate_pairwise_tabpfn,
)


def feature_definitions(custom=False):
    aptitude_keys = ("LOGIC", "PROFILE_VERBAL", "PROFILE_QUANT", "SPATIAL", "MEMORY", "LANGUAGE")
    definitions = [
        {
            "feature_key": "gwa",
            "label": "General Weighted Average",
            "logical_type": "numeric",
            "is_required": True,
            "source_definition": None,
        }
    ]
    for key in aptitude_keys:
        definitions.append({
            "feature_key": key,
            "label": key.replace("_", " ").title(),
            "logical_type": "numeric",
            "is_required": True,
            "source_definition": {
                "source_id": key,
                "source_code": key,
                "source_version": "2026-v1",
                "scale": "percentile",
                "norm_version": "2026-v1",
            },
        })
    if custom:
        definitions.append({
            "feature_key": "intake_biology",
            "label": "Biology",
            "logical_type": "numeric",
            "is_required": False,
            "source_definition": {
                "source_id": "field-42",
                "source_code": "BIOLOGY",
                "source_version": "catalog-v3",
                "unit": "percent",
                "min_value": 0,
                "max_value": 100,
            },
        })
    return definitions


def applicant(**overrides):
    profile = {
        "gwa": 90,
        "LOGIC": 90,
        "PROFILE_VERBAL": 70,
        "PROFILE_QUANT": 95,
        "SPATIAL": 90,
        "MEMORY": 75,
        "LANGUAGE": 70,
    }
    profile.update({key: value for key, value in overrides.items() if key in profile})
    return profile


def exemplar(program_code, target, **overrides):
    profile = applicant()
    profile.update({
        "program_code": program_code,
        "program_name": overrides.pop("program_name", program_code),
        "target": target,
    })
    profile.update({key: value for key, value in overrides.items() if key in profile})
    return profile


def make_mock_classifier(scores=None):
    import numpy as np

    classifier = unittest.mock.MagicMock()
    classifier.classes_ = [0, 1]

    def predict_proba(query_matrix):
        probabilities = np.asarray(scores if scores is not None else [0.75] * len(query_matrix))
        probabilities = probabilities[:len(query_matrix)]
        return np.column_stack((1.0 - probabilities, probabilities))

    classifier.predict_proba.side_effect = predict_proba
    return classifier

TEST_PROVENANCE = {
    "package": "tabpfn",
    "package_version": "9.1.0",
    "checkpoint_identity": "Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt",
    "checkpoint_sha256": "1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88",
}


@contextmanager
def mock_ready_classifier(classifier, *, already_warmed=False):
    classifier.classes_ = [0, 1]
    with unittest.mock.patch("worker._get_classifier", return_value=classifier), \
            unittest.mock.patch("worker._MODEL_PROVENANCE", TEST_PROVENANCE.copy()), \
            unittest.mock.patch(
                "worker._WARMED_CLASSIFIER",
                classifier if already_warmed else None,
            ):
        yield


def evaluate(query, examples, definitions=None, scores=None):
    classifier = make_mock_classifier(scores)
    with mock_ready_classifier(classifier):
        return evaluate_pairwise_tabpfn({
            "feature_definitions": definitions or feature_definitions(),
            "context_exemplars": examples,
            "query_applicant": query,
        })



class TabPFNPairwiseWorkerTest(unittest.TestCase):
    def test_neural_scores_rank_programs_from_canonical_profiles(self):
        query = applicant()
        examples = [
            exemplar("BSIT", 1),
            exemplar("BSIT", 0, gwa=68, LOGIC=58, PROFILE_QUANT=52, SPATIAL=55),
            exemplar("BSCS", 1, program_name="Computer Science"),
            exemplar("BSCS", 0, gwa=65, LOGIC=56, PROFILE_QUANT=48, SPATIAL=52),
            exemplar("BSN", 1, gwa=84, PROFILE_VERBAL=88, PROFILE_QUANT=62),
            exemplar("BSN", 0, gwa=60, LOGIC=55, PROFILE_QUANT=50),
            exemplar("BSEd-Eng", 1, gwa=86, PROFILE_VERBAL=95, PROFILE_QUANT=55),
            exemplar("BSEd-Eng", 0, gwa=62, LOGIC=56, PROFILE_QUANT=49),
        ]

        results = evaluate(query, examples, scores=[0.75, 0.92, 0.65, 0.40])["recommendations"]
        ranks = {row["program_code"]: row["rank"] for row in results}
        self.assertLess(ranks["BSIT"], ranks["BSN"])
        self.assertLess(ranks["BSCS"], ranks["BSEd-Eng"])
        self.assertGreater(results[0]["compatibility_index"], 68)
        self.assertEqual([row["rank"] for row in results], [1, 2, 3, 4])

    def test_neural_scores_rank_programs_from_canonical_aptitude_profile(self):
        query = applicant(
            gwa=91,
            LOGIC=82,
            PROFILE_VERBAL=97,
            PROFILE_QUANT=55,
            SPATIAL=58,
            MEMORY=92,
            LANGUAGE=65,
        )
        examples = [
            exemplar("BSEd-Eng", 1, **query),
            exemplar("BSEd-Eng", 0, gwa=62, PROFILE_VERBAL=55, LANGUAGE=52),
            exemplar("BSPSY", 1, **query),
            exemplar("BSPSY", 0, gwa=64, PROFILE_VERBAL=60, MEMORY=55),
            exemplar("BSCE", 1, gwa=82, PROFILE_VERBAL=58, PROFILE_QUANT=97, SPATIAL=96),
            exemplar("BSCE", 0, gwa=61, PROFILE_VERBAL=54, PROFILE_QUANT=58, SPATIAL=55),
        ]

        recommendations = evaluate(query, examples, scores=[0.94, 0.87, 0.35])["recommendations"]
        ranks = {row["program_code"]: row["rank"] for row in recommendations}
        self.assertLess(ranks["BSEd-Eng"], ranks["BSCE"])
        self.assertLess(ranks["BSPSY"], ranks["BSCE"])

    def test_missing_required_aptitude_is_rejected_without_optional_subject_modalities(self):
        query = applicant()
        del query["LOGIC"]
        with self.assertRaisesRegex(PayloadValidationError, "LOGIC is required"):
            evaluate(query, [exemplar("BSIT", 1), exemplar("BSIT", 0)])

    def test_match_band_does_not_set_departmental_cutoff(self):
        result = evaluate(applicant(), [exemplar("BSIT", 1), exemplar("BSIT", 0)], scores=[0.67])
        recommendation = result["recommendations"][0]
        self.assertEqual(recommendation["compatibility_index"], 67)
        self.assertEqual(recommendation["band"], "below_threshold")
        self.assertNotIn("passed_cutoff", recommendation)

    def test_declared_aptitude_features_use_the_exact_canonical_keys(self):
        result = evaluate(applicant(), [exemplar("BSIT", 1), exemplar("BSIT", 0)])
        self.assertEqual(result["inference_backend"], "tabpfn_pytorch")
        self.assertEqual(
            set(result["modality_coverage"]["feature_coverage"]),
            {"gwa", "LOGIC", "PROFILE_VERBAL", "PROFILE_QUANT", "SPATIAL", "MEMORY", "LANGUAGE"},
        )

    def test_legacy_subject_strand_and_riasec_features_are_rejected(self):
        for key, logical_type in [
            ("shs_math", "numeric"),
            ("shs_strand", "categorical"),
            ("riasec_i", "binary"),
        ]:
            with self.subTest(feature=key):
                definitions = feature_definitions()
                definitions.append({
                    "feature_key": key,
                    "label": key,
                    "logical_type": logical_type,
                    "is_required": False,
                    "source_definition": None,
                })
                with self.assertRaisesRegex(PayloadValidationError, "not an approved"):
                    worker._validate_feature_definitions(definitions)


    def test_missing_checkpoint_configuration_marks_classifier_unavailable(self):
        with unittest.mock.patch.dict("os.environ", {
            "TABPFN_CHECKPOINT_PATH": "",
            "TABPFN_CHECKPOINT_ID": "",
            "TABPFN_CHECKPOINT_SHA256": "",
        }), unittest.mock.patch("worker._CLASSIFIER_INSTANCE", None), \
                unittest.mock.patch("worker._CLASSIFIER_ERROR", None), \
                unittest.mock.patch("worker._MODEL_PROVENANCE", None):
            self.assertIsNone(worker._get_classifier())
            self.assertIn("TABPFN_CHECKPOINT_PATH is required", worker._CLASSIFIER_ERROR)

    def test_directory_checkpoint_is_rejected(self):
        with tempfile.TemporaryDirectory() as model_path:
            with unittest.mock.patch.dict("os.environ", {
                "TABPFN_CHECKPOINT_PATH": model_path,
                "TABPFN_CHECKPOINT_ID": "external/model",
                "TABPFN_CHECKPOINT_SHA256": "0" * 64,
            }), unittest.mock.patch("worker._CLASSIFIER_INSTANCE", None), \
                    unittest.mock.patch("worker._CLASSIFIER_ERROR", None), \
                    unittest.mock.patch("worker._construct_classifier") as construct:
                self.assertIsNone(worker._get_classifier())
                self.assertIn("regular file", worker._CLASSIFIER_ERROR)
                construct.assert_not_called()

    def test_checkpoint_hash_mismatch_is_rejected_before_model_load(self):
        with tempfile.NamedTemporaryFile() as checkpoint:
            checkpoint.write(b"external-checkpoint")
            checkpoint.flush()
            with unittest.mock.patch.dict("os.environ", {
                "TABPFN_CHECKPOINT_PATH": checkpoint.name,
                "TABPFN_CHECKPOINT_ID": "external/model",
                "TABPFN_CHECKPOINT_SHA256": hashlib.sha256(b"different-checkpoint").hexdigest(),
            }), unittest.mock.patch("worker._CLASSIFIER_INSTANCE", None), \
                    unittest.mock.patch("worker._CLASSIFIER_ERROR", None), \
                    unittest.mock.patch("worker._construct_classifier") as construct:
                self.assertIsNone(worker._get_classifier())
                self.assertIn("SHA-256 does not match", worker._CLASSIFIER_ERROR)
                construct.assert_not_called()

    def test_verified_checkpoint_records_actual_package_and_configured_identity(self):
        with tempfile.NamedTemporaryFile() as checkpoint:
            checkpoint.write(b"external-test-checkpoint")
            checkpoint.flush()
            expected_sha256 = hashlib.sha256(b"external-test-checkpoint").hexdigest()
            classifier = unittest.mock.MagicMock()
            with unittest.mock.patch.dict("os.environ", {
                "TABPFN_CHECKPOINT_PATH": checkpoint.name,
                "TABPFN_CHECKPOINT_ID": "publisher/model-release",
                "TABPFN_CHECKPOINT_SHA256": expected_sha256,
            }), unittest.mock.patch("worker._CLASSIFIER_INSTANCE", None), \
                    unittest.mock.patch("worker._CLASSIFIER_ERROR", None), \
                    unittest.mock.patch("worker.importlib.metadata.version", return_value="verified-test-version"), \
                    unittest.mock.patch("worker._construct_classifier", return_value=classifier) as construct:
                self.assertIs(worker._get_classifier(), classifier)
                construct.assert_called_once_with(checkpoint.name)
                self.assertEqual(worker._MODEL_PROVENANCE, {
                    "package": "tabpfn",
                    "package_version": "verified-test-version",
                    "checkpoint_identity": "publisher/model-release",
                    "checkpoint_sha256": expected_sha256,
                })

    def test_single_target_class_is_unavailable(self):
        with unittest.mock.patch("worker._get_classifier", return_value=make_mock_classifier()):
            with self.assertRaises(InferenceUnavailableError):
                evaluate_pairwise_tabpfn({
                    "feature_definitions": feature_definitions(),
                    "query_applicant": applicant(),
                    "context_exemplars": [exemplar("BSIT", 1)],
                })

    def test_inference_lock_covers_fit_and_prediction(self):
        import numpy as np

        classifier = unittest.mock.MagicMock()
        first_prediction_entered = threading.Event()
        release_first_prediction = threading.Event()
        second_fit_entered = threading.Event()
        second_request_started = threading.Event()
        state_lock = threading.Lock()
        fit_calls = 0
        prediction_calls = 0
        failures = []

        def fit(*_args):
            nonlocal fit_calls
            with state_lock:
                fit_calls += 1
                if fit_calls == 2:
                    second_fit_entered.set()

        def predict_proba(_query_matrix):
            nonlocal prediction_calls
            with state_lock:
                prediction_calls += 1
                current_call = prediction_calls
            if current_call == 1:
                first_prediction_entered.set()
                release_first_prediction.wait(timeout=2)
            return np.array([[0.1, 0.9]])

        classifier.fit.side_effect = fit
        classifier.predict_proba.side_effect = predict_proba
        payload = {
            "feature_definitions": feature_definitions(),
            "query_applicant": applicant(),
            "context_exemplars": [exemplar("BSIT", 1), exemplar("BSIT", 0)],
        }

        def run_inference():
            try:
                evaluate_pairwise_tabpfn(payload)
            except Exception as error:
                failures.append(error)

        first_request = threading.Thread(target=run_inference)

        def run_second_request():
            second_request_started.set()
            run_inference()

        second_request = threading.Thread(target=run_second_request)
        with mock_ready_classifier(classifier, already_warmed=True):
            first_request.start()
            try:
                self.assertTrue(first_prediction_entered.wait(timeout=1))
                second_request.start()
                self.assertTrue(second_request_started.wait(timeout=1))
                self.assertTrue(_INFERENCE_LOCK.locked())
                self.assertFalse(second_fit_entered.wait(timeout=0.05))
            finally:
                release_first_prediction.set()
                first_request.join(timeout=2)
                if second_request.ident is not None:
                    second_request.join(timeout=2)

        self.assertFalse(first_request.is_alive())
        self.assertFalse(second_request.is_alive())
        self.assertTrue(second_fit_entered.is_set())
        self.assertEqual(failures, [])


    def test_subject_strand_riasec_and_custom_schema_columns_are_rejected(self):
        legacy_definitions = [
            {"feature_key": "shs_math", "label": "Math", "logical_type": "numeric", "is_required": False, "source_definition": None},
            {"feature_key": "shs_strand", "label": "Strand", "logical_type": "categorical", "is_required": False, "source_definition": None},
            {"feature_key": "riasec_i", "label": "Interest", "logical_type": "binary", "is_required": False, "source_definition": None},
            {"feature_key": "intake_biology", "label": "Biology", "logical_type": "numeric", "is_required": False, "source_definition": None},
        ]
        for definition in legacy_definitions:
            with self.subTest(feature=definition["feature_key"]):
                with self.assertRaisesRegex(PayloadValidationError, "not an approved"):
                    worker._validate_feature_definitions([*feature_definitions(), definition])

    def test_rejects_invalid_target_and_missing_required_exemplar_feature(self):
        with self.assertRaises(PayloadValidationError):
            evaluate(applicant(), [exemplar("BSIT", 2)])
        invalid_exemplar = exemplar("BSIT", 1)
        del invalid_exemplar["LOGIC"]
        with self.assertRaisesRegex(PayloadValidationError, "required"):
            evaluate(applicant(), [invalid_exemplar])


class TabPFNHttpContractTest(unittest.TestCase):
    def setUp(self):
        approvals = unittest.mock.patch.dict(os.environ, {
            "TABPFN_INSTITUTIONAL_USE_APPROVED": "true",
            "TABPFN_HOSTED_USE_APPROVED": "true",
            "TABPFN_CHECKPOINT_ID": TEST_PROVENANCE["checkpoint_identity"],
            "TABPFN_CHECKPOINT_SHA256": TEST_PROVENANCE["checkpoint_sha256"],
        })
        approvals.start()
        self.addCleanup(approvals.stop)
        package_version = unittest.mock.patch(
            "worker.importlib.metadata.version",
            return_value="9.1.0",
        )
        package_version.start()
        self.addCleanup(package_version.stop)

    def test_http_denies_unapproved_requests_without_running_inference(self):
        payload = {
            "feature_definitions": feature_definitions(),
            "query_applicant": applicant(),
            "context_exemplars": [exemplar("BSIT", 1), exemplar("BSIT", 0)],
        }
        for name, key, value in [
            ("institutional_false", "TABPFN_INSTITUTIONAL_USE_APPROVED", "false"),
            ("hosted_false", "TABPFN_HOSTED_USE_APPROVED", "false"),
            ("institutional_invalid", "TABPFN_INSTITUTIONAL_USE_APPROVED", "yes"),
            ("hosted_invalid", "TABPFN_HOSTED_USE_APPROVED", "off"),
            ("institutional_blank", "TABPFN_INSTITUTIONAL_USE_APPROVED", ""),
        ]:
            with self.subTest(name=name), unittest.mock.patch.dict(os.environ, {key: value}):
                with unittest.mock.patch("worker._ready_classifier") as ready, \
                        unittest.mock.patch("worker.evaluate_pairwise_tabpfn") as evaluate_request:
                    health_status, health = self.request("GET", "/health")
                    infer_status, inference = self.request(
                        "POST",
                        "/inference/tabpfn",
                        payload,
                    )

                self.assertEqual(health_status, 503)
                self.assertFalse(health["ready"])
                self.assertNotIn("provenance", health)
                self.assertEqual(infer_status, 503)
                self.assertNotIn("recommendations", inference)
                ready.assert_not_called()
                evaluate_request.assert_not_called()

    def test_http_denies_wrong_package_and_artifact_pins_before_loading(self):
        for name, env, package_version in [
            ("wrong_package", {}, "9.1.1"),
            ("wrong_identity", {"TABPFN_CHECKPOINT_ID": "other/model.ckpt"}, "9.1.0"),
            ("wrong_checksum", {"TABPFN_CHECKPOINT_SHA256": "0" * 64}, "9.1.0"),
        ]:
            with self.subTest(name=name), \
                    unittest.mock.patch.dict(os.environ, env), \
                    unittest.mock.patch(
                        "worker.importlib.metadata.version",
                        return_value=package_version,
                    ), \
                    unittest.mock.patch("worker._ready_classifier") as ready:
                status, result = self.request("GET", "/health")
                self.assertEqual(status, 503)
                self.assertFalse(result["ready"])
                self.assertNotIn("provenance", result)
                ready.assert_not_called()

    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), TabPFNRequestHandler)
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()
        cls.port = cls.server.server_address[1]

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()
        cls.thread.join(timeout=2)

    def request(self, method, path, body=None):
        connection = HTTPConnection("127.0.0.1", self.port, timeout=3)
        encoded = None if body is None else json.dumps(body).encode()
        try:
            connection.request(method, path, body=encoded, headers={"Content-Type": "application/json"})
            response = connection.getresponse()
            payload = json.loads(response.read())
            return response.status, payload
        finally:
            connection.close()

    def test_health_endpoint_reports_classifier_readiness(self):
        import numpy as np

        classifier = unittest.mock.MagicMock()
        classifier.predict_proba.return_value = np.array([[0.4, 0.6]])
        with mock_ready_classifier(classifier):
            ready_status, ready_payload = self.request("GET", "/health")
        self.assertEqual(ready_status, 200)
        self.assertEqual(ready_payload, {
            "status": "ready",
            "ready": True,
            "backend": "tabpfn_pytorch",
            "provenance": TEST_PROVENANCE,
        })

        classifier = unittest.mock.MagicMock()
        classifier.fit.side_effect = RuntimeError("warm-up failed")
        with mock_ready_classifier(classifier):
            unavailable_status, unavailable_payload = self.request("GET", "/health")
        self.assertEqual(unavailable_status, 503)
        self.assertEqual(unavailable_payload, {
            "status": "unavailable",
            "ready": False,
            "backend": "tabpfn_pytorch",
            "message": "TabFM neural foundation model is unavailable. Counselors proceed with standard evaluation.",
        })

    def test_health_rejects_invalid_binary_warmup_probabilities(self):
        import numpy as np

        invalid_matrices = [
            np.array([[-0.2, 1.2]]),
            np.array([[0.2, 0.2]]),
            np.array([[np.nan, 0.3]]),
            np.array([0.5, 0.5]),
        ]
        for invalid_matrix in invalid_matrices:
            with self.subTest(invalid_matrix=invalid_matrix):
                classifier = unittest.mock.MagicMock()
                classifier.predict_proba.return_value = invalid_matrix
                with mock_ready_classifier(classifier):
                    status, result = self.request("GET", "/health")
                self.assertEqual(status, 503)
                self.assertFalse(result["ready"])

    def test_health_runs_real_fit_and_prediction_once_under_inference_lock(self):
        import numpy as np

        classifier = unittest.mock.MagicMock()
        classifier.predict_proba.return_value = np.array([[0.4, 0.6]])
        lock_states = []
        classifier.fit.side_effect = lambda *_args: lock_states.append(_INFERENCE_LOCK.locked())
        classifier.predict_proba.side_effect = lambda *_args: (
            lock_states.append(_INFERENCE_LOCK.locked()) or np.array([[0.4, 0.6]])
        )

        with mock_ready_classifier(classifier):
            first_status, _first_payload = self.request("GET", "/health")
            second_status, _second_payload = self.request("GET", "/health")

        self.assertEqual((first_status, second_status), (200, 200))
        classifier.fit.assert_called_once()
        classifier.predict_proba.assert_called_once()
        self.assertEqual(lock_states, [True, True])

    def test_inference_endpoint_returns_503_without_classifier_and_validates_bad_request(self):
        payload = {
            "feature_definitions": feature_definitions(),
            "query_applicant": applicant(),
            "context_exemplars": [
                exemplar("BSIT", 1),
                exemplar("BSIT", 0, gwa=60, LOGIC=52, PROFILE_QUANT=46),
                exemplar("BSN", 1, gwa=82, PROFILE_VERBAL=88),
                exemplar("BSN", 0, gwa=60, PROFILE_VERBAL=50),
            ],
        }
        with unittest.mock.patch("worker._get_classifier", return_value=None):
            status, result = self.request("POST", "/inference/tabpfn", payload)
            error_status, error = self.request("POST", "/inference/tabpfn", {"context_exemplars": []})
        self.assertEqual(status, 503)
        self.assertEqual(result, {
            "status": "unavailable",
            "message": "TabFM neural foundation model is unavailable. Counselors proceed with standard evaluation.",
        })

        self.assertEqual(error_status, 400)
        self.assertEqual(error["error"], "invalid_request")

    def test_http_requires_aptitude_and_gwa_and_rejects_subject_grade_values(self):
        required_field_cases = [
            ("missing_gwa", lambda query: query.pop("gwa")),
            ("null_gwa", lambda query: query.update(gwa=None)),
            ("missing_aptitude", lambda query: query.pop("LOGIC")),
            ("null_aptitude", lambda query: query.update(LOGIC=None)),
        ]
        examples = [exemplar("BSIT", 1), exemplar("BSIT", 0)]
        for name, invalidate in required_field_cases:
            with self.subTest(name=name):
                query = applicant()
                invalidate(query)
                status, result = self.request("POST", "/inference/tabpfn", {
                    "feature_definitions": feature_definitions(),
                    "context_exemplars": examples,
                    "query_applicant": query,
                })
                self.assertEqual(status, 400)
                self.assertEqual(result["error"], "invalid_request")

        query = applicant()
        query["shs_math"] = 92
        status, result = self.request("POST", "/inference/tabpfn", {
            "feature_definitions": feature_definitions(),
            "context_exemplars": examples,
            "query_applicant": query,
        })
        self.assertEqual(status, 400)
        self.assertEqual(result["error"], "invalid_request")

        import numpy as np

        classifier = unittest.mock.MagicMock()
        classifier.predict_proba.side_effect = [
            np.array([[0.5, 0.5]]),
            np.array([[0.3, 0.7]]),
        ]
        with mock_ready_classifier(classifier):
            status, result = self.request("POST", "/inference/tabpfn", {
                "feature_definitions": feature_definitions(),
                "context_exemplars": examples,
                "query_applicant": applicant(),
            })
        self.assertEqual(status, 200)
        self.assertEqual(result["status"], "evaluated")

    def test_invalid_probability_matrices_return_unavailable_without_scores(self):
        import numpy as np

        invalid_matrices = [
            ("out_of_range", np.array([[-0.2, 1.2]])),
            ("non_finite", np.array([[np.nan, np.nan]])),
            ("wrong_shape", np.array([0.5, 0.5])),
            ("unnormalized", np.array([[0.2, 0.2]])),
        ]
        payload = {
            "feature_definitions": feature_definitions(),
            "query_applicant": applicant(),
            "context_exemplars": [exemplar("BSIT", 1), exemplar("BSIT", 0)],
        }
        for name, invalid_matrix in invalid_matrices:
            with self.subTest(name=name):
                classifier = unittest.mock.MagicMock()
                classifier.predict_proba.side_effect = [
                    np.array([[0.5, 0.5]]),
                    invalid_matrix,
                ]
                with mock_ready_classifier(classifier):
                    status, result = self.request("POST", "/inference/tabpfn", payload)
                self.assertEqual(status, 503)
                self.assertNotIn("recommendations", result)

    def test_fit_and_prediction_failures_return_clean_unavailable_responses(self):
        payload = {
            "feature_definitions": feature_definitions(),
            "query_applicant": applicant(),
            "context_exemplars": [
                exemplar("BSIT", 1),
                exemplar("BSIT", 0),
            ],
        }
        for method_name in ("fit", "predict_proba"):
            with self.subTest(method_name=method_name):
                classifier = unittest.mock.MagicMock()
                getattr(classifier, method_name).side_effect = RuntimeError("model failure")
                with mock_ready_classifier(classifier):
                    status, result = self.request("POST", "/inference/tabpfn", payload)
                self.assertEqual(status, 503)
                self.assertEqual(result, {
                    "status": "unavailable",
                    "message": "TabFM neural foundation model is unavailable. Counselors proceed with standard evaluation.",
                })
                self.assertNotIn("recommendations", result)

    def test_unknown_route_is_not_found(self):
        status, _payload = self.request("GET", "/unknown")
        self.assertEqual(status, 404)

    def test_tabpfn_pytorch_backend_executes_with_provenance_when_classifier_available(self):
        import numpy as np

        mock_classifier = unittest.mock.MagicMock()
        mock_classifier.predict_proba.side_effect = [
            np.array([[0.5, 0.5]]),
            np.array([
                [0.92, 0.08],
                [0.65, 0.35],
            ]),
        ]

        with mock_ready_classifier(mock_classifier):
            mock_classifier.classes_ = [1, 0]
            payload = {
                "feature_definitions": feature_definitions(),
                "query_applicant": applicant(),
                "context_exemplars": [
                    exemplar("BSIT", 1),
                    exemplar("BSIT", 0),
                    exemplar("BSN", 1),
                    exemplar("BSN", 0),
                ],
            }
            status, result = self.request("POST", "/inference/tabpfn", payload)

        self.assertEqual(status, 200)
        self.assertEqual(result["status"], "evaluated")
        self.assertEqual(result["inference_backend"], "tabpfn_pytorch")
        self.assertEqual(result["engine_version"], "9.1.0")
        self.assertEqual(result["provenance"], TEST_PROVENANCE)
        self.assertIsNone(result["fallback_reason"])
        self.assertGreater(result["execution_time_ms"], 0)
        self.assertEqual(result["recommendations"][0]["program_code"], "BSIT")
        self.assertEqual(result["recommendations"][0]["compatibility_index"], 92)
        self.assertNotIn("passed_cutoff", result["recommendations"][0])
        self.assertTrue(result["recommendations"][0]["drivers"])
        self.assertTrue(mock_classifier.fit.called)

    def test_missing_required_applicant_aptitude_rejects_without_imputation_or_model_dispatch(self):
        classifier = unittest.mock.MagicMock()
        with mock_ready_classifier(classifier):
            with self.assertRaises(worker.PayloadValidationError):
                evaluate_pairwise_tabpfn({
                    "feature_definitions": feature_definitions(),
                    "query_applicant": applicant(LOGIC=None),
                    "context_exemplars": [
                        exemplar("BSIT", 1),
                        exemplar("BSIT", 0),
                    ],
                })
        classifier.fit.assert_not_called()
        classifier.predict_proba.assert_not_called()
if __name__ == "__main__":
    unittest.main()
