#!/usr/bin/env python3

import argparse
import sys

try:
    import cv2
except Exception:
    sys.exit(3)


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("input_path")
    parser.add_argument("output_path")
    parser.add_argument("--start-ratio", type=float, required=True)
    parser.add_argument("--target-width", type=int, default=3200)
    parser.add_argument("--block-size", type=int, default=41)
    parser.add_argument("--constant", type=float, default=15.0)
    return parser.parse_args()


def normalized_block_size(requested, height, width):
    smallest = min(height, width)
    if smallest < 3:
        return None

    block = max(3, int(requested))
    if block % 2 == 0:
        block += 1

    maximum = smallest if smallest % 2 == 1 else smallest - 1
    block = min(block, maximum)

    if block < 3:
        return None

    return block


def preprocess_mrz_band(image, start_ratio, target_width, block_size, constant):
    height, width = image.shape[:2]
    ratio = max(0.40, min(float(start_ratio), 0.90))
    start_y = min(height - 1, max(0, int(round(height * ratio))))
    band = image[start_y:height, :]

    if band.size == 0 or min(band.shape[:2]) < 20:
        return None

    gray = cv2.cvtColor(band, cv2.COLOR_BGR2GRAY)
    requested_width = max(1600, min(int(target_width), 4200))
    scale = requested_width / float(gray.shape[1])

    if abs(scale - 1.0) > 0.05:
        interpolation = cv2.INTER_CUBIC if scale > 1.0 else cv2.INTER_AREA
        gray = cv2.resize(
            gray,
            (requested_width, max(1, int(round(gray.shape[0] * scale)))),
            interpolation=interpolation,
        )

    block = normalized_block_size(block_size, gray.shape[0], gray.shape[1])
    if block is None:
        return None

    return cv2.adaptiveThreshold(
        gray,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        block,
        float(constant),
    )


def main():
    args = parse_args()
    image = cv2.imread(args.input_path, cv2.IMREAD_COLOR)
    if image is None:
        return 4

    output = preprocess_mrz_band(
        image,
        args.start_ratio,
        args.target_width,
        args.block_size,
        args.constant,
    )
    if output is None:
        return 5

    if not cv2.imwrite(args.output_path, output):
        return 6

    return 0


if __name__ == "__main__":
    sys.exit(main())
