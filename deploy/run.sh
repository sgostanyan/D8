#!/bin/bash

cd ../ &&
composer install -n &&
drush updb -y &&
drush cim -y &&
drush cr
