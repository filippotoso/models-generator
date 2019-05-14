<?php

namespace FilippoToso\ModelsGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;

use Doctrine\DBAL\Types\TimeType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\VarDateTimeType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\JsonArrayType;
use Doctrine\DBAL\Types\ObjectType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\ArrayType;
use Doctrine\DBAL\Types\Type;
use Illuminate\Database\Schema\Builder;

class GenerateModels extends Command
{
    protected const OPEN_ROW = '<' . '?php' . "\n\n";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:models
                            {--overwrite= : Overwrite already generated models. Available options: all, models, factories}
                            {--factories : Specify if the generator has to create database factories too}
                            {--connection=default : Which connection use}';

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
     * Specify if overwrite existing generated models
     *
     * @var boolean
     */
    protected $overwrite = false;

    /**
     * Specify if the generator has to create database factories too
     *
     * @var boolean
     */
    protected $factories = false;

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

        foreach ($columns as $column => $data) {
            $column = str_replace('`', '', $column);
            $results[$column] = $data;
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
        $this->tables = DB::connection($this->connection)->getDoctrineSchemaManager()->listTableNames();

        $this->columns = [];
        $this->indexes = [];
        $this->foreignKeys = [];
        $this->primaryKeys = [];
        $this->relationships = [];
        $this->uses = [];

        foreach ($this->tables as $table) {
            $this->columns[$table] = $this->normalizeColumns(DB::connection($this->connection)->getDoctrineSchemaManager()->listTableColumns($table));
            $this->indexes[$table] = DB::connection($this->connection)->getDoctrineSchemaManager()->listTableIndexes($table);
            $this->foreignKeys[$table] = DB::connection($this->connection)->getDoctrineSchemaManager()->listTableForeignKeys($table);
            $this->primaryKeys[$table] = isset($this->indexes[$table]['primary']) ? head($this->indexes[$table]['primary']->getColumns()) : null;
            $this->relationships[$table] = [];
        }

        $this->buildManyToManyRelationships();
        $this->buildOneToOneRelationships();
        $this->buildPolymorphicRelationships();
        $this->buildRelationships();
        $this->buildUses();

        $this->handleAliases();
    }

    protected function buildUses()
    {
        foreach ($this->relationships as $table => $relationships) {
            $this->uses[$table] = array_unique(array_map(function ($relationship) {
                return $relationship['class'];
            }, $relationships));
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

        foreach ($oneToOne as $ownerTable => $ownedTable) {
            $foreignKeys = array_where($this->foreignKeys[$ownedTable], function ($foreignKey) use ($ownerTable) {
                return $foreignKey->getForeignTableName() == $ownerTable;
            });

            if (count($foreignKeys) != 1) {
                continue;
            }

            $foreignKey = head($foreignKeys);

            $remoteColumn = head($foreignKey->getLocalColumns());

            $this->relationships[$ownerTable][] = [
                'type' => 'hasOne',
                'name' => camel_case(str_singular($ownedTable)),
                'class' => $this->getModelName($ownedTable),
                'foreign_key' => $remoteColumn,
                'local_key' => $this->primaryKeys[$ownerTable],
            ];
        }
    }

    protected function buildManyToManyRelationships()
    {
        $this->manyToMany = [];

        $tables = array_where($this->tables, function ($value, $key) {
            return str_contains($value, '_');
        });

        foreach ($tables as $table) {
            $foreignKeys = array_where($this->foreignKeys[$table], function ($foreignKey) use ($table) {
                $foreignModel = str_singular($foreignKey->getForeignTableName());

                if (ends_with($table, '_' . $foreignModel) || starts_with($table, $foreignModel . '_')) {
                    $localColumn = head($foreignKey->getLocalColumns());

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
                [$this->primaryKeys[$table], head($leftForeignKey->getLocalColumns()), head($rightForeignKey->getLocalColumns()), 'created_at', 'updated_at', 'deleted_at']
            );

            $this->manyToMany[$table][] = [
                'table' => $leftForeignKey->getForeignTableName(),
                'timestamps' => $this->getTimestamps($table),
                'columns' => $columns,
                'remote_table' => $rightForeignKey->getForeignTableName(),
                'foreign_key' => head($leftForeignKey->getLocalColumns()),
                'local_key' => head($rightForeignKey->getLocalColumns()),
            ];

            $this->manyToMany[$table][] = [
                'table' => $rightForeignKey->getForeignTableName(),
                'timestamps' => $this->getTimestamps($table),
                'columns' => $columns,
                'remote_table' => $leftForeignKey->getForeignTableName(),
                'foreign_key' => head($rightForeignKey->getLocalColumns()),
                'local_key' => head($leftForeignKey->getLocalColumns()),
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

        return str_singular($table) . config('models-generator.polymorphic_suffix');

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
            $this->relationships[$table] = isset($this->relationships[$table]) ? $this->relationships[$table] : [];

            // Skip many to many tables
            if (isset($this->manyToMany[$table])) {
                continue;
            }

            $relationships = [];

            $currentForeignKeys = $this->foreignKeys[$table];

            foreach ($currentForeignKeys as $foreignKey) {
                $localColumn = head($foreignKey->getLocalColumns());
                $localName = ends_with($localColumn, '_id') ? substr($localColumn, 0, -3) : $localColumn;

                $foreignTable = $foreignKey->getForeignTableName();

                $this->relationships[$table][] = [
                    'type' => 'belongsTo',
                    'name' => camel_case(str_singular($localName)),
                    'class' => $this->getModelName($foreignTable),
                    'foreign_key' => $localColumn,
                    'local_key' => $this->primaryKeys[$table],
                ];

                $name = $table;

                if ($localName != str_singular($foreignTable)) {
                    $name = $localName . '_' . $name;
                }

                $this->relationships[$foreignTable][] = [
                    'type' => 'hasMany',
                    'name' => camel_case($name),
                    'class' => $this->getModelName($table),
                    'foreign_key' => $localColumn,
                    'local_key' => $this->primaryKeys[$table],
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

        $filename = app_path(sprintf('%s.php', $class));

        if (!file_exists($filename)) {
            $params = [
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

        $indexes = $this->indexes[$table];
        $primary = isset($indexes['primary']) ? head($indexes['primary']->getColumns()) : null;

        $exclude = [
            'created_at', 'updated_at', 'deleted_at',
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

                try {
                    $default = $column->getType()->convertToPHPValue(
                        $column->getDefault(),
                        DB::connection($this->connection)->getDoctrineSchemaManager()->getDatabasePlatform()
                    );
                } catch (\Exception $e) {
                    $default = null;
                }

                if ($column->getNotnull()) {
                    if (!is_null($default)) {
                        $results[$columnName] = $default;
                    } else {
                        $typeName = $column->getType()->getName();
                        if (in_array($typeName, [Type::STRING, Type::TEXT, Type::BINARY, Type::BLOB, Type::GUID])) {
                            $results[$columnName] = '';
                        } elseif (in_array($typeName, [Type::BIGINT, Type::DECIMAL, Type::INTEGER, Type::FLOAT, Type::SMALLINT])) {
                            $results[$columnName] = 0;
                        } elseif (in_array($typeName, [Type::TARRAY, Type::SIMPLE_ARRAY, Type::JSON_ARRAY, Type::JSON])) {
                            $results[$columnName] = '[]';
                        } elseif (in_array($typeName, [Type::BOOLEAN])) {
                            $results[$columnName] = false;
                        }
                    }
                } else {
                    $results[$columnName] = $default;
                }
            }
        } elseif ($type == 'dates') {
            $dateTypes = [
                TimeType::class,
                DateType::class,
                DateTimeType::class,
                DateTimeTzType::class,
                VarDateTimeType::class,
            ];

            foreach ($columns as $columnName => $column) {
                if (in_array($columnName, $exclude)) {
                    continue;
                }

                $type = get_class($column->getType());

                if (in_array($type, $dateTypes)) {
                    $results[] = $columnName;
                }
            }
        } elseif ($type == 'casts') {
            $types = [
                JsonType::class => 'array',
                JsonArrayType::class => 'array',
                ObjectType::class => 'array',
                ArrayType::class => 'array',
                BooleanType::class => 'boolean',
            ];

            foreach ($columns as $columnName => $column) {
                if (in_array($columnName, $exclude)) {
                    continue;
                }

                $type = get_class($column->getType());

                if (isset($types[$type])) {
                    $results[$columnName] = $types[$type];
                }
            }
        }

        return $results;
    }


    protected function getFactoryColumns($table)
    {
        $results = [];

        $columns = $this->columns[$table];
        $columnsNames = array_keys($columns);

        $indexes = $this->indexes[$table];
        $primary = isset($indexes['primary']) ? head($indexes['primary']->getColumns()) : null;

        $exclude = [
            'created_at', 'updated_at', 'deleted_at',
        ];

        if (!is_null($primary)) {
            $exclude[] = $primary;
        }

        foreach ($columns as $columnName => $column) {
            if (in_array($columnName, $exclude)) {
                continue;
            }

            // Relationships

            $foreignKey = current(array_filter($this->foreignKeys[$table], function ($foreignKey) use ($columnName) {
                return in_array($columnName, $foreignKey->getLocalColumns());
            }));

            if ($foreignKey) {
                $results[$columnName] = 'App\\' . $this->getModelName($foreignKey->getForeignTableName());
                continue;
            }

            // Polimorphic Relationships

            // Skip the type column
            $idColumn = str_replace('able_type', 'able_id', $columnName);

            if (ends_with($columnName, 'able_type') && isset($columns[$idColumn])) {
                $results[$columnName] = 'App\\User::class';
                continue;
            }

            $typeColumn = str_replace('able_id', 'able_type', $columnName);

            if (ends_with($columnName, 'able_id') && isset($columns[$typeColumn])) {
                $results[$columnName] = 'App\\User';
                continue;
            }

            $typeName = $column->getType()->getName();

            $columnSize = $column->getLength();

            if ($guessed = $this->guessFromName($columnName, $columnSize)) {
                $results[$columnName] = $guessed;
            } elseif ($typeName == Type::STRING) {
                $results[$columnName] = $this->getTextFaker($columnSize);
            } elseif ($typeName == Type::TEXT) {
                $results[$columnName] = '$faker->paragraphs()';
            } elseif ($typeName == Type::SMALLINT) {
                $results[$columnName] = '$faker->numberBetween(0, 255)';
            } elseif (in_array($typeName, [Type::BIGINT, Type::INTEGER])) {
                $results[$columnName] = '$faker->randomNumber()';
            } elseif (in_array($typeName, [Type::DECIMAL, Type::FLOAT])) {
                $results[$columnName] = '$faker->randomFloat()';
            } elseif (in_array($typeName, [Type::BOOLEAN])) {
                $results[$columnName] = '$faker->boolean()';
            } elseif (in_array($typeName, [Type::DATE, Type::DATE_IMMUTABLE])) {
                $results[$columnName] = '$faker->date()';
            } elseif (in_array($typeName, [Type::DATETIME, Type::DATETIMETZ, Type::DATETIME_IMMUTABLE, Type::DATETIMETZ_IMMUTABLE])) {
                $results[$columnName] = '$faker->dateTime()';
            } elseif (in_array($typeName, [Type::TIME, Type::TIME_IMMUTABLE])) {
                $results[$columnName] = '$faker->time()';
            } elseif ($column->getNotnull()) {
                $results[$columnName] = "null, // INVALID BUT DON'T KNOW WHAT TO DO!";
            } else {
                $results[$columnName] = 'null';
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
                return '$faker->domain';
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
        if ($size < 200) {
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
        $indexes = $this->indexes[$table];

        $primary = isset($indexes['primary']) ? head($indexes['primary']->getColumns()) : null;

        if (!is_null($primary)) {
            if (isset($this->columns[$table][$primary])) {
                return $this->columns[$table][$primary]->getAutoincrement();
            }
        }

        return false;
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
     * Get the model name from the table name
     * @method getModelName
     * @param  string       $table
     * @return string
     */
    protected function getModelName($table)
    {
        return studly_case(str_singular($table));
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

        $filename = app_path(sprintf('Models\%s.php', $class));

        if (!file_exists($filename) || in_array($this->overwrite, ['all', 'models'])) {
            $this->comment(sprintf('Generating model for "%s" table.', $table));

            $params = [
                'table' => $table,
                'class' => $class,
                'softDeletes' => $this->getSoftDeletes($table),
                'timestamps' => $this->getTimestamps($table),
                'primaryKey' => $this->getPrimaryKey($table),
                'incrementing' => $this->getIncrementing($table),
                'fillable' => $this->getTableColumns($table, 'fillable'),
                'attributes' => $this->getTableColumns($table, 'attributes'),
                'dates' => $this->getTableColumns($table, 'dates'),
                'casts' => $this->getTableColumns($table, 'casts'),
                'relationships' => $this->getRelationships($table),
                'uses' => $this->getUses($table),
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

    protected function generateFactory($table)
    {
        $class = $this->getModelName($table);

        $filename = database_path(sprintf('factories/%sFactory.php', $class));

        if (!file_exists($filename) || in_array($this->overwrite, ['all', 'factories'])) {
            $this->comment(sprintf('Generating factory for "%s" model.', $class));

            $params = [
                'model' => $class,
                'columns' => $this->getFactoryColumns($table),
            ];

            $content = self::OPEN_ROW . View::make('models-generator::generated-factory', $params)->render();

            $directory = dirname($filename);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($filename, $content);

            $this->info(sprintf('Factory "%sFactory" successfully generated!', $class));
        } else {
            $this->error(sprintf('Factory "%sFactory" already exists (and no "overwrite" parameter), skipping.', $class));
        }

    }

    protected function copyBaseModel()
    {
        $filename = app_path('Models/BaseModel.php');

        if (!file_exists($filename) || in_array($this->overwrite, ['all', 'models'])) {
            $dir = dirname($filename);

            if (!is_dir($dir)) {
                mkdir($dir);
            }

            copy(dirname(__DIR__) . '/resources/models/BaseModel.php', $filename);
            $this->info('BaseModel successfully copied!');
        } else {
            $this->comment("The class App\Models\BaseModel already exists, don't overwrite.");
        }
    }

    /**
     * Copy the base model into the App\Models namespace
     * @method copyBaseModel
     * @return void
     */
    protected function generateUserRelationshipsTrait()
    {
        if (isset($this->relationships['users'])) {
            $filename = app_path('Models/Traits/UserRelationships.php');

            if (!file_exists($filename) || in_array($this->overwrite, ['all', 'models'])) {
                $params = [
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
        $this->overwrite = $this->option('overwrite');
        $this->factories = $this->option('factories');

        $this->connection = $this->option('connection') == 'default' ? null : $this->option('connection');

        // DBAL enum bug workaround
        DB::connection($this->connection)->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $this->buildInternalData();

        $this->extendBlade();

        $this->info('Models generation started.');

        $tables = array_diff(
            DB::connection($this->connection)->getDoctrineSchemaManager()->listTableNames(),
            config('models-generator.exclude')
        );

        foreach ($tables as $table) {

            // Skip many to many tables
            if (isset($this->manyToMany[$table])) {
                continue;
            }

            $this->generateModel($table);

            $this->generateUserModel($table);

            if ($this->factories) {
                $this->generateFactory($table);
            }
        }

        $this->copyBaseModel();

        $this->generateUserRelationshipsTrait();

        $this->info('Models successfully generated!');
    }
}
