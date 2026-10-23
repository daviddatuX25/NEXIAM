import json
import threading
import unittest
import unittest.mock
from http.client import HTTPConnection
from http.server import ThreadingHTTPServer
from tabpfn_worker import (
    PayloadValidationError,
    TabPFNRequestHandler,
    evaluate_pairwise_tabpfn,
)


def applicant(**overrides):
    profile = {
        "gwa": 90,
        "shs_strand": "STEM",
        "gen_ability": 90,
        "verbal_apt": 70,
        "num_apt": 95,
        "spatial_apt": 90,
        "percept_apt": 75,
        "manual_dext": 70,
        "shs_math": 96,
        "shs_sci": 92,
        "shs_eng": 75,
        "riasec_r": 1,
        "riasec_i": 1,
        "riasec_a": 0,
        "riasec_s": 0,
        "riasec_e": 0,
        "riasec_c": 1,
    }
    profile.update(overrides)
    return profile


def exemplar(program_code, target, **overrides):
    profile = applicant()
    profile.update({
        "program_code": program_code,
        "program_name": overrides.pop("program_name", program_code),
        "target": target,
    })
    profile.update(overrides)
    return profile


def evaluate(query, examples):
    return evaluate_pairwise_tabpfn({
        "context_exemplars": examples,
        "query_applicant": query,
    })


class TabPFNPairwiseWorkerTest(unittest.TestCase):
    def test_stem_applicant_prefers_computing_over_nursing_and_english(self):
        query = applicant()
        examples = [
            exemplar("BSIT", 1),
            exemplar("BSIT", 0, gwa=68, gen_ability=58, num_apt=52, spatial_apt=55, shs_math=54),
            exemplar("BSCS", 1, program_name="Computer Science"),
            exemplar("BSCS", 0, gwa=65, gen_ability=56, num_apt=48, spatial_apt=52, shs_math=50),
            exemplar("BSN", 1, gwa=84, verbal_apt=88, num_apt=62, shs_strand="HUMSS", riasec_s=1),
            exemplar("BSN", 0, gwa=60, gen_ability=55, num_apt=50, shs_math=51),
            exemplar("BSEd-Eng", 1, gwa=86, verbal_apt=95, num_apt=55, shs_strand="HUMSS", riasec_s=1),
            exemplar("BSEd-Eng", 0, gwa=62, gen_ability=56, num_apt=49, shs_math=53),
        ]

        results = evaluate(query, examples)["recommendations"]
        ranks = {row["program_code"]: row["rank"] for row in results}
        self.assertLess(ranks["BSIT"], ranks["BSN"])
        self.assertLess(ranks["BSCS"], ranks["BSEd-Eng"])
        self.assertGreater(results[0]["compatibility_index"], 68)
        self.assertEqual([row["rank"] for row in results], [1, 2, 3, 4])

    def test_humss_verbal_applicant_prefers_english_and_psychology_over_civil(self):
        query = applicant(
            gwa=91,
            shs_strand="HUMSS",
            gen_ability=82,
            verbal_apt=97,
            num_apt=55,
            spatial_apt=58,
            percept_apt=92,
            manual_dext=65,
            shs_math=58,
            shs_sci=63,
            shs_eng=96,
            riasec_r=0,
            riasec_i=0,
            riasec_a=1,
            riasec_s=1,
            riasec_e=0,
            riasec_c=0,
        )
        examples = [
            exemplar("BSEd-Eng", 1, **query),
            exemplar("BSEd-Eng", 0, gwa=62, verbal_apt=55, shs_eng=52, shs_strand="STEM"),
            exemplar("BSPSY", 1, **query),
            exemplar("BSPSY", 0, gwa=64, verbal_apt=60, percept_apt=55, shs_strand="STEM"),
            exemplar("BSCE", 1, gwa=82, verbal_apt=58, num_apt=97, spatial_apt=96, shs_strand="STEM"),
            exemplar("BSCE", 0, gwa=61, verbal_apt=54, num_apt=58, spatial_apt=55),
        ]

        recommendations = evaluate(query, examples)["recommendations"]
        ranks = {row["program_code"]: row["rank"] for row in recommendations}
        self.assertLess(ranks["BSEd-Eng"], ranks["BSCE"])
        self.assertLess(ranks["BSPSY"], ranks["BSCE"])

    def test_missing_optional_modalities_are_marginalized(self):
        query = applicant(shs_math=None, shs_sci=float("nan"), shs_eng=None, riasec_r=0, riasec_i=0,
                          riasec_a=0, riasec_s=0, riasec_e=0, riasec_c=0)
        result = evaluate(query, [
            exemplar("BSIT", 1, shs_math=None, shs_sci=None, shs_eng=None),
            exemplar("BSIT", 0, gwa=65, num_apt=50, shs_math=None, shs_sci=None, shs_eng=None,
                     riasec_r=None, riasec_i=None, riasec_a=None, riasec_s=None, riasec_e=None, riasec_c=None),
        ])

        recommendation = result["recommendations"][0]
        self.assertIsInstance(recommendation["compatibility_index"], int)
        self.assertGreaterEqual(recommendation["compatibility_index"], 0)
        self.assertLessEqual(recommendation["compatibility_index"], 100)
        self.assertEqual(result["modality_coverage"]["missing_optional_features"],
                         ["shs_math", "shs_sci", "shs_eng"])

    def test_negative_anchor_with_nonzero_riasec_does_not_override_low_aptitude(self):
        low_query = applicant(
            gwa=58,
            gen_ability=50,
            verbal_apt=48,
            num_apt=45,
            spatial_apt=47,
            percept_apt=52,
            manual_dext=50,
            shs_math=42,
            shs_sci=45,
            shs_eng=54,
            riasec_r=1,
            riasec_i=0,
            riasec_a=1,
            riasec_s=1,
            riasec_e=0,
            riasec_c=0,
        )
        negative_anchor = exemplar(
            "BSIT", 0,
            gwa=60,
            gen_ability=53,
            verbal_apt=51,
            num_apt=47,
            spatial_apt=50,
            percept_apt=49,
            manual_dext=52,
            shs_math=44,
            shs_sci=46,
            shs_eng=53,
            riasec_r=1,
            riasec_i=0,
            riasec_a=1,
            riasec_s=1,
            riasec_e=0,
            riasec_c=0,
        )
        result = evaluate(low_query, [
            exemplar("BSIT", 1, gwa=92, gen_ability=93, num_apt=96, spatial_apt=91, shs_math=95),
            negative_anchor,
        ])

        recommendation = result["recommendations"][0]
        self.assertLess(recommendation["compatibility_index"], 68)
        self.assertFalse(recommendation["passed_cutoff"])
        self.assertEqual(recommendation["band"], "below_threshold")


    def test_nested_provider_exemplar_values_and_aptitude_area_codes_are_accepted(self):
        query = applicant()
        query["LOGIC"] = query.pop("gen_ability")
        query["PROFILE_VERBAL"] = query.pop("verbal_apt")
        query["PROFILE_QUANT"] = query.pop("num_apt")
        query["SPATIAL"] = query.pop("spatial_apt")
        query["MEMORY"] = query.pop("percept_apt")
        query["LANGUAGE"] = query.pop("manual_dext")
        nested_values = applicant()
        examples = [
            {
                "program_code": "BSIT",
                "tier_level": 1,
                "target_class": 1,
                "values": {
                    "gwa": nested_values["gwa"],
                    "shs_strand": nested_values["shs_strand"],
                    "LOGIC": nested_values["gen_ability"],
                    "PROFILE_VERBAL": nested_values["verbal_apt"],
                    "PROFILE_QUANT": nested_values["num_apt"],
                    "SPATIAL": nested_values["spatial_apt"],
                    "MEMORY": nested_values["percept_apt"],
                    "LANGUAGE": nested_values["manual_dext"],
                    **{key: nested_values[key] for key in nested_values if key.startswith(("shs_", "riasec_"))},
                },
            },
            {
                "program_code": "BSIT",
                "tier_level": 5,
                "target_class": 0,
                "values": {
                    "gwa": 55,
                    "shs_strand": "HUMSS",
                    "LOGIC": 50,
                    "PROFILE_VERBAL": 50,
                    "PROFILE_QUANT": 45,
                    "SPATIAL": 48,
                    "MEMORY": 52,
                    "LANGUAGE": 51,
                    "shs_math": 45,
                    "riasec_r": 1,
                },
            },
        ]

        result = evaluate(query, examples)
        self.assertEqual(result["recommendations"][0]["program_code"], "BSIT")
        self.assertEqual(result["inference_backend"], "bayesian_prior_kernel_v1")
    def test_rejects_invalid_target_and_missing_required_applicant_feature(self):
        with self.assertRaises(PayloadValidationError):
            evaluate(applicant(), [exemplar("BSIT", 2)])
        invalid_query = applicant()
        del invalid_query["num_apt"]
        with self.assertRaises(PayloadValidationError):
            evaluate(invalid_query, [exemplar("BSIT", 1)])


class TabPFNHttpContractTest(unittest.TestCase):
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
        connection.request(method, path, body=encoded, headers={"Content-Type": "application/json"})
        response = connection.getresponse()
        payload = json.loads(response.read())
        status = response.status
        connection.close()
        return status, payload

    def test_health_endpoint(self):
        status, payload = self.request("GET", "/health")
        self.assertEqual(status, 200)
        self.assertEqual(payload["status"], "ok")
        self.assertEqual(payload["engine"], "tabfm_pairwise")

    def test_inference_endpoint_returns_ranked_contract_and_validates_bad_request(self):
        payload = {
            "query_applicant": applicant(),
            "context_exemplars": [
                exemplar("BSIT", 1),
                exemplar("BSIT", 0, gwa=60, gen_ability=52, num_apt=46),
                exemplar("BSN", 1, gwa=82, verbal_apt=88, shs_strand="HUMSS"),
                exemplar("BSN", 0, gwa=60, verbal_apt=50),
            ],
        }
        status, result = self.request("POST", "/inference/tabpfn", payload)
        self.assertEqual(status, 200)
        self.assertEqual([row["rank"] for row in result["recommendations"]], [1, 2])
        self.assertIn(result["inference_backend"], ("tabpfn_pytorch", "bayesian_prior_kernel_v1"))
        self.assertEqual(result["engine_version"], "bayesian-prior-kernel-v1")

        status, error = self.request("POST", "/inference/tabpfn", {"context_exemplars": []})
        self.assertEqual(status, 400)
        self.assertEqual(error["error"], "invalid_request")

    def test_unknown_route_is_not_found(self):
        status, _payload = self.request("GET", "/unknown")
        self.assertEqual(status, 404)

    def test_tabpfn_pytorch_backend_executes_with_provenance_when_classifier_available(self):
        import numpy as np
        mock_classifier = unittest.mock.MagicMock()
        mock_classifier.predict_proba.return_value = np.array([
            [0.08, 0.92],
            [0.35, 0.65],
        ])

        with unittest.mock.patch("tabpfn_worker._get_classifier", return_value=mock_classifier):
            payload = {
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
            self.assertEqual(result["inference_backend"], "tabpfn_pytorch")
            self.assertEqual(result["engine_version"], "tabpfn-v2")
            self.assertIsNone(result["fallback_reason"])
            self.assertGreater(result["execution_time_ms"], 0)
            self.assertEqual(result["recommendations"][0]["program_code"], "BSIT")
            self.assertEqual(result["recommendations"][0]["compatibility_index"], 92)
            self.assertTrue(mock_classifier.fit.called)

    def test_native_missingness_preserved_without_column_mean_imputation(self):
        import numpy as np
        mock_classifier = unittest.mock.MagicMock()
        mock_classifier.predict_proba.return_value = np.array([[0.1, 0.9]])

        with unittest.mock.patch("tabpfn_worker._get_classifier", return_value=mock_classifier):
            null_applicant = applicant(shs_math=None, shs_sci=None, shs_eng=None)
            result = evaluate_pairwise_tabpfn({
                "query_applicant": null_applicant,
                "context_exemplars": [
                    exemplar("BSIT", 1, shs_math=None),
                    exemplar("BSIT", 0),
                ],
            })
            self.assertTrue(mock_classifier.fit.called)
            context_matrix = mock_classifier.fit.call_args[0][0]
            # Verify that NaN is preserved in the context matrix
            self.assertTrue(np.isnan(context_matrix).any())
            self.assertEqual(result["inference_backend"], "tabpfn_pytorch")

if __name__ == "__main__":
    unittest.main()
