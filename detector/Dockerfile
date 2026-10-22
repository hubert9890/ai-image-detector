FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV PYTHONUNBUFFERED=1
ENV PYTHONDONTWRITEBYTECODE=1
ENV HF_HOME=/models/huggingface \
    TRANSFORMERS_CACHE=/models/huggingface \
    HF_HUB_DISABLE_TELEMETRY=1

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        python3 \
        python3-pip \
        python3-venv \
        libjpeg-dev \
        zlib1g-dev \
        ca-certificates && \
    rm -rf /var/lib/apt/lists/*
    
RUN pip3 install \
        --no-cache-dir \
        --break-system-packages \
        torch \
        --index-url https://download.pytorch.org/whl/cpu

RUN pip3 install \
        --no-cache-dir \
        --break-system-packages \
        transformers \
        huggingface_hub \
        accelerate \
        safetensors \
        fastapi \
        "uvicorn[standard]" \
        python-multipart \
        pillow

WORKDIR /app

COPY requirements.txt .
COPY . /app

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]

