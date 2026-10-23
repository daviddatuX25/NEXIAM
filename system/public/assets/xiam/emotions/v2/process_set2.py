import os
import json
import numpy as np
from PIL import Image

RAW_SET2 = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2/raw_set2'
OUT_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2'
FRAMES_DIR = os.path.join(OUT_DIR, 'frames')
TARGET_SIZE = 512

# Master alignment reference from Set 1:
min_x, min_y, max_x, max_y = 174, 58, 723, 686
ref_w = max_x - min_x
ref_h = max_y - min_y
target_h = int(TARGET_SIZE * 0.85)
scale = target_h / float(ref_h)
target_w = int(ref_w * scale)

def normalize_canvas_set2(img_rgba):
    # Set 2 images are already 900x694 RGBA with transparent backgrounds
    # We crop with the same global reference bounding box, but allow extra width for side props (phone/paper)
    # The bottom is y=694 (grounded)
    # Crop horizontally centered at 450, width 700
    crop_x1 = max(0, 450 - 350)
    crop_x2 = min(img_rgba.width, 450 + 350)
    crop_y1 = 30
    crop_y2 = 694
    
    crop = img_rgba.crop((crop_x1, crop_y1, crop_x2, crop_y2))
    
    # Scale proportionally to fit within 512x512
    # Fit inside 460x460 to preserve side props
    max_dim = 450
    scale_factor = min(max_dim / float(crop.width), max_dim / float(crop.height))
    new_w = int(crop.width * scale_factor)
    new_h = int(crop.height * scale_factor)
    scaled = crop.resize((new_w, new_h), Image.Resampling.LANCZOS)
    
    canvas = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
    paste_x = (TARGET_SIZE - new_w) // 2
    paste_y = (TARGET_SIZE - new_h) // 2 + 15 # grounded
    canvas.paste(scaled, (paste_x, paste_y), scaled)
    return canvas

# Specifically for IDLE frames, we want EXACT pixel-level alignment with Set 1 head/body:
def normalize_idle_frame(img_rgba):
    cropped = img_rgba.crop((min_x, min_y, max_x, max_y))
    scaled = cropped.resize((target_w, target_h), Image.Resampling.LANCZOS)
    canvas = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
    paste_x = (TARGET_SIZE - target_w) // 2
    paste_y = (TARGET_SIZE - target_h) // 2 + 10
    canvas.paste(scaled, (paste_x, paste_y), scaled)
    return canvas

print("1. Normalizing Idle frames...")
raw_oe = Image.open(os.path.join(RAW_SET2, 'idle-open-eyes.png'))
raw_ce = Image.open(os.path.join(RAW_SET2, 'idle-close-eyes.png'))
raw_gs = Image.open(os.path.join(RAW_SET2, 'idle-glance-side.png'))

idle_oe = normalize_idle_frame(raw_oe)
idle_ce = normalize_idle_frame(raw_ce)
idle_gs = normalize_idle_frame(raw_gs)

# Save individual idle frame PNGs
idle_oe.save(os.path.join(FRAMES_DIR, 'idle-open.png'), 'PNG')
idle_ce.save(os.path.join(FRAMES_DIR, 'idle-blink.png'), 'PNG')
idle_gs.save(os.path.join(FRAMES_DIR, 'idle-glance.png'), 'PNG')

# Master Idle Sequence:
# Open (2400ms) -> Blink (160ms) -> Open (1200ms) -> Glance Side (1800ms) -> Blink (160ms)
idle_master_frames = [idle_oe, idle_ce, idle_oe, idle_gs, idle_ce]
idle_master_durations = [2400, 160, 1200, 1800, 160]

idle_master_frames[0].save(
    os.path.join(OUT_DIR, 'idle.webp'),
    format='WEBP',
    save_all=True,
    append_images=idle_master_frames[1:],
    duration=idle_master_durations,
    loop=0,
    transparency=0,
    disposal=2
)

# Idle Variant: Simple Quick Blink (Open 2200ms <-> Blink 160ms)
idle_blink_frames = [idle_oe, idle_ce]
idle_blink_frames[0].save(
    os.path.join(OUT_DIR, 'idle-blink-only.webp'),
    format='WEBP',
    save_all=True,
    append_images=[idle_ce],
    duration=[2200, 160],
    loop=0,
    transparency=0,
    disposal=2
)

print("  Master Idle WebP and variants generated.")

# ---------------------------------------------------------------------
# 2. THINKING SET 1: Pondering (thinking-1.png)
# ---------------------------------------------------------------------
print("2. Normalizing Thinking Set 1 (Pondering)...")
raw_th = Image.open(os.path.join(RAW_SET2, 'thinking-1.png'))
th_frame1 = normalize_canvas_set2(raw_th)

# Create a subtle micro-pulse keyframe for thinking (stars shimmer / subtle thought pulse)
th_frame2 = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
th_scaled = th_frame1.resize((int(TARGET_SIZE * 1.018), int(TARGET_SIZE * 1.018)), Image.Resampling.LANCZOS)
px = (TARGET_SIZE - th_scaled.width) // 2
py = (TARGET_SIZE - th_scaled.height) // 2
th_frame2.paste(th_scaled, (px, py), th_scaled)

th_frame1.save(os.path.join(FRAMES_DIR, 'thinking-1.png'), 'PNG')
th_frame2.save(os.path.join(FRAMES_DIR, 'thinking-2.png'), 'PNG')

th_frame1.save(
    os.path.join(OUT_DIR, 'thinking.webp'),
    format='WEBP',
    save_all=True,
    append_images=[th_frame2],
    duration=[700, 700],
    loop=0,
    transparency=0,
    disposal=2
)
print("  Thinking Set 1 (Pondering) generated.")

# ---------------------------------------------------------------------
# 3. THINKING SET 2: Paper Searching (paper-searching.png)
# ---------------------------------------------------------------------
print("3. Normalizing Thinking Set 2 (Paper Searching)...")
raw_pp = Image.open(os.path.join(RAW_SET2, 'paper-searching.png'))
pp_frame1 = normalize_canvas_set2(raw_pp)

# Micro-shift for paper shuffle
pp_frame2 = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
pp_scaled = pp_frame1.resize((int(TARGET_SIZE * 1.015), int(TARGET_SIZE * 1.015)), Image.Resampling.LANCZOS)
px = (TARGET_SIZE - pp_scaled.width) // 2
py = (TARGET_SIZE - pp_scaled.height) // 2
pp_frame2.paste(pp_scaled, (px, py), pp_scaled)

pp_frame1.save(os.path.join(FRAMES_DIR, 'searching-paper-1.png'), 'PNG')
pp_frame2.save(os.path.join(FRAMES_DIR, 'searching-paper-2.png'), 'PNG')

pp_frame1.save(
    os.path.join(OUT_DIR, 'searching-paper.webp'),
    format='WEBP',
    save_all=True,
    append_images=[pp_frame2],
    duration=[600, 600],
    loop=0,
    transparency=0,
    disposal=2
)
print("  Thinking Set 2 (Paper Searching) generated.")

# ---------------------------------------------------------------------
# 4. THINKING SET 3: Phone Searching (phone-searching.png)
# ---------------------------------------------------------------------
print("4. Normalizing Thinking Set 3 (Phone Searching)...")
raw_ph = Image.open(os.path.join(RAW_SET2, 'phone-searching.png'))
ph_frame1 = normalize_canvas_set2(raw_ph)

# Micro-pulse for notification/screen tap
ph_frame2 = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
ph_scaled = ph_frame1.resize((int(TARGET_SIZE * 1.015), int(TARGET_SIZE * 1.015)), Image.Resampling.LANCZOS)
px = (TARGET_SIZE - ph_scaled.width) // 2
py = (TARGET_SIZE - ph_scaled.height) // 2
ph_frame2.paste(ph_scaled, (px, py), ph_scaled)

ph_frame1.save(os.path.join(FRAMES_DIR, 'searching-phone-1.png'), 'PNG')
ph_frame2.save(os.path.join(FRAMES_DIR, 'searching-phone-2.png'), 'PNG')

ph_frame1.save(
    os.path.join(OUT_DIR, 'searching-phone.webp'),
    format='WEBP',
    save_all=True,
    append_images=[ph_frame2],
    duration=[600, 600],
    loop=0,
    transparency=0,
    disposal=2
)
print("  Thinking Set 3 (Phone Searching) generated.")

# ---------------------------------------------------------------------
# 5. UPDATE MANIFEST.JSON
# ---------------------------------------------------------------------
with open(os.path.join(OUT_DIR, 'manifest.json'), 'r') as mf:
    manifest_data = json.load(mf)

# Update idle with authentic 3-frame master cycle
manifest_data["emotions"]["idle"] = {
    "label": "Idle (Master Life Cycle)",
    "animated_webp": "/assets/xiam/emotions/v2/idle.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/idle-open.png",
        "/assets/xiam/emotions/v2/frames/idle-blink.png",
        "/assets/xiam/emotions/v2/frames/idle-open.png",
        "/assets/xiam/emotions/v2/frames/idle-glance.png",
        "/assets/xiam/emotions/v2/frames/idle-blink.png"
    ],
    "durations_ms": [2400, 160, 1200, 1800, 160]
}

# Add Thinking Action Sets
manifest_data["emotions"]["thinking"] = {
    "label": "Thinking • Pondering",
    "animated_webp": "/assets/xiam/emotions/v2/thinking.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/thinking-1.png",
        "/assets/xiam/emotions/v2/frames/thinking-2.png"
    ],
    "durations_ms": [700, 700]
}

manifest_data["emotions"]["searching-paper"] = {
    "label": "Thinking • Researching Papers",
    "animated_webp": "/assets/xiam/emotions/v2/searching-paper.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/searching-paper-1.png",
        "/assets/xiam/emotions/v2/frames/searching-paper-2.png"
    ],
    "durations_ms": [600, 600]
}

manifest_data["emotions"]["searching-phone"] = {
    "label": "Thinking • Checking Device",
    "animated_webp": "/assets/xiam/emotions/v2/searching-phone.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/searching-phone-1.png",
        "/assets/xiam/emotions/v2/frames/searching-phone-2.png"
    ],
    "durations_ms": [600, 600]
}

with open(os.path.join(OUT_DIR, 'manifest.json'), 'w') as mf:
    json.dump(manifest_data, mf, indent=2)

print("SUCCESS: manifest.json updated with authentic Idle cycle and 3 Thinking actions!")
