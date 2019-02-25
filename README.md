# Models Generator

A Laravel Artisan command to automatically generate models from database tables.

## Requirements

- PHP 5.6+
- Laravel 5.4+
- Doctrine DBAL +2.8

## Installing

Use Composer to install it:

```
composer require filippo-toso/models-generator
```

## How does it work?

This generator is very simple. It builds the models from the database and saves them in the App\Models namespace. Then extends these models with the user's counterpart in the App namespace. If you follow Laravel's guidelines for tables and columns naming, it works like a charm ;)

After you have executed the first generation, you can go and customize the models in the App namespace as usual. If you change the database (as often happens during development), you can run the generator again (with the override option enabled) and get an updated set of models without any additional effort.

This solution leaves you the benefit of automatic code generation plus the ability to add/change the behavior of your models (ie. change attributes visibility, add relationships, and so on).

By default the generator doesn't create the models of Laravel's tables like jobs, cache, and so on. You can modify this behavior publishing the package resources and editing the config/models-generator.php file.

## Configuration

You can public the configuration file with the following command:

```
php artisan vendor:publish --tag=config --provider="FilippoToso\ModelsGenerator\ServiceProvider"
```

The config/model-generator.php file allows you to:

- define which tables exclude form the generation (ie. cache, jobs, migrations, ...)
- define one to one relationships
- define polymorphic relationships

Just open the file and read the comments :)

Keep in mind that the one to many and many to many relationships are built using the foreign keys you define in the database.

## Options

The predefined use from command line is:

```
php artisan generate:models
```

This command executes the following steps:

- Creates an App\Models namespace.
- Fills it with the Models from the database.
- Creates the user editable models in the App namespace.
- Creates the App\Models\Traits\UserRelationships trait for the User model.

If there are existing models in the App\Models namespace they will not be overwritten by default (see the next example).
If there are existing models in the App namespace they will never be overwritten.

You can modify the default behavior using the following parameters:

```
php artisan generate:models --overwrite
```

With the overwrite option the generator will always overwrite the models in the App\Models namespace. This can be done safely if you follow the rule to not change these models but edit the ones in the App namespace.

```
php artisan generate:models --connection=sqlite
```

You can specify a different connection if you need to.

## Workflow

To gain the maximum benefits from this package you should follow this workflow:

- design the database
- write the migrations (including all the required foreign keys)
- migrate the database
- configure the generator
- run the generator
- customize the models in the App namespace

Then, every time you create and run a new migration, you should execute the generator again to keep the models in sync with the database.

You must also follow Laravel's guidelines about tables and columns names otherwise the generator will not be able to identify the existing relationships.
