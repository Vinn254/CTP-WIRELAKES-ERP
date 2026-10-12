from PIL import Image
src = r'C:\Users\ADMIN\Downloads\CTP-WireLakes\logo.PNG'
img = Image.open(src).convert('RGBA')
sizes = [(16, 16), (24, 24), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)]
frames = [img.resize(s, Image.LANCZOS) for s in sizes]
out = r'C:\Users\ADMIN\Downloads\CTP-WireLakes\app\windows\runner\resources\app_icon.ico'
frames[-1].save(out, format='ICO', sizes=sizes, append_images=frames[:-1])
print('wrote', out, 'sizes:', sizes)
