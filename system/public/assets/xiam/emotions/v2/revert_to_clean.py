import os
import json
import numpy as np
from PIL import Image
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

def normalize_canvas(rgba_img):
    cropped = rgba_img.crop((min_x, min_y, max_x, max_y))
    scaled = cropped.resize((target_w, target_h), Image.Resampling.LANCZOS)
    canvas = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
    paste_x = (TARGET_SIZE - target_w) // 2
    paste_y = (TARGET_SIZE - target_h) // 2 + 10
    canvas.paste(scaled, (paste_x, paste_y), scaled)
    return canvas

# Only real, authentic artwork from user upload
CLEAN_MAP = {
    'hi': ['wave-1.png', '17.png'],
    'thumbs-up': ['thumbs-up-1.png', 'thumbs-up-2.png'],
    'celebrating': ['excited-1.png', 'excited-2.png'],
    'star-eyes': ['happy-star-1.png', 'happy-star-2.png'],
    'laughing': ['laugh-2.png', 'laugh-3.png'], # laugh-1 kept in unpaired
    'shock': ['shocked-1.png', 'shocked-2.png'],
    'sad': ['sad-1.png', 'sad-2.png'],
    'angry': ['angry-1.png', 'angry-2.png'],
}

normalized = {}
for emotion, fns in CLEAN_MAP.items():
    normalized[emotion] = [normalize_canvas(extract_clean_rgba(os.path.join(RAW_DIR, fn))) for fn in fns]

# For idle, use the authentic clean resting pose from wave-1 (hi[0]) without any programmatic drawing
# Just authentic natural 1-frame or authentic 2-frame
idle_real_1 = normalized['hi'][0].copy()
idle_real_2 = normalized['hi'][1].copy()
normalized['idle'] = [idle_real_1, idle_real_2]

# Remove synthesized files
for bad in ['thinking-1.png', 'thinking-2.png', 'smirk-1.png', 'smirk-2.png', 'idle-3.png']:
    p = os.path.join(OUT_DIR, 'frames', bad)
    if os.path.exists(p):
        os.remove(p)

for bad_webp in ['thinking.webp', 'smirk.webp']:
    p = os.path.join(OUT_DIR, bad_webp)
    if os.path.exists(p):
        os.remove(p)

frames_dir = os.path.join(OUT_DIR, 'frames')
manifest_data = {
    "version": "v2",
    "target_size": TARGET_SIZE,
    "emotions": {}
}

for emotion, frames in normalized.items():
    fn_list = []
    for idx, frame in enumerate(frames):
        fn = f"{emotion}-{idx+1}.png"
        frame.save(os.path.join(frames_dir, fn), 'PNG')
        fn_list.append(f"/assets/xiam/emotions/v2/frames/{fn}")

    webp_path = os.path.join(OUT_DIR, f"{emotion}.webp")
    frames[0].save(
        webp_path,
        format='WEBP',
        save_all=True,
        append_images=frames[1:],
        duration=[450, 450],
        loop=0,
        transparency=0,
        disposal=2
    )

    manifest_data["emotions"][emotion] = {
        "label": emotion.replace('-', ' ').title(),
        "animated_webp": f"/assets/xiam/emotions/v2/{emotion}.webp",
        "keyframes": fn_list,
        "interpolated_frames": fn_list,
        "durations_ms": [450, 450]
    }

with open(os.path.join(OUT_DIR, 'manifest.json'), 'w') as mf:
    json.dump(manifest_data, mf, indent=2)

print("Reverted to 100% authentic artist assets. Removed all fake composites.")
