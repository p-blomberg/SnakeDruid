<?php

class QueryBuilder {

	public $select, $table_name;
	public $pcount, $glue;
	public $where = [];
	public $joins = '';
	public $params = [];
	public $order = [];
	public $limit = null;
	public $default_order = null;
	private $tables = [];

	public function __construct($select, $table_name, $glue='AND', $pcount=0) {
		$this->select = $select;
		$this->table_name = $table_name;
		$this->pcount = $pcount;
		$this->glue = $glue;
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
		return $this->joins;
	}

	public function get_where() {
		return implode($this->glue."\n", $this->where);
	}

	public function params() {
		return $this->params;
	}

	public function join($from, $to, $criteria=null) {
		if(in_array($to, $this->tables)) {
			return null;
		}
		if(isset($criteria)) {
			switch($criteria['operator']) {
			case 'using':
				$this->joins .= 'JOIN "'.$to.'" USING("'.implode('", ', $criteria['params'])."\")\n";
				break;
			case 'on':
				$this->joins .= 'JOIN "'.$to.'" ON(';
				$joins = [];
				foreach($criteria['params'] as $f => $t) {
					$joins[] = '"'.$from.'"."'.$f.'" = "'.$to.'"."'.$t.'"';
				}
				$this->joins .= implode(' AND ', $joins).")\n";
				break;
			default:
				throw new Exception("Unknown join type ".$criteria['type']);
			}
		} else {
			$con = DBSchema::connection($from, $to);
			$this->joins .= 'JOIN "'.$to.'" ON(';
			$terms = [];
			foreach($con['fields'] as $f => $t) {
				$terms[] = '"'.$from.'"."'.$f.'" = "'.$to.'"."'.$t.'"';
			}
			$this->joins .= implode(' AND ', $terms).")\n";
		}
		$this->tables[] = $to;
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
				throw new Exception("operator in requires array as value, got '$value'");
			}
			$ret['operator'] = '= ANY(';
			$ret['after']    = ')';
			return $ret;

		case 'not_in':
			if(!is_array($value) && $value !== null) {
				throw new Exception("operator not_in requires array as value, got '$value'");
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
			throw new Exception("Operator '$operator' is not implemented");
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
