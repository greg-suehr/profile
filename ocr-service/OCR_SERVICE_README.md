# Katzen OCR Service

FastAPI-based microservice for receipt and invoice OCR processing using Tesseract 5.

## Quick Start

### Using Docker (Recommended)

```bash
# Build the image
docker build -t katzen-ocr-service -f Dockerfile .

# Run the container
docker run -d -p 5005:5005 --name katzen-ocr katzen-ocr-service

# Check health
curl http://localhost:5005/health
```

### Local Development

```bash
# Install system dependencies (Ubuntu/Debian)
sudo apt-get install -y tesseract-ocr tesseract-ocr-eng libtesseract-dev \
    libgl1-mesa-glx libglib2.0-0 poppler-utils ghostscript

# Install Python packages
pip install -r requirements.txt --break-system-packages

# Run the service
python ocr_service.py
```

## API Endpoints

### Health Check

```bash
GET /health
```

Response:
```json
{
  "status": "healthy",
  "engine": "tesseract5"
}
```

### OCR Receipt

```bash
POST /ocr/receipt
Content-Type: multipart/form-data
```

Request:
```bash
curl -X POST http://localhost:5005/ocr/receipt \
  -F "file=@receipt.jpg"
```

Response:
```json
{
  "pages": [
    {
      "width": 1728,
      "height": 2336,
      "words": [
        {
          "text": "SUBTOTAL",
          "x": 126,
          "y": 2010,
          "w": 210,
          "h": 34,
          "conf": 88
        }
      ],
      "lines": [
        {
          "text": "BAGELS 6CT  4.99",
          "bbox": [110, 920, 1510, 960],
          "avg_conf": 86
        }
      ],
      "orientation_deg": 0
    }
  ],
  "engine": "tesseract5",
  "avg_conf": 84
}
```

## Testing

### Basic Test

```bash
# Test with provided script
python test_ocr.py

# Test with your own image
python test_ocr.py /path/to/receipt.jpg
```

### Manual Test

```bash
# Upload a receipt
curl -X POST http://localhost:5005/ocr/receipt \
  -F "file=@sample_receipt.jpg" \
  | jq '.'
```

## Configuration

Environment variables:

- `PORT` - Service port (default: 5005)
- `WORKERS` - Number of worker processes (default: 2)
- `LOG_LEVEL` - Logging level (default: info)

## Architecture

### Image Processing Pipeline

1. **Decode** - Convert uploaded bytes to OpenCV image
2. **Grayscale** - Convert to single channel
3. **Denoise** - Remove noise with fastNlMeans
4. **Deskew** - Detect and correct rotation
5. **Binarize** - Adaptive thresholding
6. **Enhance** - CLAHE contrast enhancement
7. **OCR** - Tesseract extraction
8. **Structure** - Organize into words and lines

### Performance

- **Typical processing time**: 0.5-3 seconds per page
- **Memory usage**: ~200-500MB per worker
- **Throughput**: 5-10 receipts/second (single worker)

## Supported File Types

- JPEG (.jpg, .jpeg)
- PNG (.png)
- HEIC/HEIF (.heic, .heif)
- PDF (.pdf)

## Accuracy Tips

For best OCR results:

1. **Good lighting** - Avoid shadows and glare
2. **Flat surface** - Minimize wrinkles and curves
3. **High resolution** - At least 300 DPI for scans
4. **Full capture** - Include entire receipt from top to bottom
5. **Clean image** - Remove backgrounds and borders

## Troubleshooting

### Low Confidence Scores

If average confidence < 75%, try:

1. Checking image quality (lighting, focus)
2. Adjusting PSM mode in `ocr_service.py`
3. Using higher resolution image
4. Enabling PaddleOCR fallback for difficult cases

### Memory Issues

If running out of memory:

```bash
# Reduce workers
uvicorn ocr_service:app --workers 1

# Or set memory limit in Docker
docker run --memory=1g katzen-ocr-service
```

### Tesseract Not Found

```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr tesseract-ocr-eng

# macOS
brew install tesseract

# Verify installation
tesseract --version
```

## Development

### Running Tests

```bash
pytest tests/
```

### Hot Reload

```bash
uvicorn ocr_service:app --reload --host 0.0.0.0 --port 5005
```

### Adding Languages

```bash
# Install additional language packs
sudo apt-get install tesseract-ocr-fra  # French
sudo apt-get install tesseract-ocr-spa  # Spanish

# Update Dockerfile
RUN apt-get install -y tesseract-ocr-fra tesseract-ocr-spa
```

### Custom Preprocessing

Edit `preprocess_image()` in `ocr_service.py`:

```python
def preprocess_image(img: np.ndarray) -> np.ndarray:
    # Add your custom preprocessing steps
    pass
```

## Production Deployment

### Docker Compose

```yaml
ocr-service:
  image: katzen-ocr-service:latest
  deploy:
    replicas: 2
    resources:
      limits:
        cpus: '2'
        memory: 2G
```

### Kubernetes

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: ocr-service
spec:
  replicas: 3
  template:
    spec:
      containers:
      - name: ocr
        image: katzen-ocr-service:latest
        resources:
          limits:
            cpu: "2"
            memory: "2Gi"
```

## Monitoring

### Health Checks

```bash
# Docker healthcheck
HEALTHCHECK CMD curl -f http://localhost:5005/health

# Kubernetes liveness probe
livenessProbe:
  httpGet:
    path: /health
    port: 5005
  initialDelaySeconds: 10
  periodSeconds: 30
```

### Metrics

Add Prometheus metrics:

```python
from prometheus_client import Counter, Histogram

ocr_requests = Counter('ocr_requests_total', 'Total OCR requests')
ocr_duration = Histogram('ocr_duration_seconds', 'OCR processing duration')
```

## Optional: PaddleOCR Integration

For improved accuracy on difficult receipts:

```bash
# Uncomment in requirements.txt
paddlepaddle==2.6.0
paddleocr==2.7.0.3

# Add fallback in ocr_service.py
from paddleocr import PaddleOCR

paddle_ocr = PaddleOCR(use_angle_cls=True, lang='en')

def process_with_paddle(img_bytes):
    result = paddle_ocr.ocr(img_bytes, cls=True)
    # Parse result...
```

## License

Part of the Katzen project.

## Support

For issues or questions:
1. Check logs: `docker logs katzen-ocr`
2. Verify Tesseract: `tesseract --version`
3. Test with sample image: `python test_ocr.py`
