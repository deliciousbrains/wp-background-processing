.PHONY: test
test: test-unit test-style

.PHONY: test-unit
test-unit: vendor
	vendor/bin/phpunit

.PHONY: test-style
test-style: vendor
	vendor/bin/phpcs

vendor: composer.json
	composer install --ignore-platform-reqs

.PHONY: clean
clean:
	rm -rf vendor
	rm -f tests/.phpunit.result.cache .phpunit.result.cache
