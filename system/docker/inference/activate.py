import json
import sys
import tempfile
from contextlib import redirect_stdout
from pathlib import Path

from dotmap import DotMap


def load_omrchecker():
    with redirect_stdout(sys.stderr):
        from src.defaults import CONFIG_DEFAULTS
        from src.template import Template

    return CONFIG_DEFAULTS, Template


def merge_config(overrides: dict, defaults: DotMap) -> DotMap:
    config = DotMap(defaults.toDict(), _dynamic=False)

    for section, values in overrides.items():
        if not isinstance(values, dict):
            continue
        if section not in config:
            config[section] = DotMap({}, _dynamic=False)
        for key, value in values.items():
            config[section][key] = value

    config.outputs.show_image_level = 0
    config.outputs.save_image_level = 0
    config.outputs.save_detections = False
    return config


def expand(payload: dict) -> dict:
    template_json = payload.get("template")
    config_json = payload.get("config", {})
    bundle = payload.get("bundle", {})

    if not isinstance(template_json, dict) or not isinstance(config_json, dict) or not isinstance(bundle, dict):
        raise ValueError("activation payload must contain template, config, and bundle objects")

    defaults, template_class = load_omrchecker()

    with tempfile.TemporaryDirectory(prefix="nexiam-omr-activation-") as directory:
        template_path = Path(directory) / "template.json"
        template_path.write_text(json.dumps(template_json, separators=(",", ":")), encoding="utf-8")
        with redirect_stdout(sys.stderr):
            template = template_class(template_path, merge_config(config_json, defaults))

        page_width, page_height = template.page_dimensions
        fields = []
        for block in template.field_blocks:
            for field_bubbles in block.traverse_bubbles:
                for bubble in field_bubbles:
                    x = int(bubble.x)
                    y = int(bubble.y)
                    width, height = template.bubble_dimensions
                    fields.append(
                        {
                            "block": str(block.name),
                            "label": str(bubble.field_label),
                            "option": str(bubble.field_value),
                            "pixel": {"x": x, "y": y, "w": int(width), "h": int(height)},
                            "region": {
                                "x": x / float(page_width),
                                "y": y / float(page_height),
                                "w": float(width) / float(page_width),
                                "h": float(height) / float(page_height),
                            },
                        }
                    )

        return {
            "engine": bundle.get("engine"),
            "template_code": bundle.get("template_code"),
            "template_version": bundle.get("template_version"),
            "page_dimensions": {"width": int(page_width), "height": int(page_height)},
            "bubble_dimensions": {"width": int(template.bubble_dimensions[0]), "height": int(template.bubble_dimensions[1])},
            "fields": fields,
        }


def main() -> int:
    try:
        payload = json.load(sys.stdin)
        result = expand(payload)
        json.dump(result, sys.stdout, separators=(",", ":"), sort_keys=True)
        sys.stdout.write("\n")
        return 0
    except Exception as error:
        detail = str(error)
        code = "template_invalid" if "Provided Template JSON is Invalid" in detail else "manifest_derivation_failed"
        print(json.dumps({"error": code, "detail": detail}), file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
