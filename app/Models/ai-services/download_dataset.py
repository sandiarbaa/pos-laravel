from icrawler.builtin import BingImageCrawler
import os, shutil, random

MENU_LIST = [
    {"folder": "nasi_goreng",  "keyword": "nasi goreng indonesia"},
    {"folder": "ayam_geprek",  "keyword": "ayam geprek indonesia"},
    {"folder": "mie_ayam",     "keyword": "mie ayam indonesia"},
    {"folder": "es_teh",       "keyword": "es teh manis indonesia"},
    {"folder": "jus_alpukat",  "keyword": "jus alpukat indonesia"},
]

JUMLAH_FOTO = 500
SPLIT_RATIO = 0.8
OUTPUT_DIR  = "dataset"
VALID_EXT   = ('.jpg', '.jpeg', '.png', '.webp')

def download_menu(folder, keyword, jumlah):
    tmp_path = os.path.join("_tmp", folder)
    os.makedirs(tmp_path, exist_ok=True)
    print(f'\nDownloading: {folder} ({jumlah} foto)')
    crawler = BingImageCrawler(
        storage={'root_dir': tmp_path},
        feeder_threads=2, parser_threads=2, downloader_threads=6,
    )
    crawler.crawl(keyword=keyword, max_num=jumlah, file_idx_offset=0)
    files = [f for f in os.listdir(tmp_path) if f.lower().endswith(VALID_EXT)]
    print(f'Terdownload: {len(files)} foto')
    return tmp_path, files

def organize(folder, tmp_path, files):
    random.shuffle(files)
    split_idx   = int(len(files) * SPLIT_RATIO)
    train_files = files[:split_idx]
    val_files   = files[split_idx:]
    for d in [
        os.path.join(OUTPUT_DIR, "images", "train"),
        os.path.join(OUTPUT_DIR, "images", "val"),
        os.path.join(OUTPUT_DIR, "labels", "train"),
        os.path.join(OUTPUT_DIR, "labels", "val"),
    ]:
        os.makedirs(d, exist_ok=True)
    def copy_files(file_list, dest):
        for i, fname in enumerate(file_list):
            ext = os.path.splitext(fname)[1].lower()
            shutil.copy2(os.path.join(tmp_path, fname),
                         os.path.join(dest, f'{folder}_{i+1:03d}{ext}'))
    copy_files(train_files, os.path.join(OUTPUT_DIR, "images", "train"))
    copy_files(val_files,   os.path.join(OUTPUT_DIR, "images", "val"))
    print(f'Train: {len(train_files)} | Val: {len(val_files)}')
    return len(train_files), len(val_files)

def make_yaml():
    names = [m["folder"] for m in MENU_LIST]
    with open(os.path.join(OUTPUT_DIR, "data.yaml"), "w") as f:
        f.write(f"path: ./dataset\ntrain: images/train\nval: images/val\nnc: {len(names)}\nnames: {names}\n")
    print("data.yaml dibuat!")

summary = []
for menu in MENU_LIST:
    tmp_path, files = download_menu(menu["folder"], menu["keyword"], JUMLAH_FOTO)
    train_c, val_c  = organize(menu["folder"], tmp_path, files)
    summary.append({"menu": menu["folder"], "train": train_c, "val": val_c})

make_yaml()
shutil.rmtree("_tmp", ignore_errors=True)

print("\n" + "="*50)
print(f"{'Menu':<20} {'Train':>8} {'Val':>8} {'Total':>8}")
print("-"*50)
grand = 0
for s in summary:
    t = s['train'] + s['val']
    grand += t
    print(f"{s['menu']:<20} {s['train']:>8} {s['val']:>8} {t:>8}")
print(f"{'TOTAL':<20} {'':>8} {'':>8} {grand:>8}")
print("="*50)
print(f"\nDataset siap di folder: dataset/")
