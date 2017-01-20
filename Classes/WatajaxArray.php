<?php
/**
 * @info Watajax JQUERY plugin server side
 * @author West Art Communication AB
 * @version 1.0
 * @changelog
 * 		20100106 1.0 - First release
 */


class WatajaxArray extends Watajax {
	public function __construct() { parent::__construct(); }
	
	public function getNumberOfResults() {
		return count($this->data);
	}
	
	public function getData() {
		return array_slice($this->data, (($this->page-1)*$this->perPage), $this->perPage);
	}
	
	public function searchFilterData() {
		if (!empty($this->search)) {
			$this->data = $this->arr_search( $this->data, $this->search);
		}
	}
	
	function arr_search( $data, $query ) {
		$result = array();
		foreach ($data as $row_id => $row_data) {
			foreach($row_data as $column) {
				if (stripos($column, $query) !== false) {
					$result[$row_id] = $row_data;
				}
			}
		}
		return $result;
	}
	
	public function sortData() {
		if (!empty($this->sortBy)) {
			$f='strcasecmp';
			$arr = $this->data;
			$l = $this->sortBy;
			if ($this->columns[$l]['sort_type']  == "numeric") {
				$function = "return (preg_replace('@\D@','',trim(strip_tags(\$a[$l]))) > preg_replace('@\D@','',trim(strip_tags(\$b[$l]))));";
			} else {
				$function = "return $f(trim(strip_tags(\$a[$l])), trim(strip_tags(\$b[$l])));";
			}
			usort($arr, create_function('$a, $b', $function));
			$this->data = (strtoupper($this->sortOrder) == "ASC") ? $arr : array_reverse($arr);
		}
	}
	
}