FROM node:20-alpine

WORKDIR /app

COPY server.js index.html pago.html tarjetas.html ./
COPY *.png *.jpg *.svg *.ico ./

# --- AGREGA ESTA LÍNEA ---
COPY api ./api
# -------------------------

EXPOSE 3000

CMD ["node", "server.js"]
