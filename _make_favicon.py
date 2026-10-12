from PIL import Image
src = r'C:\Users\ADMIN\Downloads\CTP-WireLakes\logo.PNG'
img = Image.open(src).convert('RGBA')
# Generate standard favicon sizes
sizes = [(16, 16), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)]
frames = [img.resize(s, Image.LANCZOS) for s in sizes]
out = r'C:\Users\ADMIN\Downloads\CTP-WireLakes\web\public\favicon.ico'
# The largest image is the source for older browsers
frames[-1].save(out, format='ICO', sizes=sizes, append_images=frames[:-1])
print('wrote', out, 'sizes:', sizes)
