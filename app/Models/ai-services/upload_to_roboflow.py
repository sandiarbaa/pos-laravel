"""
Upload Dataset YOLO ke Roboflow
================================
pip install roboflow
python upload_to_roboflow.py
"""

import os
import roboflow

# ================================================
API_KEY     = "IsVz0HmwwXQNPjwRWC9Y"
WORKSPACE   = "kuros-workspace"
PROJECT     = "menu-detector-v4"
DATASET_DIR = "dataset"
# ================================================

rf      = roboflow.Roboflow(api_key=API_KEY)
project = rf.workspace(WORKSPACE).project(PROJECT)

def upload_split(split):
    folder  = "val" if split == "valid" else split  # folder lokal
    img_dir = os.path.join(DATASET_DIR, "images", folder)  # ← folder
    lbl_dir = os.path.join(DATASET_DIR, "labels", folder)  # ← folder

    files = [
        f for f in os.listdir(img_dir)
        if f.lower().endswith(('.jpg', '.jpeg', '.png'))
    ]

    print(f"\n{'='*50}")
    print(f"Upload {split}: {len(files)} foto")
    print(f"{'='*50}")

    ok = fail = 0
    for i, fname in enumerate(files, 1):
        img_path = os.path.join(img_dir, fname)
        lbl_path = os.path.join(lbl_dir, os.path.splitext(fname)[0] + ".txt")

        try:
            if os.path.exists(lbl_path):
                project.upload(
                    image_path=img_path,
                    annotation_path=lbl_path,
                    annotation_labelmap={
                        "0": "nasi_goreng",
                        "1": "ayam_geprek",
                        "2": "mie_ayam",
                        "3": "es_teh",
                        "4": "jus_alpukat",
                        "5": "telur_ceplok",
                        "6": "ikan_goreng",
                    },
                    split=split,
                    num_retry_uploads=3,
                    batch_name=f"batch_{split}",
                )
                print(f"  [{i}/{len(files)}] ✅ {fname}")
                ok += 1
            else:
                print(f"  [{i}/{len(files)}] ⚠️  {fname} — skip")
        except Exception as e:
            print(f"  [{i}/{len(files)}] ❌ {fname} — {str(e)[:80]}")
            fail += 1

    print(f"\nHasil {split}: {ok} berhasil, {fail} gagal")
    return ok

def main():
    print("UPLOAD DATASET KE ROBOFLOW")
    print(f"Project: {WORKSPACE}/{PROJECT}")

    total  = 0
    total += upload_split("train")
    total += upload_split("valid")

    print(f"\n{'='*50}")
    print(f"SELESAI! Total: {total} foto terupload")
    print(f"{'='*50}")

if __name__ == "__main__":
    main()
