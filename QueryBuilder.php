<?php
require_once 'JoinBuilder.php';

class QueryBuilder {

	public $select, $table_name;
	public $pcount, $glue;
	public $where = [];
	public $params = [];
	public $order = [];
	public $limit = null;
	public $default_order = null;
	public $join_builder;

	public function __construct($select, $table_name, $glue='AND', $master_query=null) {
		$this->select = $select;
		$this->table_name = $table_name;
		$this->glue = $glue;
		if($master_query === null) {
			$this->pcount = 0;
			$this->join_builder = new JoinBuilder($table_name);
		} else {
			$this->pcount = $master_query->pcount;
			$this->join_builder = $master_query->join_builder;
		}
	}

	public function query() {
		switch($this->select) {
		case '*':
			$query = "SELECT DISTINCT \"{$this->table_name}\".*\n";
			break;
		default:
			throw new Exception('not implemented');
		}
		$query .= "FROM {$this->table_name}\n".$this->get_joins();
		if(!empty($this->where)) {
			$query .= 'WHERE '.$this->get_where()."\n";
		}
		if(empty($this->order) && !empty($this->default_order)) {
			$this->order($this->default_order);
		}
		if(!empty($this->order)) {
			$query .= "ORDER BY ".join(', ', $this->order)."\n";
		}
		if(isset($this->limit)) {
			$query .= "LIMIT cast($".++$this->pcount." AS int)\n";
			$this->params[] = $this->limit;
		}
		return $query;
	}

	public function get_joins() {
		return $this->join_builder->get_joins();
	}

	public function get_where() {
		return implode(' '.$this->glue."\n", $this->where);
	}

	public function params() {
		return $this->params;
	}

	public function join($from, $to, $criteria=null) {
		$this->join_builder->join($from, $to, $criteria);
	}

	public function update_master(&$master) {
		$master->pcount = $this->pcount;
		$master->params = array_merge($master->params, $this->params);
		$master->where[] = "({$this->get_where()})";
	}

	protected function operator($operator, &$value) {
		$ret = ['after' => ''];
		switch($operator) {
		case NULL:
			$ret['operator'] = '=';
			return $ret;
		case '=':
		case '<':
		case '>':
		case '<=':
		case '>=':
		case '<>':  // not eq
		case '~':   // case sensitive regexp
		case '~*';  // case insensitive regexp
		case '!~':  // not match case sensitive
		case '!~*': // not match case insensitive
			$ret['operator'] = $operator;
			return $ret;

		case '!=':
			$ret['operator'] = '<>';
			return $ret;

		case 'regexp':
			$ret['operator'] = '~';
			return $ret;

		case 'not_null':
			$value = null;
			// fallthrough
		case 'distinct_from':
			$ret['operator'] = 'IS DISTINCT FROM';
			return $ret;

		case 'null':
			$value = null;
			// fallthrough
		case 'not_distinct_from':
			$ret['operator'] = 'IS NOT DISTINCT FROM';
			return $ret;

		case 'in':
			if(!is_array($value) && $value !== null) {
				throw new ParameterException("operator in requires array as value, got '$value'");
			}
			$ret['operator'] = '= ANY(';
			$ret['after']    = ')';
			return $ret;

		case 'not_in':
			if(!is_array($value) && $value !== null) {
				throw new ParameterException("operator not_in requires array as value, got '$value'");
			}
			$ret['operator'] = '<> ALL(';
			$ret['after']    = ')';
			return $ret;

		case 'like':
			$ret['operator'] = 'LIKE';
			return $ret;

		case 'ilike':
			$ret['operator'] = 'ILIKE';
			return $ret;

		default:
			throw new ParameterException("Operator '$operator' is not implemented");
		}
	}

	public function where($table, $column, $operator, $value) {
		$op = $this->operator($operator, $value);
		$this->where[] = "\"$table\".\"$column\" ".$op['operator']." $".++$this->pcount.$op['after'];
		$this->params[] = $value;
	}

	public function order($order) {
		if(is_array($order)) {
			foreach($order as $o) {
				$this->order($o);
			}
		} else {
			$order = explode(':', $order);
			$o = '"'.$order[0].'"';
			if(count($order) > 1 && $order[1] == 'desc') {
				$o .= ' DESC';
			}
			$this->order[] = $o;
		}
	}

	public function limit($limit) {
		if(is_array($limit)) {
			throw new Exception('Not implemented');
		} else {
			$this->limit = $limit;
		}
	}
}
