import unittest
from types import SimpleNamespace

import cv2
import numpy as np

from tools import passport_vision


class PassportVisionTest(unittest.TestCase):
    def test_detects_and_rectifies_a_skewed_document(self):
        image = np.zeros((900, 1400, 3), dtype=np.uint8)
        polygon = np.array([[180, 160], [1180, 100], [1260, 760], [120, 800]], dtype=np.int32)
        cv2.fillConvexPoly(image, polygon, (205, 205, 205))
        cv2.polylines(image, [polygon], True, (255, 255, 255), 14)
        cv2.line(image, (250, 640), (1110, 600), (20, 20, 20), 8)
        cv2.line(image, (250, 690), (1110, 650), (20, 20, 20), 8)

        points, area_ratio, method = passport_vision.find_document(image, 0.20, 1200)

        self.assertIsNotNone(points)
        self.assertGreater(area_ratio, 0.20)
        self.assertIn(method, {"contour_quad", "min_area_rect"})

        corrected = passport_vision.warp_document(image, points)
        self.assertGreater(corrected.shape[1], corrected.shape[0])
        self.assertGreater(corrected.shape[0], 400)

    def test_quality_gate_rejects_a_flat_blurry_frame(self):
        image = np.full((700, 1100, 3), 120, dtype=np.uint8)
        args = SimpleNamespace(
            blur_warning=75.0,
            blur_reject=35.0,
            glare_warning=0.18,
            glare_reject=0.35,
        )

        quality = passport_vision.quality_metrics(image, args)

        self.assertEqual("rejected", quality["status"])
        self.assertIn("blur", quality["reasons"])
        self.assertLess(quality["blur_score"], 35.0)

    def test_quality_gate_rejects_extreme_overexposure(self):
        image = np.full((700, 1100, 3), 255, dtype=np.uint8)
        args = SimpleNamespace(
            blur_warning=0.0,
            blur_reject=-1.0,
            glare_warning=0.18,
            glare_reject=0.35,
        )

        quality = passport_vision.quality_metrics(image, args)

        self.assertEqual("rejected", quality["status"])
        self.assertIn("glare", quality["reasons"])
        self.assertGreater(quality["glare_ratio"], 0.35)


if __name__ == "__main__":
    unittest.main()
