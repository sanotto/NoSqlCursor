# NoSqlCursor

##What is it?
This is a library that provides a wrapper to the tz-lom/HSPHP library.
The [tz-lom/HSPHP] (https://github.com/tz-lom/HSPHP) library is a library that provides an interface to a mysql/mariadb
plugin called [handlersocket](https://mariadb.com/kb/en/mariadb/handlersocket/).
Handler socket plugin let's you access a row using a btree index DIRECTLY, without using
SQL.
##Why should I use this?
Accessing a table row directly is blazingly fast!.
When you use SQL there are multiple code layer that have to be run before the 
actual insert/delete/retrieve takes place.
The SQL engine needs to analize all the posible forms to retrieve the data and come out
with an access plan for the row.
When using the plugins you short circuit all that and tell the engine what to do directly.
Just to give you a taste of what I'm talking about, In my machine,
 a Core i7 Notebook with 4 GB Ram, inserting 65535 rows took a little less than 3 seconds.
##Why should I use your wrapper instead of tz-loms directly.
tz-lom's library is really a thin, thin wrapper around the pure "plugin protocol";
This library provides two (IMHO) great advantages.
1) It opens only one connection to the plugin and shares it with all the opened cursors
2) It provides you with easy to use goTop, goBottom, next, prev, add, update and delete methods 
(provided you are using a btree index);

##Ok, can you give an example of what it is like?
But of course!, let's see ...
```php

  $c_accounts = Cursor::Open('dbname.table_name.index_number.key_depth.field1:n:15:0,field2:c:50,field3:d');

  $c_accounts->goTop(); //Go to the first record
  $c_accounts->next(); //Go to the next record
  $c_accounts->prev(); //Go to the previous record
  $c_accounts->goBottom(); //Go to the last record

  

```
So a little explanation in in order in that Open Sentence, let's see, that big string is called
a connection definition, and it is rather long, I know, but bear with me, I'll show you that 
it is not a big deal to handle.
Let's explode it in its components:

dbname: Database name where your table resides.
table_name: The table you want to access.
index_number: If you issue a SHOW CREATE TABLE the index are listed in order
the primary key is index 0, all the others are numbered in creation order i.e.:

```sql
 accounts | CREATE TABLE `accounts` (
  `fuirrn` bigint(20) NOT NULL AUTO_INCREMENT,
  `fuisuc` decimal(5,0) DEFAULT '0',
  `fuicah` decimal(15,0) DEFAULT '0',
  `fufalt` date DEFAULT NULL,
  `fuimca` char(6) DEFAULT '0',
  `fuimpo` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`fuirrn`),
  KEY `idx002` (`fuisuc`,`fuicah`,`fufalt`,`fuirrn`) USING BTREE,
  KEY `idx003` (`fufalt`,`fuisuc`,`fuicah`,`fuirrn`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 |

```
PRIMARY KEY Is index number 0
idx002 is Index number 1
idx003 is Index number 2

key_depth: Tells how many fields are used to build the index, i.e. 1 for primary key and 4 for idx002

fields: this is a list of the fields that should be accessed, it is not necessary to include all
the table's fields but it is mandatory to include at least of the key fields (of course).

The field list is formed by a sequence of field definitios separated by commas i.e.:

field1:n:15:2

Stands for:

field1 numeric 15 with 2 decimal positions

field2:c:50 

Stands for:

field2 is chararcter with a lenght of 50

field3:d

Stands for:

field 3 Date Field

And that is it, just that 3 basic types should suffice for a LOT of cases (granted, maybe not all, but hey!!! its open source, hack your way ;-) )

So, Should I write that big string any time I want to open a cursor?
YES!!! well, no, not really I propose the following solution:

```php
$tables['accounts_by_id'=>'dbname.tblnme.idxnbr......',
        'accounts_by_date'=>'dbname.tblnme.idxnbr......',
        .
        .
        .
        ];
```
Define that array somewhere (Perhaps globally? hehehe) and that is it, now you can do

$c = Cursor:Open($tables['accounts_by_id']);

and voila!

##Show me a full example, please;
Of course, here we go:

```php

//Scan all rows (records) - Not the better way for this a standar select is better, but hey
//it is a example
$c_accounts = Cursor::Open('cursor_test_db.account.0.1.id:n:20:0,brach:n:15:0,accout:n:15:0,acmmout:n:15:2');
$c_accounts->goTop();
while($c_accounts->next())
{
    //Do something with the records
}

//Update a record - this is much faster than an sql update;
if($c_accounts->find($accout_id))
{
    $c_account->ammount=123.50;
    $c_account->update();
}

//Delete de first record

$c_accounts->goTop();
$c_accounts->delete();

//Check account ammount
if($c_accounts->find($accout_id))
{
    if ($c_account->ammount<0)
    {
        print "overdrawn\n";
    }
}

//Find a delete a record - this is much faster than a DELETE FROM...WHERE
if($c_accounts->find($accout_id))
{
    $c_accounts->delete();
}


```

Take a look at the Test directory in vendo/sanotto/NoSqlCursor for more examples.

##A word of caution.
This is **NOT** [ACID] (https://en.wikipedia.org/wiki/ACID)  compliant please read
the docs on the plugin in [handlersocket](https://mariadb.com/kb/en/mariadb/handlersocket/).
in order to know how and when it is safe/usefull to use.
And as allway if you intend to use it in production. **NO** I never used this in production
but the gold is for the braves, go make history.


