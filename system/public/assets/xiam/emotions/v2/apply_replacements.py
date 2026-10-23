import os
import json
import numpy as np
from PIL import Image
from scipy.ndimage import label, gaussian_filter

REP_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2/raw_replacements'
OUT_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2'
FRAMES_DIR = os.path.join(OUT_DIR, 'frames')
TARGET_SIZE = 512

def process_replacement(filename):
    p = os.path.join(REP_DIR, filename)
    img = Image.open(p)
    arr = np.array(img)
    
    # 1. Flood-fill from borders only to remove white background while preserving papers/eyes/shirt
    is_white = (arr[:, :, 0] > 240) & (arr[:, :, 1] > 240) & (arr[:, :, 2] > 240)
    labeled, num_features = label(is_white)
    
    border_labels = set(np.unique(labeled[0, :])) | set(np.unique(labeled[-1, :])) | set(np.unique(labeled[:, 0])) | set(np.unique(labeled[:, -1]))
    border_labels.discard(0)
    
    outside_bg = np.isin(labeled, list(border_labels))
    
    # 2. Smooth anti-aliased edge
    alpha = np.where(outside_bg, 0, 255).astype(np.uint8)
    smooth_alpha = gaussian_filter(alpha.astype(float), sigma=0.85)
    alpha_clean = np.clip(smooth_alpha, 0, 255).astype(np.uint8)
    
    rgba = np.dstack((arr[:, :, 0], arr[:, :, 1], arr[:, :, 2], alpha_clean))
    rgba_img = Image.fromarray(rgba, 'RGBA')
    
    # 3. Crop to content bounding box
    bbox = rgba_img.getbbox()
    cropped = rgba_img.crop(bbox)
    
    # 4. Scale to fit within 512x512 (max dimension ~ 445px for nice margins)
    max_dim = 445
    scale_factor = min(max_dim / float(cropped.width), max_dim / float(cropped.height))
    new_w = int(cropped.width * scale_factor)
    new_h = int(cropped.height * scale_factor)
    scaled = cropped.resize((new_w, new_h), Image.Resampling.LANCZOS)
    
    # 5. Place on transparent canvas
    canvas = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
    paste_x = (TARGET_SIZE - new_w) // 2
    paste_y = (TARGET_SIZE - new_h) // 2 + 10 # grounded slightly toward bottom
    canvas.paste(scaled, (paste_x, paste_y), scaled)
    return canvas

print("Processing new replacements...")
norm_paper = process_replacement('paper-searching.png')
norm_phone = process_replacement('phone-searching.png')

# Create subtle micro-focus/pulse frame 2 for both
def create_subtle_pulse(canvas):
    pulse = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
    scaled = canvas.resize((int(TARGET_SIZE * 1.015), int(TARGET_SIZE * 1.015)), Image.Resampling.LANCZOS)
    px = (TARGET_SIZE - scaled.width) // 2
    py = (TARGET_SIZE - scaled.height) // 2
    pulse.paste(scaled, (px, py), scaled)
    return pulse

norm_paper_2 = create_subtle_pulse(norm_paper)
norm_phone_2 = create_subtle_pulse(norm_phone)

# Save frames
norm_paper.save(os.path.join(FRAMES_DIR, 'searching-paper-1.png'), 'PNG')
norm_paper_2.save(os.path.join(FRAMES_DIR, 'searching-paper-2.png'), 'PNG')

norm_phone.save(os.path.join(FRAMES_DIR, 'searching-phone-1.png'), 'PNG')
norm_phone_2.save(os.path.join(FRAMES_DIR, 'searching-phone-2.png'), 'PNG')

# Save Animated WebPs
norm_paper.save(
    os.path.join(OUT_DIR, 'searching-paper.webp'),
    format='WEBP',
    save_all=True,
    append_images=[norm_paper_2],
    duration=[650, 650],
    loop=0,
    transparency=0,
    disposal=2
)

norm_phone.save(
    os.path.join(OUT_DIR, 'searching-phone.webp'),
    format='WEBP',
    save_all=True,
    append_images=[norm_phone_2],
    duration=[650, 650],
    loop=0,
    transparency=0,
    disposal=2
)

print("Saved new frames and WebPs for searching-paper and searching-phone.")

# Update manifest
with open(os.path.join(OUT_DIR, 'manifest.json'), 'r') as mf:
    manifest_data = json.load(mf)

manifest_data["emotions"]["searching-paper"] = {
    "label": "Thinking • Researching Papers (New)",
    "animated_webp": "/assets/xiam/emotions/v2/searching-paper.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/searching-paper-1.png",
        "/assets/xiam/emotions/v2/frames/searching-paper-2.png"
    ],
    "durations_ms": [650, 650]
}

manifest_data["emotions"]["searching-phone"] = {
    "label": "Thinking • Checking Device (New)",
    "animated_webp": "/assets/xiam/emotions/v2/searching-phone.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/searching-phone-1.png",
        "/assets/xiam/emotions/v2/frames/searching-phone-2.png"
    ],
    "durations_ms": [650, 650]
}

with open(os.path.join(OUT_DIR, 'manifest.json'), 'w') as mf:
    json.dump(manifest_data, mf, indent=2)

print("manifest.json successfully updated with new replacement assets!")
