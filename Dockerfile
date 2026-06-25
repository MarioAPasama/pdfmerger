FROM php:8.1-apache

# Pasang Python3, pip, dan dependensi sistem yang dibutuhkan
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    && rm -rf /var/lib/apt/lists/*

# Aktifkan mod_rewrite Apache (berguna jika ada kebutuhan routing htaccess)
RUN a2enmod rewrite

# Tentukan folder kerja web server
WORKDIR /var/www/html

# Salin seluruh kode proyek ke dalam kontainer
COPY . /var/www/html/

# Pasang seluruh package Python yang dibutuhkan untuk merger dan konversi dokumen
RUN pip3 install --no-cache-dir --break-system-packages \
    pillow \
    pypdf \
    openpyxl \
    mammoth \
    xhtml2pdf \
    fpdf2

# Buat folder uploads, output, dan logs serta set hak akses untuk user Apache (www-data)
RUN mkdir -p uploads output logs && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Ekspos port standar HTTP 80
EXPOSE 80
