#!/usr/bin/env bash
set -e

export BUSTED_ARGS="-o gtest -v"
export TEST_CMD=".ci/busted $BUSTED_ARGS"

make lint
make test
