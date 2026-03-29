# Build estático (produção) + Nginx para servir em /SignRhFlow/
FROM node:20-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# URL da API gravada no bundle (mesmo valor do NG_API_URL do front em runtime).
ARG NG_API_URL=https://matheuswastchuk.tech/SignRhFlow/api
RUN printf 'NG_API_URL=%s\n' "$NG_API_URL" > .env \
  && node scripts/sync-web-env.mjs --production \
  && npm run build

FROM nginx:1.27-alpine

COPY nginx-docker.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/signrhflow-web/browser /usr/share/nginx/html/SignRhFlow

EXPOSE 80
