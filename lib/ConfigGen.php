<?php

abstract class ConfigGenerator {
    
    public function __construct($hostname, $config) {
        
        if (empty($hostname) || empty($config))
            throw new Exception("Error Processing Request", 1);
        
        
        $this->hostname = $hostname;
        foreach ($config as $line => $prop) {
            $aprop        = strtolower($line);
            $this->$aprop = $prop;
        }
    }
    
    public function generate() {
        $methods = get_class_methods($this);
        
        unset($methods[0]);
        unset($methods[1]);
        foreach ($methods as $method)
            $this->$method();
    }
    
}

class NGINX extends ConfigGenerator {
    
    
    public function __construct() {
        parent::__construct();
    }
    
    public function addUserAndGroup() {
        //fpm setup 
        shell_exec("groupadd {$this->hostname}");
        shell_exec("useradd -g {$this->hostname} {$this->hostname}");
        shell_exec("service php5-fpm restart");
    }
    
    public function makeFpmTemplate() {
        
        $fpm_template = ' [{$hostname}]
user = {$hostname}
group = {$hostname}
listen = /var/run/php5-fpm-site1.sock
listen.owner = www-data
listen.group = www-data
php_admin_value[disable_functions] = exec,passthru,shell_exec,system
php_admin_flag[allow_url_fopen] = off
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
chdir = /
';
        $template     = str_replace('{$hostname}', $this->hostname, $fpm_template);
        
        file_put_contents("{$this->hostname}.conf", $template);
        shell_exec("mv {$this->hostname}.conf /etc/php5/fpm/pool.d/{$this->hostname}.conf");
    }
    
    public function makeNginXTemplate() {
        $template = 'server {
    listen 80;';
        if ($this->ssl_cert) {
            $template .= '     listen 443 ssl;
    ssl_certificate {$certs_dir}/{$hostname}.crt;
    ssl_certificate_key {$certs_dir}/{$hostname}.key;
';
            $template = str_replace('{$certs_dir}', $this->cert_dir, $template);
        }
        $template .= ' 
    root {$virtualhosts_dir}/{$hostname};
    index index.php index.html index.htm;

    server_name {$hostname};

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php5-fpm-site1.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}';
        $template = str_replace('{$hostname}', $this->hostname, $template);
        file_put_contents($this->hostname . '.conf', $template);
    }
    
    public function setupServer() {
        $conf = $this->hostname . '.conf';
        shell_exec("mv {$conf} /etc/nginx/sites-available/");
        shell_exec("mkdir {$virtualhosts_dir}/{$this->hostname}");
        shell_exec("git clone {$this->git_method}://{$this->git_user}:{$this->git_pass}@{$this->git_ip}:$this->git_port}/{$this->hostname}.git");
        shell_exec("cd {$this->hostname}/sql; mysql {$this->hostname} < {$this->hostname}_shema.sql");
        
        shell_exec("cd ..; cp -R {$hostname} {$this->vhost_dir}/{$this->hostname}/");
        shell_exec("sudo ln -s /etc/nginx/sites-available/{$this->hostname} /etc/nginx/sites-enabled/{$this->hostname}");
        shell_exec("service nginx restart");
        shell_exec("service php5-fpm restart");
    }
}


class APACHE extends ConfigGenerator {
    
    public function __construct($hostname, $config) {
        parent::__construct($hostname, $config);
    }
    public function generate() {
        parent::generate();
    }
    
    
    public function template() {
        
        $template = ' <VirtualHost *:80 *:443 >

    ServerAdmin webmaster@{$hostname}
    ServerName  {$hostname}
    ServerAlias www.{$hostname}';
        if ($this->ssl_cert) {
            
            $template .= '
        SSLEngine on
        SSLOptions +FakeBasicAuth +ExportCertData +StrictRequire
        SSLCertificateFile {$certdir}/www.{$hostname}.crt
        SSLCertificateKeyFile {$certdir}/www.{$hostname}.key
        SSLCertificateChainFile  {$certdir}/intermediate.ca-crt
        ';
            $template = str_replace('{$certdir}', $this->cert_dir, $template);
        }
        
        $template .= '
    Documentroot  /var/www/virtualhosts/{$hostname}
    <Directory />
        Options FollowSymLinks
        AllowOverride All
    </Directory>

    <Directory /var/www/virtualhosts/{$hostname}/>
    Options Indexes FollowSymLinks MultiViews
    AllowOverride all
    Order allow,deny
    allow from all
    </Directory>

    ScriptAlias /cgi-bin/ /usr/lib/cgi-bin/
    <Directory "/usr/lib/cgi-bin">
        AllowOverride All
        Options +ExecCGI -MultiViews +SymLinksIfOwnerMatch
        Order allow,deny
        Allow from all
    </Directory>

    ErrorLog /var/log/apache2/{$hostname}-error.log

    # Possible values include: debug, info, notice, warn, error, crit,
    # alert, emerg.
    LogLevel debug

    CustomLog /var/log/apache2/{$hostname}-access.log combined

</VirtualHost>

';
        $template = str_replace('{$hostname}', $this->hostname, $template);
        file_put_contents($this->hostname . '.conf', $template);
        
    }
    
    public function setupServer() {
        shell_exec("mv {$this->hostname}.conf /etc/apache2/sites-available/");
        shell_exec("mkdir {$virtualhosts_dir}/{$hostname}");
        shell_exec("git clone {$this->git_method}://{$this->git_user}:{$this->git_pass}@{$this->git_ip}:$this->git_port}/{$this->hostname}.git");
        shell_exec("cd {$this->hostname}/sql; mysql {$this->hostname} < {$this->hostname}_shema.sql");
        
        shell_exec("cd ..; cp -R {$hostname} {$virtualhosts_dir}/{$hostname}/");
        shell_exec("a2ensite {$this->hostname}");
        shell_exec("service apache2 restart");
    }
    
}