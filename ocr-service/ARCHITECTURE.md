# OCR Integration Architecture

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                           USER UPLOADS FILE                          │
│                    (JPEG/PNG/PDF receipt/invoice)                    │
└─────────────────────────────┬───────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    SYMFONY WEB APPLICATION                           │
│                                                                       │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  VendorInvoiceController::importOCR()                         │  │
│  │  • Receives file from ImportVendorInvoiceType form            │  │
│  │  • Validates file (size, type, etc)                           │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
│                   │                                                  │
│                   ▼                                                  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  OCRAdapter::process($file)                                   │  │
│  │  • Opens file stream                                           │  │
│  │  • Sends multipart POST to OCR service                        │  │
│  │  • Retries on failure (max 2 retries)                         │  │
│  │  • Normalizes response for Katzen                             │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
└───────────────────┼──────────────────────────────────────────────────┘
                    │
                    │ HTTP POST
                    │ http://ocr-service:5005/ocr/receipt
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      PYTHON OCR SERVICE                              │
│                      (FastAPI on port 5005)                          │
│                                                                       │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  POST /ocr/receipt                                            │  │
│  │  • Receives uploaded file                                     │  │
│  │  • Detects type (image vs PDF)                                │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
│                   │                                                  │
│                   ▼                                                  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  process_image(bytes) or process_pdf(bytes)                   │  │
│  │  • Decodes image/converts PDF to image                        │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
│                   │                                                  │
│                   ▼                                                  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  preprocess_image()                                           │  │
│  │  ┌─────────────────────────────────────────────────────────┐ │  │
│  │  │ 1. Grayscale conversion                                  │ │  │
│  │  │ 2. Denoise (fastNlMeans)                                 │ │  │
│  │  │ 3. Deskew (Hough transform)                              │ │  │
│  │  │ 4. Binarization (adaptive threshold)                     │ │  │
│  │  │ 5. Contrast enhancement (CLAHE)                          │ │  │
│  │  └─────────────────────────────────────────────────────────┘ │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
│                   │                                                  │
│                   ▼                                                  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  Tesseract OCR Engine                                         │  │
│  │  pytesseract.image_to_data(img, config='--psm 6')            │  │
│  │  • Extracts text with word-level coordinates                 │  │
│  │  • Provides confidence scores per word                        │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
│                   │                                                  │
│                   ▼                                                  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  Structure OCR Output                                         │  │
│  │  • Group words into lines                                     │  │
│  │  • Calculate bounding boxes                                   │  │
│  │  • Compute average confidence                                 │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
│                   │                                                  │
│                   │ Returns JSON                                     │
│                   │                                                  │
└───────────────────┼──────────────────────────────────────────────────┘
                    │
                    │ {pages: [...], avg_conf: 84, engine: "tesseract5"}
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    SYMFONY WEB APPLICATION                           │
│                                                                       │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  OCRAdapter::normalizeForKatzen()                             │  │
│  │  • Extracts vendor name                                       │  │
│  │  • Finds invoice number                                       │  │
│  │  • Parses date                                                │  │
│  │  • Extracts totals (subtotal, tax, total)                    │  │
│  │  • Structures line items                                      │  │
│  │  • Identifies low-confidence fields                           │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
│                   │                                                  │
│                   ▼                                                  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  ReceiptImportService::processOCRResult()                     │  │
│  │  • Vendor identification (via VendorIdentificationService)    │  │
│  │  • Item matching (UPC lookup → name similarity)              │  │
│  │  • Builds invoice data structure                              │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
│                   │                                                  │
│                   ▼                                                  │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  VendorInvoiceService::importFromOCR()                        │  │
│  │  • Creates VendorInvoice entity                               │  │
│  │  • Creates VendorInvoiceItem entities                         │  │
│  │  • Sets approval_status based on confidence                   │  │
│  │  • Persists to database                                       │  │
│  └────────────────┬─────────────────────────────────────────────┘  │
└───────────────────┼──────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         DATABASE                                     │
│  Tables Updated:                                                     │
│  • vendor_invoice                                                    │
│  • vendor_invoice_item                                               │
│  • audit/changelog (if enabled)                                      │
└─────────────────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    USER SEES RESULT                                  │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  If avg_conf >= 75:                                          │   │
│  │    → Redirect to invoice detail page                         │   │
│  │    → Show "Invoice imported successfully!" flash message     │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  If avg_conf < 75:                                           │   │
│  │    → Redirect to manual review page                          │   │
│  │    → Show "Please review the details" warning                │   │
│  │    → Highlight low-confidence fields in red                  │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

### PHP Components (Katzen)

**ImportVendorInvoiceType.php**
- Renders file upload form
- Validates file type and size
- Handles multipart form submission

**VendorInvoiceController.php**
- Receives uploaded file
- Coordinates OCR processing
- Handles success/error flows
- Routes to manual review if needed

**OCRAdapter.php**
- Sends file to OCR service via HTTP
- Handles retries and errors
- Normalizes OCR response
- Extracts key invoice fields

**ReceiptImportService.php** (to be created)
- Identifies vendor from text
- Matches items to catalog
- Builds structured invoice data
- Determines if manual review needed

**VendorInvoiceService.php**
- Persists invoice to database
- Creates line items
- Sets approval workflow status
- Handles accounting entries

### Python Components (OCR Service)

**ocr_service.py**
- FastAPI web server
- Image/PDF processing
- Tesseract integration
- Response structuring

**preprocess_image()**
- Image quality enhancement
- Deskewing and straightening
- Noise removal
- Contrast optimization

**Tesseract OCR**
- Text recognition
- Word-level positioning
- Confidence scoring
- Multi-language support

## Service Communication

### Request Format
```
POST /ocr/receipt
Content-Type: multipart/form-data

file: [binary image/pdf data]
```

### Response Format
```json
{
  "pages": [
    {
      "width": 1728,
      "height": 2336,
      "words": [
        {"text": "SUBTOTAL", "x": 126, "y": 2010, "w": 210, "h": 34, "conf": 88}
      ],
      "lines": [
        {"text": "BAGELS 6CT  4.99", "bbox": [110, 920, 1510, 960], "avg_conf": 86}
      ],
      "orientation_deg": 0
    }
  ],
  "engine": "tesseract5",
  "avg_conf": 84
}
```

## Error Handling Flow

```
Upload File
    │
    ▼
Validate File ──────────────► Invalid? → Show error message
    │                                      "Unsupported file type"
    ▼
Send to OCR
    │
    ├──► Retry 1 (1s delay) ──► Service down? → Show error message
    │                                           "OCR service unavailable"
    ├──► Retry 2 (2s delay)
    │
    ▼
Process Response
    │
    ├──► No text detected? ──────────────► Show error message
    │                                      "Could not read receipt"
    ├──► avg_conf < 50? ─────────────────► Show error message
    │                                      "Image quality too poor"
    ▼
avg_conf >= 75? ──► Yes ──► Auto-import ──► Success page
    │
    ▼ No
Manual Review ──► User edits ──► Save ──► Success page
```

## Deployment Architecture

### Development
```
[Developer Machine]
    │
    ├─► Symfony Dev Server (localhost:8000)
    │       └─► Uses HTTP Client to connect to:
    │
    └─► OCR Service (localhost:5005)
            └─► Python process running directly
```

### Production
```
[Load Balancer]
    │
    ▼
[Docker Network: katzen_network]
    │
    ├─► katzen-app (Symfony)
    │       │
    │       └─► Internal connection to:
    │
    ├─► ocr-service (Python)
    │       └─► 2 workers for parallel processing
    │
    ├─► database (PostgreSQL/MySQL)
    │
    └─► redis (optional, for caching)
```

## Security Boundaries

```
Internet → Load Balancer → Symfony (exposed)
                              │
                              └→ OCR Service (internal only)
                                    └→ No direct internet access
```

The OCR service should **never** be exposed directly to the internet. All access goes through Symfony's OCRAdapter, which provides:
- Authentication checking
- Rate limiting
- File validation
- Request logging
