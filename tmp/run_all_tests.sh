#!/bin/bash
rm -f tmp/test_results.txt
for f in tests/Unit/*.php tests/Feature/*.php; do
  name=$(basename "$f")
  out=$(php -d memory_limit=2G vendor/vendor_path_placeholder 2>/dev/null; true)
  : # placeholder replaced below
  out=$(php -d memory_limit=2G vendor/phpunit/phpunit/phpunit --no-progress --path "$f" 2>&1)
  status=$?
  summary=$(echo "$out" | tail -3 | tr '\n' ' ' | cut -c1-300)
  echo "$name|EXIT=$status|$summary" >> tmp/test_results.txt
done
echo "ALL_DONE" >> tmp/test_results.txt
