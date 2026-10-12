import zipfile
from xml.etree import ElementTree as ET
import sys

path = r'C:\Users\ADMIN\Downloads\CTP-WireLakes\CTP-WireLakes-ERP-System-Blueprint.docx'
ns = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'

with zipfile.ZipFile(path) as z:
    with z.open('word/document.xml') as f:
        xml = f.read().decode('utf-8')

root = ET.fromstring(xml)
out = []
for p in root.iter(ns+'p'):
    text = ''.join(t.text or '' for t in p.iter(ns+'t'))
    if text.strip():
        out.append(text)

import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
print('\n'.join(out))
