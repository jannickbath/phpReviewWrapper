#!/bin/bash
echo "server {
		listen 8080;
		server_name ${1};
		root /var/www/${1}/src;
		
		error_log /var/www/${1}/logs/error.log;
		include contao4.conf;
	}" >> ~/www/dev-env/build/php72/config/nginx.conf

sudo echo "127.0.0.1       ${1}" >> /etc/hosts

cd ~/www/dev-env
sudo service mysql stop
sudo service apache2 stop

docker-compose down
docker-compose build
docker-compose up -d