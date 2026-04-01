"""
Auto-Annotasi via Roboflow API (Fixed)
=======================================
pip install requests Pillow
python auto_annotate.py
"""

import os
import json
import requests
from PIL import Image

# ================================================
# KONFIGURASI
# ================================================
API_KEY   = "cvfZFht619BQFwkKDR7n"   # ganti dengan API key baru
PROJECT   = "menu-detector"
WORKSPACE = "kuros-workspace"

DATASET_DIR = "dataset"

CLASS_MAP = {
    "nasi_goreng": 0,
    "ayam_geprek": 1,
    "mie_ayam":    2,
    "es_teh":      3,
    "jus_alpukat": 4,
}
# ================================================

def get_label_from_filename(filename):
    name = os.path.splitext(filename)[0]
    for label in CLASS_MAP.keys():
        if name.startswith(label):
            return label
    return None

def get_image_size(img_path):
    img = Image.open(img_path)
    return img.size  # (width, height)

def build_annotation_json(filename, label, img_width, img_height):
    """
    Build annotation dalam format JSON yang diterima Roboflow
    """
    margin = 0.05
    x1 = int(img_width  * margin)
    y1 = int(img_height * margin)
    x2 = int(img_width  * (1 - margin))
    y2 = int(img_height * (1 - margin))

    annotation = {
        "image": {
            "width":    img_width,
            "height":   img_height,
            "filename": filename,
        },
        "annotations": [
            {
                "label":  label,
                "x":      x1,
                "y":      y1,
                "width":  x2 - x1,
                "height": y2 - y1,
            }
        ]
    }
    return json.dumps(annotation)

def upload_image_and_annotation(img_path, filename, split):
    label = get_label_from_filename(filename)
    if label is None:
        return "skip"

    img_width, img_height = get_image_size(img_path)
    annotation_json = build_annotation_json(filename, label, img_width, img_height)

    # Upload image + annotation sekaligus
    url = f"https://api.roboflow.com/dataset/{PROJECT}/upload"
    params = {
        "api_key":   API_KEY,
        "name":      filename,
        "split":     split,
        "overwrite": "true",
    }

    with open(img_path, "rb") as img_file:
        response = requests.post(
            url,
            params=params,
            files={
                "file":       (filename, img_file, "image/jpeg"),
                "annotation": ("annotation.json", annotation_json, "application/json"),
            }
        )

    if response.status_code == 200:
        return "ok"
    else:
        print(f"    Error: {response.text[:200]}")
        return "fail"

def process_folder(folder_path, split):
    files = [
        f for f in os.listdir(folder_path)
        if f.lower().endswith(('.jpg', '.jpeg', '.png', '.webp'))
    ]

    print(f"\n{'='*55}")
    print(f"Folder : {split} ({len(files)} foto)")
    print(f"{'='*55}")

    ok = skip = fail = 0
    for i, filename in enumerate(files, 1):
        img_path = os.path.join(folder_path, filename)
        result   = upload_image_and_annotation(img_path, filename, split)

        if result == "ok":
            label = get_label_from_filename(filename)
            print(f"  [{i}/{len(files)}] ✅ {filename} → {label}")
            ok += 1
        elif result == "skip":
            print(f"  [{i}/{len(files)}] ⏭  {filename} → label ga dikenali, skip")
            skip += 1
        else:
            print(f"  [{i}/{len(files)}] ❌ {filename} → gagal")
            fail += 1

    print(f"\nHasil: {ok} berhasil | {skip} skip | {fail} gagal")
    return ok

def main():
    print("AUTO ANNOTASI ROBOFLOW")
    print(f"Project : {WORKSPACE}/{PROJECT}")
    print(f"Classes : {list(CLASS_MAP.keys())}\n")

    train_dir = os.path.join(DATASET_DIR, "images", "train")
    val_dir   = os.path.join(DATASET_DIR, "images", "val")

    total  = 0
    total += process_folder(train_dir, "train")
    total += process_folder(val_dir,   "valid")

    print(f"\n{'='*55}")
    print(f"SELESAI! Total: {total} foto terannotasi")
    print(f"Cek: app.roboflow.com/{WORKSPACE}/{PROJECT}")
    print(f"{'='*55}")

if __name__ == "__main__":
    main()
