# Virtual Host configuration for www.example.com

server {
	listen 80;
	listen [::]:80;

	# stop outputting the version of Nginx and OS in headers
	#
	server_tokens off;

	server_name www.example.com;

	root /var/www/example/www;
	index index.php;

	# serve file or directory only if exists otherwise shout 
	# pass it to OZone entry point index.php 
	#
	location / {
		try_files $uri $uri/ @handle;
		access_log off;
		log_not_found off;
	}

	# for any other request rewrite to our OZone entry point index.php
	#
	location @handle {
        	rewrite ^/.*$ /index.php last;
	}

	# pass OZone entry point index.php to PHP FastCGI server
	#
	location /index.php {
		include snippets/fastcgi-php.conf;
		fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
		fastcgi_param SCRIPT_FILENAME $document_root/index.php;
	}
}
