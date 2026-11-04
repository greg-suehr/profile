# Dockerfile for Katzen OCR Service
FROM python:3.11-slim

# Install system dependencies for Tesseract, OpenCV, and PDF processing
RUN apt-get update && apt-get install -y \
    tesseract-ocr \
    tesseract-ocr-eng \
    libtesseract-dev \
    # OpenCV dependencies (libgl1-mesa-glx no longer exists in current Debian)
    libgl1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    # PDF processing
    poppler-utils \
    ghostscript \
    # Build tools (needed for some Python packages)
    gcc \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy requirements and install Python packages
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY ocr_service.py .

# Run as an unprivileged user
RUN useradd --system --no-create-home --uid 10001 ocr
USER ocr

# Health check (stdlib only; `requests` is not installed)
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:5005/health', timeout=5)"

# Container-network port only. Do not publish it to the host.
EXPOSE 5005

# Run the service
CMD ["uvicorn", "ocr_service:app", "--host", "0.0.0.0", "--port", "5005", "--workers", "2"]
