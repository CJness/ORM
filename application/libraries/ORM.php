<?php

/**
 * ORM
 *
 * @author		Clifford James
 * @link 		http://cliffordjames.nl/
 * @version		2010.12.10 PHP 5.2
 */
class ORM {

	function __construct($params = NULL) 
	{
		switch (TRUE) 
		{
      		case is_numeric($params):
        		$this->find_one($params);
        	break;
			
      		case is_array($params):
      		case is_object($params):
        		$this->fill_object($params);
        	break;
        	
        	default:
        		$this->fill_object();
        	break;
    	}
	}
	
	function __call($method, $arguments) 
	{
		$arguments = (isset($arguments[0])) ? $arguments[0] : NULL;
	
		switch (TRUE)
		{
			case in_array($method, $this->has_many()):
				return $this->return_has_many($method, $arguments);
			break;
			
			case in_array($method, $this->has_one()):
				return $this->return_has_one($method, $arguments);
			break;
			
			case in_array($method, $this->belongs_to()):
				return $this->return_belongs_to($method, $arguments);
			break;
		}
	}
	
	function has_many()   { return array(); }
	function has_one()    { return array(); }
	function belongs_to() { return array(); }
	
	function CI() { return get_instance(); }
	
	function return_has_many($method, $data = NULL) 
	{
		$relation 	 = ucfirst($method);
		$relation 	 = new $relation();
		
		if (is_numeric($data))
		{
			$where = array(
				'id' => (int) $data,
				$this->get_foreign_key() => (int) $this->id
			);
			
			$relation->find_one($where);
		}
		else
		{
			$relation->fill_object($data);		
			$relation->{$this->get_foreign_key()} = (int) $this->id;
		}
		
		return $relation;
	}
	
	function return_has_one($method, $data = NULL)
	{
		$relation 	 = ucfirst($method);
		$relation 	 = new $relation();
		$where = array(
			$this->get_foreign_key() => (int) $this->id
		);
		
		if (is_numeric($data))
		{
			$where['id'] = (int) $data;
		}
		
		$relation->find_one($where);
		
		if (is_array($data) OR is_object($data))
		{
			$relation->fill_object($data);
		}
		
		return $relation;
	}
	
	function return_belongs_to($method, $data = NULL) 
  	{
  		$relation 	 = ucfirst($method);
  		$relation 	 = new $relation();
  		
  		$relation->find_one($this->{$relation->get_foreign_key()});
  		
  		if (is_array($data) OR is_object($data))
  		{
			$relation->fill_object($data);
		}
		
		return $relation;
  	}
	
	function find($where = NULL, $options = NULL)
	{
		if (is_numeric($where)) 
		{
			return $this->find_one($where, $options);
		}
		
		$this->set_where($where);
		$this->set_options($options);
		
		return $this->fill_objects($this->CI()->db->get($this->table())->result());
	}
	
	function all($options = NULL)
	{
		return $this->find(NULL, $options);
	}
	
	function find_one($where = NULL, $options = NULL)
	{
		$options['limit'] = 1;
		
		if (is_numeric($where)) 
		{
			$where = array('id' => (int) $where);
		}
		
		$this->set_where($where);
		$this->set_options($options);
		
		return $this->fill_object($this->CI()->db->get($this->table())->row());
	}
	
	function first($where = NULL, $options = NULL) 
	{	
		if ( ! isset($options['order_by'])) 
		{
      		$options['order_by'] = $this->table().'.id ASC';
    	}
    	
		return $this->find_one($where, $options);
	}
	
	function last($where = NULL, $options = NULL) 
	{
		if ( ! isset($options['order_by'])) 
		{
      		$options['order_by'] = $this->table().'.id DESC';
    	}
    	
		return $this->find_one($where, $options);
	}
	
	function exists()
  	{
  		return (isset($this->id) AND ! is_null($this->id));
  	}
	
	function save()
	{
		$arguments = func_get_args();
		
		foreach ($arguments as $arg)
		{
			switch (TRUE)
			{
				case ($arg instanceof a):
					foreach ($arg as $object)
					{
						$this->save_relation($object);
					}
				break;
				
				case is_object($arg):
					$this->save_relation($arg);
				break;
				
				case is_array($arg):
					$this->fill_object($arg);
				break;
			}
		}
		
		if ($this->exists())
		{
			$this->update();
		}
		else
		{
			$this->insert();
		}
	}
	
	function save_relation($relation)
	{
		$class = strtolower(get_class($relation));
		
		switch (TRUE)
		{
			case in_array($class, $this->has_many()):
				$relation->{$this->get_foreign_key()} = $this->id;
				var_dump($relation);
				$relation->save();
			break;
			
			case in_array($class, $this->has_one()):
				$relation->{$this->get_foreign_key()} = $this->id;
				$relation->save();
			break;
			
			case in_array($class, $this->belongs_to()):
				$relation->save();
				
				$this->{$relation->get_foreign_key()} = $relation->id;
			break;
		}
	}
	
	protected function insert()
	{
		if ($this->CI()->db->insert($this->table(), $this->sanitize())) 
		{
			if ($id = $this->CI()->db->insert_id())
			{
				$this->id = $id;
			}
			
			$this->CI()->db->cache_delete_all();
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	protected function update()
	{
		$this->set_where($this->id);
		
		if ($this->CI()->db->update($this->table(), $this->sanitize())) 
		{
			$this->CI()->db->cache_delete_all();
	
	   		return TRUE;
	    }

    	return FALSE;
	}
	
	function sanitize()
	{
		$array = array();
	
		foreach ($this as $key => $val)
		{
			if (array_key_exists($key, $this->get_fields()))
			{
				$array[ $key ] = $val;
			}
		}
		
		return $array;
	}
	
	protected function explain() 
	{
		foreach ($this->CI()->db->query("EXPLAIN `".$this->table()."`")->result() as $field) 
    	{
      		$this->CI()->db->tables[ $this->table() ][ $field->Field ] = array(
        		'type'    => $field->Type,
        		'null'    => ($field->Null == 'YES'),
        		'pri'     => ($field->Key == 'PRI'),
        		'default' => $field->Default,
        		'extra'   => $field->Extra
        	);
    	}
	}
	
	function get_fields()
	{
		if ( ! isset($this->CI()->db->tables[ $this->table() ]))
		{
			$this->explain();
		}
		
		return $this->CI()->db->tables[ $this->table() ];
	}
	
	function get_foreign_key()
	{
		return strtolower(get_class($this)).'_id';
	}
	
	function set_where($where = NULL) 
	{
		$this->CI()->db->select($this->table().'.*');
	
		foreach ($this->belongs_to() as $relation)
		{
			$foreign_key = strtolower($relation).'_id';
		
			if (isset($this->{$foreign_key}) AND ! is_null($this->{$foreign_key}))
			{
				$where[ $foreign_key ] = $this->{$foreign_key};
			}
		}
		
		foreach ($this->has_many() as $relation)
		{
			$foreign_key = strtolower($relation).'_id';
		
			if (isset($this->{$foreign_key}) AND ! is_null($this->{$foreign_key}))
			{
				$this->set_join(new $relation);
			}
		}
			
		if ( ! $where) 
		{
			return;
		}
		elseif (is_numeric($where))
		{
			$this->CI()->db->where($this->table().'.id', (int) $where);
			
			return;
		}
		
		foreach ($where as $field => $value) 
		{
      		if (strpos($field, '.') === FALSE) 
      		{
      			$field = $this->table().'.'.$field;
      		}

      		if (is_array($value)) 
      		{
				$this->CI()->db->where_in($field, $value);
      		} 
      		else 
      		{
				$this->CI()->db->where($field, $value);
			}
    	}
	}
	
	function set_options($options = NULL) 
	{
		if ( ! $options) 
		{
			return;
		}
		
    	foreach ($options as $option => $value) 
    	{
      		switch ($option) 
      		{
        		case 'limit':
          			if (is_numeric($value)) 
          			{
          				$value = array($value);
          			}
          			
          			if ( ! isset($value[1])) 
          			{
          				$value[1] = NULL;
          			}
          			
          			$this->CI()->db->{$option}($value[0], $value[1]);
          		break;
          		
	        	case 'join':
	        		if ( ! isset($value[2])) 
	        		{
	        			$value[2] = NULL;
	        		}
	        	
	          		$this->CI()->db->join($value[0], $value[1], $value[2]);
	          	break;
	        
	        	default:
	          		$this->CI()->db->{$option}($value);
	        	break;
	      	}
	    }
	}
	
	function set_join($relation) 
	{
		$join_table = $this->format_join_table($this->table(), $relation->table());

    	$this->CI()->db->join($join_table, $join_table.'.'.$this->get_foreign_key() .' = '.$this->table().'.id', 'right');
    	$this->CI()->db->where($join_table.'.'.$relation->get_foreign_key(), $this->{$relation->get_foreign_key()});
	}
	
	function format_join_table() 
  	{
    	$table_array = func_get_args();
    	sort($table_array);
    	
    	return implode('_', $table_array);
  	}
	
	function fill_object($data = NULL)
	{
		switch (TRUE)
		{
			case is_array($data):
			case is_object($data):
				foreach ($data as $field => $value)
				{
					$this->{$field} = $value;
				}
			break;
			
			default:
				foreach (array_keys($this->get_fields()) as $field)
				{
					$this->{$field} = NULL;
				}
			break;
		}
		
		return $this;
	}
	
	function fill_objects($data) 
	{
		$object  = get_class($this);
		$objects = new a();
		
    	foreach ($data as $row) 
    	{
      		$objects[] = new $object($row);
    	}
    	
    	return $objects;
	}
	
}

class a extends ArrayObject {

	function __call($method, $arguments) 
	{
		$arguments = (isset($arguments[0])) ? $arguments[0] : NULL;
	
		foreach ($this as $object)
		{
			$object->$method($arguments);
		}
	}

	function first() 
	{
		return reset($this);
	}
	
	function last() 
	{
		return end($this);
	}
	
	function count()
	{
		return count($this);
	}
	
}

spl_autoload_register('orm_autoload');

function orm_autoload($class) 
{
	$class = APPPATH.'models/'.strtolower($class).EXT;
  
	if (file_exists($class)) 
	{
		include $class;
	}
}