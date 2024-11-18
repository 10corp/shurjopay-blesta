<?php return array(
    'root' => array(
        'name' => '10corp/shurjopay-blesta',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '71b91d0c3791dee6b8b0cdd6ae76e78ec43c8c46',
        'type' => 'blesta-gateway-nonmerchant',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        '10corp/shurjopay-blesta' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '71b91d0c3791dee6b8b0cdd6ae76e78ec43c8c46',
            'type' => 'blesta-gateway-nonmerchant',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'blesta/composer-installer' => array(
            'pretty_version' => '1.1.0',
            'version' => '1.1.0.0',
            'reference' => 'f0529f892e4cb0db5e9e949031965f212c7da269',
            'type' => 'composer-installer',
            'install_path' => __DIR__ . '/../blesta/composer-installer',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'composer/installers' => array(
            'pretty_version' => 'v1.12.0',
            'version' => '1.12.0.0',
            'reference' => 'd20a64ed3c94748397ff5973488761b22f6d3f19',
            'type' => 'composer-plugin',
            'install_path' => __DIR__ . '/./installers',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'roundcube/plugin-installer' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'shama/baton' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
    ),
);
