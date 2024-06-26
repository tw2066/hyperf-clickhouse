# phpClickHouse-hyperf
Adapter to Hyperf framework of the most popular libraries:

- https://github.com/tw2066/ClickhouseBuilder - good query builder

## Features
No dependency

More: https://github.com/smi2/phpClickHouse#features

## Prerequisites
- PHP 8.1
- Hyperf PHP
- Clickhouse server

## Installation

1. Install via composer

```sh
$ composer require tangwei/hyperf-clickhouse
```

2. Add new connection into your config/database.php:

```php
    'clickhouse' => [
        'driver' => 'clickhouse',
        'host' => env('CLICKHOUSE_HOST'),
        'port' => env('CLICKHOUSE_PORT','8123'),
        'database' => env('CLICKHOUSE_DATABASE','default'),
        'username' => env('CLICKHOUSE_USERNAME','default'),
        'password' => env('CLICKHOUSE_PASSWORD',''),
        'https' => (bool)env('CLICKHOUSE_HTTPS',false),
        'settings' => [ // optional
            // 'max_partitions_per_insert_block' => 300,
        ],
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 3,
            'connect_timeout' => 10.0,
            'wait_timeout'    => 3.0,
            'heartbeat'       => -1,
            'max_idle_time'   => 60,
        ],
    ],

```

Then patch your .env:

```sh
CLICKHOUSE_HOST=localhost
CLICKHOUSE_PORT=8123
CLICKHOUSE_DATABASE=default
CLICKHOUSE_USERNAME=default
CLICKHOUSE_PASSWORD=
# only if you use https connection
CLICKHOUSE_HTTPS=true
```

3. Used

You can use smi2/phpClickHouse functionality directly:

```php
$client = \Tang\HyperfClickhouse\DB::query()->getClient();
$statement = $client->select('SELECT * FROM summing_url_views LIMIT 2');
```

More about $db see here: https://github.com/smi2/phpClickHouse/blob/master/README.md

Or use dawnings of Eloquent ORM (will be implemented completely)

1. Add model

```php
<?php


namespace App\Models\Clickhouse;

use Tang\HyperfClickhouse\Model;

class MyTable extends Model
{
    // Not necessary. Can be obtained from class name MyTable => my_table
    protected string $table = 'my_table';

}
```

2. Add migration

```php
<?php


class CreateMyTable extends \Tang\HyperfClickhouse\Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        static::write('
            CREATE TABLE my_table (
                id UInt32,
                created_at DateTime,
                field_one String,
                field_two Int32
            )
            ENGINE = MergeTree()
            ORDER BY (id)
        ');
    }

    /**
    * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        static::write('DROP TABLE my_table');
    }
}
```

3. And then you can insert data

One row
```php
$model = MyTable::create(['field_one' => 'model 1', 'field_two' => 1]);
# or
$model = MyTable::make(['field_one' => 'model 2']);
$model->field_two = 2;
$model->save();
# or
$model = new MyTable();
$model->fill(['field_one' => 'model 3', 'field_two' => 3])->save();
```
Or bulk insert
```php
MyTable::query()->insert(['field_one' => 'model 11','field_two' => 11]);
```

4. Now check out the query builder
```php
$rows = MyTable::query()->select(['field_one', \Tinderbox\ClickhouseBuilder\raw('sum(field_two)', 'field_two_sum')])
    ->where('created_at', '>', '2020-09-14 12:47:29')
    ->groupBy('field_one')
    ->getCollect();
```

## Advanced usage
Retries
You may enable ability to retry requests while received not 200 response, maybe due network connectivity problems.

Patch your .env:

    CLICKHOUSE_RETRIES=2
    
retries is optional, default value is 0.
0 mean only one attempt.
1 mean one attempt + 1 retry while error (total 2 attempts).

## Working with huge rows
You can chunk results like in Laravel
```php
// Split the result into chunks of 30 rows 
$rows = MyTable::query()->select(['field_one', 'field_two'])
    ->chunk(30, function ($rows) {
        foreach ($rows as $row) {
            echo $row['field_two'] . "\n";
        }
    });
```

Buffer engine for insert queries
See https://clickhouse.tech/docs/en/engines/table-engines/special/buffer/

```php
<?php

namespace App\Models\Clickhouse;

use Tang\HyperfClickhouse\Model;

class MyTable extends Model
{
    // Not necessary. Can be obtained from class name MyTable => my_table
    protected $table = 'my_table';
    // All inserts will be in the table $tableForInserts 
    // But all selects will be from $table
    protected $tableForInserts = 'my_table_buffer';
}
```
    
If you also want to read from your buffer table, put its name in $table
```php
<?php

namespace App\Models\Clickhouse;

use Tang\HyperfClickhouse\Model;

class MyTable extends Model
{
    protected $table = 'my_table_buffer';
}
```

OPTIMIZE Statement
See https://clickhouse.com/docs/ru/sql-reference/statements/optimize/
```php
MyTable::query()->optimize($final = false, $partition = null);
```
## Deletions
See https://clickhouse.com/docs/en/sql-reference/statements/alter/delete/
```php
MyTable::query()->where('field_one', 123)->delete();
```
Using buffer engine and performing OPTIMIZE or ALTER TABLE DELETE
```php
<?php

namespace App\Models\Clickhouse;

use Tang\HyperfClickhouse\Model;

class MyTable extends Model
{
    // All SELECT's and INSERT's on $table
    protected $table = 'my_table_buffer';
    // OPTIMIZE and DELETE on $tableSources
    protected $tableSources = 'my_table';
}
```

