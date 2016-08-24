[![Slack](https://img.shields.io/badge/join%20the%20conversation-on%20slack-green.svg)](https://mongodb-cakephp3.slack.com/messages/general/)

Mongodb for Cakephp3
========

An Mongodb datasource for CakePHP 3.0

## Installing via composer

Install [composer](http://getcomposer.org) and run:

```bash
composer require hayko/mongodb
```

## Connecting the Plugin to your application

add the following line in your config/bootstrap.php to tell your application to load the plugin:

```php
Plugin::load('Hayko/Mongodb');

```

## Defining a connection
Now, you need to set the connection in your config/app.php file:

```php
 'Datasources' => [
    'default' => [
        'className' => 'Hayko\Mongodb\Database\Connection',
        'driver' => 'Hayko\Mongodb\Database\Driver\Mongodb',
        'persistent' => false,
        'host' => 'localhost',
        'port' => 27017,
        'username' => '',
        'password' => '',
        'database' => 'devmongo',
        'ssh' => [
        	'host' => '',
			'port' => 22,
			'user' => '',
			'password' => '',
			'key' => [
				'public' => '',
				'private' => '',
				'passphrase' => ''
			]
		]
    ],
],
```

### SSH tunnel variables (in the 'ssh' array)
If you want to connect to MongoDB using a SSH tunnel, you need to set additional variables in your Datasource. Some variables are unnecessary, depending on how you intend to connect. IF you're connecting using a SSH key file, the ```['ssh']['key']['public']``` and ```['ssh']['key']['private']``` variables are necessary and the ```['ssh']['password']``` variable is unnecessary. If you're connecting using a text-based password (which is **not** a wise idea), the reverse is true. The function needs, at minimum, ```['ssh']['host']```, ```['ssh']['user']``` and one method of authentication to establish a SSH tunnel.

## Models
After that, you need to load Hayko\Mongodb\ORM\Table in your tables class:

```php
//src/Model/Table/YourTable.php

use Hayko\Mongodb\ORM\Table;

class CategoriesTable extends Table {

}
```

## Observations

The function find() works only in the old fashion way.
So, if you want to find something, you to do like the example:

```php
$this->Categories->find('all', ['conditions' => ['name' => 'teste']]);
$this->Categories->find('all', ['conditions' => ['name LIKE' => 'teste']]);
$this->Categories->find('all', ['conditions' => ['name' => 'teste'], 'limit' => 3]);
```

## LICENSE

[The MIT License (MIT) Copyright (c) 2013](http://opensource.org/licenses/MIT)
