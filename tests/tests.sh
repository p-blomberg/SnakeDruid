echo "Running tests\n"
phpunit/phpunit --bootstrap "bootstrap.php" --verbose $@

ret=$?

if [ $ret -ne 0 ]; then
	exit $ret
fi
