#!/bin/bash
# =============================================================================
# Скрипт настройки HTTPS (Let's Encrypt) на сервере mephi22.ru
# Запускать от root на сервере:  bash docker/setup_https.sh
# =============================================================================

set -e

DOMAIN="mephi22.ru"
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "==> [1/4] Устанавливаем certbot..."
apt-get update -q
apt-get install -y certbot

echo "==> [2/4] Останавливаем nginx (освобождаем порт 80)..."
docker compose -f "$PROJECT_DIR/docker-compose.yml" stop nginx

echo "==> [3/4] Получаем SSL-сертификат для $DOMAIN..."
certbot certonly --standalone \
    -d "$DOMAIN" \
    -d "www.$DOMAIN" \
    --non-interactive \
    --agree-tos \
    --email admin@$DOMAIN

echo "==> [4/4] Поднимаем контейнеры обратно..."
docker compose -f "$PROJECT_DIR/docker-compose.yml" up -d

echo ""
echo "✓ HTTPS настроен. Сайт доступен по адресу https://$DOMAIN"
echo ""
echo "--- Автообновление сертификата (добавить в crontab) ---"
echo "0 3 * * * certbot renew --pre-hook 'docker compose -f $PROJECT_DIR/docker-compose.yml stop nginx' --post-hook 'docker compose -f $PROJECT_DIR/docker-compose.yml start nginx' >> /var/log/certbot-renew.log 2>&1"
echo "-------------------------------------------------------"
echo "Добавить командой: crontab -e"
