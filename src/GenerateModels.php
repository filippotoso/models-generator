<?php

namespace FilippoToso\ModelsGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Schema;

class GenerateModels extends Command
{
    protected const OPEN_ROW = '<' . '?php' . "\n\n";

    protected const DEFAULT_MODELS_NAMESPACE = 'App\\Models';
    protected const DEFAULT_SUPPORT_NAMESPACE = 'App\\Models\\Support';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:models
                            {--overwrite : Overwrite already generated models}
                            {--connection=default : Which connection use}
                            {--schema=default : Which schema use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate models from the database';

    /**
     * Specify the database connection to be used
     *
     * @var string|null
     */
    protected $connection = null;

    /**
     * Specify the schema to be used
     *
     * @var string|null
     */
    protected $schema = null;

    /**
     * Specify if overwrite existing generated models
     *
     * @var boolean
     */
    protected $overwrite = false;

    /**
     * Database tables
     * @var array
     */
    protected $tables = [];

    /**
     * Database columns
     * @var array
     */
    protected $columns = [];

    /**
     * Database indexes
     * @var array
     */
    protected $indexes = [];

    /**
     * Database foreign keys
     * @var array
     */
    protected $foreignKeys = [];

    /**
     * Database relationships based on foreign keys
     * @var array
     */
    protected $relationships = [];

    /**
     * Many to many tables
     * @var array
     */
    protected $manyToMany = [];

    /**
     * Database primary keys
     * @var array
     */
    protected $primaryKeys = [];

    /**
     * Relationship uses declarations
     * @var array
     */
    protected $uses = [];

    /**
     * Table properties
     * @var array
     */
    protected $properties = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Normalize columns
     * @method normalizeColumns
     * @param  array $columns
     * @return array
     */
    protected function normalizeColumns($columns)
    {
        $results = [];

        foreach ($columns as $data) {
            $data['name'] = str_replace('`', '', $data['name']);
            $results[$data['name']] = $data;
        }

        return $results;
    }

    /**
     * Load the database information in local arrays
     * @method buildInternalData
     * @return void
     */
    protected function buildInternalData()
    {
        $this->tables = Schema::connection($this->connection)->getTableListing($this->schema, false);

        $this->columns = [];
        $this->indexes = [];
        $this->foreignKeys = [];
        $this->primaryKeys = [];
        $this->relationships = [];
        $this->uses = [];
        $this->properties = [];

        foreach ($this->tables as $table) {

            $this->columns[$table] = $this->normalizeColumns(Schema::connection($this->connection)->getColumns($table));
            $this->indexes[$table] = Schema::connection($this->connection)->getIndexes($table);
            $this->foreignKeys[$table] = Schema::connection($this->connection)->getForeignKeys($table);
            $this->primaryKeys[$table] = $this->primaryKey($table);
            $this->relationships[$table] = [];
            $this->properties[$table] = $this->properties($this->columns[$table]);
        }

        $this->buildManyToManyRelationships();
        $this->buildOneToOneRelationships();
        $this->buildPolymorphicRelationships();
        $this->buildRelationships();
        $this->buildUses();

        $this->handleAliases();
    }

    protected function primaryKey($table)
    {
        return head(array_filter($this->indexes[$table], function ($index) {
            return $index['primary'] ?? false;
        }))['columns'][0] ?? null;
    }

    protected function properties($columns)
    {
        return array_keys($columns);
    }

    protected function buildUses()
    {
        foreach ($this->relationships as $table => $relationships) {
            $this->uses[$table] = array_unique(array_map(function ($relationship) {
                return $relationship['class'];
            }, $relationships));

            $currentModel = $this->getModelName($table);
            $this->uses[$table] = array_filter($this->uses[$table], function ($use) use ($currentModel) {
                return $currentModel != $use;
            });
        }
    }

    protected function handleAliases()
    {
        $aliases = config('models-generator.aliases', []);

        foreach ($this->relationships as $table => &$relationships) {
            foreach ($relationships as &$relationship) {
                $relationship['name'] = isset($aliases[$table][$relationship['name']]) ? $aliases[$table][$relationship['name']] : $relationship['name'];
            }
        }
    }

    protected function buildOneToOneRelationships()
    {
        $oneToOne = config('models-generator.one_to_one', []);

        foreach ($oneToOne as $ownerTable => $ownedTables) {
            $ownedTables = is_array($ownedTables) ? $ownedTables : [$ownedTables];

            foreach ($ownedTables as $ownedTable) {
                $foreignKeys = array_where($this->foreignKeys[$ownedTable], function ($foreignKey) use ($ownerTable) {
                    return $foreignKey['foreign_table'] == $ownerTable;
                });

                if (count($foreignKeys) != 1) {
                    continue;
                }

                $foreignKey = head($foreignKeys);

                $remoteColumn = head($foreignKey['columns']);
                $foreignColumn = head($foreignKey['foreign_columns']);

                $this->relationships[$ownerTable][] = [
                    'type' => 'hasOne',
                    'name' => camel_case($this->relationshipName($ownedTable)),
                    'class' => $this->getModelName($ownedTable),
                    'foreign_key' => $remoteColumn,
                    'local_key' => $foreignColumn, // $this->primaryKeys[$ownerTable],
                ];
            }
        }
    }

    protected function relationshipName($element, $table = null)
    {
        return $this->singular($element);
    }

    protected function buildManyToManyRelationships()
    {
        $this->manyToMany = [];

        $tables = array_where($this->tables, function ($value, $key) {
            return str_contains($value, '_');
        });

        foreach ($tables as $table) {
            $foreignKeys = array_where($this->foreignKeys[$table], function ($foreignKey) use ($table) {
                $foreignModel = $this->singular($foreignKey['foreign_table']);

                if (ends_with($table, '_' . $foreignModel) || starts_with($table, $foreignModel . '_')) {
                    $localColumn = head($foreignKey['columns']);

                    if (ends_with($localColumn, '_id')) {
                        $potentialRemoteModel = substr($localColumn, 0, -3);

                        if ($potentialRemoteModel == $foreignModel) {
                            return true;
                        }
                    }
                }

                return false;
            });

            if (count($foreignKeys) != 2) {
                continue;
            }

            $foreignKeys = array_values($foreignKeys);

            $leftForeignKey = $foreignKeys[0];
            $rightForeignKey = $foreignKeys[1];

            $columns = array_diff(
                array_keys($this->columns[$table]),
                [$this->primaryKeys[$table], head($leftForeignKey['columns']), head($rightForeignKey['columns']), 'created_at', 'updated_at', 'deleted_at']
            );

            $this->manyToMany[$table][] = [
                'table' => $leftForeignKey['foreign_table'],
                'timestamps' => $this->getTimestamps($table),
                'columns' => $columns,
                'remote_table' => $rightForeignKey['foreign_table'],
                'foreign_key' => head($leftForeignKey['columns']),
                'local_key' => head($rightForeignKey['columns']),
            ];

            $this->manyToMany[$table][] = [
                'table' => $rightForeignKey['foreign_table'],
                'timestamps' => $this->getTimestamps($table),
                'columns' => $columns,
                'remote_table' => $leftForeignKey['foreign_table'],
                'foreign_key' => head($rightForeignKey['columns']),
                'local_key' => head($leftForeignKey['columns']),
            ];
        }

        foreach ($this->manyToMany as $manyToManyTables => $tables) {
            foreach ($tables as $table) {
                $this->relationships[$table['table']][] = [
                    'type' => 'belongsToMany',
                    'name' => camel_case($table['remote_table']),
                    'class' => $this->getModelName($table['remote_table']),
                    'table' => $manyToManyTables,
                    'foreign_key' => $table['foreign_key'],
                    'local_key' => $table['local_key'],
                    'timestamps' => $table['timestamps'],
                    'columns' => $table['columns'],
                ];
            }
        }
    }

    protected function getPolimorphicRelationshipName($table)
    {
        $columns = $this->columns[$table];

        foreach ($columns as $columnName => $column) {
            $fieldId = config('models-generator.polymorphic_suffix') . '_id';
            $fieldType = config('models-generator.polymorphic_suffix') . '_type';

            $typeColumn = str_replace($fieldId, $fieldType, $columnName);

            if (ends_with($columnName, $fieldId) && isset($columns[$typeColumn])) {
                return str_replace('_id', '', $columnName);
            }
        }

        return $this->relationshipName($table) . config('models-generator.polymorphic_suffix');
    }

    protected function buildPolymorphicRelationships()
    {
        $polimorphicRelationships = config('models-generator.polymorphic', []);

        foreach ($polimorphicRelationships as $polimorphicTable => $tables) {
            foreach ($tables as $table) {
                $this->relationships[$table][] = [
                    'type' => 'morphMany',
                    'name' => camel_case($polimorphicTable),
                    'class' => $this->getModelName($polimorphicTable),
                    'relationship' => $this->getPolimorphicRelationshipName($polimorphicTable),
                ];
            }

            $columns = $this->columns[$polimorphicTable];

            foreach ($columns as $idColumn => $column) {
                $typeColumn = str_replace('able_id', 'able_type', $idColumn);

                if (ends_with($idColumn, 'able_id') && isset($columns[$typeColumn])) {
                    $relationshipName = substr($idColumn, 0, -3);

                    $this->relationships[$polimorphicTable][$relationshipName] = [
                        'type' => 'morphTo',
                        'class' => $this->getModelName($polimorphicTable),
                        'name' => $relationshipName,
                    ];
                }
            }
        }
    }

    /**
     * Build the relationship data
     * @method buildRelationships
     * @return void
     */
    protected function buildRelationships()
    {
        foreach ($this->tables as $table) {

            if (in_array($table, config('models-generator.exclude', []))) {
                continue;
            }

            $this->relationships[$table] = isset($this->relationships[$table]) ? $this->relationships[$table] : [];

            // Skip many to many tables
            if (isset($this->manyToMany[$table])) {
                continue;
            }

            $currentForeignKeys = $this->foreignKeys[$table];

            foreach ($currentForeignKeys as $foreignKey) {
                $localColumn = head($foreignKey['columns']);
                $foreignColumn = head($foreignKey['foreign_columns']);
                $localName = ends_with($localColumn, '_id') ? substr($localColumn, 0, -3) : $localColumn;

                $foreignTable = $foreignKey['foreign_table'];

                $this->relationships[$table][] = [
                    'type' => 'belongsTo',
                    'name' => camel_case($this->relationshipName($localName, $foreignTable)),
                    'class' => $this->getModelName($foreignTable),
                    'foreign_key' => $localColumn,
                    'local_key' => $foreignColumn, // $this->primaryKeys[$table],
                ];

                $name = $table;

                if ($localName != $this->relationshipName($foreignTable)) {
                    $name = $localName . '_' . $name;
                }

                $this->relationships[$foreignTable][] = [
                    'type' => 'hasMany',
                    'name' => camel_case($name),
                    'class' => $this->getModelName($table),
                    'foreign_key' => $localColumn,
                    'local_key' => $foreignColumn, // $this->primaryKeys[$table],
                ];
            }
        }
    }

    /**
     * Generate the user's models
     * @method generateUserModel
     * @param  string $table The origin table
     * @return void
     */
    protected function generateUserModel($table)
    {
        $class = $this->getModelName($table);

        $namespace = config('models-generator.namespaces.models', static::DEFAULT_MODELS_NAMESPACE);

        $filename = app_path(sprintf($this->directory($namespace) . '/%s.php', $class));

        if (!file_exists($filename)) {

            $params = [
                'supportNamespace' => config('models-generator.namespaces.support', static::DEFAULT_SUPPORT_NAMESPACE),
                'namespace' => $namespace,
                'class' => $class,
            ];

            $content = self::OPEN_ROW . View::make('models-generator::user-model', $params)->render();

            $directory = dirname($filename);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($filename, $content);
        }
    }

    /**
     * Get the table columns list of different types
     * @method getTableColumns
     * @param  string          $table
     * @param  string          $type
     * @return array
     */
    protected function getTableColumns($table, $type)
    {
        $results = [];

        $columns = $this->columns[$table];
        $columnsNames = array_keys($columns);

        $primary = $this->primaryKey($table);

        $exclude = [
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        if ($type == 'fillable') {
            if (!is_null($primary)) {
                $exclude[] = $primary;
            }

            $results = array_diff($columnsNames, $exclude);
        } elseif ($type == 'attributes') {
            if (!is_null($primary)) {
                $exclude[] = $primary;
            }

            foreach ($columns as $columnName => $column) {
                if (in_array($columnName, $exclude)) {
                    continue;
                }


                $typeName = $column['type_name'];

                $default = is_string($column['default']) ? trim($column['default'], "'") : $column['default'];

                if (($column['default'] !== 'NULL') && in_array($typeName, ['integer', 'int', 'smallint', 'tinyint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double', 'bit'])) {
                    $default = (int) $default;
                }

                $results[$columnName] = ($default === 'NULL') ? null : $default;

                if (is_null($default) && !$column['nullable']) {
                    if (in_array($typeName, ['char ', 'varchar', 'binary', 'varbinary', 'enum', 'set', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'tinytext', 'text', 'mediumtext', 'longtext'])) {
                        $results[$columnName] = '';
                    } elseif (in_array($typeName, ['integer', 'int', 'smallint', 'tinyint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double', 'bit'])) {
                        $results[$columnName] = 0;
                    } elseif (in_array($typeName, ['json'])) {
                        $results[$columnName] = '[]';
                    } elseif (in_array($typeName, ['boolean'])) {
                        $results[$columnName] = false;
                    }
                }
            }
        } elseif ($type == 'casts') {
            foreach ($columns as $columnName => $column) {
                if (in_array($columnName, $exclude)) {
                    continue;
                }

                $columnType = $column['type_name'];

                if ($columnType == 'json') {
                    $results[$columnName] = 'array';
                } elseif (in_array($columnType, ['date', 'time', 'datetime', 'timestamp '])) {
                    $results[$columnName] = 'datetime';
                } elseif (in_array($columnType, ['tinyint'])) {
                    $results[$columnName] = 'boolean';
                }
            }
        }

        return $results;
    }

    protected function guessFromName($name, $size = null)
    {
        $name = strtolower($name);

        if (preg_match('/^is[_A-Z]/', $name)) {
            return '$faker->boolean';
        }

        if (preg_match('/(_a|A)t$/', $name)) {
            return '$faker->dateTime';
        }

        switch (str_replace('_', '', $name)) {
            case 'firstname':
                return '$faker->firstName';
            case 'lastname':
                return '$faker->lastName';
            case 'username':
            case 'login':
                return '$faker->userName';
            case 'email':
            case 'emailaddress':
                return '$faker->email';
            case 'phonenumber':
            case 'phone':
            case 'telephone':
            case 'telnumber':
                return '$faker->phoneNumber';
            case 'address':
                return '$faker->address';
            case 'city':
            case 'town':
                return '$faker->city';
            case 'streetaddress':
                return '$faker->streetAddress';
            case 'postcode':
            case 'zipcode':
                return '$faker->postcode';
            case 'state':
                return '$faker->state';
            case 'county':
                return '$faker->state';
            case 'country':
                switch ($size) {
                    case 2:
                        return '$faker->countryCode';
                    case 3:
                        return '$faker->countryISOAlpha3';
                    case 5:
                    case 6:
                        return '$faker->locale';
                    default:
                        return '$faker->country';
                }
            case 'latitude':
                return '$faker->latitude';
            case 'longitude':
                return '$faker->longitude';
            case 'domain':
                return '$faker->domainName';
            case 'locale':
                return '$faker->locale';
            case 'currency':
            case 'currencycode':
                return '$faker->currencyCode';
            case 'url':
            case 'website':
                return '$faker->url';
            case 'company':
            case 'companyname':
            case 'employer':
                return '$faker->company';
            case 'title':
                if ($size !== null && $size <= 10) {
                    return '$faker->title';
                }
                return '$faker->sentence';
            case 'body':
            case 'summary':
            case 'article':
            case 'description':
                return $this->getTextFaker($size);
        }

        return false;
    }

    protected function getTextFaker($size)
    {
        if (($size > 5) && ($size < 200)) {
            return sprintf('$faker->text(%d)', $size);
        }
        return '$faker->text()';
    }

    /**
     * Get the table timestamp status
     * @method getTimestamps
     * @param  string        $table
     * @return boolean
     */
    protected function getTimestamps($table)
    {
        return isset($this->columns[$table]['created_at']) && isset($this->columns[$table]['updated_at']);
    }

    /**
     * Get the table soft delete status
     * @method getSoftDeletes
     * @param  string        $table
     * @return boolean
     */
    protected function getSoftDeletes($table)
    {
        return isset($this->columns[$table]['deleted_at']);
    }

    /**
     * Get the table primary key
     * @method getSoftDeletes
     * @param  string        $table
     * @return string|null
     */
    protected function getPrimaryKey($table)
    {
        return $this->primaryKeys[$table];
    }

    /**
     * Checks if the table has an autoincrement primary key
     * @method getIncrementing
     * @param  string        $table
     * @return boolean
     */
    protected function getIncrementing($table)
    {
        $primary = $this->primaryKey($table);

        return $this->columns[$table][$primary]['auto_increment'] ?? false;
    }

    /**
     * Get the table relationships
     * @method getRelationships
     * @param  string        $table
     * @return array
     */
    protected function getRelationships($table)
    {
        return $this->relationships[$table];
    }

    /**
     * Get the table uses
     * @method getUses
     * @param  string        $table
     * @return array
     */
    protected function getUses($table)
    {
        return $this->uses[$table];
    }

    /**
     * Get the table properties
     * @method getProperties
     * @param  string        $table
     * @return array
     */
    protected function getProperties($table)
    {
        $relationships = array_map(function ($relationship) {
            return $relationship['name'];
        }, $this->relationships[$table]);

        $properties = array_merge($relationships, $this->properties[$table]);

        sort($properties);

        return $properties;
    }

    /**
     * Get the model name from the table name
     * @method getModelName
     * @param  string       $table
     * @return string
     */
    protected function getModelName($table)
    {
        $models = config('models-generator.models', []);
        return $models[$table] ?? studly_case($this->singular($table));
    }

    /**
     * Generate the generated models
     * @method generateModel
     * @param  string $table The origin table
     * @return void
     */
    protected function generateModel($table)
    {
        $class = $this->getModelName($table);

        $namespace = config('models-generator.namespaces.support', static::DEFAULT_SUPPORT_NAMESPACE);

        $filename = app_path(sprintf($this->directory($namespace) . '/%s.php', $class));

        if (!file_exists($filename) || $this->overwrite) {
            $this->comment(sprintf('Generating model for "%s" table.', $table));

            $params = [
                'namespace' => $namespace,
                'modelsNamespace' => config('models-generator.namespaces.models', static::DEFAULT_MODELS_NAMESPACE),
                'table' => $table,
                'class' => $class,
                'softDeletes' => $this->getSoftDeletes($table),
                'timestamps' => $this->getTimestamps($table),
                'primaryKey' => $this->getPrimaryKey($table),
                'incrementing' => $this->getIncrementing($table),
                'fillable' => $this->getTableColumns($table, 'fillable'),
                'attributes' => $this->getTableColumns($table, 'attributes'),
                'casts' => $this->getTableColumns($table, 'casts'),
                'relationships' => $this->getRelationships($table),
                'uses' => $this->getUses($table),
                'properties' => $this->getProperties($table),
            ];

            $content = self::OPEN_ROW . View::make('models-generator::generated-model', $params)->render();

            $directory = dirname($filename);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($filename, $content);

            $this->info(sprintf('Model "%s" successfully generated!', $class));
        } else {
            $this->error(sprintf('Model "%s" already exists (and no overwrite requested), skipping.', $class));
        }
    }

    protected function generateBaseModel()
    {
        $namespace = config('models-generator.namespaces.support', static::DEFAULT_SUPPORT_NAMESPACE);

        $filename = app_path($this->directory($namespace) . '/BaseModel.php');

        if (!file_exists($filename)) {
            $dir = dirname($filename);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $params = [
                'namespace' => $namespace,
            ];

            $content = self::OPEN_ROW . View::make('models-generator::base-model', $params)->render();

            $directory = dirname($filename);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($filename, $content);

            $this->info('BaseModel successfully generated!');
        } else {
            $this->comment("The class App\Models\Support\BaseModel already exists, don't overwrite.");
        }
    }

    /**
     * Copy the base model into the App\Models namespace
     * @method generateUserRelationshipsTrait
     * @return void
     */
    protected function generateUserRelationshipsTrait()
    {
        if (isset($this->relationships['users'])) {

            $namespace = config('models-generator.namespaces.support', static::DEFAULT_SUPPORT_NAMESPACE);

            $filename = app_path($this->directory($namespace) . '/Traits/UserRelationships.php');

            if (!file_exists($filename) || $this->overwrite) {
                $params = [
                    'namespace' => $namespace,
                    'modelsNamespace' => config('models-generator.namespaces.models', static::DEFAULT_MODELS_NAMESPACE),
                    'uses' => isset($this->uses['users']) ? $this->uses['users'] : [],
                    'relationships' => isset($this->relationships['users']) ? $this->relationships['users'] : [],
                ];

                $content = self::OPEN_ROW . View::make('models-generator::user-relationships', $params)->render();

                $directory = dirname($filename);

                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                file_put_contents($filename, $content);

                $this->info('UserRelationships trait successfully generated!');
            } else {
                $this->comment("The trait App\Models\Traits\UserRelationships already exists, don't overwrite.");
            }
        }
    }

    protected function directory($namespace)
    {
        $namespace = ($namespace == 'App') ? '' : $namespace;
        return str_replace(['App\\', '\\'], ['', '/'], $namespace);
    }

    /**
     * Add to blade an exportModelProperty directive
     * @method extendBlade
     * @return void
     */
    protected function extendBlade()
    {
        Blade::directive('exportModelProperty', function ($expression) {
            return "<?php
                if (is_array($expression)) {
                    echo(\"[\\n\");
                    foreach($expression as \$key => \$value) {
                        printf(\"        %s => %s,\\n\", var_export(\$key, true), var_export(\$value, true));
                    }
                    echo(\"    ];\\n\");
                } else {
                    echo(var_export($expression, true));
                }
            ?>";
        });
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->overwrite = (bool)$this->option('overwrite');

        $this->connection = $this->option('connection') == 'default' ? null : $this->option('connection');

        $this->schema = $this->option('schema') == 'default' ? null : $this->option('schema');

        $this->buildInternalData();

        $this->extendBlade();

        $this->info('Models generation started.');

        $tables = array_diff(
            Schema::connection($this->connection)->getTableListing($this->schema, false),
            config('models-generator.exclude')
        );

        $this->generateBaseModel();

        $this->generateUserRelationshipsTrait();

        foreach ($tables as $table) {

            // Skip many to many tables
            if (isset($this->manyToMany[$table])) {
                continue;
            }

            $this->generateModel($table);

            $this->generateUserModel($table);
        }

        foreach ($tables as $table) {

            // Skip many to many tables
            if (isset($this->manyToMany[$table])) {
                continue;
            }
        }

        $this->info('Models successfully generated!');
    }

    protected function singular($string)
    {
        return Str::singular($string);
    }
}
