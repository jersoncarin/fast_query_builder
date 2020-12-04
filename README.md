# Fast Query Builder

Fast Query Builder is a database PDO/MYSQL Builder thats make you easy to work with querying the database

  - Fast and Smooth
  - No more sphagetti code
  - Easy to query

# Requirements

  - MYSQL Install on Server (PDO/MYSQLi)
  - PHP 7.3+


# Why Fast Query Builder

  - Fast Query Builder
  - Many features
  - Easy to use Like (SELECT,WHERE,etc.)
  - Safe from sql injection attacks (FQB uses Prepared statement / PDO for building queries)

# How to Use 

 - Download or Clone [fast_query_builder.php](https://github.com/jersoncarin/fast_query_builder)

 To use it :
```php
require ./folder/fast_query_builder.php;

$database = [
    'username' => 'root',
    'password' => '',
    'database' => 'hi',
    'port' => 3306,
    'driver' => 'mysql',
    'host' => 'localhost',
    'timezone' => 'Asia/Manila',
    'charset' => 'utf8',
    'sql_mode' => array()
];

$fqb = new FastQueryBuilder( $database );

//Then you already set please refer below for additional function
```

### Query building with parameters

```php
$fqb->query("SELECT * FROM users WHERE id = ? AND name = ?" , array( 1 , 'jersnet') );
```

### Query building with raw statement (NOT Recommended for input data)

```php

$id = 1;
$name = 'jersnet';
$fqb->queryRaw("SELECT * FROM users WHERE id = '$id' AND name = '$name");
```

### Getting the Data (To Array)
```php
$fqb->query("SELECT * FROM users WHERE id = ? AND name = ?" , array( 1 , 'jersnet') );
$result = $fqb->get();

//or you can use callback

$fqb->get(function($data) {
    foreach($data as $user) {
        print_r($user);
    }
});
```

### Set SQL Mode

```php

// This will be in array
// If you want to allow other incompatible mods
// open fast_query_builder.php and find private $incompatible_modes
// and change whatever you want

$fqb->set_sql_mode( array( ) );
```

### Close FQB Connection

```php
// This will close all database connection
$fqb->close();
```

### Last ID

```php
// You can get last inserted/updated primary key ids
// using

$fqb->last_id();
```

### Find By
```php
$table = 'users';
$column_name = 'id';
$column_value = 1;
$fqb->find( $table , $column_name , $column_value );
```

### Escape String

```php
// Note PDO drivers don't work with escape
// Use mysql driver instead

$unsafe_string = 'Unsafe string';
$fqb->escape( $unsafe_string );
```

### Pluck
The Pluck will take the column name rows into single array
```php
$column_name = 'name';
$fqb->query( "SELECT * FROM users" );
$result = $fqb->pluck( $column_name );
print_r($result);
// [ Jerson , Jersnet ]
```

### First
The First will take the first rows of data

```php
$fqb->query( "SELECT * FROM users" );
$result = $fqb->first();
```
### Latest
The Lates will take the lates rows of data

```php
$fqb->query( "SELECT * FROM users" );
$result = $fqb->latest();
```

### Insert Data
The Insert data will insert new rows to the database

```php
$table_name = 'users';
$data_to_insert = [
    'name' => 'dev',
    'email' => 'dev@email.com'
];

$insert = $fqb->insert( $table_name , $data_to_insert );

if($insert) {
    ////
}
```

### Select 
The select statement need to connect to from and where

```php
// the paramater of select is array if it's empty / not define
// then it will use * automatically
$fqb->select() ...
```

### From
The from statement need to connect to select or where

```php
// the parameter of from is string ( a table name )
$fqb->select()->from('users') ....
```

### Where
The Where statement need to connect from select and where

```php
// the parameter of where is array , and string
// the wheres, it can be a single array or multidimensional
// operator parameter can be defined AND/OR (in default value is AND)
// operator can be undefined ( It will automatically use AND operator )

// the single array
$wheres = ['name' , '=' , 'jersnet'];

// the multidimensional
// Now you can use operator if you want to use
// OR or AND
$wheres = [
  ['name' , '=' ,  'jersnet'],
  ['nickname' , '=' ,  'jerson'],
  ['firstname' , '=' ,  'jers']
];

$fqb->select()->from('users')->where( $wheres , $operator );
$result = $fqb->get();
// now you can get the data rows from condition
```
### AndWhere
The andWhere need to use the where first inorder to work this
```php
// The andWhere is same with where but with AND operator from first
$wheres = ['name' , '=' , 'jersnet'];

$fqb->select()->from('users')->where( $wheres )->andWhere( $wheres ) ;
```

### OrWhere
The OrWhere need to use the where first inorder to work this
```php
// The OrWhere is same with where but with OR operator from first
$wheres = ['name' , '=' , 'jersnet'];

$fqb->select()->from('users')->where( $wheres )->orWhere( $wheres ) ;
```

### Flush
The flush will clear all cache from the data bindings

```php
$fqb->flush();
```

### Update
The update will update the existing rows data
```php
$table_name = 'users';
$data_to_update = [
    'name' => 'dev',
    'email' => 'dev@email.com'
];

$update = $fqb->update( $table_name , $data_to_update );

if(update) {
    ////
}
```

### Delete
The delete will delete rows data (ALL/or by conditions)

```php

// If you want to delete all rows data
// in a table
$fqb->from('users')->delete();

// If you want to delete by condition 
// you may use where statement
$fqb->from('users')->where(['name', '=' , 'Jersnet'])->delete();
```

### Offset
The offset will take the start index (defined) to end index (defined)

```php
// the parameter of offset ( the start is the first)
// second is the end index
//Note: if you put null on first , the end index will be the start same with end

$fqb->query( "SELECT * FROM users" )->offset(2,3);
$result = $fqb->get();
```

### Reverse
The reverse will reverse the array data

```php
$fqb->query( "SELECT * FROM users" );
// The preversed keys will not reversed the keys even its reverse
$preserved_keys = TRUE;
//Note: you can undefine the preserved_keys
//it will automatically use TRUE
$result = $fqb->reverse( $preversed_keys );
```

### Shuffle
The shuffle will shuffle the array data

```php
$fqb->query( "SELECT * FROM users" );
$result = $fqb->shuffle();
```

### Count
The count will count the number of rows in array data

```php
$fqb->query( "SELECT * FROM users" );
$result = $fqb->count();
```

### Sum
The sum will sum up the given numeric column name

```php
$fqb->query( "SELECT * FROM users" );

// First argument is the column name
$sum = $fqb->sum('credits');
```

### Max
The Max will get the max value of the given numeric column name

```php
$fqb->query( "SELECT * FROM users" );

// First argument is the column name
$max = $fqb->max('credits');
```
### Average
The Avg will get the average value of the given numeric column name

```php
$fqb->query( "SELECT * FROM users" );

// First argument is the column name
$avg = $fqb->avg('credits');
```
### Min
The Min will get the min value of the given numeric column name

```php
$fqb->query( "SELECT * FROM users" );

// First argument is the column name
$min = $fqb->min('credits');
```

### Exists
The exists will return true if exist instead of using count > 0 

```php
$fqb->query( "SELECT * FROM users" );
$query = $fqb->exists();

if($query) {
    //
}
```

## Filtering
The filter will filter the data by user condition

```php

// ARRAY_FILTER_USE_KEY - pass key as the only argument to callback instead of the value
// ARRAY_FILTER_USE_BOTH - pass both value and key as arguments to callback instead of the value

$filtered = $fqb->query( "SELECT * FROM users" )->filter(function($data) {
    return $data;
} , ARRAY_FILTER_USE_KEY);

print_r($filtered);
```

### Chunk
The Chunk will create new chunk of the array from the data 

```php
 /**
 * @param int the chunk size 
 * @param boolean flags (preversed keys)
 * @param object (function callback ) use if defined
 * 
 * @return array the chunk array
 */
 
 $fqb->query( "SELECT * FROM users" );
 $chunk = $fqb->chunk(100);
 
 or you can use callback
 
 $fqb->chunk(100,TRUE,function($data_chunk) {
     foreach($data_chunk as $chunked) {
         //
     }
 });
```

### Sort Array
The sort will sort the array according to the order provided
```php
 $fqb->query( "SELECT * FROM users" );
 
 // The True argument is ascending order and false (Desc)
 $sorted = $fqb->sort(TRUE);
```

### Pop
The Pop will remove the elements by value on array data

```php
$fqb->query( "SELECT * FROM users" );

// First argument is column name and second is value
$result = $fqb->pop('name' , 'Jersnet');
```

### To Object
The toObject will convert array to object data
```php
$fqb->query( "SELECT * FROM users" );

$object = $fqb->toObject();
```

### To Json
The toJson will convert array to json string data

```php
$fqb->query( "SELECT * FROM users" );

// Note that flags is just like json_encode flags
$json = $fqb->toJson( $flags = 0 );
```

### Serialize
The serialize will convert array to serialize data

```php
$fqb->query( "SELECT * FROM users" );
$serialized = $fqb->serialize();
```

### Distinct
The distinct will remove duplicate values from array

```php
$fqb->query( "SELECT * FROM users" );
$result = $fqb->distinct();
```

### Affected Rows
The affected rows will get the rows from (INSERT/UPDATE)

```php
$fqb->query( "UPDATE users SET name = 'name' ");
$rows = $fqb->affected_rows();
```

### Truncate Table
The truncate will be truncate the define table

```php
$fqb->truncate('users');
```

### Drop Table
The drop will be drop the define table

```php
$fqb->drop('users');
```

### IN

```php
 /**
 * IN the rows
 *
 * @param string (column name)
 * @param array (search for this array)
 * 
 * @return boolean (true has given array else false)
 */
 
 $fqb->query( "SELECT * FROM users" );
 
 $search = [
    'jersnet',
    'jerson',
    'jerome'
 ];
 
 // the first arugment is column name
$result = $fqb->in('name' , $search);

```

### Like

```php
 /**
 * LIKE (search for a specified pattern in a column.)
 * 
 * @param array (array key(column_name) && value(needle))
 * @param boolean use AND => TRUE / OR => FALSE
 * @param int search method (0 => ANY position , 1 => First position , 2 => Last position)
 * @param string hasWhere Operator  (If WHERE is exist then you can defined the operator (OR/AND))
 * 
 * @return array (filtered array)
 */
 
 $search = [
    'name' => 'jersnet',
    'name' => 'jerome'
 ];
 
 $result = $fqb->select()->from('users')->like($search,false);

```

### Limit by
The limit by is just like limit on mysql queries

```php
$fqb->select()->from('users')->limitBy(0,2)->get();
```

### Order by
The order By is just like order by on mysql queries

```php
$fqb->select()->from('users')->orderBy('name', 'ASC')->get();
```

### Joining Table

   - InnerJoin => innerJoin()
   - LeftJoin => leftJoin()
   - RightJoin => rightJoin()
   - Full outer join => fullOuterJoin()
   - Join => join()

```php 
// The third argument is operator it can be and/or OR not defined
$fqb->select()->from('users')->join('user_info' , ['users.id' , 'user_info.user_id'] , 'AND')->where(....) ..
```

### Group by
The group by is just like group by on mysql queries

```php
$fqb->select()->from('users')->groupBy('name')->get();
```

### Having
The having is just like having on mysql queries

```php
$fqb->select()->from('users')->groupBy('name')->having(' condition here ')->get();
```

### Fetch all data
The all() will fetch all data rows by table

```php
$result = $fqb->all('users');
```

### Where In

```php
 /**
 * Where IN
 * 
 * @param string column name
 * @param mixed (Array to search)
 * @param boolean doesUseSelectCondition
 * 
 * @return object this field
 */
 
 $fqb->select()->from('users')->whereIn('users', ['jersnet','jerome'] , false );
 
 // OR using select statement
 
  $fqb->select()->from('users')->whereIn('users', ' SELECT * FROM users ' , true );

```

### Where Not In

```php
 /**
 * Where Not IN
 * 
 * @param string column name
 * @param mixed (Array to search)
 * @param boolean doesUseSelectCondition
 * 
 * @return object this field
 */
 
 $fqb->select()->from('users')->whereNotIn('users', ['jersnet','jerome'] , false );
 
 // OR using select statement
 
  $fqb->select()->from('users')->whereNotIn('users', ' SELECT * FROM users ' , true );

```


### Where between

```php
 /**
 * Where Between
 * 
 * @param string column name
 * @param array condition
 * 
 * @return object this field object
 */
 
 $fqb->select()->from('users')->whereBetween('users', [10, 'AND' , 100] );
 
// or it can be use raw statement
 
 $fqb->select()->from('users')->whereBetween('users', ' 10 AND 100 ' );

```

### Where Not between

```php
 /**
 * Where Not Between
 * 
 * @param string column name
 * @param array condition
 * 
 * @return object this field object
 */
 
 $fqb->select()->from('users')->whereNotBetween('users', [10, 'AND' , 100] );
 
 //or it can be use raw statement
 
 $fqb->select()->from('users')->whereNotBetween('users', ' 10 AND 100 ' );

```

### Has error 
The has_error() will check if the query has error (true) otherwise false on success

```php
$error = $fqb->has_error();

if($error) {
    //
}
```

### Dump sql tables by one click

```php
/**
 * Dump Database tables
 * 
 * @param array tables ( specific table )
 * @param string backup name (otherwise it will generate random name)
 * 
 * @return string contents (it will download .sql file)
 * 
 * Originally this script fork from https://github.com/ttodua/useful-php-scripts
 * Credits to the author of this script
 * 
 * The orignal link can be found here https://github.com/ttodua/useful-php-scripts/blob/master/my-sql-export%20(backup)%20database.php
 * 
 * I made little changes for this scripts
 */
 
 // You can specify table want to dump
 $tables = [
    'users',
    'user_info'
 ];
 
 // You can specify backup name other
 // a random name will generate
 $backup_name = 'users';
 
 // Specify
 $fqb->dump($tables,$backup_name);
 
 //Unspecify
 $fqb->dump();
```

## Author
- Jerson Carin

## Credits
- Dump tables by [ttdoua](https://github.com/ttodua/useful-php-scripts)


License
----

MIT License

```
Copyright (c) 2020 Jerson Carin

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
