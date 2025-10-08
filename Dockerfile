FROM php:8.2-apache

# Enable Apache rewrite dan ekstensi zip (untuk ekspor XLSX)
RUN a2enmod rewrite \
 && apt-get update && apt-get install -y libzip-dev unzip \
 && docker-php-ext-install zip

# Set DocumentRoot ke php/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
  /etc/apache2/sites-available/000-default.conf \
  /etc/apache2/apache2.conf \
  /etc/apache2/conf-available/*.conf

# Allow .htaccess
RUN printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' \
  > /etc/apache2/conf-available/htaccess.conf \
  && a2enconf htaccess

# Copy kode (pastikan folder 'php/' ada di root repo)
COPY ./php/ /var/www/html/

# Permission aman untuk Apache
RUN chown -R www-data:www-data /var/www/html
