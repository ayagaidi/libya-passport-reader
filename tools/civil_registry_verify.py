#!/usr/bin/env python3

import argparse
import hashlib
import json
import re
import sys
import uuid

try:
    import cv2
    import numpy as np
except Exception:
    print(json.dumps({"status": "unavailable", "reason": "opencv_import_failed"}))
    sys.exit(3)


CHECK_NUMBER_PATTERN = re.compile(
    r"check\s*number\s*[:=]\s*([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})",
    re.IGNORECASE,
)


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("input_path")
    parser.add_argument("document_type")
    parser.add_argument("--max-dimension", type=int, default=2200)
    return parser.parse_args()


def resize_preview(image, max_dimension):
    height, width = image.shape[:2]
    longest = max(height, width)
    if longest <= max_dimension:
        return image, 1.0

    scale = max_dimension / float(longest)
    resized = cv2.resize(
        image,
        (max(1, int(round(width * scale))), max(1, int(round(height * scale)))),
        interpolation=cv2.INTER_AREA,
    )
    return resized, scale


def normalize_qr_points(points, scale, original_width, original_height):
    if points is None:
        return None

    pts = np.asarray(points, dtype=np.float32).reshape(-1, 2)
    pts = pts / max(scale, 1e-6)
    center = pts.mean(axis=0)

    x = float(center[0] / original_width) if original_width else 0.0
    y = float(center[1] / original_height) if original_height else 0.0
    xs = pts[:, 0]
    ys = pts[:, 1]

    return {
        "center": {
            "x": round(max(0.0, min(1.0, x)), 4),
            "y": round(max(0.0, min(1.0, y)), 4),
        },
        "box": {
            "x": round(max(0.0, float(xs.min() / original_width)), 4),
            "y": round(max(0.0, float(ys.min() / original_height)), 4),
            "width": round(max(0.0, min(1.0, float((xs.max() - xs.min()) / original_width))), 4),
            "height": round(max(0.0, min(1.0, float((ys.max() - ys.min()) / original_height))), 4),
        },
    }


def qr_payload_metadata(payload):
    text = (payload or "").strip()
    if not text:
        return {
            "decoded": False,
            "payload_format": None,
            "structure_valid": None,
        }

    match = CHECK_NUMBER_PATTERN.search(text)
    if match:
        candidate = match.group(1).lower()
        try:
            parsed = str(uuid.UUID(candidate))
        except ValueError:
            parsed = None

        if parsed:
            return {
                "decoded": True,
                "payload_format": "civil_registry_check_number",
                "structure_valid": True,
                "check_number": parsed,
            }

    return {
        "decoded": True,
        "payload_format": "unrecognized",
        "structure_valid": False,
        "payload_sha256": hashlib.sha256(text.encode("utf-8", errors="ignore")).hexdigest(),
    }


def decode_qr_from_crop(detector, image, points):
    if points is None:
        return ""

    pts = np.asarray(points, dtype=np.float32).reshape(-1, 2)
    x0 = max(0, int(np.floor(pts[:, 0].min())))
    y0 = max(0, int(np.floor(pts[:, 1].min())))
    x1 = min(image.shape[1], int(np.ceil(pts[:, 0].max())))
    y1 = min(image.shape[0], int(np.ceil(pts[:, 1].max())))

    width = x1 - x0
    height = y1 - y0
    if width <= 8 or height <= 8:
        return ""

    padding = max(8, int(round(max(width, height) * 0.35)))
    x0 = max(0, x0 - padding)
    y0 = max(0, y0 - padding)
    x1 = min(image.shape[1], x1 + padding)
    y1 = min(image.shape[0], y1 + padding)
    crop = image[y0:y1, x0:x1]

    if crop.size == 0:
        return ""

    variants = []
    for scale in (2, 4):
        enlarged = cv2.resize(crop, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
        variants.append(enlarged)

        gray = cv2.cvtColor(enlarged, cv2.COLOR_BGR2GRAY)
        variants.append(gray)

        clahe = cv2.createCLAHE(clipLimit=2.5, tileGridSize=(8, 8)).apply(gray)
        variants.append(clahe)

        _, otsu = cv2.threshold(clahe, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        variants.append(otsu)

    for variant in variants:
        try:
            data, _, _ = detector.detectAndDecode(variant)
        except cv2.error:
            continue
        if data:
            return data

    return ""


def detect_qr(image, max_dimension):
    original_height, original_width = image.shape[:2]
    preview, preview_scale = resize_preview(image, max(800, max_dimension))
    detector = cv2.QRCodeDetector()

    bases = [(image, 1.0)]
    if preview_scale < 0.999:
        bases.append((preview, preview_scale))

    detected_points = None
    detected_scale = 1.0
    detected_base = image
    decoded_payload = ""

    for base, base_scale in bases:
        gray = cv2.cvtColor(base, cv2.COLOR_BGR2GRAY)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8)).apply(gray)

        for variant in (base, gray, clahe):
            try:
                data, points, _ = detector.detectAndDecode(variant)
            except cv2.error:
                continue

            if points is not None and detected_points is None:
                detected_points = points
                detected_scale = base_scale
                detected_base = base

            if data:
                decoded_payload = data
                if points is not None:
                    detected_points = points
                    detected_scale = base_scale
                    detected_base = base
                break

        if decoded_payload:
            break

    if detected_points is not None and not decoded_payload:
        decoded_payload = decode_qr_from_crop(detector, detected_base, detected_points)

    location = normalize_qr_points(
        detected_points,
        detected_scale,
        original_width,
        original_height,
    )

    payload = qr_payload_metadata(decoded_payload)
    detected = detected_points is not None or payload["decoded"]
    position_consistent = None

    if location is not None:
        center = location["center"]
        position_consistent = center["x"] >= 0.60 and center["y"] <= 0.30

    return {
        "detected": bool(detected),
        **payload,
        "expected_position": "top_right",
        "position_consistent": position_consistent,
        "location": location,
        "issuer_lookup_performed": False,
    }


def classify_stamp_location(center_x, center_y):
    if center_y >= 0.60 and 0.20 <= center_x <= 0.75:
        return "lower_center"
    if center_y <= 0.30 and center_x <= 0.42:
        return "upper_left"
    if center_y <= 0.30 and center_x >= 0.58:
        return "upper_right"
    if center_y >= 0.60:
        return "lower_other"
    return "other"


def detect_blue_seals(image, max_dimension, document_type):
    preview, _ = resize_preview(image, min(max(900, max_dimension), 1800))
    height, width = preview.shape[:2]
    image_area = float(height * width)

    hsv = cv2.cvtColor(preview, cv2.COLOR_BGR2HSV)
    mask = cv2.inRange(
        hsv,
        np.array([80, 45, 35], dtype=np.uint8),
        np.array([150, 255, 255], dtype=np.uint8),
    )

    kernel_size = max(3, int(round(min(height, width) * 0.008)))
    if kernel_size % 2 == 0:
        kernel_size += 1

    close_kernel = cv2.getStructuringElement(
        cv2.MORPH_ELLIPSE,
        (kernel_size, kernel_size),
    )
    merged = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, close_kernel, iterations=2)

    dilate_size = max(3, kernel_size // 2)
    if dilate_size % 2 == 0:
        dilate_size += 1

    merged = cv2.dilate(
        merged,
        cv2.getStructuringElement(
            cv2.MORPH_ELLIPSE,
            (dilate_size, dilate_size),
        ),
        iterations=1,
    )

    count, _, stats, _ = cv2.connectedComponentsWithStats((merged > 0).astype(np.uint8), 8)
    candidates = []

    for index in range(1, count):
        x, y, box_width, box_height, _ = stats[index]
        if box_width <= 0 or box_height <= 0:
            continue

        box_ratio = float((box_width * box_height) / image_area) if image_area else 0.0
        if box_ratio < 0.001 or box_ratio > 0.08:
            continue

        aspect = box_width / float(box_height)
        if aspect < 0.35 or aspect > 4.0:
            continue

        raw_blue = float(np.count_nonzero(mask[y:y + box_height, x:x + box_width]))
        blue_fill = raw_blue / float(box_width * box_height)
        if blue_fill < 0.03:
            continue

        center_x = (x + box_width / 2.0) / width
        center_y = (y + box_height / 2.0) / height
        location = classify_stamp_location(center_x, center_y)

        confidence = min(
            0.99,
            0.45
            + min(0.30, box_ratio * 10.0)
            + min(0.24, blue_fill * 0.70),
        )

        candidates.append({
            "location": location,
            "confidence": round(float(confidence), 3),
            "box": {
                "x": round(float(x / width), 4),
                "y": round(float(y / height), 4),
                "width": round(float(box_width / width), 4),
                "height": round(float(box_height / height), 4),
            },
        })

    candidates.sort(key=lambda candidate: candidate["confidence"], reverse=True)
    candidates = candidates[:4]

    if document_type == "family_status_certificate":
        location_match = any(
            candidate["location"] in {"lower_center", "upper_left"}
            for candidate in candidates
        )
        expected_locations = ["lower_center", "upper_left"]
    else:
        location_match = any(
            candidate["location"] == "lower_center"
            for candidate in candidates
        )
        expected_locations = ["lower_center"]

    blue_ratio = float(np.count_nonzero(mask) / mask.size) if mask.size else 0.0

    return {
        "detected": len(candidates) > 0,
        "candidate_count": len(candidates),
        "expected_locations": expected_locations,
        "expected_location_match": bool(location_match) if candidates else None,
        "blue_ink_ratio": round(blue_ratio, 5),
        "candidates": candidates,
        "method": "blue_ink_connected_components",
    }


def visual_score(qr, seals):
    score = 0.0

    if qr["detected"]:
        score += 0.30
        if qr["position_consistent"] is True:
            score += 0.10
        if qr["decoded"] and qr["structure_valid"] is True:
            score += 0.15

    if seals["detected"]:
        score += 0.30
        if seals["expected_location_match"] is True:
            score += 0.15

    return round(min(1.0, score), 3)


def main():
    args = parse_args()
    image = cv2.imread(args.input_path, cv2.IMREAD_COLOR)

    if image is None:
        print(json.dumps({"status": "unavailable", "reason": "image_decode_failed"}))
        return 4

    qr = detect_qr(image, max(800, args.max_dimension))
    seals = detect_blue_seals(
        image,
        max(800, args.max_dimension),
        args.document_type,
    )

    print(json.dumps({
        "status": "processed",
        "qr": qr,
        "seals": seals,
        "visual_signal_score": visual_score(qr, seals),
        "authenticity_verified": False,
    }))
    return 0


if __name__ == "__main__":
    sys.exit(main())
