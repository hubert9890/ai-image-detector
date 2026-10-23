from io import BytesIO

from fastapi import FastAPI, File, HTTPException, UploadFile
from PIL import Image, UnidentifiedImageError
from transformers import pipeline

app = FastAPI(title="AI Image Detector")

pipez = pipeline(
    "image-classification", 
    model="umm-maybe/AI-image-detector",
    device_map="auto"
)

@app.get("/health")
def health():
    return {"status": "ok"}

@app.post("/predict")
async def predict(file: UploadFile = File(...)):  # noqa: B008
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
        raise HTTPException(
            status_code=413, 
            detail="Image is too large."
        )

    try:
        with Image.open(BytesIO(data)) as image:
            image.verify()
        with Image.open(BytesIO(data)) as image:
            rgb_image = image.convert("RGB")
    except (
        UnidentifiedImageError,
        OSError
    ):
        raise HTTPException(
            status_code=400, 
            detail="Invalid image."
        )
        
    result = pipez(rgb_image)

    print(result)
    return result
