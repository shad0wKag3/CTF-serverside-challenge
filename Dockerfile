FROM php:8.1-apache

# Set working directory
WORKDIR /var/www/html

# Enable AllowOverride so .htaccess in uploads is active
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy all application files to Apache root
COPY . /var/www/html/

# Ensure uploads directory exists and has writable permissions
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod 777 /var/www/html/uploads

# Copy flag to root filesystem and set read-only permission (444)
COPY flag.txt /flag.txt
RUN chmod 444 /flag.txt

# Expose HTTP port
EXPOSE 80

CMD ["apache2-foreground"]
