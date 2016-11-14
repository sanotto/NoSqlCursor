<?php

namespace NoSqlCursor;

class Cursor
{
	private $read_socket;
	private $write_socket;
	private $read_id;
	private $write_id;
	private $current_key=[];
	private $dbname;
	private $tblnme;
	private $idxnbr;
	private $fldlst;
	private $fldlststr;
	private $keylen;
	private $record=[];

	private function __construct(string $definition, $read_socket, $write_socket)
	{
		$parts = explode('.', $definition);
		if (count($parts) != 5)
		{
			throw new \Exception('Incorrect connection string, 5 parts separated by dots needed i.e. dbname.table.idxnbr.keylen.field1,field2...');
		}

		$this->read_socket=$read_socket;
		$this->write_socket=$write_socket;

		$this->dbname=$parts[0];
		$this->tblnme=$parts[1];
		$this->idxnbr=$parts[2];
		$this->keylen=$parts[3];
		$fields = explode(',', $parts[4]);

		if (count($fields) <= 0)
		{
			throw new \Exception('No fields in field list, comma separated list needed i.e field1,field2, ...');
		}

		if (count($fields) < $this->keylen)
		{
			throw new \Exception('Key lenght can not exced number of available fields');
		}

		foreach($fields as $field)
		{
			$parts = explode(':',$field);
			if (count($parts) < 2)
			{
				throw new \Exception('Incorrect field definition must have at least fieldname:fieldtype');
			}

			$fldtyp = strtolower($parts[1]);
			switch($fldtyp) 
			{
				case 'n':
					if (count($parts) < 4)
					{
						throw new \Exception('Incorrect field definition numeric needs 4 values fieldname:type:len:dec');
					}
					$this->fldlst[$parts[0]]=['fldtyp'=>$fldtyp, 'fldlen'=>$parts[2], 'flddec'=>$parts[3]];
					break;

				case 'c':
					if (count($parts) != 3)
					{
						throw new \Exception('Incorrect field definition char needs 3 values fieldname:type:len');
					}
					$this->fldlst[$parts[0]]=['fldtyp'=>$fldtyp, 'fldlen'=>$parts[2]];
					break;
				case 'd':
					if (count($parts) != 2)
					{
						throw new \Exception('Incorrect field definition char date 2 values fieldname:type');
					}
					$this->fldlst[$parts[0]]=['fldtyp'=>$fldtyp];
					break;
			}
		}

		$this->fldlststr = '';
		$sep='';
		foreach($this->fldlst as $key=>$value)
		{
			$this->fldlststr .= $sep.trim($key);
			$sep=',';
		}
		//Get indexes id
		$this->read_id  = $this->read_socket->getIndexId($this->dbname, $this->tblnme , $this->idxnbr, $this->fldlststr);
		$this->write_id = $this->write_socket->getIndexId($this->dbname, $this->tblnme , $this->idxnbr, $this->fldlststr);

	}

	static public function Open(string $conn_string) :Cursor
	{
		static $read_socket = null;
		static $write_socket = null;

		if ($read_socket === null)
		{
			$read_socket= new \HSPHP\ReadSocket();
			$read_socket->connect();
		}

		if ($write_socket === null)
		{
			$write_socket= new \HSPHP\WriteSocket();
			$write_socket->connect();
		}

		return new Cursor($conn_string, $read_socket, $write_socket);

	}

	public function close()
	{
	}

	public function goTop()
	{
		$loval= [];
		for($i=0;$i<= $this->keylen;$i++)
		{
			$loval[]='';
		}
		return $this->find($loval,'>=');
	}

	public function goBottom()
	{
		$hival= [];
		$i=0;
		foreach($this->fldlst as $field=>$properties)
		{
			if ($i == $this->keylen) break;
			switch($properties['fldtyp'])
			{
				case 'n':
					$hival[]=str_repeat('9',$properties['fldlen']+$properties['flddec']);
					break;
				case 'c':
					$hival[]=str_repeat('Z',$properties['fldlen']);
					break;
				case 'd':
					$hival[]='9999-12-31';
					break;
			}
			$i++;
		}
		return $this->find($hival,'<=');

	}

	public function find(array $keys, string $op='=')
	{

		$this->read_socket->select($this->read_id, $op, $keys); // SELECT WITH PRIMARY KEY
		$response = $this->read_socket->readResponse();
		if(count($response) === 0)
		{
			return false;
		}

		$this->assignFields($response[0]);
		return true;
	}

	private function assignFields(array $response)
	{
		$i = 0;
		foreach($this->fldlst as $field=>$values)
		{
			$this->record[$field]=$response[$i];
			$i++;
		}
	}

	public function __get(string $item)
	{
		return $this->record[$item];
	}

	public function __set(string $item, $value)
	{
		$this->record[$item]=$value;
	}

	public function next()
	{
		$key = array_slice($this->record,0, $this->keylen);
		return $this->find($key, '>');
	}

	public function prev()
	{
		$key = array_slice($this->record,0, $this->keylen);
		return $this->find($key, '<');
	}

	public function add()
	{
		$record = $this->getRawRecord();
		$this->write_socket->insert($this->write_id, $record);
		$response = $this->write_socket->readResponse();
		if ($response[0][0] == 0)
		{
			return true;
		}
		return false;
	}

	public function update()
	{
		$record = $this->getRawRecord();
		$key = array_slice($this->record,0, $this->keylen);
		$this->write_socket->update($this->write_id, '=',$key,$record);
		$response = $this->write_socket->readResponse();
		if ($response[0][0] == 1)
		{
			return true;
		}
		return false;
	}

	private function getRawRecord()
	{
		$record=[];
		foreach($this->record as $key=>$value)
		{
			$record[]=$value;
		}
		return $record;
	}
	public function del($key=null)
	{
		if($key == null)
		{
			$key = array_slice($this->record,0, $this->keylen);
		}

		$this->write_socket->delete($this->write_id, '=',$key);
		$response = $this->write_socket->readResponse();
		if ($response[0][0] == 1)
		{
			$this->find($key,'<=');
			return true;
		}
		return false;
	}
}
?>
