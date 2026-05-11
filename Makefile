# Makefile for OpenBuilt development
#
# Note: the repository directory name (`openbuilt`) already matches the
# app `<id>` in appinfo/info.xml, so no symlink is required for local
# Nextcloud dev. Keeping the dev-link / dev-unlink targets as no-ops
# preserves shell compatibility with workspace scripts that invoke them.

dev-link:
	@echo "No symlink needed — directory name 'openbuilt' already matches the app id."

dev-unlink:
	@echo "No symlink to remove — directory name 'openbuilt' already matches the app id."

.PHONY: dev-link dev-unlink
