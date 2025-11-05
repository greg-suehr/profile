# Quick Reference Card - OCR Integration

## 🚀 Setup Commands

### Initial Setup
```bash
# 1. Create OCR service directory
mkdir ocr-service && cd ocr-service

# 2. Move files (copy from outputs directory):
# - ocr_service.py
# - requirements.txt  
# - Dockerfile (from Dockerfile.ocr)
# - test_ocr.py

# 3. Return to project root
cd ..
```

### Docker Deployment (Recommended)
```bash
# Build and start OCR service
docker-compose up -d --build ocr-service

# Check service is running
docker-compose ps ocr-service

# View logs
docker-compose logs -f ocr-service

# Restart service
docker-compose restart ocr-service

# Stop service
docker-compose stop ocr-service
```

### Local Development (No Docker)
```bash
# Install system dependencies (Ubuntu/Debian)
sudo apt-get install -y tesseract-ocr tesseract-ocr-eng \
    libtesseract-dev libgl1-mesa-glx libglib2.0-0 \
    poppler-utils ghostscript

# Install Python packages
cd ocr-service
pip install -r requirements.txt --break-system-packages

# Run service
python ocr_service.py

# Service will be available at http://localhost:5005
```

## 🧪 Testing Commands

### Test OCR Service
```bash
# Health check
curl http://localhost:5005/health

# Test with image
curl -X POST http://localhost:5005/ocr/receipt \
  -F "file=@sample_receipt.jpg" | jq '.'

# Run test script
python ocr-service/test_ocr.py

# Test with your own image
python ocr-service/test_ocr.py /path/to/receipt.jpg
```

### Test from Symfony
```bash
# Navigate to upload page in browser
open http://localhost:8000/bill/import

# Check Symfony logs
tail -f var/log/dev.log | grep OCR
```

## 🔧 Troubleshooting Commands

### OCR Service Issues

#### Check if service is running
```bash
# Docker
docker ps | grep ocr-service

# Local
ps aux | grep ocr_service
```

#### Check Tesseract installation
```bash
# Docker
docker exec -it katzen_ocr_service tesseract --version

# Local
tesseract --version
```

#### Rebuild from scratch
```bash
# Docker
docker-compose down ocr-service
docker-compose build --no-cache ocr-service
docker-compose up -d ocr-service
```

### Connection Issues

#### Test connectivity from Symfony container
```bash
docker exec -it katzen-app curl http://ocr-service:5005/health
```

#### Check Docker network
```bash
docker network ls | grep katzen
docker network inspect katzen_network
```

#### Check environment variables
```bash
# In Symfony container
docker exec -it katzen-app printenv | grep OCR
```

### Performance Issues

#### Check resource usage
```bash
# Docker
docker stats katzen_ocr_service

# Local
top -p $(pgrep -f ocr_service)
```

#### Increase workers (in Dockerfile)
```dockerfile
CMD ["uvicorn", "ocr_service:app", "--host", "0.0.0.0", "--port", "5005", "--workers", "4"]
```

## 📝 File Locations

### PHP Files (Katzen)
```
src/Katzen/
├── Adapter/OCRAdapter.php
├── Controller/VendorInvoiceController.php (add import method)
├── Form/ImportVendorInvoiceType.php
└── Service/Accounting/ReceiptImportService.php (to create)

templates/katzen/vendor_invoice/
└── import.html.twig

config/
└── services.yaml (add OCR services)

.env.local
└── OCR_SERVICE_URL=http://ocr-service:5005
```

### Python Files (OCR Service)
```
ocr-service/
├── ocr_service.py
├── requirements.txt
├── Dockerfile
├── test_ocr.py
└── .dockerignore
```

## 🎯 Common Tasks

### Add a new language to Tesseract
```bash
# In Dockerfile, add:
RUN apt-get install -y tesseract-ocr-fra  # French
RUN apt-get install -y tesseract-ocr-spa  # Spanish

# Rebuild container
docker-compose build --no-cache ocr-service
```

### Change OCR processing settings
```python
# In ocr_service.py, modify:
ocr_data = pytesseract.image_to_data(
    processed,
    output_type=pytesseract.Output.DICT,
    config='--psm 6'  # Change PSM mode here
)

# PSM modes:
# 3 = Fully automatic page segmentation
# 6 = Uniform block of text (default)
# 11 = Sparse text
```

### Add vendor profile
```php
// In VendorIdentificationService.php
private array $profiles = [
    'new_vendor' => [
        'keywords' => ['VENDOR NAME', 'VENDOR.COM'],
        'invoice_pattern' => '/INV-\d{6}/',
        'date_format' => 'Y-m-d',
    ],
];
```

### Enable caching
```php
// In OCRAdapter.php, add:
$cacheKey = 'ocr:' . hash('sha256', file_get_contents($file->getPathname()));

if ($cached = $this->cache->get($cacheKey)) {
    return $cached;
}

// ... process OCR ...

$this->cache->set($cacheKey, $result, 86400 * 30); // 30 days
```

## 🐛 Debug Mode

### Enable verbose logging in OCR service
```python
# In ocr_service.py
logging.basicConfig(level=logging.DEBUG)  # Change from INFO to DEBUG
```

### Enable Symfony debug for HTTP client
```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        main:
            level: debug
            channels: ['!event']
```

### Test with curl (bypass Symfony)
```bash
# Create test payload
curl -X POST http://localhost:5005/ocr/receipt \
  -H "Content-Type: multipart/form-data" \
  -F "file=@receipt.jpg" \
  -v  # Verbose output
```

## 📊 Monitoring

### Check OCR accuracy over time
```bash
# Query database for average confidence
SELECT AVG(CAST(ocr_confidence AS DECIMAL)) as avg_conf, 
       COUNT(*) as count,
       DATE(created_at) as date
FROM vendor_invoice 
WHERE source_type = 'ocr_scan'
GROUP BY DATE(created_at)
ORDER BY date DESC
LIMIT 30;
```

### Find low-confidence invoices
```bash
SELECT id, invoice_number, vendor_id, ocr_confidence
FROM vendor_invoice
WHERE source_type = 'ocr_scan' 
  AND CAST(ocr_confidence AS DECIMAL) < 75
ORDER BY created_at DESC;
```

## 🔄 Update/Restart Workflow

### After code changes (PHP)
```bash
# Clear Symfony cache
php bin/console cache:clear

# Restart PHP-FPM (if using)
sudo service php8.2-fpm restart
```

### After code changes (Python)
```bash
# Docker
docker-compose restart ocr-service

# Local (if using --reload)
# Service auto-reloads

# Local (manual restart)
pkill -f ocr_service
python ocr_service.py
```

## 🔐 Security Checklist

- [ ] OCR service only accessible via internal Docker network
- [ ] File upload size limited (10MB)
- [ ] File types validated (JPEG, PNG, PDF only)
- [ ] MIME type checking enabled
- [ ] OCR results sanitized before DB insertion
- [ ] Rate limiting configured (if service exposed)
- [ ] Logs don't contain sensitive data
- [ ] Temp files cleaned up after processing

## 💡 Performance Tips

### For better OCR accuracy:
```bash
# 1. Ensure good image quality
#    - Minimum 300 DPI for scans
#    - Good lighting for photos
#    - Flat, unwrinkled receipts

# 2. Adjust preprocessing parameters
#    - Increase CLAHE clip limit for low contrast
#    - Adjust threshold block size for different font sizes
#    - Enable/disable deskewing based on your receipts

# 3. Use appropriate PSM mode
#    - PSM 6 for standard receipts
#    - PSM 3 for mixed layouts
#    - PSM 11 for sparse text
```

### For better performance:
```bash
# 1. Scale workers based on CPU cores
#    workers = (2 * num_cores) + 1

# 2. Enable caching for repeated uploads

# 3. Set resource limits in docker-compose.yml
#    cpu: 2
#    memory: 2G

# 4. Use SSDs for temp file storage
```

## 📞 Quick Help

### Error: "Connection refused"
```bash
# Check service is running
docker-compose ps

# Check environment variable
echo $OCR_SERVICE_URL

# Test connectivity
curl http://localhost:5005/health
```

### Error: "OCR service unavailable"
```bash
# Check logs
docker-compose logs ocr-service

# Restart service
docker-compose restart ocr-service

# Check Tesseract
docker exec -it katzen_ocr_service tesseract --version
```

### Error: "No text detected"
```bash
# Check image quality
# Try with a clearer image
# Check if image is upside down or rotated 90°
```

### Error: "Low confidence scores"
```bash
# Improve image quality
# Check lighting and focus
# Try different PSM mode
# Consider enabling PaddleOCR fallback
```

## 📚 Documentation References

- **Full Setup**: See SETUP_GUIDE.md
- **Architecture**: See ARCHITECTURE.md  
- **OCR Service Details**: See OCR_SERVICE_README.md
- **Complete Overview**: See SUMMARY.md

## 🎓 Learning Resources

```bash
# Tesseract documentation
open https://tesseract-ocr.github.io/

# FastAPI documentation
open https://fastapi.tiangolo.com/

# Symfony HTTP Client
open https://symfony.com/doc/current/http_client.html

# OpenCV Python
open https://docs.opencv.org/4.x/d6/d00/tutorial_py_root.html
```

---

**Need help?** Check the SETUP_GUIDE.md troubleshooting section first!
