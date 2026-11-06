# ==============================================================================
# AushadhSeva - Production Dockerfile
# SEVA First Innovation Challenge 2026 | Healthcare Problem H4
# ==============================================================================

FROM node:22-alpine

# Install curl/wget for health checks
RUN apk add --no-cache curl

# Set root workspace directory in container
WORKDIR /app

# 1. Install dependencies first (layer caching)
COPY react_node/package*.json ./react_node/
WORKDIR /app/react_node
RUN npm install

# 2. Copy clinical knowledge base data and application source code
WORKDIR /app
COPY FULL_CODE/data ./FULL_CODE/data
COPY react_node ./react_node

# 3. Build frontend static assets with Vite
WORKDIR /app/react_node
RUN npm run build

# 4. Set production runtime environment
ENV NODE_ENV=production
ENV PORT=5000
ENV DATA_DIR=/app/FULL_CODE/data

# 5. Expose application port
EXPOSE 5000

# 6. Container health check
HEALTHCHECK --interval=20s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -f http://localhost:5000/api/health || exit 1

# 7. Start Express clinical decision support server
CMD ["node", "server/index.js"]
