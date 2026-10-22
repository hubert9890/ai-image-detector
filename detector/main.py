from io import BytesIO

from fastapi import FastAPI, File, HTTPException, UploadFile
from PIL import Image, UnidentifiedImageError

app = FastAPI(title="AI Image Detector")


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/predict")
async def predict(file: UploadFile = File(...)):
    allowed_types = {
        "image/jpeg",
        "image/png",
        "image/webp",
        "image/jpg",
    }

    if file.content_type not in allowed_types:
        raise HTTPException(
            status_code=400,
            detail="Only JPEG, PNG, and WebP images are supported.",
        )

    data = await file.read()

    # Limit request payload to approximately 10 MB.
    if len(data) > 10 * 1024 * 1024:
        raise HTTPException(status_code=413, detail="Image is too large.")

    try:
        image = Image.open(BytesIO(data))
        image.verify()
    except UnidentifiedImageError:
        raise HTTPException(status_code=400, detail="Invalid image.")
    except Exception:
        raise HTTPException(status_code=400, detail="Could not read image.")

    # Replace this section with the actual ML model.
    #
    # This is only a placeholder response and must not be used
    # as a real detector.
    return {
        "label": "unknown",
        "is_ai_generated": False,
        "confidence": 0.0,
        "message": "Model not installed yet."
    }

    from transformers import pipeline

    pipe = pipeline("image-classification", model="umm-maybe/AI-image-detector")
    pipe("https://huggingface.co/datasets/huggingface/documentation-images/resolve/main/hub/parrots.png")
