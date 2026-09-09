import unittest

import cv2
import numpy as np

from tools import civil_registry_verify


class CivilRegistryVerifyTest(unittest.TestCase):
    def test_parses_structured_check_number_without_returning_raw_payload(self):
        result = civil_registry_verify.qr_payload_metadata(
            "{CheckNumber:123e4567-e89b-12d3-a456-426614174000 }"
        )

        self.assertTrue(result["decoded"])
        self.assertTrue(result["structure_valid"])
        self.assertEqual("civil_registry_check_number", result["payload_format"])
        self.assertEqual("123e4567-e89b-12d3-a456-426614174000", result["check_number"])
        self.assertNotIn("raw_payload", result)

    def test_detects_blue_seal_in_expected_lower_center_region(self):
        image = np.full((1200, 900, 3), 255, dtype=np.uint8)
        cv2.ellipse(image, (450, 900), (150, 70), 0, 0, 360, (255, 0, 0), 18)
        cv2.putText(
            image,
            "SEAL",
            (360, 915),
            cv2.FONT_HERSHEY_SIMPLEX,
            1.2,
            (255, 0, 0),
            5,
            cv2.LINE_AA,
        )

        result = civil_registry_verify.detect_blue_seals(
            image,
            1800,
            "residence_certificate",
        )

        self.assertTrue(result["detected"])
        self.assertTrue(result["expected_location_match"])
        self.assertGreaterEqual(result["candidate_count"], 1)
        self.assertEqual("lower_center", result["candidates"][0]["location"])


if __name__ == "__main__":
    unittest.main()
