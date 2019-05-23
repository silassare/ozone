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

	# disable access to oz_private directory
	#
	location ^~ /oz_private/ {
		return 404;
	}

	# disable access to debug.log file
	#
	location ^~ ^/debug\.log$ {
		return 404;
	}

	# serve file or directory only if exists otherwise
	# pass it to O'Zone entry point index.php
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

	# let PHP FastCGI server to handle PHP files
	#
	location ~ [^/]\.php(/|$) {
		include snippets/fastcgi-php.conf;

		fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
	}
}
