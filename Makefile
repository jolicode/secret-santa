serve:
	bin/console server:run

cs:
	./vendor/bin/php-cs-fixer fix --verbose

cs_dry_run:
	./vendor/bin/php-cs-fixer fix --verbose --dry-run

tests:
	./vendor/bin/simple-phpunit
