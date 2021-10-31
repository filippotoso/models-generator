<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables
    |--------------------------------------------------------------------------
    |
    | This array specifies a list of tables for which the generator will not
    | create models. This is usually used to avoid the creation of Laravel
    | own models like jobs, queue, and so on.
    |
 */

    'exclude' => [

        // Laravel
        'cache', 'failed_jobs', 'jobs', 'migrations', 'password_resets', 'sessions', 'users',

        // Laravel Passport package
        'oauth_access_tokens', 'oauth_auth_codes', 'oauth_clients', 'oauth_personal_access_clients', 'oauth_refresh_tokens',

        // Laratrust package
        'roles', 'permissions', 'teams', 'role_user', 'permission_role', 'permission_user',

    ],

    /*
    |--------------------------------------------------------------------------
    | One To One Relationships
    |--------------------------------------------------------------------------
    |
    | Define the one to one relationships of your database.
    | In the following array include the owner table as key and owned table as value.
    | For instance, following the example in the Laravel documentation,
    | the owner will be 'users' and the owned table 'phones'.
    | Ref: https://laravel.com/docs/5.6/eloquent-relationships#one-to-one
    |
     */

    'one_to_one' => [

        /*
        |--------------------------------------------------------------------------
        | Laravel Documentation Example
        |--------------------------------------------------------------------------
        |
        | 'users' => [
        |    'phones',
        | ],
        |
     */],

    /*
     |--------------------------------------------------------------------------
     | Polymorphic Relationships
     |--------------------------------------------------------------------------
     |
     | Define the polymorphic relationships of your database.
     | In the following array include the morphTo table as key and the other tables as array values.
     | For instance, following the example in the Laravel documentation,
     | the morphTo table will be 'comments' and the other tables 'posts' and  'videos'.
     | Ref: https://laravel.com/docs/5.6/eloquent-relationships#polymorphic-relations
     |
     */

    'polymorphic' => [

        /*
        |--------------------------------------------------------------------------
        | Laravel Documentation Example
        |--------------------------------------------------------------------------
        |
        | 'comments' => [
        |     'posts',
        |     'videos',
        | ],
        |
     */],

    /*
    |--------------------------------------------------------------------------
    | Relationship aliases
    |--------------------------------------------------------------------------
    |
    | Define the aliases for the models relationships.
    | Let's say you have an owner_id foreign key in the projects table that
    | links a project to the relative owner user. Normally the generator will
    | create a relationship named ownerProjects from the foreign key and table
    | names. If you think about it, the right name should be something like
    | ownedProjects. Using the aliases config parameter you can rename the
    | generated relationships whatever you want. 
     */

    'aliases' => [

        /*
        |--------------------------------------------------------------------------
        | Example
        |--------------------------------------------------------------------------
        |
        | 'users' => [
        |     'ownerProjects' => 'ownedProjects',
        | ],
        |
     */],

    /*
    |--------------------------------------------------------------------------
    | Polimorphic fields suffic
    |--------------------------------------------------------------------------
    |
    | Define the suffix used to identify the field used in a polyorphic relationship.
    | By default, it's "able" to identify fields like translatable_id and translatable_type.  
     */

    'polymorphic_suffix' => 'able',


    /*
    |--------------------------------------------------------------------------
    | Models mapping
    |--------------------------------------------------------------------------
    |
    | Define a mapping between the table name and the model name.
    | It's useful when working on legacy databases or databases with tables named in another language.  
     */

    'models' =>  [
        /*
        |--------------------------------------------------------------------------
        | Example
        |--------------------------------------------------------------------------
        |
        | 'users' => 'Utente',
        |
     */],

];
