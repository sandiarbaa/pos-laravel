from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
import onnxruntime as ort
import numpy as np
import cv2
from pathlib import Path
from menu_config import CLASS_NAMES

app = FastAPI()

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "model" / "best.onnx"
CLASSES_PATH = BASE_DIR / "model" / "classes.txt"


def load_menu_classes() -> list[str]:
    if CLASSES_PATH.exists():
        classes = [line.strip() for line in CLASSES_PATH.read_text(encoding="utf-8").splitlines() if line.strip()]
        if classes:
            return classes
    return CLASS_NAMES


MENU_CLASSES = load_menu_classes()

session = ort.InferenceSession(str(MODEL_PATH))

def preprocess(img: np.ndarray) -> np.ndarray:
    img = cv2.resize(img, (320, 320))
    img = img[:, :, ::-1]
    img = img / 255.0
    img = np.transpose(img, (2, 0, 1))
    img = np.expand_dims(img, axis=0).astype(np.float32)
    return img

def postprocess(outputs, conf_threshold=0.2):
    predictions = outputs[0]
    predictions = predictions[0]
    predictions = predictions.T

    results = []
    for pred in predictions:
        x, y, w, h = pred[:4]
        class_scores = pred[4:]
        class_id = int(np.argmax(class_scores))
        confidence = float(class_scores[class_id])

        if confidence < conf_threshold:
            continue

        if class_id >= len(MENU_CLASSES):
            continue

        results.append({
            "label": MENU_CLASSES[class_id],
            "confidence": round(confidence, 3),
            "bbox": [float(x), float(y), float(w), float(h)]
        })

    seen = {}
    for r in results:
        label = r["label"]
        if label not in seen or r["confidence"] > seen[label]["confidence"]:
            seen[label] = r

    return list(seen.values())

@app.post("/detect")
async def detect(file: UploadFile = File(...)):
    contents = await file.read()

    if not contents:
        return JSONResponse(status_code=400, content={"error": "File kosong"})

    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

    if img is None:
        return JSONResponse(status_code=400, content={"error": "Invalid image"})

    input_tensor = preprocess(img)
    outputs = session.run(None, {"images": input_tensor})
    detections = postprocess(outputs)

    return {"detections": detections}

@app.get("/health")
def health():
    return {"status": "ok"}
