# Katzen OCR Integration Setup Guide

Complete step-by-step instructions for adding OCR receipt/invoice scanning to Katzen.

## Overview

This setup adds:
- Python FastAPI microservice for OCR processing
- Symfony file upload form and controller
- OCR adapter that connects PHP to Python
- Docker containerization for the OCR service

## File Structure

Create this directory structure in your project:

```
katzen/
├── src/
│   └── Katzen/
│       ├── Adapter/
│       │   └── OCRAdapter.php           (CREATED)
│       ├── Controller/
│       │   └── VendorInvoiceController.php  (MODIFY - add import method)
│       ├── Form/
│       │   └── ImportVendorInvoiceType.php  (CREATED)
│       └── Service/
│           └── Accounting/
│               └── ReceiptImportService.php  (TO CREATE)
├── templates/
│   └── katzen/
│       └── vendor_invoice/
│           └── import.html.twig         (CREATED)
├── config/
│   └── services.yaml                    (MODIFY - add OCR services)
├── .env.local                           (MODIFY - add OCR_SERVICE_URL)
└── ocr-service/                         (NEW DIRECTORY)
    ├── ocr_service.py                   (CREATED)
    ├── requirements.txt                 (CREATED)
    ├── Dockerfile.ocr                   (CREATED)
    └── .dockerignore                    (CREATE)

docker-compose.yml                       (MODIFY - add ocr-service)
```

## Step-by-Step Setup

### 1. Create OCR Service Directory

```bash
mkdir ocr-service
cd ocr-service
```

Copy these files into `ocr-service/`:
- `ocr_service.py`
- `requirements.txt`
- `Dockerfile.ocr` (rename to `Dockerfile`)

Create `.dockerignore`:
```
__pycache__/
*.pyc
*.pyo
*.pyd
.Python
pip-log.txt
.pytest_cache/
```

### 2. Install Symfony Components

```bash
# Install HTTP Client if not already installed
composer require symfony/http-client

# Install form components if not already installed
composer require symfony/form
composer require symfony/validator
```

### 3. Add PHP Files

#### a. Create the Form Type

Copy `ImportVendorInvoiceType.php` to:
```
src/Katzen/Form/ImportVendorInvoiceType.php
```

#### b. Create the OCR Adapter

Copy `OCRAdapter.php` to:
```
src/Katzen/Adapter/OCRAdapter.php
```

#### c. Update VendorInvoiceController

Add the import method from `VendorInvoiceController_import_method.php` to your existing:
```
src/Katzen/Controller/VendorInvoiceController.php
```

You'll need to add these use statements at the top:
```php
use App\Katzen\Form\ImportVendorInvoiceType;
use App\Katzen\Adapter\OCRAdapter;
use App\Katzen\Service\Accounting\ReceiptImportService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
```

#### d. Create the Twig Template

Copy `import.html.twig` to:
```
templates/katzen/vendor_invoice/import.html.twig
```

### 4. Configure Services

#### a. Update config/services.yaml

Add the OCR service configuration from `services_ocr.yaml` to your `config/services.yaml`.

#### b. Update .env.local

Add the environment variable:
```bash
OCR_SERVICE_URL=http://ocr-service:5005
```

For local development without Docker, use:
```bash
OCR_SERVICE_URL=http://localhost:5005
```

### 5. Build and Run OCR Service

#### Option A: Docker Compose (Recommended)

Update your `docker-compose.yml` to include the OCR service (see provided `docker-compose.yml`).

Then run:
```bash
docker-compose up -d --build ocr-service
```

Test the service:
```bash
curl http://localhost:5005/health
# Should return: {"status":"healthy","engine":"tesseract5"}
```

#### Option B: Local Development (without Docker)

Install system dependencies (Ubuntu/Debian):
```bash
sudo apt-get update
sudo apt-get install -y tesseract-ocr tesseract-ocr-eng libtesseract-dev \
    libgl1-mesa-glx libglib2.0-0 libsm6 libxext6 libxrender-dev \
    poppler-utils ghostscript
```

Install Python dependencies:
```bash
cd ocr-service
pip install -r requirements.txt --break-system-packages
```

Run the service:
```bash
python ocr_service.py
# Service runs on http://localhost:5005
```

### 6. Create ReceiptImportService (Stub)

Create `src/Katzen/Service/Accounting/ReceiptImportService.php`:

```php
<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Service\Response\ServiceResponse;

final class ReceiptImportService
{
    public function __construct(
        private VendorInvoiceService $invoicing,
    ) {}

    public function processOCRResult(array $ocrData, int $userId): ServiceResponse
    {
        // TODO: Implement vendor identification
        // TODO: Implement item matching
        // TODO: Build invoice data structure
        
        // For now, just pass through to VendorInvoiceService
        $invoiceData = [
            'vendor_name' => $ocrData['vendor_guess'] ?? 'Unknown Vendor',
            'invoice_number' => $ocrData['invoice_number'] ?? 'OCR-' . uniqid(),
            'invoice_date' => $ocrData['invoice_date'] ?? date('Y-m-d'),
            'items' => $ocrData['line_items'] ?? [],
            'confidence' => $ocrData['confidence_report']['avg'] ?? 0,
        ];

        // This will need the full implementation as per your strategy
        // return $this->invoicing->importFromOCR($invoiceData, $userId);
        
        return ServiceResponse::failure(
            errors: ['ReceiptImportService not fully implemented yet'],
            message: 'Implementation pending'
        );
    }
}
```

### 7. Test the Integration

#### a. Test OCR Service Directly

```bash
# Create a test image or use a sample receipt
curl -X POST http://localhost:5005/ocr/receipt \
  -F "file=@/path/to/receipt.jpg" \
  | jq '.'
```

#### b. Test through Symfony

1. Start your Symfony application
2. Navigate to `/bill/import`
3. Upload a receipt image
4. Check logs for OCR processing

#### c. Check Logs

```bash
# Symfony logs
tail -f var/log/dev.log

# OCR service logs
docker-compose logs -f ocr-service
```

## Verification Checklist

- [ ] OCR service health check returns success
- [ ] Can access upload form at `/bill/import`
- [ ] File upload accepts images and PDFs
- [ ] OCR service receives files from Symfony
- [ ] OCR results return to Symfony
- [ ] Error handling works (try invalid files)

## Troubleshooting

### OCR Service Won't Start

**Problem:** Tesseract not found
```bash
# In Docker container
docker exec -it katzen_ocr_service tesseract --version
# Should show Tesseract version

# If missing, rebuild with --no-cache
docker-compose build --no-cache ocr-service
```

### Symfony Can't Connect to OCR Service

**Problem:** Connection refused
```bash
# Check if service is running
docker-compose ps ocr-service

# Check if network exists
docker network ls | grep katzen

# Test connection from PHP container
docker exec -it katzen-app curl http://ocr-service:5005/health
```

### File Upload Fails

**Problem:** File size limit
```bash
# Check PHP settings
php -i | grep upload_max_filesize
php -i | grep post_max_size

# Increase in php.ini or .env
# upload_max_filesize = 10M
# post_max_size = 10M
```

### Low OCR Accuracy

**Tips for better results:**
1. Ensure good lighting when taking photos
2. Flatten receipts (no wrinkles)
3. Capture entire receipt including header/footer
4. Use high resolution (at least 300 DPI for scans)
5. Try different Tesseract PSM modes in `ocr_service.py`:
   - PSM 3: Fully automatic page segmentation
   - PSM 6: Uniform block of text (current default)
   - PSM 11: Sparse text

## Next Steps

After basic setup works:

1. **Implement ReceiptImportService** - Add vendor identification and item matching logic
2. **Add Manual Review UI** - For low-confidence OCR results
3. **Create Vendor Profiles** - Regex patterns for common vendors
4. **Add Caching** - Store OCR results to avoid reprocessing
5. **Add Monitoring** - Track OCR accuracy and performance metrics
6. **Consider PaddleOCR** - For receipts with poor quality or curved text

## Performance Optimization

### Production Deployment

1. **Use dedicated hardware**: OCR is CPU-intensive
2. **Scale workers**: Set `--workers 4` in Dockerfile CMD
3. **Add Redis caching**: Cache OCR results by file hash
4. **Set resource limits**: Prevent memory leaks

```yaml
# docker-compose.yml
ocr-service:
  deploy:
    replicas: 2
    resources:
      limits:
        cpus: '2'
        memory: 2G
```

### Monitoring

Add these metrics:
- Average OCR processing time
- Average confidence score by vendor
- Manual review rate
- Error rate

## Security Considerations

1. **File validation**: Already implemented in OCRAdapter
2. **Rate limiting**: Add to OCR service with `slowapi`
3. **File size limits**: Set in form validation (currently 10MB)
4. **Temporary file cleanup**: OCR service should delete after processing
5. **Authentication**: Add API key if OCR service is exposed

## Support

If you encounter issues:
1. Check the logs (both Symfony and OCR service)
2. Verify all environment variables are set
3. Ensure Docker networks are properly configured
4. Test each component independently

## Reference

Files provided in this setup:
- `OCRAdapter.php` - Symfony adapter for OCR service
- `ocr_service.py` - FastAPI OCR microservice
- `requirements.txt` - Python dependencies
- `Dockerfile.ocr` - Container definition
- `ImportVendorInvoiceType.php` - File upload form
- `import.html.twig` - Upload UI template
- `docker-compose.yml` - Service orchestration
- `services_ocr.yaml` - Symfony service configuration
- `VendorInvoiceController_import_method.php` - Controller route
