import os
import sys
import json
from PIL import Image
from pypdf import PdfWriter

base_dir = os.path.dirname(os.path.abspath(__file__))  # path absolut
input_file = os.path.join(base_dir, sys.argv[1])
output_dir = os.path.join(base_dir, "output")

# Tentukan nama file output
if len(sys.argv) > 2:
    output_filename = sys.argv[2]
else:
    output_filename = "hasil_gabungan.pdf"

output_path = os.path.join(output_dir, output_filename)

print(f"Base dir: {base_dir}")
print(f"Input file: {input_file}")

# Baca input.json
with open(input_file, 'r') as f:
    all_files = json.load(f)

print("File input:", all_files)

image_files = [f for f in all_files if f.lower().endswith(('.png', '.jpg', '.jpeg'))]
pdf_files = [f for f in all_files if f.lower().endswith('.pdf')]
converted_images = []

# Convert images ke PDF
for img in image_files:
    try:
        image_path = os.path.join(base_dir, img)
        image = Image.open(image_path).convert('RGB')
        temp_pdf = image_path + ".temp.pdf"
        image.save(temp_pdf)
        converted_images.append(temp_pdf)
        print(f"Converted: {img} -> {temp_pdf}")
    except Exception as e:
        print(f"Gagal convert {img}: {str(e)}")

# Gabungkan semua PDF
merger = PdfWriter()
for pdf in pdf_files + converted_images:
    full_pdf_path = os.path.join(base_dir, pdf) if not pdf.endswith(".temp.pdf") else pdf
    if os.path.exists(full_pdf_path):
        merger.append(full_pdf_path)
        print(f"Ditambahkan: {full_pdf_path}")
    else:
        print(f"Tidak ditemukan: {full_pdf_path}")

# Pastikan folder output ada
if not os.path.exists(output_dir):
    os.makedirs(output_dir)
    print("Folder output dibuat.")

# Simpan hasil
try:
    merger.write(output_path)
    merger.close()
    print("Gabungan disimpan di:", output_path)
except Exception as e:
    print("Gagal menyimpan PDF:", str(e))

# Hapus file sementara
for temp in converted_images:
    if os.path.exists(temp):
        os.remove(temp)
        print("File sementara dihapus:", temp)
