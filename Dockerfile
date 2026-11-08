FROM node:20-alpine
RUN apk add --no-cache php83 php83-pdo php83-pdo_pgsql php83-curl php83-json php83-session
RUN ln -sf /usr/bin/php83 /usr/bin/php
WORKDIR /app
COPY server.js index.html pago.html tarjetas.html router.php ./
COPY *.png *.jpg *.svg *.ico ./
COPY api ./api
EXPOSE 3000
CMD ["sh", "-c", "php -S 0.0.0.0:8080 -t /app router.php & node server.js"]
