#!/usr/bin/env python3

import argparse
import json
import math
import sys

try:
    import cv2
    import numpy as np
except Exception:
    print(json.dumps({"status": "unavailable", "reason": "opencv_import_failed"}))
    sys.exit(3)


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("input_path")
    parser.add_argument("output_path")
    parser.add_argument("--min-area-ratio", type=float, default=0.20)
    parser.add_argument("--blur-warning", type=float, default=75.0)
    parser.add_argument("--blur-reject", type=float, default=35.0)
    parser.add_argument("--glare-warning", type=float, default=0.18)
    parser.add_argument("--glare-reject", type=float, default=0.35)
    parser.add_argument("--max-dimension", type=int, default=1400)
    return parser.parse_args()


def order_points(points):
    points = np.asarray(points, dtype=np.float32).reshape(4, 2)
    ordered = np.zeros((4, 2), dtype=np.float32)
    sums = points.sum(axis=1)
    diffs = np.diff(points, axis=1).reshape(-1)
    ordered[0] = points[np.argmin(sums)]
    ordered[2] = points[np.argmax(sums)]
    ordered[1] = points[np.argmin(diffs)]
    ordered[3] = points[np.argmax(diffs)]
    return ordered


def resize_for_detection(image, max_dimension):
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


def find_document(image, min_area_ratio, max_dimension):
    preview, scale = resize_for_detection(image, max_dimension)
    height, width = preview.shape[:2]
    image_area = float(height * width)
    gray = cv2.cvtColor(preview, cv2.COLOR_BGR2GRAY)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8)).apply(gray)
    blurred = cv2.GaussianBlur(clahe, (5, 5), 0)
    edges = cv2.Canny(blurred, 45, 140)
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (7, 7))
    edges = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, kernel, iterations=2)
    edges = cv2.dilate(edges, kernel, iterations=1)

    found = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    contours = found[-2]
    contours = sorted(contours, key=cv2.contourArea, reverse=True)[:20]

    for contour in contours:
        area = cv2.contourArea(contour)
        area_ratio = area / image_area if image_area else 0.0
        if area_ratio < min_area_ratio:
            continue
        perimeter = cv2.arcLength(contour, True)
        for epsilon_ratio in (0.015, 0.02, 0.025, 0.03):
            approx = cv2.approxPolyDP(contour, epsilon_ratio * perimeter, True)
            if len(approx) == 4 and cv2.isContourConvex(approx):
                points = approx.reshape(4, 2).astype(np.float32) / scale
                return points, float(area_ratio), "contour_quad"

    for contour in contours[:8]:
        area = cv2.contourArea(contour)
        area_ratio = area / image_area if image_area else 0.0
        if area_ratio < min_area_ratio:
            continue
        rect = cv2.minAreaRect(contour)
        box = cv2.boxPoints(rect)
        rect_area = float(rect[1][0] * rect[1][1])
        rectangularity = area / rect_area if rect_area > 0 else 0.0
        if rectangularity >= 0.78:
            points = box.astype(np.float32) / scale
            return points, float(area_ratio), "min_area_rect"

    return None, 0.0, None


def warp_document(image, points):
    rect = order_points(points)
    top_left, top_right, bottom_right, bottom_left = rect
    width_a = np.linalg.norm(bottom_right - bottom_left)
    width_b = np.linalg.norm(top_right - top_left)
    height_a = np.linalg.norm(top_right - bottom_right)
    height_b = np.linalg.norm(top_left - bottom_left)
    max_width = max(1, int(round(max(width_a, width_b))))
    max_height = max(1, int(round(max(height_a, height_b))))

    destination = np.array(
        [
            [0, 0],
            [max_width - 1, 0],
            [max_width - 1, max_height - 1],
            [0, max_height - 1],
        ],
        dtype=np.float32,
    )
    matrix = cv2.getPerspectiveTransform(rect, destination)
    warped = cv2.warpPerspective(image, matrix, (max_width, max_height), flags=cv2.INTER_CUBIC)

    if warped.shape[0] > warped.shape[1]:
        warped = cv2.rotate(warped, cv2.ROTATE_90_CLOCKWISE)

    return warped


def quality_metrics(image, args):
    preview, _ = resize_for_detection(image, 1200)
    gray = cv2.cvtColor(preview, cv2.COLOR_BGR2GRAY)
    blur_score = float(cv2.Laplacian(gray, cv2.CV_64F).var())
    hsv = cv2.cvtColor(preview, cv2.COLOR_BGR2HSV)
    saturation = hsv[:, :, 1]
    value = hsv[:, :, 2]
    glare_mask = (value >= 250) & (saturation <= 25)
    glare_ratio = float(np.count_nonzero(glare_mask) / glare_mask.size)

    rejected = blur_score < args.blur_reject or glare_ratio > args.glare_reject
    warning = blur_score < args.blur_warning or glare_ratio > args.glare_warning
    status = "rejected" if rejected else ("warning" if warning else "accepted")
    reasons = []
    if blur_score < args.blur_warning:
        reasons.append("blur")
    if glare_ratio > args.glare_warning:
        reasons.append("glare")

    return {
        "status": status,
        "reasons": reasons,
        "blur_score": round(blur_score, 4),
        "glare_ratio": round(glare_ratio, 6),
    }


def main():
    args = parse_args()
    image = cv2.imread(args.input_path, cv2.IMREAD_COLOR)
    if image is None:
        print(json.dumps({"status": "unavailable", "reason": "image_decode_failed"}))
        return 4

    points, area_ratio, detection_method = find_document(
        image,
        max(0.05, min(args.min_area_ratio, 0.95)),
        max(640, args.max_dimension),
    )

    if points is None:
        quality = quality_metrics(image, args)
        print(json.dumps({
            "status": "not_detected",
            "document_detected": False,
            "perspective_corrected": False,
            "detection_method": None,
            "document_area_ratio": 0.0,
            "quality": quality,
        }))
        return 0

    corrected = warp_document(image, points)
    if corrected.size == 0 or min(corrected.shape[:2]) < 120:
        print(json.dumps({"status": "unavailable", "reason": "perspective_transform_failed"}))
        return 5

    quality = quality_metrics(corrected, args)
    if not cv2.imwrite(args.output_path, corrected):
        print(json.dumps({"status": "unavailable", "reason": "corrected_image_write_failed"}))
        return 6

    print(json.dumps({
        "status": "corrected",
        "document_detected": True,
        "perspective_corrected": True,
        "detection_method": detection_method,
        "document_area_ratio": round(float(area_ratio), 6),
        "quality": quality,
    }))
    return 0


if __name__ == "__main__":
    sys.exit(main())
