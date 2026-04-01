import os
import zipfile
from PIL import Image

# ================================================
# KONFIGURASI
# ================================================
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

def generate_label_file(img_path, label_path, class_id):
    """Generate file .txt YOLO format"""
    # Bbox seluruh gambar dengan margin 5%
    margin   = 0.05
    x_center = 0.5
    y_center = 0.5
    width    = 1.0 - (margin * 2)
    height   = 1.0 - (margin * 2)

    with open(label_path, "w") as f:
        f.write(f"{class_id} {x_center:.6f} {y_center:.6f} {width:.6f} {height:.6f}\n")

def process_split(split_name):
    img_dir = os.path.join(DATASET_DIR, "images", split_name)
    lbl_dir = os.path.join(DATASET_DIR, "labels", split_name)
    os.makedirs(lbl_dir, exist_ok=True)

    files = [
        f for f in os.listdir(img_dir)
        if f.lower().endswith(('.jpg', '.jpeg', '.png', '.webp'))
    ]

    print(f"\n{'='*50}")
    print(f"Split: {split_name} ({len(files)} foto)")
    print(f"{'='*50}")

    ok = skip = 0
    for i, filename in enumerate(files, 1):
        label = get_label_from_filename(filename)

        if label is None:
            print(f"  [{i}/{len(files)}] SKIP {filename}")
            skip += 1
            continue

        class_id   = CLASS_MAP[label]
        img_path   = os.path.join(img_dir, filename)
        label_name = os.path.splitext(filename)[0] + ".txt"
        label_path = os.path.join(lbl_dir, label_name)

        generate_label_file(img_path, label_path, class_id)
        print(f"  [{i}/{len(files)}] ✅ {filename} → {label} (class {class_id})")
        ok += 1

    print(f"Hasil: {ok} label dibuat, {skip} skip")
    return ok

def make_zip():
    """Zip seluruh dataset untuk upload ke Roboflow"""
    zip_path = "dataset_roboflow.zip"

    print(f"\nMembuat zip: {zip_path}")
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for split in ["train", "val"]:
            # images
            img_dir = os.path.join(DATASET_DIR, "images", split)
            for fname in os.listdir(img_dir):
                if fname.lower().endswith(('.jpg', '.jpeg', '.png', '.webp')):
                    zf.write(
                        os.path.join(img_dir, fname),
                        os.path.join("images", split, fname)
                    )
            # labels
            lbl_dir = os.path.join(DATASET_DIR, "labels", split)
            for fname in os.listdir(lbl_dir):
                if fname.endswith(".txt"):
                    zf.write(
                        os.path.join(lbl_dir, fname),
                        os.path.join("labels", split, fname)
                    )
        # data.yaml
        yaml_path = os.path.join(DATASET_DIR, "data.yaml")
        if os.path.exists(yaml_path):
            zf.write(yaml_path, "data.yaml")

    size_mb = os.path.getsize(zip_path) / (1024 * 1024)
    print(f"✅ Zip selesai: {zip_path} ({size_mb:.1f} MB)")
    return zip_path

def main():
    print("GENERATE LABEL YOLO + ZIP UNTUK ROBOFLOW")

    total = 0
    total += process_split("train")
    total += process_split("val")

    zip_path = make_zip()

    print(f"""
{'='*50}
SELESAI! {total} label file dibuat.

Langkah selanjutnya:
1. Buka app.roboflow.com/kuros-workspace/menu-detector
2. Klik Upload Data
3. Upload file: {zip_path}
4. Roboflow otomatis baca label dari file .txt
5. Generate Dataset → Export YOLOv8 → Training!
{'='*50}
""")

if __name__ == "__main__":
    main()
