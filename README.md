# easy-html-purifier

A Laravel package to sanitize input using HTML Purifier

Require this package with composer:

```
composer require nocturnal/easy-html-purifier
```

Then publish config.

```
php artisan vendor:publish --provider="Nocturnal\EasyHtmlPurifier\EasyHtmlPurifierServiceProvider"
```

Then add the following keyword to app/Http/Kernel.php files:

```
protected $middlewareGroups = [
    'api' => [
       'easy-html-purifier'
    ],
];
```