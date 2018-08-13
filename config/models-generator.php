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
        // 'oauth_access_tokens', 'oauth_auth_codes', 'oauth_clients', 'oauth_personal_access_clients', 'oauth_refresh_tokens',

        // Laratrust package
        // 'roles', 'permissions', 'teams', 'role_user', 'permission_role', 'permission_user',

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
        | 'users' => 'phones',
        |
        */

    ],

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
        */

    ],

];
