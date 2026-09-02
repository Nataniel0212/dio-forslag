# Bara för lokal utveckling. one.com-servern har redan PHP med pdo_mysql —
# den här filen finns för att kunna köra samma sak här hemma.
FROM php:8.2-cli
RUN docker-php-ext-install pdo_mysql
