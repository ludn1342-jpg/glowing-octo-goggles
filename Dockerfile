FROM node:20-alpine

WORKDIR /app

COPY server.js index.html pago.html tarjetas.html ./
COPY *.png *.jpg *.svg *.ico ./

EXPOSE 3000

CMD ["node", "server.js"]
