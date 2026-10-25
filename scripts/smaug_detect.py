#!/usr/bin/env python3
"""Local-only Smaug AI-image detector CLI.

The only positional argument is PHP's validated temporary upload path. The
script emits one bounded JSON object on stdout; diagnostic details go to
stderr and never include the uploaded image or credentials.
"""
from __future__ import annotations

import json
import math
import os
import sys
from pathlib import Path
from typing import Any

MODEL_ID = os.environ.get("SMAUG_MODEL_ID", "abacusai/Smaug-AI-Image-Detector")
MAX_IMAGE_BYTES = 5 * 1024 * 1024
MAX_PIXELS = 20_000_000


def result(status: str, label: str | None = None, confidence: float | None = None,
           error_code: str | None = None) -> dict[str, Any]:
    signals: list[dict[str, str]] = []
    if status == "completed" and label is not None and confidence is not None:
        outcome = "ai_generated" if label == "likely_ai_generated" else "likely_human_created"
        signals.append({
            "type": "smaug_local_classifier",
            "outcome": outcome,
            "explanation": (
                f"Local Smaug model score for this label: {confidence:.4f}. "
                "This experimental score is not proof of authorship."
            ),
        })
    return {
        "provider": "smaug_local",
        "status": status,
        "label": label,
        "confidence": confidence,
        "signals": signals,
        "error_code": error_code,
    }


def normalize_score(raw_score: Any) -> dict[str, Any]:
    """Convert Smaug's AI-generation score into the app's stable result shape."""
    if isinstance(raw_score, (list, tuple)):
        if len(raw_score) != 1:
            return result("inconclusive", error_code="model_return_shape_unrecognized")
        raw_score = raw_score[0]

    try:
        score = float(raw_score)
    except (TypeError, ValueError, OverflowError):
        return result("inconclusive", error_code="model_score_unrecognized")

    if not math.isfinite(score) or score < 0.0 or score > 1.0:
        return result("inconclusive", error_code="model_score_out_of_range")

    if score >= 0.5:
        return result("completed", "likely_ai_generated", score)
    return result("completed", "likely_real", 1.0 - score)


def load_detector() -> Any:
    """Download/cache model artifacts and load the model's documented helper."""
    from huggingface_hub import snapshot_download

    cache_dir = os.environ.get("HF_HOME", "/var/cache/huggingface")
    revision = os.environ.get("SMAUG_MODEL_REVISION", "").strip() or None
    model_path = snapshot_download(
        repo_id=MODEL_ID,
        revision=revision,
        cache_dir=cache_dir,
    )
    sys.path.insert(0, model_path)
    from smaug_image_detect import SmaugImageDetect

    return SmaugImageDetect.from_pretrained(model_path)


def main(argv: list[str]) -> int:
    if len(argv) != 2:
        print(json.dumps(result("failed", error_code="image_path_required")))
        return 0

    image_path = Path(argv[1])
    try:
        if not image_path.is_file() or not os.access(image_path, os.R_OK):
            print(json.dumps(result("failed", error_code="invalid_image_input")))
            return 0
        size = image_path.stat().st_size
        if size < 1 or size > MAX_IMAGE_BYTES:
            print(json.dumps(result("failed", error_code="image_size_invalid")))
            return 0

        from PIL import Image, ImageFile
        Image.MAX_IMAGE_PIXELS = MAX_PIXELS
        ImageFile.LOAD_TRUNCATED_IMAGES = False
        with Image.open(image_path) as image:
            if image.format not in {"JPEG", "PNG", "WEBP"}:
                print(json.dumps(result("failed", error_code="unsupported_image_format")))
                return 0
            if image.width < 1 or image.height < 1 or image.width * image.height > MAX_PIXELS:
                print(json.dumps(result("failed", error_code="image_dimensions_invalid")))
                return 0
            image.verify()
        with Image.open(image_path) as image:
            image = image.convert("RGB")
            detector = load_detector()
            try:
                import torch
                threads = os.environ.get("SMAUG_NUM_THREADS", "4")
                torch.set_num_threads(max(1, min(8, int(threads))))
            except (ImportError, ValueError):
                pass
            raw_score = detector(image)
            normalized = normalize_score(raw_score)
            print(json.dumps(normalized, separators=(",", ":"), allow_nan=False))
            return 0
    except Exception as exc:  # safe boundary: don't print paths or provider internals
        print(f"Smaug local inference failed ({type(exc).__name__}).", file=sys.stderr)
        print(json.dumps(result("unavailable", error_code="local_model_load_or_inference_failed")))
        return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
