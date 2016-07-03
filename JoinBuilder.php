<?php

class JoinBuilder {
	public $joins = '';
	private $tables = [];

	public function get_joins() {
		return $this->joins;
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
				throw new ParameterException("Unknown join type ".$criteria['type']);
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
}
