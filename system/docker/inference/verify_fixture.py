import json
import sys
from pathlib import Path

import detect


def main() -> int:
    if len(sys.argv) != 4:
        raise SystemExit("usage: verify_fixture.py MANIFEST IMAGE OUTPUT_DIR")

    manifest_path, image_path, output_directory = map(Path, sys.argv[1:])
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    result = detect.detect(manifest, image_path, output_directory)
    by_position = {int(item["position"]): item for item in result["items"]}
    expected = {
        1: "C",
        2: "B",
        3: "C",
        4: "D",
        5: "D",
    }
    wrong_marks = []
    for position, item in by_position.items():
        selected = item.get("selected_option")
        if selected is None:
            continue
        expected_option = expected.get(position)
        if expected_option != selected:
            wrong_marks.append({"position": position, "selected": selected, "expected": expected_option})

    if wrong_marks:
        print(json.dumps({"status": "failed", "wrong_marks": wrong_marks}, separators=(",", ":")))
        return 1

    marked = [
        {"position": int(item["position"]), "answer": item["selected_option"]}
        for item in result["items"]
        if item.get("state") == "marked"
    ]
    print(json.dumps({
        "status": "accepted_or_abstained",
        "marked": marked,
        "marked_count": len(marked),
        "applicant_number": result.get("applicant_number_proposal"),
        "identity_abstained": result.get("applicant_number_proposal") is None,
        "issue_codes": sorted({issue["code"] for issue in result.get("issues", [])}),
        "field_block_shifts": result.get("field_block_shifts", []),
        "aligned_image": {
            "width": result["aligned_image"]["width"],
            "height": result["aligned_image"]["height"],
        },
    }, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
