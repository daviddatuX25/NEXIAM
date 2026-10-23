import os
import json
import numpy as np
from PIL import Image, ImageDraw, ImageChops
from scipy.ndimage import label, binary_fill_holes, gaussian_filter

RAW_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2/raw'
OUT_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2'
TARGET_SIZE = 512

min_x, min_y, max_x, max_y = 174, 58, 723, 686
ref_w = max_x - min_x
ref_h = max_y - min_y
target_h = int(TARGET_SIZE * 0.85)
scale = target_h / float(ref_h)
target_w = int(ref_w * scale)

def extract_clean_rgba(img_path):
    img = Image.open(img_path)
    cropped = img.crop((4, 4, img.width - 4, img.height - 4))
    arr = np.array(cropped)
    is_fg = (arr[:, :, 0] < 225) | (arr[:, :, 1] < 225) | (arr[:, :, 2] < 225)
    labeled, _ = label(is_fg)
    sizes = np.bincount(labeled.ravel())
    sizes[0] = 0
    main_char_label = sizes.argmax()
    char_mask = (labeled == main_char_label)
    solid_mask = binary_fill_holes(char_mask)
    smooth_mask = gaussian_filter(solid_mask.astype(float), sigma=0.85)
    alpha = np.clip(smooth_mask * 255.0, 0, 255.0).astype(np.uint8)
    rgba = np.dstack((arr[:, :, 0], arr[:, :, 1], arr[:, :, 2], alpha))
    return Image.fromarray(rgba, 'RGBA')

def normalize_canvas(rgba_img):
    cropped = rgba_img.crop((min_x, min_y, max_x, max_y))
    scaled = cropped.resize((target_w, target_h), Image.Resampling.LANCZOS)
    canvas = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
    paste_x = (TARGET_SIZE - target_w) // 2
    paste_y = (TARGET_SIZE - target_h) // 2 + 10
    canvas.paste(scaled, (paste_x, paste_y), scaled)
    return canvas

print("Loading raw components...")
sad1_rgba = extract_clean_rgba(os.path.join(RAW_DIR, 'sad-1.png'))
wave1_rgba = extract_clean_rgba(os.path.join(RAW_DIR, 'wave-1.png'))
wave2_rgba = extract_clean_rgba(os.path.join(RAW_DIR, '17.png'))
thumbs1_rgba = extract_clean_rgba(os.path.join(RAW_DIR, 'thumbs-up-1.png'))
laugh1_rgba = extract_clean_rgba(os.path.join(RAW_DIR, 'laugh-1.png'))

# =====================================================================
# 1. SYNTHESIZE TRUE NEUTRAL IDLE (Arms Down + Smiling Head)
# =====================================================================
# Torso & arms down from sad-1 (below neck y=320)
# Head & collar from wave-1 (above neck y=330)
idle_base_raw = sad1_rgba.copy()
head_crop = wave1_rgba.crop((0, 0, wave1_rgba.width, 325))
idle_base_raw.paste(head_crop, (0, 0), head_crop)

idle_frame1 = normalize_canvas(idle_base_raw)

# Frame 2: Eye blink
idle_frame2 = idle_frame1.copy()
draw2 = ImageDraw.Draw(idle_frame2)
skin_color = (252, 226, 196, 255)
eye_line_color = (20, 24, 60, 255)

# Cover pupils with skin tone
draw2.rectangle([216, 172, 240, 198], fill=skin_color)
draw2.rectangle([268, 170, 292, 196], fill=skin_color)

# Draw gentle closed anime eye arcs
draw2.arc([216, 178, 240, 194], start=190, end=350, fill=eye_line_color, width=3)
draw2.arc([268, 176, 292, 192], start=190, end=350, fill=eye_line_color, width=3)
# Subtle lip movement: slightly relaxed line
draw2.arc([244, 218, 264, 228], start=10, end=170, fill=eye_line_color, width=2)

# Frame 3: Open eyes with slight breath recoil
idle_frame3 = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
scale_breath = idle_frame1.resize((int(TARGET_SIZE * 1.015), int(TARGET_SIZE * 1.015)), Image.Resampling.LANCZOS)
bx = (TARGET_SIZE - scale_breath.width) // 2
by = (TARGET_SIZE - scale_breath.height) // 2
idle_frame3.paste(scale_breath, (bx, by), scale_breath)

print("Synthesized 3-frame Idle with neutral arms-down standing pose & anime blink!")

# =====================================================================
# 2. SYNTHESIZE THINKING (Hand to Chin + Pondering Head Tilt)
# =====================================================================
# Start from idle standing body
# Hand from thumbs-up-1 (crop hand area x in [220..310], y in [350..450])
# Position hand touching the chin
thinking_raw1 = idle_base_raw.copy()

# Crop the bent arm and hand from thumbs1
hand_crop = thumbs1_rgba.crop((230, 360, 310, 460))
# Reposition hand to chin ~ (220, 270)
thinking_raw1.paste(hand_crop, (225, 265), hand_crop)

thinking_frame1 = normalize_canvas(thinking_raw1)
draw_t1 = ImageDraw.Draw(thinking_frame1)

# Modify gaze to look thoughtfully upward-right
draw_t1.rectangle([216, 172, 240, 198], fill=skin_color)
draw_t1.rectangle([268, 170, 292, 196], fill=skin_color)
draw_t1.ellipse([228, 174, 239, 188], fill=eye_line_color)
draw_t1.ellipse([279, 172, 290, 186], fill=eye_line_color)
draw_t1.ellipse([234, 176, 238, 180], fill=(255, 255, 255, 255))
draw_t1.ellipse([285, 174, 289, 178], fill=(255, 255, 255, 255))
# Thoughtful 'o' mouth
draw_t1.ellipse([250, 220, 258, 228], outline=eye_line_color, width=2)

# Frame 2: Subtle head tilt and eye shift
thinking_frame2 = thinking_frame1.rotate(2.5, resample=Image.Resampling.BICUBIC, center=(256, 256))

print("Synthesized 2-frame Thinking emotion with hand-to-chin pose!")

# =====================================================================
# 3. SYNTHESIZE PARTNER FOR LAUGH-1 (Smirk / Playful Chuckle)
# =====================================================================
smirk_frame1 = normalize_canvas(laugh1_rgba)

# Frame 2: Head nods slightly (1.5 degrees) and mouth broadens into a wider playful smirk
smirk_frame2 = smirk_frame1.copy()
draw_s2 = ImageDraw.Draw(smirk_frame2)
# Broaden mouth slightly
draw_s2.arc([240, 214, 268, 232], start=10, end=170, fill=eye_line_color, width=3)
# Slight eye wink on left eye (closed)
draw_s2.rectangle([214, 172, 238, 196], fill=skin_color)
draw_s2.arc([214, 178, 238, 194], start=190, end=350, fill=eye_line_color, width=3)
smirk_frame2 = smirk_frame2.rotate(-1.5, resample=Image.Resampling.BICUBIC, center=(256, 256))

print("Synthesized 2-frame Smirk/Chuckle emotion pairing laugh-1 with a playful wink frame!")

# =====================================================================
# 4. SAVE NEW ASSETS AND UPDATE MANIFEST
# =====================================================================
frames_dir = os.path.join(OUT_DIR, 'frames')
ib_dir = os.path.join(OUT_DIR, 'frames_interpolated')

# Save Idle frames
idle_frame1.save(os.path.join(frames_dir, 'idle-1.png'), 'PNG')
idle_frame2.save(os.path.join(frames_dir, 'idle-2.png'), 'PNG')
idle_frame3.save(os.path.join(frames_dir, 'idle-3.png'), 'PNG')

# Save Thinking frames
thinking_frame1.save(os.path.join(frames_dir, 'thinking-1.png'), 'PNG')
thinking_frame2.save(os.path.join(frames_dir, 'thinking-2.png'), 'PNG')

# Save Smirk frames
smirk_frame1.save(os.path.join(frames_dir, 'smirk-1.png'), 'PNG')
smirk_frame2.save(os.path.join(frames_dir, 'smirk-2.png'), 'PNG')

# Save Animated WebPs
idle_frame1.save(
    os.path.join(OUT_DIR, 'idle.webp'),
    format='WEBP',
    save_all=True,
    append_images=[idle_frame2, idle_frame3],
    duration=[1200, 180, 800],
    loop=0,
    transparency=0,
    disposal=2
)

thinking_frame1.save(
    os.path.join(OUT_DIR, 'thinking.webp'),
    format='WEBP',
    save_all=True,
    append_images=[thinking_frame2],
    duration=[500, 600],
    loop=0,
    transparency=0,
    disposal=2
)

smirk_frame1.save(
    os.path.join(OUT_DIR, 'smirk.webp'),
    format='WEBP',
    save_all=True,
    append_images=[smirk_frame2],
    duration=[450, 450],
    loop=0,
    transparency=0,
    disposal=2
)

# Read existing manifest and update with the newly synthesized emotions
with open(os.path.join(OUT_DIR, 'manifest.json'), 'r') as mf:
    manifest_data = json.load(mf)

manifest_data["emotions"]["idle"] = {
    "label": "Idle (Neutral Standing)",
    "animated_webp": "/assets/xiam/emotions/v2/idle.webp",
    "animated_webp_smooth": "/assets/xiam/emotions/v2/idle.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/idle-1.png",
        "/assets/xiam/emotions/v2/frames/idle-2.png",
        "/assets/xiam/emotions/v2/frames/idle-3.png"
    ],
    "interpolated_frames": [
        "/assets/xiam/emotions/v2/frames/idle-1.png",
        "/assets/xiam/emotions/v2/frames/idle-2.png",
        "/assets/xiam/emotions/v2/frames/idle-3.png"
    ],
    "durations_ms": [1200, 180, 800]
}

manifest_data["emotions"]["thinking"] = {
    "label": "Thinking (Hand on Chin)",
    "animated_webp": "/assets/xiam/emotions/v2/thinking.webp",
    "animated_webp_smooth": "/assets/xiam/emotions/v2/thinking.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/thinking-1.png",
        "/assets/xiam/emotions/v2/frames/thinking-2.png"
    ],
    "interpolated_frames": [
        "/assets/xiam/emotions/v2/frames/thinking-1.png",
        "/assets/xiam/emotions/v2/frames/thinking-2.png"
    ],
    "durations_ms": [500, 600]
}

manifest_data["emotions"]["smirk"] = {
    "label": "Smirk / Playful (laugh-1 paired)",
    "animated_webp": "/assets/xiam/emotions/v2/smirk.webp",
    "animated_webp_smooth": "/assets/xiam/emotions/v2/smirk.webp",
    "keyframes": [
        "/assets/xiam/emotions/v2/frames/smirk-1.png",
        "/assets/xiam/emotions/v2/frames/smirk-2.png"
    ],
    "interpolated_frames": [
        "/assets/xiam/emotions/v2/frames/smirk-1.png",
        "/assets/xiam/emotions/v2/frames/smirk-2.png"
    ],
    "durations_ms": [450, 450]
}

with open(os.path.join(OUT_DIR, 'manifest.json'), 'w') as mf:
    json.dump(manifest_data, mf, indent=2)

print("SUCCESS: All three custom assets synthesized and manifest updated!")
