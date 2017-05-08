## Laravel Simple Crud

This is a very light CRUD generator for Laravel 5.4 without all the bloat. It uses Twitter Bootstrap for easy integration.

It requires an already working database connection and it generates a CRUD for an existing table.
The resulting route will be /model (example /posts) with a list of all records and functionality to create, edit and delete records.


## Installation

1. Install with

```
composer require agustind/laravel-simple-crud dev-master
```


2. Add service provider to /config/app.php file

```
'providers' => [
    ...
    Agustind\Crud\CrudServiceProvider::class,
],
```


3. Use the new artisan command

```
php artisan crud:generate table_name
