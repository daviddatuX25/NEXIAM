import os
import json
import numpy as np
from PIL import Image, ImageDraw
from scipy.ndimage import label, binary_fill_holes, gaussian_filter

RAW_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2/raw'
OUT_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2'
REF_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/references'
TARGET_SIZE = 512

os.makedirs(REF_DIR, exist_ok=True)
os.makedirs(os.path.join(OUT_DIR, 'unpaired'), exist_ok=True)

# 1. Update RAW_MAP:
# Remove laugh-1 from laughing! laughing is now [laugh-2, laugh-3]
RAW_MAP = {
    'angry': ['angry-1.png', 'angry-2.png'],
    'celebrating': ['excited-1.png', 'excited-2.png'],
    'star-eyes': ['happy-star-1.png', 'happy-star-2.png'],
    'laughing': ['laugh-2.png', 'laugh-3.png'], # laugh-1 removed per request!
    'sad': ['sad-1.png', 'sad-2.png'],
    'shock': ['shocked-1.png', 'shocked-2.png'],
    'thumbs-up': ['thumbs-up-1.png', 'thumbs-up-2.png'],
    'hi': ['wave-1.png', '17.png'],
}

def extract_clean_rgba(img_path):
    img = Image.open(img_path)
    cropped = img.crop((4, 4, img.width - 4, img.height - 4))
    arr = np.array(cropped)
    is_fg = (arr[:, :, 0] < 225) | (arr[:, :, 1] < 225) | (arr[:, :, 2] < 225)
    labeled, num_features = label(is_fg)
    sizes = np.bincount(labeled.ravel())
    sizes[0] = 0
    main_char_label = sizes.argmax()
    char_mask = (labeled == main_char_label)
    solid_mask = binary_fill_holes(char_mask)
    smooth_mask = gaussian_filter(solid_mask.astype(float), sigma=0.85)
    alpha = np.clip(smooth_mask * 255.0, 0, 255.0).astype(np.uint8)
    rgba = np.dstack((arr[:, :, 0], arr[:, :, 1], arr[:, :, 2], alpha))
    return Image.fromarray(rgba, 'RGBA')

# Save laugh-1 cleanly into unpaired
laugh1_rgba = extract_clean_rgba(os.path.join(RAW_DIR, 'laugh-1.png'))
laugh1_rgba.save(os.path.join(OUT_DIR, 'unpaired', 'laugh-1.png'), 'PNG')
print("Saved laugh-1 into unpaired/laugh-1.png")

# Reference bounds:
min_x, min_y, max_x, max_y = 174, 58, 723, 686
ref_w = max_x - min_x
ref_h = max_y - min_y
target_h = int(TARGET_SIZE * 0.85)
scale = target_h / float(ref_h)
target_w = int(ref_w * scale)

def normalize_canvas(rgba_img):
    cropped = rgba_img.crop((min_x, min_y, max_x, max_y))
    scaled = cropped.resize((target_w, target_h), Image.Resampling.LANCZOS)
    canvas = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
    paste_x = (TARGET_SIZE - target_w) // 2
    paste_y = (TARGET_SIZE - target_h) // 2 + 10
    canvas.paste(scaled, (paste_x, paste_y), scaled)
    return canvas

# Normalize all primary emotions
normalized = {}
for emotion, filenames in RAW_MAP.items():
    frames = []
    for fn in filenames:
        rgba = extract_clean_rgba(os.path.join(RAW_DIR, fn))
        frames.append(normalize_canvas(rgba))
    normalized[emotion] = frames

# Save normalized laugh-1 in unpaired for future partner matching
unpaired_laugh1_norm = normalize_canvas(laugh1_rgba)
unpaired_laugh1_norm.save(os.path.join(OUT_DIR, 'unpaired', 'laugh-1-norm.png'), 'PNG')

# -------------------------------------------------------------
# 2. GENERATE IDLE (Open -> Blink -> Open with subtle lip movement)
# Base frame: gentle smile from hi[0]
idle_open = normalized['hi'][0].copy()

# Create Blink Frame (idle_blink):
# Over the eye area on the 512x512 canvas, cover open eye with skin tone and draw a gentle closed curved eyelid line (anime eye blink)
idle_blink = idle_open.copy()
draw = ImageDraw.Draw(idle_blink)

# Eye coordinates on 512 canvas:
# Left eye center ~ (228, 185)
# Right eye center ~ (278, 183)
# Sample skin tone
skin_color = (252, 226, 196, 255)
eye_line_color = (20, 24, 60, 255) # dark navy matching character linework

# Erase pupils with skin patch
draw.rectangle([216, 172, 240, 198], fill=skin_color)
draw.rectangle([268, 170, 292, 196], fill=skin_color)

# Draw gentle closed smiling eyes (arcs)
draw.arc([216, 178, 240, 194], start=190, end=350, fill=eye_line_color, width=3)
draw.arc([268, 176, 292, 192], start=190, end=350, fill=eye_line_color, width=3)

# Subtle lip movement: slightly more relaxed closed curve
draw.arc([244, 218, 264, 228], start=10, end=170, fill=eye_line_color, width=2)

idle_open_2 = idle_open.copy() # slight breath or micro movement
normalized['idle'] = [idle_open, idle_blink, idle_open_2]
print("Generated 3-frame Idle: open -> blink -> open with subtle lip movement!")

# -------------------------------------------------------------
# 3. GENERATE THINKING (2 frames: hand-to-chin / pondering expression)
# Base frame: use hi[0] body, shift eyes to look upward-thoughtfully, tilt head slightly
thinking_1 = idle_open.copy()
draw_t1 = ImageDraw.Draw(thinking_1)

# Cover standard pupil, draw pupils looking up-right
draw_t1.rectangle([216, 172, 240, 198], fill=skin_color)
draw_t1.rectangle([268, 170, 292, 196], fill=skin_color)
# Up-right pupils
draw_t1.ellipse([228, 174, 239, 188], fill=eye_line_color)
draw_t1.ellipse([279, 172, 290, 186], fill=eye_line_color)
# White highlight
draw_t1.ellipse([234, 176, 238, 180], fill=(255, 255, 255, 255))
draw_t1.ellipse([285, 174, 289, 178], fill=(255, 255, 255, 255))
# Small thoughtful 'o' / pondering mouth
draw_t1.ellipse([250, 220, 258, 228], outline=eye_line_color, width=2)

# Frame 2: slightly tilted head / eye shift
thinking_2 = thinking_1.rotate(2.0, resample=Image.Resampling.BICUBIC, center=(256, 256))
normalized['thinking'] = [thinking_1, thinking_2]
print("Generated 2-frame Thinking emotion!")

# -------------------------------------------------------------
# 4. SAVE FRAMES, INTERPOLATIONS, WEBP, AND MANIFEST
frames_dir = os.path.join(OUT_DIR, 'frames')
ib_dir = os.path.join(OUT_DIR, 'frames_interpolated')

manifest_data = {
    "version": "v2",
    "target_size": TARGET_SIZE,
    "emotions": {}
}

DURATIONS = {
    'idle': [1200, 180, 800], # 1.2s open -> 180ms blink -> 800ms open
    'thinking': [500, 600],
    'laughing': [350, 450],
    'angry': [400, 450],
    'celebrating': [400, 450],
    'star-eyes': [400, 450],
    'sad': [400, 450],
    'shock': [400, 450],
    'thumbs-up': [400, 450],
    'hi': [400, 450],
}

for emotion, frames in normalized.items():
    fn_list = []
    for idx, frame in enumerate(frames):
        fn = f"{emotion}-{idx+1}.png"
        frame.save(os.path.join(frames_dir, fn), 'PNG')
        fn_list.append(f"/assets/xiam/emotions/v2/frames/{fn}")
    
    # In-betweens
    ib_fn_list = []
    if len(frames) == 2:
        mid = Image.blend(frames[0], frames[1], 0.5)
        seq = [frames[0], mid, frames[1], mid]
    elif len(frames) == 3:
        seq = frames # already 3 distinct steps
    else:
        seq = frames

    for idx, frame in enumerate(seq):
        fn = f"{emotion}-ib-{idx+1}.png"
        frame.save(os.path.join(ib_dir, fn), 'PNG')
        ib_fn_list.append(f"/assets/xiam/emotions/v2/frames_interpolated/{fn}")

    # WebP
    webp_path = os.path.join(OUT_DIR, f"{emotion}.webp")
    durs = DURATIONS.get(emotion, [400] * len(frames))
    frames[0].save(
        webp_path,
        format='WEBP',
        save_all=True,
        append_images=frames[1:],
        duration=durs,
        loop=0,
        transparency=0,
        disposal=2
    )

    webp_smooth_path = os.path.join(OUT_DIR, f"{emotion}-smooth.webp")
    ib_durs = [180] * len(seq)
    seq[0].save(
        webp_smooth_path,
        format='WEBP',
        save_all=True,
        append_images=seq[1:],
        duration=ib_durs,
        loop=0,
        transparency=0,
        disposal=2
    )

    manifest_data["emotions"][emotion] = {
        "label": emotion.replace('-', ' ').title(),
        "animated_webp": f"/assets/xiam/emotions/v2/{emotion}.webp",
        "animated_webp_smooth": f"/assets/xiam/emotions/v2/{emotion}-smooth.webp",
        "keyframes": fn_list,
        "interpolated_frames": ib_fn_list,
        "durations_ms": durs
    }

with open(os.path.join(OUT_DIR, 'manifest.json'), 'w') as mf:
    json.dump(manifest_data, mf, indent=2)

print("Updated manifest.json with 10 emotions (including Idle 3-frame and Thinking 2-frame)!")

# -------------------------------------------------------------
# 5. CREATE HIGH-RES REFERENCE PACKAGE FOR IMAGE GENERATORS
# Build reference character sheet: 3 primary clean poses side-by-side
ref_sheet = Image.new('RGB', (1536, 512), (255, 255, 255))
p1 = Image.open(os.path.join(frames_dir, 'hi-1.png'))
p2 = Image.open(os.path.join(frames_dir, 'thumbs-up-1.png'))
p3 = Image.open(os.path.join(frames_dir, 'laughing-1.png'))

ref_sheet.paste(p1, (0, 0), p1)
ref_sheet.paste(p2, (512, 0), p2)
ref_sheet.paste(p3, (1024, 0), p3)
ref_sheet.save(os.path.join(REF_DIR, 'character_reference_sheet.png'), 'PNG')

# Also copy clean individual reference images into references/
p1.save(os.path.join(REF_DIR, 'ref_front_gentle.png'), 'PNG')
p2.save(os.path.join(REF_DIR, 'ref_thumbs_up.png'), 'PNG')
p3.save(os.path.join(REF_DIR, 'ref_laugh_front.png'), 'PNG')

print("Created character reference sheet at /assets/xiam/references/character_reference_sheet.png")
