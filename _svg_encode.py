import base64, sys, io
src = r'C:\Users\ADMIN\Downloads\CTP-WireLakes\logo.PNG'
with open(src, 'rb') as f:
    data = f.read()
b64 = base64.b64encode(data).decode('ascii')
# Read PNG width/height from IHDR (bytes 16-24)
w = int.from_bytes(data[16:20], 'big')
h = int.from_bytes(data[20:24], 'big')
svg = f'''<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 {w} {h}" width="{w}" height="{h}" role="img" aria-label="CTP-WireLakes Construction and Technology Enterprise logo">
  <title>CTP-WireLakes</title>
  <desc>WireLakes Construction and Technology Enterprise logo. The source vectorization is the next step — this file embeds the original PNG so it can be used as &lt;img src="logo.svg"&gt; in HTML and Blade.</desc>
  <image x="0" y="0" width="{w}" height="{h}" xlink:href="data:image/png;base64,{b64}"/>
</svg>
'''
out = r'C:\Users\ADMIN\Downloads\CTP-WireLakes\web\public\logo.svg'
with open(out, 'w', encoding='utf-8') as f:
    f.write(svg)
print('wrote', out, 'size', len(svg), 'bytes; source png', w, 'x', h)
