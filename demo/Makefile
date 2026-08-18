# Aggregate demo targets (FrankenPHP under demo/symfony8).

.PHONY: help up up8 down down8 release-verify release-verify8 release-check release-check8 demo-smoke

help:
	@echo "HtmlToWordBundle demos"
	@echo "  make up               Start demo/symfony8 (REQ-DEMO-005 message)"
	@echo "  make up8              Alias of make up (Symfony 8, default port 8021)"
	@echo "  make down / down8     Stop demo/symfony8"
	@echo "  make demo-smoke       REQ-TEST-011: up -> HTTP 200 -> down"
	@echo "  make release-verify   Alias of demo-smoke"
	@echo "  make release-verify8  Alias of make release-verify"
	@echo "  make release-check    update-bundle + test + verify-http (REQ-DEMO-007, symfony8)"
	@echo "  make release-check8   Alias of make release-check"

up up8:
	@$(MAKE) -C symfony8 up

down down8:
	@$(MAKE) -C symfony8 down

# REQ-TEST-011: boot demo stack and expect HTTP 200
demo-smoke:
	@PORT=$$(grep "^PORT=" symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8021; \
	echo "Demo smoke: symfony8 (http://127.0.0.1:$$PORT)"; \
	$(MAKE) up8; sleep 8; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT" 2>/dev/null || echo "000"); \
	$(MAKE) down8 2>/dev/null || true; \
	if [ "$$code" != "200" ] && [ "$$code" != "302" ]; then echo "symfony8 failed with HTTP $$code"; exit 1; fi; \
	echo "symfony8 OK (HTTP $$code)"

release-verify release-verify8: demo-smoke

release-check release-check8:
	@$(MAKE) -C symfony8 release-check

update-bundle:
	@$(MAKE) -C symfony8 update-bundle

DEMOS := symfony8
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
