import os
import json
import numpy as np
from PIL import Image, ImageChops
from scipy.ndimage import label, binary_fill_holes, gaussian_filter

RAW_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2/raw'
OUT_DIR = '/home/user/Projects/NEXIAM/system/public/assets/xiam/emotions/v2'
TARGET_SIZE = 512

RAW_MAP = {
    'angry': ['angry-1.png', 'angry-2.png'],
    'celebrating': ['excited-1.png', 'excited-2.png'],
    'star-eyes': ['happy-star-1.png', 'happy-star-2.png'],
    'laughing': ['laugh-1.png', 'laugh-2.png', 'laugh-3.png'],
    'sad': ['sad-1.png', 'sad-2.png'],
    'shock': ['shocked-1.png', 'shocked-2.png'],
    'thumbs-up': ['thumbs-up-1.png', 'thumbs-up-2.png'],
    'hi': ['wave-1.png', '17.png'],
}

def extract_clean_rgba(img_path):
    img = Image.open(img_path)
    # Trim 4px to eliminate any edge scanning/screenshot lines
    cropped = img.crop((4, 4, img.width - 4, img.height - 4))
    arr = np.array(cropped)
    
    # Non-background: pixels darker than off-white or with chromatic saturation
    is_fg = (arr[:, :, 0] < 225) | (arr[:, :, 1] < 225) | (arr[:, :, 2] < 225)
    labeled, num_features = label(is_fg)
    sizes = np.bincount(labeled.ravel())
    sizes[0] = 0
    
    # Retain main character component
    main_char_label = sizes.argmax()
    char_mask = (labeled == main_char_label)
    
    # Fill interior holes (eyes, teeth, pupil highlights, shirt whites)
    solid_mask = binary_fill_holes(char_mask)
    
    # Subpixel anti-aliasing along the boundary
    smooth_mask = gaussian_filter(solid_mask.astype(float), sigma=0.85)
    alpha = np.clip(smooth_mask * 255.0, 0, 255.0).astype(np.uint8)
    
    rgba = np.dstack((arr[:, :, 0], arr[:, :, 1], arr[:, :, 2], alpha))
    return Image.fromarray(rgba, 'RGBA')

print("1. Extracting all raw images...")
extracted = {}
for emotion, filenames in RAW_MAP.items():
    extracted[emotion] = []
    for fn in filenames:
        p = os.path.join(RAW_DIR, fn)
        rgba = extract_clean_rgba(p)
        extracted[emotion].append(rgba)
        print(f"  {emotion} - {fn}: extracted ({rgba.width}x{rgba.height})")

# Determine a global reference bounding box so the character doesn't bounce/jump arbitrarily across emotions
# Character is grounded at feet/bottom
all_bboxes = []
for emotion, frames in extracted.items():
    for f in frames:
        bbox = f.getbbox()
        if bbox:
            all_bboxes.append(bbox)

min_x = min(b[0] for b in all_bboxes)
min_y = min(b[1] for b in all_bboxes)
max_x = max(b[2] for b in all_bboxes)
max_y = max(b[3] for b in all_bboxes)

ref_w = max_x - min_x
ref_h = max_y - min_y
print(f"Global character bounds: x=[{min_x}..{max_x}] w={ref_w}, y=[{min_y}..{max_y}] h={ref_h}")

# We fit the global character box into TARGET_SIZE with 10% padding
target_h = int(TARGET_SIZE * 0.85)
scale = target_h / float(ref_h)
target_w = int(ref_w * scale)
print(f"Global scale factor: {scale:.4f} -> scaled size ({target_w}x{target_h})")

def normalize_canvas(rgba_img):
    # Crop to the global reference box
    cropped = rgba_img.crop((min_x, min_y, max_x, max_y))
    # Scale with high-quality Lanczos resampling
    scaled = cropped.resize((target_w, target_h), Image.Resampling.LANCZOS)
    
    # Place on 512x512 transparent canvas
    canvas = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
    paste_x = (TARGET_SIZE - target_w) // 2
    paste_y = (TARGET_SIZE - target_h) // 2 + 10 # slightly grounded
    canvas.paste(scaled, (paste_x, paste_y), scaled)
    return canvas

print("2. Normalizing and centering on 512x512 canvases...")
normalized = {}
for emotion, frames in extracted.items():
    normalized[emotion] = [normalize_canvas(f) for f in frames]

# 3. Create IDLE emotion:
# Idle 1: resting state from hi[0] (wave-1 has the subtle gentle smile)
# Idle 2: subtle gentle breathing frame (scaled by 1.015 and eyes resting)
idle_base = normalized['hi'][0].copy()
idle_breathe = Image.new('RGBA', (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
# Scale slightly for gentle breathing
breathe_scaled = idle_base.resize((int(TARGET_SIZE * 1.018), int(TARGET_SIZE * 1.018)), Image.Resampling.LANCZOS)
bx = (TARGET_SIZE - breathe_scaled.width) // 2
by = (TARGET_SIZE - breathe_scaled.height) // 2
idle_breathe.paste(breathe_scaled, (bx, by), breathe_scaled)

normalized['idle'] = [idle_base, idle_breathe]
print("  Generated 'idle' emotion with subtle smile & breathing keyframes.")

# 4. Generate In-Between frames for all emotions
# For 2-frame emotions: frame 1, in-between (t=0.5), frame 2
# For 3-frame emotions: frame 1, in-between 1-2, frame 2, in-between 2-3, frame 3
all_with_inbetweens = {}
for emotion, frames in normalized.items():
    seq = []
    if len(frames) == 2:
        f1, f2 = frames[0], frames[1]
        mid = Image.blend(f1, f2, 0.5)
        seq = [f1, mid, f2, mid] # smooth 4-step loop
    elif len(frames) == 3:
        f1, f2, f3 = frames[0], frames[1], frames[2]
        mid1 = Image.blend(f1, f2, 0.5)
        mid2 = Image.blend(f2, f3, 0.5)
        seq = [f1, mid1, f2, mid2, f3, mid2, f2, mid1]
    all_with_inbetweens[emotion] = seq

# 5. Save all discrete PNG assets
frames_dir = os.path.join(OUT_DIR, 'frames')
os.makedirs(frames_dir, exist_ok=True)

manifest_data = {
    "version": "v2",
    "target_size": TARGET_SIZE,
    "emotions": {}
}

print("5. Saving PNG frames and animated WebPs...")
for emotion, frames in normalized.items():
    # Save standard keyframe PNGs
    fn_list = []
    for idx, frame in enumerate(frames):
        fn = f"{emotion}-{idx+1}.png"
        frame.save(os.path.join(frames_dir, fn), 'PNG')
        fn_list.append(f"/assets/xiam/emotions/v2/frames/{fn}")
    
    # Save in-between frames in separate subfolder
    ib_dir = os.path.join(OUT_DIR, 'frames_interpolated')
    os.makedirs(ib_dir, exist_ok=True)
    ib_fn_list = []
    for idx, frame in enumerate(all_with_inbetweens[emotion]):
        fn = f"{emotion}-ib-{idx+1}.png"
        frame.save(os.path.join(ib_dir, fn), 'PNG')
        ib_fn_list.append(f"/assets/xiam/emotions/v2/frames_interpolated/{fn}")

    # Generate Animated WebP (natural 2-frame vs interpolated)
    webp_path = os.path.join(OUT_DIR, f"{emotion}.webp")
    # Standard animated webp
    durations = [400, 450] if len(frames) == 2 else [350, 350, 450]
    frames[0].save(
        webp_path,
        format='WEBP',
        save_all=True,
        append_images=frames[1:],
        duration=durations,
        loop=0,
        transparency=0,
        disposal=2
    )

    # Interpolated animated webp
    webp_smooth_path = os.path.join(OUT_DIR, f"{emotion}-smooth.webp")
    ib_durations = [200] * len(all_with_inbetweens[emotion])
    all_with_inbetweens[emotion][0].save(
        webp_smooth_path,
        format='WEBP',
        save_all=True,
        append_images=all_with_inbetweens[emotion][1:],
        duration=ib_durations,
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
        "durations_ms": [400, 450] if len(frames) == 2 else [350, 350, 450]
    }
    print(f"  Processed {emotion:12}: keyframes={len(fn_list)}, inbetweens={len(ib_fn_list)}, webp={emotion}.webp")

manifest_path = os.path.join(OUT_DIR, 'manifest.json')
with open(manifest_path, 'w') as mf:
    json.dump(manifest_data, mf, indent=2)

print(f"Saved manifest: {manifest_path}")
print("ALL ASSETS PROCESSED SUCCESSFULLY!")
