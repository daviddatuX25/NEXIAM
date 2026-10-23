import DOMPurify from "dompurify";
import { marked } from "marked";
const figureFiles = [
  "figure_3_1_evidence_to_process_mapping.png",
  "figure_3_2_current_state_flowchart.png"
];
let imageScope = null;
DOMPurify.addHook("uponSanitizeAttribute", (_node, data) => {
  if (data.attrName.toLowerCase() === "href") {
    const value = data.attrValue.trim();
    if (!(value.startsWith("#") || /^(https?:|mailto:)/i.test(value))) {
      data.keepAttr = false;
    }
  }
  if (data.attrName.toLowerCase() === "src") {
    const approvedFile = _node.nodeName === "IMG" ? imageScope?.get(data.attrValue.trim()) : void 0;
    if (approvedFile) {
      data.attrValue = approvedFile;
    } else {
      data.keepAttr = false;
    }
  }
  if (["srcset", "poster", "xlink:href", "formaction"].includes(data.attrName.toLowerCase())) {
    data.keepAttr = false;
  }
});
function approvedImageMap(approvedAssets) {
  if (!approvedAssets || typeof approvedAssets !== "object" || Array.isArray(approvedAssets)) {
    return /* @__PURE__ */ new Map();
  }
  return new Map(Object.entries(approvedAssets).filter(([source, fileName]) => typeof source === "string" && figureFiles.includes(fileName) && [fileName, `V2_RESOURCES/figures/${fileName}`, `../../V2_RESOURCES/figures/${fileName}`].includes(source)));
}
function safeMarkdownHtml(markdown, approvedAssets = {}) {
  const images = approvedImageMap(approvedAssets);
  const rendered = marked.parse(markdown, { gfm: true, breaks: false });
  let sanitized;
  imageScope = images;
  try {
    sanitized = DOMPurify.sanitize(rendered, {
      USE_PROFILES: { html: true },
      FORBID_TAGS: ["audio", "button", "embed", "form", "iframe", "input", "object", "option", "script", "select", "style", "textarea", "video", ...images.size ? [] : ["img"]],
      FORBID_ATTR: ["srcset", "style", "target"]
    });
  } finally {
    imageScope = null;
  }
  const container = document.createElement("template");
  container.innerHTML = sanitized;
  const approvedFiles = new Set(images.values());
  for (const image of container.content.querySelectorAll("img")) {
    if (!approvedFiles.has(image.getAttribute("src"))) {
      image.remove();
    } else {
      image.setAttribute("loading", "lazy");
    }
  }
  return container.innerHTML;
}
export {
  safeMarkdownHtml as s
};
