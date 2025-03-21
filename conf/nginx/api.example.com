# Virtual Host configuration for api.example.com
# Use this when api is accessible from a subdomain

server {
	listen 80;
	listen [::]:80;

	# stop outputting the version of Nginx and OS in headers
	#
	server_tokens off;

	server_name api.example.com;

	root /var/www/example/public/api;
	index index.php;

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
	# for any other request, rewrite to our OZone API entry point
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
