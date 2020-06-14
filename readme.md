<p align="center">
<a href="http://pluginever.com" target="_blank">
    <img width="200px" src="https://pluginever.com/wp-content/themes/pluginever/images/pluginever-logo.svg">
</a>
</p>

# WP Query Builder
WP Query Builder is a lightweight and efficient SQL Query Builder based on wpdb for WordPress. It supports complicated query generation.

[![Build Status](https://travis-ci.org/sultann/wp-query-builder.svg?branch=master)](https://travis-ci.org/sultann/wp-query-builder)
[![Packagist](https://img.shields.io/packagist/dt/sultann/wp-query-builder.svg)](https://packagist.org/packages/sultann/wp-query-builder)
[![Packagist](https://img.shields.io/packagist/l/sultann/wp-query-builder.svg)](https://github.com/sultann/wp-query-builder/blob/master/LICENSE)
[![GitHub release](https://img.shields.io/github/release/sultann/wp-query-builder.svg)](https://github.com/sultann/wp-query-builder/releases)

## Installation 
WP Query Builder follows `PSR-4` autoloading and can be installed using composer:

```
$ composer require sultann/wp-query-builder
```

## Documentation 💡
### Initialize
Initialize query builder. init method takes a string argument using that later you can do action/filter based on your requirement.
without argument.
```php
$query = \PluginEver\QueryBuilder\Query::init();
```
with argument
```php
$query = \PluginEver\QueryBuilder\Query::init('query_users');
```

### Select
This will build the query, execute and returns all users from users table with applying table prefix automatically.
by default it select all(*) but you can define what to select from the query; If you are selecting all then you can omit the select statement.
```php
$results = $query->select('*')
                 ->from('users')
                 ->get();
```
Select specific column
```php
$results = $query->select('ID')
                 ->from('users')
                 ->get();
```
Select multiple column
```php
$results = $query->select('user_login, user_email')
                 ->from('users')
                 ->get();
```


### Where conditions
For the next few examples, lets assume a larger dataset so that the queries make sense.
```php
$results = $query->select('*')
                 ->from('users')
                 ->where('user_email', 'like', '%gmail.com')
                 ->get();
```
Notice how omitting the operator in the first condition ->where('user_url', '') makes this default to =.
By default all where conditions are defined with the and operator.

Different where operators:

```php
$results = $query->select('*')
                 ->from('users')
                 ->where('user_email', 'like', '%gmail.com')
                 ->orWhere('user_email', 'like', '%yahoo.com')
                 ->get();
```

There are few more builtin Where conditions available
- `andWhere()`
- `whereIn()`
- `whereNotIn()`
- `whereNull()`
- `whereNotNull()`
- `orWhereNull()`
- `orWhereNotNull()`
- `whereBetween()`
- `whereNotBetween()`
- `whereDateBetween()`
- `whereRaw()`

#### Where scopes
Allow you to group conditions:

```php
$results = $query->select('*')
                 ->from('posts')
                ->where('post_status', 'publish')
                ->where(function($q) 
                {
                    $q->where('menu_order', '>', 21);
                    $q->where('menu_order', '<', 99);
                })
                ->orWhere('post_type', 'page')
                ->get();
```

Where Between
```php
$results = $query->select('*')
                 ->from('posts')
                 ->whereBetween('menu_order', 1, 20)
                 ->get();
```

Where Not Between
```php
$results = $query->select('*')
                 ->from('posts')
                 ->whereNotBetween('menu_order', 20, 30)
                 ->get();
```

Where Date Between
```php
$results = $query->select('*')
                 ->from('posts')
                 ->whereDateBetween('post_date',  '2010-04-22 10:16:21', '2020-05-04')
                 ->get();
```

### Joins
By default, all joins are Left Join. Available join types 'LEFT', 'RIGHT', 'INNER', 'CROSS', 'LEFT OUTER', 'RIGHT OUTER'
Joining tables:
```php
$results = $query->select( '*' )
                ->from( 'posts p' )
                ->join( 'users u', 'u.ID', '=','p.post_author' )
                ->get();
```

#### Joins scopes
Allow you to group conditions:
```php
$results = $query->select( '*' )
                ->from( 'posts p' )
                ->join( 'users u', 'u.ID', '=','p.post_author' )
                ->join('usermeta um', function($q) {
                      $q->where('um.meta_key', 'first_name');
                      $q->where('um.met_value', 'like', '%sultan%');
                  })
                ->get();
```
There are few more builtin join conditions available
- `leftJoin()`
- `rightJoin()`
- `innerJoin()`
- `outerJoin()`

### Grouping

Grouping data:
```php
$results = $query->select('*')
                 ->from('posts')
                ->group_by('post_status')
                ->get();
```

### Having
Group by with having data:
```php
$results = $query->select('*')
                 ->from('posts')
                ->group_by('post_status')
                ->having('count(ID)>1')
                ->get();
```

### Ordering
Ordering data:
```php
$results = $query->select('*')
                 ->from('posts')
                ->order_by('post_title', 'DESC')
                ->get();
```

### Limiting data
Limit and offset:
```php
$results = $query->select('*')
                 ->from('posts')
                ->limit(20, 10)
                ->get();
```
Only limit
```php
$results = $query->select('*')
                 ->from('posts')
                ->limit(20)
                ->get();
```
Offset as separate
```php
$results = $query->select('*')
                 ->from('posts')
                ->limit(20)
                ->offset(10)
                ->get();
```

### Pagination
shortcut of limit and offset
```php
$results = $query->select('*')
                 ->from('posts')
                ->page(1, 20)//page number & page size
                ->get();
```

### Find 
find item with column value
```php
$results = $query->select('*')
                 ->from('posts')
                 ->find(1, 'ID');
```


### First 
Get first item from the posts table
```php
$results = $query->select('*')
                 ->from('posts')
                 ->first();
```

### Last 
Get last item from the posts table
```php
$results = $query->select('*')
                 ->from('posts')
                 ->last();
```

### Counting
count total rows
```php
$results = $query->select('*')
                 ->from('posts')
                 ->count();
```

### toSql
Out the query instead of executing
```php
$results = $query->from('posts as p')
                ->join('users as u',  'p.post_author', 'u.ID')
                ->join('usermeta um', function($q) {
                      $q->where('um.meta_key', 'first_name');
                      $q->where('um.met_value', 'like', '%sultan%');
                  })
                ->toSql();
```

### Update 
Update a row
```php
$results = $query->table('posts')
                 ->where('ID', 20)
                 ->update(['post_title' => 'updated']);
```

### Delete 
Delete a row
```php
$results = $query->from('posts')
                ->where('ID', 20)
                ->delete();
```

### Search
Search a value from columns
```php
$results = $query->from('posts')
                 ->search('Hello Word', array('post_title', 'post_content')) // it will search Hello & World both
                 ->delete();
```

## License

The MIT License (MIT). Please see [License File](https://github.com/sultann/wp-query-builder/blob/master/LICENSE) for more information.