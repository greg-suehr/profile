"""
OCR Microservice - FastAPI Application
Handles receipt/invoice OCR using Tesseract
"""
import io
import logging
from typing import Optional
from pathlib import Path

import cv2
import numpy as np
import pytesseract
import fitz  # PyMuPDF
from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import JSONResponse
from PIL import Image

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

app = FastAPI(title="Katzen OCR Service", version="1.0.0")


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {"status": "healthy", "engine": "tesseract5"}


@app.post("/ocr/receipt")
async def ocr_receipt(file: UploadFile = File(...)):
    """
    Process uploaded receipt/invoice image or PDF
    Returns structured OCR data with word positions and confidence scores
    """
    try:
        # Read file bytes
        file_bytes = await file.read()
        
        # Detect file type and process accordingly
        if file.filename.lower().endswith('.pdf'):
            pages = process_pdf(file_bytes)
        else:
            pages = [process_image(file_bytes)]
        
        # Calculate average confidence
        avg_conf = calculate_avg_confidence(pages)
        
        response = {
            "pages": pages,
            "engine": "tesseract5",
            "avg_conf": avg_conf
        }
        
        logger.info(
            f"OCR complete: {file.filename}, "
            f"pages={len(pages)}, avg_conf={avg_conf:.1f}"
        )
        
        return JSONResponse(content=response)
        
    except Exception as e:
        logger.error(f"OCR processing failed: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=500,
            detail=f"OCR processing failed: {str(e)}"
        )


def process_pdf(pdf_bytes: bytes) -> list[dict]:
    """Process PDF file - extract pages and OCR each"""
    pages = []
    
    try:
        doc = fitz.open(stream=pdf_bytes, filetype="pdf")
        
        for page_num in range(len(doc)):
            page = doc[page_num]
            
            # Convert PDF page to image (300 DPI for good quality)
            pix = page.get_pixmap(matrix=fitz.Matrix(300/72, 300/72))
            img_bytes = pix.tobytes("png")
            
            # Process the page image
            page_data = process_image(img_bytes)
            pages.append(page_data)
        
        doc.close()
        return pages
        
    except Exception as e:
        logger.error(f"PDF processing failed: {str(e)}")
        raise


def process_image(img_bytes: bytes) -> dict:
    """Process single image through OCR pipeline"""
    
    # Decode image
    img_array = np.frombuffer(img_bytes, np.uint8)
    img = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
    
    if img is None:
        raise ValueError("Failed to decode image")
    
    # Preprocessing pipeline
    processed = preprocess_image(img)
    
    # Get OCR data with word-level details
    ocr_data = pytesseract.image_to_data(
        processed,
        output_type=pytesseract.Output.DICT,
        config='--psm 6'  # Assume uniform block of text
    )
    
    # Structure the data
    height, width = processed.shape[:2]
    words = extract_words(ocr_data)
    lines = extract_lines(ocr_data)
    
    return {
        "width": width,
        "height": height,
        "words": words,
        "lines": lines,
        "orientation_deg": 0
    }


def preprocess_image(img: np.ndarray) -> np.ndarray:
    """
    Image preprocessing pipeline for better OCR accuracy
    Steps: grayscale → denoise → deskew → threshold → contrast boost
    """
    
    # 1. Convert to grayscale
    if len(img.shape) == 3:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    else:
        gray = img
    
    # 2. Denoise
    denoised = cv2.fastNlMeansDenoising(gray, None, h=10)
    
    # 3. Deskew (detect and fix rotation)
    deskewed = deskew(denoised)
    
    # 4. Binarization (adaptive threshold)
    binary = cv2.adaptiveThreshold(
        deskewed,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        blockSize=11,
        C=2
    )
    
    # 5. Contrast enhancement (CLAHE)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    enhanced = clahe.apply(binary)
    
    return enhanced


def deskew(image: np.ndarray) -> np.ndarray:
    """
    Detect and correct skew using Hough transform
    """
    # Edge detection
    edges = cv2.Canny(image, 50, 150, apertureSize=3)
    
    # Hough line detection
    lines = cv2.HoughLines(edges, 1, np.pi / 180, threshold=100)
    
    if lines is None:
        return image
    
    # Calculate average angle
    angles = []
    for rho, theta in lines[:, 0]:
        angle = np.degrees(theta) - 90
        if -45 < angle < 45:  # Only consider reasonable angles
            angles.append(angle)
    
    if not angles:
        return image
    
    median_angle = np.median(angles)
    
    # Only rotate if angle is significant
    if abs(median_angle) < 0.5:
        return image
    
    # Rotate image
    (h, w) = image.shape[:2]
    center = (w // 2, h // 2)
    M = cv2.getRotationMatrix2D(center, median_angle, 1.0)
    rotated = cv2.warpAffine(
        image, M, (w, h),
        flags=cv2.INTER_CUBIC,
        borderMode=cv2.BORDER_REPLICATE
    )
    
    logger.debug(f"Deskewed by {median_angle:.2f} degrees")
    return rotated


def extract_words(ocr_data: dict) -> list[dict]:
    """Extract word-level data with bounding boxes and confidence"""
    words = []
    n_boxes = len(ocr_data['text'])
    
    for i in range(n_boxes):
        text = ocr_data['text'][i].strip()
        conf = int(ocr_data['conf'][i])
        
        # Skip empty detections and low confidence (<0)
        if not text or conf < 0:
            continue
        
        words.append({
            "text": text,
            "x": int(ocr_data['left'][i]),
            "y": int(ocr_data['top'][i]),
            "w": int(ocr_data['width'][i]),
            "h": int(ocr_data['height'][i]),
            "conf": conf
        })
    
    return words


def extract_lines(ocr_data: dict) -> list[dict]:
    """Group words into lines with average confidence"""
    lines_dict = {}
    n_boxes = len(ocr_data['text'])
    
    for i in range(n_boxes):
        text = ocr_data['text'][i].strip()
        conf = int(ocr_data['conf'][i])
        block_num = ocr_data['block_num'][i]
        par_num = ocr_data['par_num'][i]
        line_num = ocr_data['line_num'][i]
        
        if not text or conf < 0:
            continue
        
        # Create unique line identifier
        line_key = (block_num, par_num, line_num)
        
        if line_key not in lines_dict:
            lines_dict[line_key] = {
                'words': [],
                'boxes': [],
                'confidences': []
            }
        
        lines_dict[line_key]['words'].append(text)
        lines_dict[line_key]['boxes'].append([
            int(ocr_data['left'][i]),
            int(ocr_data['top'][i]),
            int(ocr_data['left'][i] + ocr_data['width'][i]),
            int(ocr_data['top'][i] + ocr_data['height'][i])
        ])
        lines_dict[line_key]['confidences'].append(conf)
    
    # Convert to list format
    lines = []
    for line_data in lines_dict.values():
        if not line_data['words']:
            continue
        
        # Combine words into line text
        line_text = ' '.join(line_data['words'])
        
        # Calculate bounding box for entire line
        boxes = line_data['boxes']
        x_min = min(box[0] for box in boxes)
        y_min = min(box[1] for box in boxes)
        x_max = max(box[2] for box in boxes)
        y_max = max(box[3] for box in boxes)
        
        # Calculate average confidence
        avg_conf = int(np.mean(line_data['confidences']))
        
        lines.append({
            "text": line_text,
            "bbox": [x_min, y_min, x_max, y_max],
            "avg_conf": avg_conf
        })
    
    # Sort lines top-to-bottom
    lines.sort(key=lambda x: x['bbox'][1])
    
    return lines


def calculate_avg_confidence(pages: list[dict]) -> float:
    """Calculate overall average confidence across all pages"""
    all_confs = []
    
    for page in pages:
        for word in page.get('words', []):
            conf = word.get('conf', 0)
            if conf > 0:  # Ignore invalid confidences
                all_confs.append(conf)
    
    if not all_confs:
        return 0.0
    
    return round(np.mean(all_confs), 1)


if __name__ == "__main__":
    print("starting program...")
    import uvicorn
    print("fuck that took a while didnt it?")
    uvicorn.run(app, host="0.0.0.0", port=5005, log_level="info")
