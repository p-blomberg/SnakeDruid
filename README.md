SnakeDruid
==========

An ORM for PHP based on the project BasicObject, but with a PostgreSQL backend.

State of project
---------

The project is in it's infancy and has only been tested with the test suite, not in an actual project.

Usage
----

1. Create your database just like you want it.
	```
	CREATE TABLE foos (
		foo_id  serial PRIMARY KEY,
		column1 text,
		column2 int,
		created_at timestamptz DEFAULT NOW()
	);
	CREATE TABLE foo_bars (
		foo_id int,
		bar    text,
		FOREIGN KEY (foo_id) REFERENCES foos (foo_id),
		PRIMARY KEY(foo_id, bar)
	);
2. Make sure to require SnakeDruid.php and PGDatabase.php.
	```
	require 'SnakeDruid.php';
	require 'PGDatabase.php';
	```
3. Instantiate a global variable $db that is a PGDatabase. All parameters can be `null` and will be
	evaluated as a parameter string to `pg_connect`.
	```
	$port = 5432;
	$db = new PGDatabase('host', 'user', 'password', 'database', $port);
	```
4. For each table create a class inheriting from `SnakeDruid` and implement the function `table_name()`
	that returns the name of the table the class is to handle.
	```
	class Foo extends SnakeDruid {
		protected static function table_name() {
			return 'foos';
		}
	}
	class FooBar extends SnakeDruid {
		protected static function table_name() {
			return 'foo_bars';
		}
	}
	```

The classes now have an API as follows.

Class variables
-------

*The columns*

The class `Foo` will have the variables 
* `id` (alias of `foo_id`)
* `foo_id`
* `column1`
* `column2`
* `created_at`
These are editable and saved to the db by calling `commit()`. Values respond to `isset` as you would expect.

*The connected classes*

The class `Foo` will have the variable `FooBar` that returns an array of all `FooBar` objects that have
been saved to database and have the same `foo_id` as the `Foo` object. The variable `FooBar` is not editable.

The class `FooBar` will have the variable `Foo` that returns the `Foo` object with the corresponding `foo_id`.
Setting `Foo` to a `Foo` object will update all columns in the foreign key (in this case only `foo_id`). Changes
needs to be saved using `commit()`.

Static methods
-------

`from_id($id)`

Fetches the object with primary key equal to `$id`. In `Foo`s case that would be the value of `foo_id`. In
`FooBar`s case `$id` will need to be an associative array detailing each of the primary key columns values

`from_field($field, $value)`

Fetches the object where the field equals the value. Returns `NULL` if no such value exists and raises an
exception if more than one row exists.

`selection($params)`
Returns an array of objects based on the selection params. Syntax is described in detail below.

`first($params)`
Returns the first object with given params or `NULL` if no object exists. Syntax is described in detail below.

`one($params)`
Returns the object matching the params or `NULL` if none exists. Raises `Exception` if more than one row matches.
Syntax is described in detail below.

`sum($field, $params)`
Returns the sum of the column `$field` for the selection of rows matched by $params.

`count($params)`
Returns the number of matching rows for the params.

Methods
-------

`commit()`
Commits changes to the object to the db. Please note that if the db is in a transaction, this does *not*
commit that transaction.

`delete()`
Removes the row from the database. Please note that if the db is in a transaction, this does *not*
commit that transaction.

`duplicate()`
Creates a duplicate of this object sans primary key values and with status not in databse.

`<connected_class>($params)`
From outgoing foreign key situations this returns the pointed to object or null if the params do not match.
From incoming foreign key situations this returns an array of matching objects.

Filter syntax
=============

To be continued...
