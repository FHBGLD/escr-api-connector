#!/bin/sh

#!/bin/sh

echo "download composer"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
echo "verify composer"
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]
then
    >&2 echo 'ERROR: Invalid installer checksum'
    rm composer-setup.php
    exit 1
fi

echo "call composer setup"
php composer-setup.php
echo "unlink"
php -r "unlink('composer-setup.php');"
