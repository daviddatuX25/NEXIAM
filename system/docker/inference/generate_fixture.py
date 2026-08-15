import argparse
import json
import re
from pathlib import Path

import cv2
import numpy as np


RANGE_PATTERN = re.compile(r"^(.*?)(\d+)\.\.(\d+)$")
FIELD_VALUES = {
    "QTYPE_INT": [str(value) for value in range(10)],
    "QTYPE_MCQ4": ["A", "B", "C", "D"],
    "QTYPE_MCQ5": ["A", "B", "C", "D", "E"],
}


def expand_label(value: str) -> list[str]:
    match = RANGE_PATTERN.fullmatch(value)
    if match is None:
        return [value]
    prefix, start_text, end_text = match.groups()
    start = int(start_text)
    end = int(end_text)
    width = len(start_text)
    if end < start:
        raise ValueError(f"Descending field label range is not supported: {value}")
    return [f"{prefix}{index:0{width}d}" for index in range(start, end + 1)]


def block_labels(block: dict) -> list[str]:
    labels: list[str] = []
    for declaration in block.get("fieldLabels", []):
        labels.extend(expand_label(str(declaration)))
    if not labels:
        raise ValueError("Every field block must declare at least one field label.")
    return labels


def block_values(block: dict) -> list[str]:
    declared = block.get("bubbleValues")
    if isinstance(declared, list) and declared:
        return [str(value) for value in declared]
    values = FIELD_VALUES.get(str(block.get("fieldType")))
    if values is None:
        raise ValueError(f"Unsupported fixture field type: {block.get('fieldType')}")
    return values


def selected_index(block_name: str, label_index: int, values: list[str], seed: int) -> int:
    if block_name == "ApplicantNumber":
        return (label_index + seed) % len(values)
    return (label_index + seed) % len(values)


def render(template: dict, seed: int, blank: bool, margin_ratio: float) -> np.ndarray:
    page_width, page_height = map(int, template["pageDimensions"])
    bubble_width, bubble_height = map(int, template["bubbleDimensions"])
    margin_x = round(page_width * margin_ratio)
    margin_y = round(page_height * margin_ratio)
    canvas = np.full((page_height + 2 * margin_y, page_width + 2 * margin_x), 238, dtype=np.uint8)
    canvas[margin_y : margin_y + page_height, margin_x : margin_x + page_width] = 255
    cv2.rectangle(
        canvas,
        (margin_x + 8, margin_y + 8),
        (margin_x + page_width - 9, margin_y + page_height - 9),
        0,
        16,
    )

    for block_name, block in template["fieldBlocks"].items():
        labels = block_labels(block)
        values = block_values(block)
        origin_x, origin_y = map(int, block["origin"])
        labels_gap = int(block["labelsGap"])
        bubbles_gap = int(block["bubblesGap"])
        vertical = str(block.get("direction", "vertical")) == "vertical"

        for label_index, _label in enumerate(labels):
            chosen = selected_index(str(block_name), label_index, values, seed)
            for value_index, _value in enumerate(values):
                x = origin_x + (label_index * labels_gap if vertical else value_index * bubbles_gap)
                y = origin_y + (value_index * bubbles_gap if vertical else label_index * labels_gap)
                if x < 0 or y < 0 or x + bubble_width > page_width or y + bubble_height > page_height:
                    raise ValueError(f"Bubble falls outside page bounds in block {block_name}.")
                center = (margin_x + x + bubble_width // 2, margin_y + y + bubble_height // 2)
                radius = max(3, min(bubble_width, bubble_height) // 2 - 3)
                cv2.circle(canvas, center, radius, 0, 2, lineType=cv2.LINE_AA)
                if not blank and value_index == chosen:
                    cv2.circle(canvas, center, max(2, radius - 4), 0, -1, lineType=cv2.LINE_AA)

    return canvas


def render_pages(template: dict, seed: int, blank: bool, margin_ratio: float) -> list[np.ndarray]:
    pages = template.get("pages")
    if isinstance(pages, list) and pages:
        images = []
        for page in pages:
            single = dict(template)
            single.pop("pages", None)
            single["pageDimensions"] = page["pageDimensions"]
            single["fieldBlocks"] = page["fieldBlocks"]
            single["preProcessors"] = page.get("preProcessors", [])
            images.append(render(single, seed, blank, margin_ratio))
        return images
    return [render(template, seed, blank, margin_ratio)]


def main() -> int:
    parser = argparse.ArgumentParser(description="Generate a deterministic synthetic OMR sheet from a native template.")
    parser.add_argument("template", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--seed", type=int, default=0)
    parser.add_argument("--blank", action="store_true")
    parser.add_argument("--margin-ratio", type=float, default=0.05)
    args = parser.parse_args()
    if not 0.0 <= args.margin_ratio <= 0.2:
        parser.error("--margin-ratio must be between 0 and 0.2")

    template = json.loads(args.template.read_text(encoding="utf-8"))
    images = render_pages(template, args.seed, args.blank, args.margin_ratio)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    for index, image in enumerate(images, start=1):
        output = args.output if len(images) == 1 else args.output.with_name(
            f"{args.output.stem}-page-{index}{args.output.suffix}"
        )
        if not cv2.imwrite(str(output), image, [cv2.IMWRITE_PNG_COMPRESSION, 9]):
            raise RuntimeError("Failed to encode the fixture image.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
