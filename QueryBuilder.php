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

	public function where($table, $column, $operator, $value) {
		switch($operator) {
		case 'in':
		case 'null':
		case 'not_null':
			throw new Exception('not implemented');
		default:
			$this->where[] = "\"$table\".\"$column\" $operator $".++$this->pcount;
			$this->params[] = $value;
		}
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
