# Virtual Host configuration for api.example.com

server {
	listen 80;
	listen [::]:80;

	# stop outputting the version of Nginx and OS in headers
	#
	server_tokens off;

	server_name api.example.com;

	root /var/www/example/api;
	index index.php;

	# serve some basic file only if exists otherwise shout 404
	#
	location ~ ^/(favicon\.ico|robots\.txt)$ {
		try_files $uri =404;
		access_log off;
		log_not_found off;
	}

	# for any other request rewrite to our OZone entry point index.php
	#
	location / {
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
