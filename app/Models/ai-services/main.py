from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
import onnxruntime as ort
import numpy as np
import cv2

app = FastAPI()

# COCO class names (80 kelas)
# COCO_CLASSES = [
#     "person","bicycle","car","motorcycle","airplane","bus","train","truck",
#     "boat","traffic light","fire hydrant","stop sign","parking meter","bench",
#     "bird","cat","dog","horse","sheep","cow","elephant","bear","zebra","giraffe",
#     "backpack","umbrella","handbag","tie","suitcase","frisbee","skis","snowboard",
#     "sports ball","kite","baseball bat","baseball glove","skateboard","surfboard",
#     "tennis racket","bottle","wine glass","cup","fork","knife","spoon","bowl",
#     "banana","apple","sandwich","orange","broccoli","carrot","hot dog","pizza",
#     "donut","cake","chair","couch","potted plant","bed","dining table","toilet",
#     "tv","laptop","mouse","remote","keyboard","cell phone","microwave","oven",
#     "toaster","sink","refrigerator","book","clock","vase","scissors","teddy bear",
#     "hair drier","toothbrush"
# ]
MENU_CLASSES = [
    "nasi_goreng",
    "ayam_geprek",
    "mie_ayam",
    "es_teh",
    "jus_alpukat",
]

# Load model sekali saat startup
session = ort.InferenceSession("model/best.onnx")

def preprocess(img: np.ndarray) -> np.ndarray:
    img = cv2.resize(img, (640, 640))
    img = img[:, :, ::-1]  # BGR → RGB
    img = img / 255.0
    img = np.transpose(img, (2, 0, 1))  # HWC → CHW
    img = np.expand_dims(img, axis=0).astype(np.float32)
    return img

def postprocess(outputs, conf_threshold=0.5):
    predictions = outputs[0]  # shape: [1, 9, 8400]
    predictions = predictions[0]  # [9, 8400]
    predictions = predictions.T    # [8400, 84]

    results = []
    for pred in predictions:
        x, y, w, h = pred[:4]
        class_scores = pred[4:]
        class_id = int(np.argmax(class_scores))
        confidence = float(class_scores[class_id])

        if confidence < conf_threshold:
            continue

        results.append({
            # "label": COCO_CLASSES[class_id],
            "label": MENU_CLASSES [class_id],
            "confidence": round(confidence, 3),
            "bbox": [float(x), float(y), float(w), float(h)]
        })

    # Deduplicate: ambil confidence tertinggi per label
    seen = {}
    for r in results:
        label = r["label"]
        if label not in seen or r["confidence"] > seen[label]["confidence"]:
            seen[label] = r

    return list(seen.values())

@app.post("/detect")
async def detect(file: UploadFile = File(...)):
    contents = await file.read()
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
