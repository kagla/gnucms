import sys
try:
    import cv2
except ImportError:
    print("SKIP: cv2 not installed", file=sys.stderr)
    sys.exit(2)
image = cv2.imread(sys.argv[1])
data, points, _ = cv2.QRCodeDetector().detectAndDecode(image)
print(data, end="")
