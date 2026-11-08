FROM node:20-alpine
RUN apk add --no-cache php83 php83-apache2 php83-pdo php83-pdo_pgsql php83-curl
RUN sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf
WORKDIR /app
COPY server.js index.html pago.html tarjetas.html ./
COPY *.png *.jpg *.svg *.ico ./
COPY api ./api
EXPOSE 3000
EXPOSE 80
CMD ["sh", "-c", "httpd -D FOREGROUND & node server.js"]
