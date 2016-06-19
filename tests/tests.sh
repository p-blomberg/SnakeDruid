#!/bin/bash
# this is to make all_tests.sh independent of the current directory
DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
$DIR/phpunit/phpunit --color -v --bootstrap $DIR/bootstrap.php --disallow-test-output --report-useless-tests $DIR/suites

ret=$?
if [ $ret -ne 0 ]; then
	exit $ret
fi
