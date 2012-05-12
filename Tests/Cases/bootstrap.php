<?php

$rootDir = dirname( dirname( dirname( __FILE__ ) ) );
require $rootDir . '/Tests/wordpress-tests/init.php';
require_once ABSPATH . '/wp-admin/includes/plugin.php';
activate_plugin( 'jpb-yourls/jpb-yourls.php' );
