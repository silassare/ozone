# Virtual Host configuration for www.example.com
# Use this for website

# Upstream to abstract backend connection(s) for php
upstream php {
	# With php-fpm (or other unix sockets):
	server unix:/var/run/php/php7.2-fpm.sock;
	# With php-cgi (or other tcp sockets):
	server 127.0.0.1:9000;
}

server {
	listen 80;
	listen [::]:80;

	# stop outputting the version of Nginx and OS in headers
	#
	server_tokens off;

	server_name www.example.com example.com;

	root /var/www/example/www;
	index index.php;

	# disable access to /oz_private/*
	#
	location ^~ /oz_private {
		deny all;
	}

	# disable access to debug.log
	#
	location = /debug.log {
		deny all;
	}

	# disable access to hidden files such as /.ht(pass|access)
	# except .well-known used by Let's Encrypt
	#
	location ~ /\.(?!well-known) {
		deny all;
	}

	# serve the robots.txt only if it exists
	#
	location = /robots.txt {
		try_files $uri =404;
		access_log off;
		log_not_found off;
	}

	# serve the favicon only if it exists
	#
	location = /favicon.ico {
		try_files $uri =404;
		access_log off;
		log_not_found off;
	}

	# serve files or directories only if they exist otherwise 
	# for any other request, rewrite to our O'Zone Web entry point
	#
	location / {
		try_files $uri $uri/ /index.php?$args;
		access_log off;
		log_not_found off;
	}

	# pass PHP scripts to FastCGI server
	#
	location ~ [^/]\.php(/|$) {
		# regex to split $uri to $fastcgi_script_name and $fastcgi_path
		#
		fastcgi_split_path_info ^(.+?\.php)(/.*)$;

		if (!-f $document_root$fastcgi_script_name) {
			return 404;
		}

		fastcgi_index index.php;

		include fastcgi_params;

		# Mitigate https://httpoxy.org/ vulnerabilities
		#
		fastcgi_param HTTP_PROXY "";

		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

		# pass to php upstream
		fastcgi_pass php;
	}
}
