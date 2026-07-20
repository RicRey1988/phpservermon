#!/usr/bin/env bash
set -u
REPORT=.agent/focus-result.txt
PHPUNIT_REPORT=.agent/phpunit-result.txt
: > "$REPORT"
: > "$PHPUNIT_REPORT"
printf 'RUN phpunit-full\n' >> "$REPORT"
set +e
vendor/bin/phpunit --testdox --colors=never > "$PHPUNIT_REPORT" 2>&1
code=$?
set -e
if [ "$code" -ne 0 ]; then
  printf 'FAIL phpunit-full (%s)\n\n' "$code" >> "$REPORT"
  cat "$PHPUNIT_REPORT" >> "$REPORT"
  exit "$code"
fi
printf 'PASS phpunit-full\nALL PASS\n' >> "$REPORT"
