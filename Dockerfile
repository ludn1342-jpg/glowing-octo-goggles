FROM node:20-alpine

# Instalamos PHP y Apache en el mismo contenedor
RUN apk add --no-cache php83 php83-apache2 php83-pdo php83-pdo_pgsql php83-curl

# Habilitamos el módulo de reescritura de Apache
RUN sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf

WORKDIR /app

# Copiamos tu código de Node
COPY server.js index.html pago.html tarjetas.html ./
COPY *.png *.jpg *.svg *.ico ./

# Copiamos la carpeta api
COPY api ./api

# Configuramos Apache para que escuche en el puerto 80 (por defecto)
# y Node en el 3000 (tu server.js ya lo hace)
EXPOSE 3000
EXPOSE 80

# Iniciamos Apache y Node juntos
CMD ["sh", "-c", "httpd -D FOREGROUND & node server.js"]
