import os, uuid
from flask import Flask, request, jsonify
from ultralytics import YOLO
from PIL import Image

app = Flask(__name__)
UPLOAD_FOLDER = "uploads"
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

print("Loading model...")
model = YOLO("best_food_model.pt")
print(f"✅ Model loaded! Classes: {list(model.names.values())}")

@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "ok", "classes": list(model.names.values())})

@app.route('/detect', methods=['POST'])
def detect():
    if 'image' not in request.files:
        return jsonify({"error": "No image"}), 400

    file = request.files['image']
    conf = float(request.form.get('confidence', 0.35))

    filepath = os.path.join(UPLOAD_FOLDER, f"{uuid.uuid4().hex}.jpg")
    try:
        img = Image.open(file.stream).convert("RGB")
        img.save(filepath, "JPEG", quality=95)

        results = model(filepath, conf=conf, verbose=False)
        result  = results[0]
        boxes   = result.boxes

        detections = []
        if boxes is not None:
            for box in boxes:
                x1, y1, x2, y2 = box.xyxy[0].tolist()
                cls_id     = int(box.cls[0])
                confidence = float(box.conf[0])
                detections.append({
                    "label":       model.names[cls_id],
                    "label_id":    cls_id,
                    "confidence":  round(confidence, 4),
                    "bbox": {
                        "x1": round(x1, 2), "y1": round(y1, 2),
                        "x2": round(x2, 2), "y2": round(y2, 2),
                    }
                })

        detections.sort(key=lambda x: x['confidence'], reverse=True)

        return jsonify({
            "success":        True,
            "total_detected": len(detections),
            "detected_items": list(set(d['label'] for d in detections)),
            "detections":     detections,
        })

    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500
    finally:
        if os.path.exists(filepath):
            os.remove(filepath)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
