<?php

namespace NoSqlCursor\Test;

class FirstTest extends \PHPUnit_Framework_TestCase
{
	private $conn;
	private $db_name;

	/**
	* Do before the first test
	*/
	public static function setUpBeforeClass()
	{
	}

	/**
	* Do before the last test
	*/
	public static function tearDownAfterClass()
	{
		// do sth after the last test
	} 

	/**
	* @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
	*/
	public function __construct()
	{
		// do before the first test
		$db = $GLOBALS['DB_NAME'];
		$this->db_name=$db;

		$this->conn =  new \mysqli($GLOBALS['DB_HOST'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS']);
		$this->conn->connect_errno ? die("MySql Connect error:". mysqli_connect_error()."\n"):0;
		mysqli_query($this->conn,"drop   database ".$db.";");
		mysqli_query($this->conn,"create database ".$db.";");
		mysqli_query($this->conn,"use    database ".$db.";");
		$qrystr =<<<QRY
		CREATE TABLE `$db`.`accounts` (
			`fuirrn` BIGINT NOT NULL AUTO_INCREMENT,
			`fuisuc` DECIMAL(5,0) NULL DEFAULT '0',
			`fuicah` DECIMAL(15,0) NULL DEFAULT '0',
			`fufalt` DATE NULL DEFAULT NULL,
			`fuimca` CHAR(6) NULL DEFAULT '0',
			`fuimpo` DECIMAL(15,2) NULL DEFAULT '0',
			PRIMARY KEY (`fuirrn`)
		)
		COLLATE='utf8mb4_general_ci'
		ENGINE=InnoDB
;

QRY;
		mysqli_query($this->conn,$qrystr);

		$qrystr=<<<QRY
		ALTER TABLE `$db`.`accounts`
			ADD INDEX `idx002` (`fuisuc`, `fuicah`, `fufalt`, `fuirrn`) USING BTREE;
QRY;
		mysqli_query($this->conn,$qrystr);

		$qrystr=<<<QRY
		ALTER TABLE `$db`.`accounts`
			ADD INDEX `idx003` (`fufalt`, `fuisuc`, `fuicah`, `fuirrn`) USING BTREE;
QRY;
		mysqli_query($this->conn,$qrystr);


	}

	public function __destruct()
	{
		$db = $GLOBALS['DB_NAME'];
		$qrystr=<<<QRY
	    DROP DATABASE `$db`;
QRY;
	//	mysqli_query($this->conn,$qrystr);


	}

	/**
	* @return PHPUnit_Extensions_Database_DataSet_IDataSet
	*/
	public function getDataSet()
	{
	}

	public function testTrueIsTrue()
	{
		    $foo = true;
		    $this->assertTrue($foo);
	}

	public function testEmptyConnectionStringRaisesException()
	{
		try{
			$c = \NoSqlCursor\Cursor::Open('');
		}
		catch(\Exception $e)
		{
		};
		$msg='Incorrect connection string, 5 parts separated by dots needed i.e. dbname.table.idxnbr.keylen.field1,field2...';
	  	$this->assertContains($msg, $e->getMessage());

	}

	public function testConnectionStringCorrectlyParsed()
	{
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.2.2.fuisuc:n:15:0,fuicah:n:15:0');
		$this->assertAttributeEquals($this->db_name, 'dbname', $c);
		$this->assertAttributeEquals('accounts', 'tblnme', $c);
		$this->assertAttributeEquals('2', 'idxnbr', $c);
		$this->assertAttributeEquals(['fuisuc'=>['fldtyp'=>'n','fldlen'=>15, 'flddec'=>0], 'fuicah'=>['fldtyp'=>'n','fldlen'=>15, 'flddec'=>0],], 'fldlst', $c);
		$this->assertAttributeEquals('2', 'keylen', $c);
	}

	public function testColumnsNamesTrimmed()
	{
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.2.2.fuisuc:n:15:0, fuicah:n:15:0');
		$this->assertAttributeEquals('fuisuc,fuicah', 'fldlststr', $c);
	}

	public function testKeyLenghtCannotExcedFieldsCount()
	{
		try{
			$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.2.3.fuisuc:n:15:0, fuicah:n:15:0');
		}
		catch(\Exception $e)
		{
		};
		$msg='Key lenght can not exced number of available fields';
	  	$this->assertContains($msg, $e->getMessage());

	}

	public function testFindExistingRecord()
	{
		$this->populateTable();
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.1.3.fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2');
		$result = $c->find([0,11111111, '2016-01-01']);
		$this->assertTrue($result);
		$this->assertEquals($c->fuimpo , 123.50);

	}

	public function testFindNotExistingRecordiDontChangeCurrent()
	{
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.1.3.fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2');
		//This must be found
		$result = $c->find([0,11111111, '2016-01-01']);
		$this->assertEquals($c->fuimpo , 123.50);

		$result = $c->find([0,91111111, '2016-01-01']);
		$this->assertFalse($result);
        //Assert current record didn't change
		$this->assertEquals($c->fuimpo , 123.50);

	}

	public function testGoTopRetrievesFirstRow()
	{
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.1.3.fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:0,fuirrn:n:20:0');
		//This must be found
		$result = $c->goTop();
		$this->assertEquals($c->fuisuc , 0);
		$this->assertEquals($c->fuicah , 11111111);
		$this->assertEquals($c->fufalt , '2016-01-01');
		$this->assertEquals($c->fuimca, 'DEBIT');
		$this->assertEquals($c->fuimpo , 123.50);
		$this->assertEquals($c->fuirrn , 1);
	}

	public function testGoBotRetrievesLastRow()
	{
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.1.3.fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2,fuirrn:n:20:0');
		//This must be found
		$result = $c->goBottom();
		$this->assertTrue($result);
		$this->assertEquals($c->fuisuc , 1);
		$this->assertEquals($c->fuicah , 33333333);
		$this->assertEquals($c->fufalt , '2016-02-01');
		$this->assertEquals($c->fuimca, 'CREDIT');
		$this->assertEquals($c->fuimpo , 300);
		$this->assertEquals($c->fuirrn , 12);
	}

	public function testNextRetrievesNextRecord()
	{
		$this->populateTable();
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.1.3.fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2,fuirrn:n:20:0');
		$result = $c->find([0,11111111, '2016-01-01']);
		$this->assertTrue($result);
		$this->assertEquals($c->fuimpo , 123.50);
		for ($i=2;$i<=12;$i++)
		{
			$result = $c->next();
			$this->assertTrue($result);
			$this->assertEquals($c->fuirrn ,$i);
		}

	}


	public function testPrevRetrievesPreviousRecord()
	{
		$this->populateTable();
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.1.3.fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2,fuirrn:n:20:0');
		$result = $c->goBottom();
		$this->assertTrue($result);
		for ($i=11;$i>0;$i--)
		{
			$result = $c->prev();
			$this->assertTrue($result);
			$this->assertEquals($c->fuirrn ,$i);
		}

	}

	public function testDeleteCurrentRecord()
	{
		$this->populateTable();
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.0.1.fuirrn:n:20:0,fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2');
		//This must be found
		$result = $c->goBottom();
		$this->assertTrue($result);
		$this->assertEquals($c->fuisuc , 1);
		$this->assertEquals($c->fuicah , 33333333);
		$this->assertEquals($c->fufalt , '2016-02-01');
		$this->assertEquals($c->fuimca, 'CREDIT');
		$this->assertEquals($c->fuimpo , 300);
		$this->assertEquals($c->fuirrn , 12);
		$result=$c->del();
		$this->assertTrue($result);
		$this->assertEquals($c->fuirrn , 11);
		$result=$c->find([12]);
		$this->assertFalse($result);
		$result=$c->goBottom();
		$this->assertTrue($result);
		$this->assertEquals($c->fuirrn , 11);
	}

	public function testDeleteByKey()
	{
		$this->populateTable();
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.0.1.fuirrn:n:20:0,fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2');
		//This must be found
		$result=$c->del([5]);
		$this->assertTrue($result);
		$this->assertEquals($c->fuirrn , 4);
		$result=$c->find([5]);
		$this->assertFalse($result);
	}


	public function testUpdate()
	{
		$this->populateTable();
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.0.1.fuirrn:n:20:0,fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2');
		//This must be found
		$result=$c->Find([8]);
		$this->assertTrue($result);
		$this->assertEquals($c->fuirrn , 8);
		$this->assertEquals($c->fuimpo , 100);
		$c->fuimpo=1234.56;
		$result = $c->update();
		$this->assertTrue($result);
		$result=$c->find([8]);
		$this->assertTrue($result);
		$this->assertEquals($c->fuimpo , 1234.56);

	}

	public function testAdd()
	{
		$this->populateTable();
		$c = \NoSqlCursor\Cursor::Open($this->db_name.'.accounts.0.1.fuirrn:n:20:0,fuisuc:n:15:0,fuicah:n:15:0,fufalt:d,fuimca:c:6,fuimpo:n:15:2');
		$c->fuirrn=13;
		$c->fuisuc=4;
		$c->fuicah=44444444;
		$c->fuimca='DEBIT';
		$c->fufalt='2016-12-31';
		$c->fuimpo=678.90;
		
		$result=$c->add();
		$this->assertTrue($result);

		$result=$c->find([13]);
		$this->assertTrue($result);
		$this->assertEquals($c->fuirrn , 13);
	}

	private function populateTable()
	{
		$db = $GLOBALS['DB_NAME'];
		$qrystr=<<<QRY
		DELETE FROM  `$db`.`accounts`;
QRY;
		mysqli_query($this->conn,$qrystr);
		$qrystr=<<<QRY
		insert into `cursor_test_db`.`accounts` (fuirrn, fuisuc, fuicah, fufalt, fuimca, fuimpo) 
		values
			( 1, 0, 11111111, Date('2016-01-01'), 'DEBIT ', 123.50),
			( 2, 0, 11111111, Date('2016-02-01'), 'CREDIT', 100.00),
			( 3, 0, 22222222, Date('2016-02-01'), 'CREDIT', 200.00),
			( 4, 0, 22222222, Date('2016-03-01'), 'CREDIT', 210.00),
			( 5, 0, 22222222, Date('2016-04-01'), 'CREDIT', 220.00),
			( 6, 0, 33333333, Date('2016-02-01'), 'CREDIT', 300.00),
			( 7, 1, 11111111, Date('2016-01-01'), 'DEBIT ', 123.50),
			( 8, 1, 11111111, Date('2016-02-01'), 'CREDIT', 100.00),
			( 9, 1, 22222222, Date('2016-02-01'), 'CREDIT', 200.00),
			(10, 1, 22222222, Date('2016-03-01'), 'CREDIT', 210.00),
			(11, 1, 22222222, Date('2016-04-01'), 'CREDIT', 220.00),
			(12, 1, 33333333, Date('2016-02-01'), 'CREDIT', 300.00)
			;
QRY;
		mysqli_query($this->conn,$qrystr);
	}
}

?>

