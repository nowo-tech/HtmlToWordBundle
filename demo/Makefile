# Aggregate demo targets (FrankenPHP under demo/symfony8).

.PHONY: help up up8 release-verify release-verify8 release-check release-check8

help:
	@echo "HtmlToWordBundle demos"
	@echo "  make up               Start demo/symfony8 (REQ-DEMO-005 message)"
	@echo "  make up8              Alias of make up (Symfony 8, default port 8021)"
	@echo "  make release-verify   HTTP smoke check (delegates verify-http, symfony8)"
	@echo "  make release-verify8  Alias of make release-verify"
	@echo "  make release-check    update-bundle + test + verify-http (REQ-DEMO-007, symfony8)"
	@echo "  make release-check8   Alias of make release-check"

up up8:
	@$(MAKE) -C symfony8 up

release-verify release-verify8:
	@$(MAKE) -C symfony8 verify-http

release-check release-check8:
	@$(MAKE) -C symfony8 release-check

DEMOS := symfony8
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
