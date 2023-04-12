.PHONY: test
test: vendor
	vendor/bin/phpunit

vendor: composer.json
	composer install --ignore-platform-reqs

.PHONY: clean
clean:
	rm -rf vendor
	rm -f tests/.phpunit.result.cache
