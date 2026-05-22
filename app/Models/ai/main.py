"""
AI Vision Server - ONNX Food Detection
=====================================
Load model ONNX per bisnis dari folder model/<model_key>/.
Jalankan: uvicorn main:app --host 0.0.0.0 --port 8000 --reload
"""

from __future__ import annotations

import json
from pathlib import Path

import cv2
import numpy as np
from fastapi import FastAPI, File, Form, UploadFile
from fastapi.responses import JSONResponse
from ultralytics import YOLO

app = FastAPI(title="AI Food Scanner", version="3.0.0")

BASE_DIR = Path(__file__).resolve().parent
CONFIG_DIR = BASE_DIR / "config"
MODEL_DIR = BASE_DIR / "model"

MODEL_CACHE: dict[str, YOLO] = {}
BUSINESS_CONFIG: dict[str, dict] = {}


def normalize_display_name(label: str) -> str:
    return label.replace("_", " ").title()


def load_all_configs() -> None:
    BUSINESS_CONFIG.clear()

    if not CONFIG_DIR.exists():
        print(f"[WARNING] Folder config tidak ditemukan: {CONFIG_DIR}")
        return

    for config_file in CONFIG_DIR.glob("*.json"):
        business_key = config_file.stem
        try:
            with open(config_file, encoding="utf-8") as f:
                data = json.load(f)

            items = data.get("items", [])
            display_names = {
                item["label"]: item.get("display_name", normalize_display_name(item["label"]))
                for item in items
                if item.get("label")
            }

            BUSINESS_CONFIG[business_key] = {
                "business_name": data.get("business_name", business_key),
                "display_names": display_names,
            }
            print(f"[OK] Config '{business_key}' loaded ({len(display_names)} display names)")
        except Exception as e:
            print(f"[ERROR] Gagal load config '{business_key}': {e}")


def get_model_paths(model_key: str) -> tuple[Path, Path]:
    business_model_dir = MODEL_DIR / model_key
    return business_model_dir / "best.onnx", business_model_dir / "classes.txt"


def get_class_names(model_key: str) -> list[str] | None:
    _, classes_path = get_model_paths(model_key)
    if not classes_path.exists():
        return None

    class_names = [
        line.strip()
        for line in classes_path.read_text(encoding="utf-8").splitlines()
        if line.strip()
    ]
    return class_names or None


def get_display_name(model_key: str, label: str) -> str:
    config = BUSINESS_CONFIG.get(model_key, {})
    display_names = config.get("display_names", {})
    return display_names.get(label, normalize_display_name(label))


def list_available_models() -> list[str]:
    if not MODEL_DIR.exists():
        return []

    available: list[str] = []
    for model_path in MODEL_DIR.iterdir():
        if not model_path.is_dir():
            continue

        best_onnx = model_path / "best.onnx"
        classes_txt = model_path / "classes.txt"
        if best_onnx.exists() and classes_txt.exists():
            available.append(model_path.name)

    return sorted(available)


def get_model(model_key: str) -> YOLO | None:
    if model_key in MODEL_CACHE:
        return MODEL_CACHE[model_key]

    model_path, classes_path = get_model_paths(model_key)
    if not model_path.exists() or not classes_path.exists():
        return None

    print(f"[BOOT] Loading ONNX model for '{model_key}' from {model_path}")
    model = YOLO(str(model_path))
    MODEL_CACHE[model_key] = model
    print(f"[OK] Model '{model_key}' loaded")
    return model


def detect_with_onnx(
    model_key: str,
    img_bgr: np.ndarray,
    conf_threshold: float = 0.1,
) -> list[dict]:
    model = get_model(model_key)
    if model is None:
        raise FileNotFoundError(f"Model '{model_key}' tidak ditemukan")

    class_names = get_class_names(model_key)
    if not class_names:
        raise FileNotFoundError(f"classes.txt untuk '{model_key}' tidak ditemukan")

    results = model.predict(source=img_bgr, conf=conf_threshold, verbose=False)
    result = results[0]
    boxes = result.boxes

    detections: list[dict] = []
    if boxes is None:
        return detections

    for box in boxes:
        x1, y1, x2, y2 = box.xyxy[0].tolist()
        cls_id = int(box.cls[0])
        confidence = float(box.conf[0])

        if cls_id < 0 or cls_id >= len(class_names):
            label = str(cls_id)
        else:
            label = class_names[cls_id]

        detections.append(
            {
                "label": label,
                "display_name": get_display_name(model_key, label),
                "label_id": cls_id,
                "confidence": round(confidence, 4),
                "bbox": {
                    "x1": round(x1, 2),
                    "y1": round(y1, 2),
                    "x2": round(x2, 2),
                    "y2": round(y2, 2),
                },
            }
        )

    detections.sort(key=lambda item: item["confidence"], reverse=True)
    return detections


load_all_configs()


@app.post("/detect")
async def detect(
    file: UploadFile = File(...),
    model_key: str = Form(...),
    confidence: float = Form(default=0.1),
):
    if model_key not in list_available_models():
        return JSONResponse(
            status_code=404,
            content={
                "error": f"Model '{model_key}' tidak ditemukan",
                "available": list_available_models(),
            },
        )

    contents = await file.read()
    if not contents:
        return JSONResponse(status_code=400, content={"error": "File kosong"})

    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if img is None:
        return JSONResponse(status_code=400, content={"error": "Gambar tidak valid"})

    try:
        detections = detect_with_onnx(
            model_key=model_key,
            img_bgr=img,
            conf_threshold=confidence,
        )
    except FileNotFoundError as e:
        return JSONResponse(status_code=404, content={"error": str(e)})
    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})

    return {
        "success": True,
        "model_key": model_key,
        "total_detected": len(detections),
        "detected_items": list(dict.fromkeys(d["label"] for d in detections)),
        "detections": detections,
    }


@app.get("/health")
def health():
    return {
        "status": "ok",
        "available_models": list_available_models(),
        "loaded_models": sorted(MODEL_CACHE.keys()),
    }


@app.get("/config/{model_key}")
def get_config(model_key: str):
    class_names = get_class_names(model_key)
    if not class_names:
        return JSONResponse(status_code=404, content={"error": "Model/classes tidak ditemukan"})

    return {
        "model_key": model_key,
        "business_name": BUSINESS_CONFIG.get(model_key, {}).get("business_name", model_key),
        "items": [
            {
                "label": label,
                "display_name": get_display_name(model_key, label),
            }
            for label in class_names
        ],
    }


@app.post("/reload")
def reload_configs():
    load_all_configs()
    MODEL_CACHE.clear()
    return {
        "status": "reloaded",
        "available_models": list_available_models(),
    }
