import importlib.util
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "scripts" / "smaug_detect.py"
SPEC = importlib.util.spec_from_file_location("smaug_detect", SCRIPT)
assert SPEC is not None and SPEC.loader is not None
smaug = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(smaug)


class SmaugScoreTests(unittest.TestCase):
    def test_ai_score_maps_to_ai_label(self):
        value = smaug.normalize_score(0.91)
        self.assertEqual(value["status"], "completed")
        self.assertEqual(value["label"], "likely_ai_generated")
        self.assertAlmostEqual(value["confidence"], 0.91)
        self.assertEqual(value["signals"][0]["outcome"], "ai_generated")

    def test_low_ai_score_maps_to_likely_real(self):
        value = smaug.normalize_score(0.12)
        self.assertEqual(value["label"], "likely_real")
        self.assertAlmostEqual(value["confidence"], 0.88)

    def test_threshold_is_deterministic(self):
        self.assertEqual(smaug.normalize_score(0.5)["label"], "likely_ai_generated")

    def test_rejects_non_finite_and_out_of_range_scores(self):
        for score in (float("nan"), float("inf"), -0.1, 1.1, "not-a-score"):
            with self.subTest(score=score):
                self.assertEqual(smaug.normalize_score(score)["status"], "inconclusive")

    def test_rejects_multiple_scores(self):
        self.assertEqual(smaug.normalize_score([0.2, 0.8])["status"], "inconclusive")


if __name__ == "__main__":
    unittest.main()
