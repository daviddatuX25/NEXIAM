import os
import json
import numpy as np
from PIL import Image
from scipy.ndimage import label, gaussian_filter

R_REP = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2/raw_replacements'
R_TH2 = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2/raw_thinking_2nd'
R_SET2 = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2/raw_set2'

OUT_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2'
FRAMES_DIR = os.path.join(OUT_DIR, 'frames')
TARGET_SIZE = 512

def clean_white_bg(img_rgb):
    arr = np.array(img_rgb)
    is_white = (arr[:, :, 0] > 240) & (arr[:, :, 1] > 240) & (arr[:, :, 2] > 240)
    labeled, _ = label(is_white)
    border_labels = set(np.unique(labeled[0, :])) | set(np.unique(labeled[-1, :])) | set(np.unique(labeled[:, 0])) | set(np.unique(labeled[:, -1]))
    border_labels.discard(0)
    outside_bg = np.isin(labeled, list(border_labels))
    alpha = np.where(outside_bg, 0, 255).astype(np.uint8)
    smooth_alpha = gaussian_filter(alpha.astype(float), sigma=0.85)
    alpha_clean = np.clip(smooth_alpha, 0, 255).astype(np.uint8)
    rgba = np.dstack((arr[:, :, 0], arr[:, :, 1], arr[:, :, 2], alpha_clean))
    return Image.fromarray(rgba, 'RGBA')

def normalize_pair(f1_rgba, f2_rgba):
    # Determine the combined bounding box of both frames so they scale and anchor identically
    bbox1 = f1_rgba.getbbox()
    bbox2 = f2_rgba.getbbox()
    
    min_x = min(bbox1[0], bbox2[0])
    min_y = min(bbox1[1], bbox2[1])
    max_x = max(bbox1[2], bbox2[2])
    max_y = max(bbox1[3], bbox2[3])
    
    w = max_x - min_x
    h = max_y - min_y
    
    max_dim = 445
    scale_factor = min(max_dim / float(w), max_dim / float(h))
    new_w = int(w * scale_factor)
    new_h = int(h * scale_factor)
    
    paste_x = (TARGET_SIZE - new_w) // 2
    paste_y = (TARGET_SIZE - new_h) // 2 + 10 # grounded slightly toward bottom
    
    def transform(img):
        crop = img.crop((min_x, min_y, max_x, max_y))
        scaled = crop.resize((new_w, new_h), Image.Resampling.LANCZOS)
        canvas = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
        canvas.paste(scaled, (paste_x, paste_y), scaled)
        return canvas

    return transform(f1_rgba), transform(f2_rgba)

print("1. Processing Paper Searching pair...")
p1_raw = Image.open(os.path.join(R_REP, 'paper-searching.png'))
p1_rgba = clean_white_bg(p1_raw).crop((0, 0, 1125, 867))
p2_rgba = Image.open(os.path.join(R_TH2, 'paper-searching-2.png'))
paper_f1, paper_f2 = normalize_pair(p1_rgba, p2_rgba)

paper_f1.save(os.path.join(FRAMES_DIR, 'searching-paper-1.png'), 'PNG')
paper_f2.save(os.path.join(FRAMES_DIR, 'searching-paper-2.png'), 'PNG')
paper_f1.save(
    os.path.join(OUT_DIR, 'searching-paper.webp'),
    format='WEBP',
    save_all=True,
    append_images=[paper_f2],
    duration=[550, 550],
    loop=0,
    transparency=0,
    disposal=2
)
print("   Saved searching-paper-1.png, searching-paper-2.png, searching-paper.webp")

print("2. Processing Phone Searching pair...")
ph1_raw = Image.open(os.path.join(R_REP, 'phone-searching.png'))
ph1_rgba = clean_white_bg(ph1_raw).crop((0, 0, 1125, 867))
ph2_rgba = Image.open(os.path.join(R_TH2, 'phone-searching-2.png'))
phone_f1, phone_f2 = normalize_pair(ph1_rgba, ph2_rgba)

phone_f1.save(os.path.join(FRAMES_DIR, 'searching-phone-1.png'), 'PNG')
phone_f2.save(os.path.join(FRAMES_DIR, 'searching-phone-2.png'), 'PNG')
phone_f1.save(
    os.path.join(OUT_DIR, 'searching-phone.webp'),
    format='WEBP',
    save_all=True,
    append_images=[phone_f2],
    duration=[550, 550],
    loop=0,
    transparency=0,
    disposal=2
)
print("   Saved searching-phone-1.png, searching-phone-2.png, searching-phone.webp")

print("3. Processing Thinking pair...")
th1_raw = Image.open(os.path.join(R_SET2, 'thinking-1.png'))
# Scale to 1125x867 coordinate system
th1_scaled = th1_raw.resize((1125, 867), Image.Resampling.LANCZOS)
th2_rgba = Image.open(os.path.join(R_TH2, 'thinking-2.png'))
think_f1, think_f2 = normalize_pair(th1_scaled, th2_rgba)

think_f1.save(os.path.join(FRAMES_DIR, 'thinking-1.png'), 'PNG')
think_f2.save(os.path.join(FRAMES_DIR, 'thinking-2.png'), 'PNG')
think_f1.save(
    os.path.join(OUT_DIR, 'thinking.webp'),
    format='WEBP',
    save_all=True,
    append_images=[think_f2],
    duration=[600, 600],
    loop=0,
    transparency=0,
    disposal=2
)
print("   Saved thinking-1.png, thinking-2.png, thinking.webp")

# Update manifest
with open(os.path.join(OUT_DIR, 'manifest.json'), 'r') as mf:
    manifest_data = json.load(mf)

manifest_data["emotions"]["thinking"] = {
    "label": "Thinking • Pondering (Authentic 2-Frame)",
    "animated_webp": "/assets/xiam/emotions/v2/thinking.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/thinking-1.png",
        "/assets/xiam/emotions/v2/frames/thinking-2.png"
    ],
    "durations_ms": [600, 600]
}

manifest_data["emotions"]["searching-paper"] = {
    "label": "Thinking • Researching Papers (Authentic 2-Frame)",
    "animated_webp": "/assets/xiam/emotions/v2/searching-paper.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/searching-paper-1.png",
        "/assets/xiam/emotions/v2/frames/searching-paper-2.png"
    ],
    "durations_ms": [550, 550]
}

manifest_data["emotions"]["searching-phone"] = {
    "label": "Thinking • Checking Device (Authentic 2-Frame)",
    "animated_webp": "/assets/xiam/emotions/v2/searching-phone.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/searching-phone-1.png",
        "/assets/xiam/emotions/v2/frames/searching-phone-2.png"
    ],
    "durations_ms": [550, 550]
}

with open(os.path.join(OUT_DIR, 'manifest.json'), 'w') as mf:
    json.dump(manifest_data, mf, indent=2)

print("SUCCESS: manifest.json updated with authentic 2-frame pairs for all 3 thinking actions!")
