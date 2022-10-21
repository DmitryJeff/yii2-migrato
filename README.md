
Migration tool for Yii2 projects
============================

Provides a console controller that executes commands defined in a yml file. 

Installation
------------
1. Add `opus-online/yii2-migrato` to you composer. 
2. Add this to your console configuration
```
    'controllerMap' => [
        'db' => \opus\migrato\controllers\DbController::class
    ],
```
3. Make sure the `@root` alias is defined by adding this to `common/config/bootstrap.php`
```
Yii::setAlias('root', dirname(dirname(__DIR__)));
```
4. Run `php yii db`. You can also skip the previous step and use a custom alias by running `php yii db --config=@your/custom/alias`

Running tests
-------------
Run `composer install` and then in the project root directory
```
./vendor/bin/phpunit
```

TODO
----
* Unit tests
* Documentation
