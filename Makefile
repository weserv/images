DEV_ROCKS = "busted 2.1.1" "luacheck 1.0.0" "lua-vips 1.1-9" "lua-resty-http 0.16" "lua-resty-template 2.0"
BUSTED_ARGS ?= -o gtest -v
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
