DEV_ROCKS = "busted 2.0.rc13" "luacheck 0.23.0" "luacov-coveralls 0.2.2" "lua-vips 1.1-9" "lua-resty-http 0.13" "lua-resty-template 1.9"
BUSTED_ARGS ?= -v
TEST_CMD ?= .ci/busted $(BUSTED_ARGS)

.PHONY: dev lint test

dev:
	@for rock in $(DEV_ROCKS) ; do \
	  if luarocks list --porcelain $$rock | grep -q "installed" ; then \
	    echo $$rock already installed, skipping ; \
	  else \
	    echo $$rock not found, installing via luarocks... ; \
	    luarocks install $$rock ; \
	  fi \
	done;

lint:
	@luacheck -q .

test:
	@$(TEST_CMD) spec/
