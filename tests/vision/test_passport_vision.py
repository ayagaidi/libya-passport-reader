import unittest
from types import SimpleNamespace

import cv2
import numpy as np

from tools import mrz_band_preprocess, passport_vision


def quality_args(**overrides):
    values = {
        "blur_warning": 75.0,
        "blur_reject": 35.0,
        "glare_warning": 0.18,
        "glare_reject": 0.35,
        "overexposure_warning": 0.80,
        "overexposure_reject": 0.95,
    }
    values.update(overrides)
    return SimpleNamespace(**values)


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
        quality = passport_vision.quality_metrics(image, quality_args())

        self.assertEqual("rejected", quality["status"])
        self.assertIn("blur", quality["reasons"])
        self.assertLess(quality["blur_score"], 35.0)

    def test_quality_gate_rejects_extreme_overexposure(self):
        image = np.full((700, 1100, 3), 255, dtype=np.uint8)
        quality = passport_vision.quality_metrics(
            image,
            quality_args(blur_warning=0.0, blur_reject=-1.0),
        )

        self.assertEqual("rejected", quality["status"])
        self.assertIn("overexposure", quality["reasons"])
        self.assertGreater(quality["overexposure_ratio"], 0.95)

    def test_local_glare_is_warning_not_hard_rejection(self):
        image = np.full((700, 1100, 3), 205, dtype=np.uint8)
        cv2.rectangle(image, (250, 150), (850, 550), (255, 255, 255), -1)
        cv2.line(image, (120, 610), (980, 610), (20, 20, 20), 8)

        quality = passport_vision.quality_metrics(
            image,
            quality_args(blur_warning=0.0, blur_reject=-1.0),
        )

        self.assertEqual("warning", quality["status"])
        self.assertIn("glare", quality["reasons"])
        self.assertNotIn("overexposure", quality["reasons"])

    def test_scanned_page_white_margins_are_cropped_before_quality(self):
        image = np.full((1200, 900, 3), 255, dtype=np.uint8)
        cv2.rectangle(image, (170, 220), (730, 980), (232, 232, 232), -1)
        cv2.rectangle(image, (200, 260), (700, 560), (255, 255, 255), -1)
        cv2.rectangle(image, (200, 590), (700, 930), (238, 242, 245), -1)
        cv2.line(image, (240, 830), (660, 830), (20, 20, 20), 9)
        cv2.line(image, (240, 875), (660, 875), (20, 20, 20), 9)
        cv2.circle(image, (300, 690), 70, (90, 90, 90), -1)

        cropped, crop_ratio = passport_vision.crop_content_region(image)

        self.assertIsNotNone(cropped)
        self.assertLess(crop_ratio, 0.70)

        quality = passport_vision.quality_metrics(cropped, quality_args())
        self.assertNotEqual("rejected", quality["status"])
        self.assertLess(quality["overexposure_ratio"], 0.95)

    def test_adaptive_mrz_preprocessing_recovers_low_contrast_text_band(self):
        image = np.full((700, 1400, 3), 238, dtype=np.uint8)
        cv2.putText(
            image,
            "P<LBYTEST<<AYA<ABDULHAKIM<MASOUD<<<<",
            (80, 565),
            cv2.FONT_HERSHEY_SIMPLEX,
            1.25,
            (175, 175, 175),
            2,
            cv2.LINE_AA,
        )
        cv2.putText(
            image,
            "AA076550<7LBY9503283F2706188<<<<<<<<0",
            (80, 625),
            cv2.FONT_HERSHEY_SIMPLEX,
            1.25,
            (175, 175, 175),
            2,
            cv2.LINE_AA,
        )

        processed = mrz_band_preprocess.preprocess_mrz_band(
            image,
            start_ratio=0.60,
            target_width=2800,
            block_size=41,
            constant=15,
        )

        self.assertIsNotNone(processed)
        self.assertEqual(2800, processed.shape[1])
        self.assertEqual(np.uint8, processed.dtype)
        self.assertLess(int(processed.min()), 255)
        self.assertEqual(255, int(processed.max()))


if __name__ == "__main__":
    unittest.main()
