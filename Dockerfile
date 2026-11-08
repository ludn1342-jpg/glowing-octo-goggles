FROM node:20-alpine

# PHP + todas las extensiones que necesitan tus archivos
RUN apk add --no-cache \
    php83 \
    php83-pdo \
    php83-pdo_pgsql \
    php83-pgsql \
    php83-curl \
    php83-json \
    php83-session \
    php83-openssl \
    php83-mbstring \
    php83-ctype \
    php83-tokenizer \
 && ln -sf /usr/bin/php83 /usr/bin/php

WORKDIR /app
RUN npm install axios
COPY server.js index.html pago.html tarjetas.html router.php ./
COPY *.php ./
COPY *.html ./
COPY *.png *.jpg *.svg *.ico ./
COPY api ./api

EXPOSE 3000

CMD ["sh", "-c", "php -S 0.0.0.0:8080 -t /app router.php & node server.js"]
