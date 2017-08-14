#!/bin/bash

ssh -o "StrictHostKeyChecking no" pbc_cg@dev.cg.providencedenver.org << EOF

echo '1. Updating sources'
cd /home/pbc_cg/dev.cg.providencedenver.org
git fetch --all
git checkout reporting
git pull

echo "2. Run Composer"
composer install

echo '3. Import Drupal Config'
cd /home/pbc_cg/dev.cg.providencedenver.org/web
drush --root=/home/pbc_cg/dev.cg.providencedenver.org/web cim -y
git checkout .htaccess'

EOF
