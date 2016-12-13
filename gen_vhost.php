<?php
reuqire_once('/lib/ConfigGen.php');
if (0 == !posix_getuid()) {
       echo 'You need to use this script as root!';
     exit(0);
}

if (!$argv[0] || strlen($argv[1]) < 2)
    throw new Exception("Please put domain as argument ", 1);

$domain                = $argv[1];
$config                = parse_ini_file('vhostgen.ini');
$pattern               = new $config['SERVER_TYPE']($domain, $config);
$pattern->generate();