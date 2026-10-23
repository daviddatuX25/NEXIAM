SHELL := /bin/bash
COMPOSE_FILE ?= compose.yaml
COMPOSE := docker compose -f $(COMPOSE_FILE)
JITSI_COMPOSE := docker compose --env-file .env -f docker/jitsi/docker-compose.jitsi.yml

.PHONY: help up down stop restart status logs jitsi-up jitsi-down clean-orphans backup-db

help:
	@echo "NEXIAM Stack Management"
	@echo "========================"
	@echo "  make up            - Start core services in health-ordered sequence"
	@echo "  make down          - Gracefully drain queues and stop containers (preserves DB volume)"
	@echo "  make stop          - Gracefully drain queues and halt containers"
	@echo "  make restart       - Safe down followed by up"
	@echo "  make status        - Show container health and status"
	@echo "  make logs          - Tail container logs"
	@echo "  make jitsi-up      - Verify app health and start Jitsi conferencing"
	@echo "  make jitsi-down    - Stop Jitsi conferencing"
	@echo "  make clean-orphans - Remove stopped one-off containers"
	@echo "  make backup-db     - Create a timestamped pg_dump snapshot"

up:
	@echo "==> [nexiam] Starting core infrastructure..."
	$(COMPOSE) up -d postgres inference mailpit
	@echo "==> [nexiam] Waiting for PostgreSQL healthy state..."
	@until [ "$$($(COMPOSE) ps -q postgres | xargs docker inspect -f '{{.State.Health.Status}}' 2>/dev/null)" = "healthy" ]; do sleep 1; done
	@echo "==> [nexiam] Starting web application..."
	$(COMPOSE) up -d app
	@echo "==> [nexiam] Waiting for application HTTP readiness..."
	@until curl -fsS http://localhost:18000/up >/dev/null 2>&1; do sleep 1; done
	@echo "==> [nexiam] Starting queue workers and scheduler..."
	$(COMPOSE) up -d queue omr-verification-worker scheduler
	@echo "==> [nexiam] Stack is fully operational."
	@$(COMPOSE) ps

down:
	@echo "==> [nexiam] Stopping Jitsi conferencing (if active)..."
	-@$(JITSI_COMPOSE) down --remove-orphans 2>/dev/null || true
	@echo "==> [nexiam] Gracefully draining workers (up to 90s)..."
	-@$(COMPOSE) stop -t 90 queue omr-verification-worker 2>/dev/null || true
	@echo "==> [nexiam] Stopping remaining containers (persistent volumes preserved)..."
	$(COMPOSE) down --remove-orphans

stop:
	@echo "==> [nexiam] Gracefully draining workers..."
	-@$(COMPOSE) stop -t 90 queue omr-verification-worker 2>/dev/null || true
	$(COMPOSE) stop

restart: down up

status:
	@$(COMPOSE) ps

logs:
	@$(COMPOSE) logs -f --tail=100

jitsi-up:
	@echo "==> [jitsi] Verifying NEXIAM web application availability..."
	@curl -fsS http://localhost:18000/up >/dev/null 2>&1 || { echo "ERROR: NEXIAM app must be healthy at http://localhost:18000/up before starting Jitsi" >&2; exit 1; }
	@echo "==> [jitsi] Starting Jitsi stack..."
	$(JITSI_COMPOSE) up -d
	@$(JITSI_COMPOSE) ps

jitsi-down:
	@echo "==> [jitsi] Stopping Jitsi stack..."
	$(JITSI_COMPOSE) down --remove-orphans

clean-orphans:
	@echo "==> [nexiam] Removing stopped one-off containers..."
	$(COMPOSE) rm -f -s

backup-db:
	@mkdir -p backups
	@FILE="backups/nexiam_$$(date +%Y%m%d_%H%M%S).dump"; \
	echo "==> [nexiam] Dumping database to $$FILE..."; \
	$(COMPOSE) exec -T postgres pg_dump -U nexiam -d nexiam -Fc > "$$FILE" && \
	echo "==> [nexiam] Backup complete: $$FILE"
