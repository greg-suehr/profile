#!/usr/bin/env python3
"""
OCR Service Test Script
Tests the OCR service with a sample receipt
"""
import sys
import requests
from pathlib import Path

OCR_SERVICE_URL = "http://localhost:5005"

def test_health_check():
    """Test if OCR service is running"""
    print("Testing health check...")
    try:
        response = requests.get(f"{OCR_SERVICE_URL}/health", timeout=5)
        if response.status_code == 200:
            print("✓ Health check passed")
            print(f"  Response: {response.json()}")
            return True
        else:
            print(f"✗ Health check failed: {response.status_code}")
            return False
    except Exception as e:
        print(f"✗ Cannot connect to OCR service: {e}")
        print("  Make sure the service is running on http://localhost:5005")
        return False

def test_ocr_processing(image_path: str):
    """Test OCR processing with an image"""
    print(f"\nTesting OCR with file: {image_path}")
    
    if not Path(image_path).exists():
        print(f"✗ File not found: {image_path}")
        return False
    
    try:
        with open(image_path, 'rb') as f:
            files = {'file': (Path(image_path).name, f)}
            response = requests.post(
                f"{OCR_SERVICE_URL}/ocr/receipt",
                files=files,
                timeout=30
            )
        
        if response.status_code == 200:
            result = response.json()
            print("✓ OCR processing successful")
            print(f"  Engine: {result.get('engine')}")
            print(f"  Pages: {len(result.get('pages', []))}")
            print(f"  Average Confidence: {result.get('avg_conf')}%")
            
            # Show first page details
            if result.get('pages'):
                first_page = result['pages'][0]
                print(f"  Words detected: {len(first_page.get('words', []))}")
                print(f"  Lines detected: {len(first_page.get('lines', []))}")
                
                # Show first few lines
                print("\n  First lines detected:")
                for line in first_page.get('lines', [])[:5]:
                    print(f"    - {line['text']} (conf: {line['avg_conf']}%)")
            
            return True
        else:
            print(f"✗ OCR processing failed: {response.status_code}")
            print(f"  Response: {response.text}")
            return False
            
    except Exception as e:
        print(f"✗ Error during OCR processing: {e}")
        return False

def create_test_image():
    """Create a simple test image with text"""
    try:
        from PIL import Image, ImageDraw, ImageFont
        
        # Create test receipt image
        img = Image.new('RGB', (400, 600), color='white')
        draw = ImageDraw.Draw(img)
        
        # Add text
        text_lines = [
            "GROCERY STORE",
            "123 Main Street",
            "Invoice #12345",
            "Date: 2024-01-15",
            "",
            "BANANAS        $2.99",
            "MILK           $3.50",
            "BREAD          $2.25",
            "",
            "SUBTOTAL       $8.74",
            "TAX            $0.70",
            "TOTAL          $9.44",
        ]
        
        y = 50
        for line in text_lines:
            draw.text((50, y), line, fill='black')
            y += 40
        
        test_path = '/tmp/test_receipt.png'
        img.save(test_path)
        print(f"Created test image: {test_path}")
        return test_path
        
    except ImportError:
        print("Pillow not installed. Install with: pip install Pillow")
        return None

def main():
    """Run all tests"""
    print("=" * 60)
    print("Katzen OCR Service Test")
    print("=" * 60)
    
    # Test 1: Health check
    if not test_health_check():
        print("\n✗ OCR service is not running or not accessible")
        print("  Start it with: python ocr_service.py")
        print("  Or: docker-compose up ocr-service")
        sys.exit(1)
    
    # Test 2: OCR processing
    test_image_path = None
    
    # Check if image provided as argument
    if len(sys.argv) > 1:
        test_image_path = sys.argv[1]
    else:
        # Try to create test image
        print("\nNo test image provided. Creating sample receipt...")
        test_image_path = create_test_image()
    
    if test_image_path:
        success = test_ocr_processing(test_image_path)
        if success:
            print("\n" + "=" * 60)
            print("✓ All tests passed!")
            print("=" * 60)
        else:
            print("\n" + "=" * 60)
            print("✗ Some tests failed")
            print("=" * 60)
            sys.exit(1)
    else:
        print("\nSkipping OCR test - no image available")
        print("Run with: python test_ocr.py /path/to/receipt.jpg")

if __name__ == "__main__":
    main()
