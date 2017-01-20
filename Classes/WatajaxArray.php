<?php

/**
 * @info    Watajax JQUERY plugin server side
 * @author  West Art Communication AB
 * @version 1.1
 * @changelog
 *         20100106 1.1 - Fixed UTF encoding issue
 *         20100106 1.0 - First release
 * @see     http://code.google.com/p/watajax/
 */

class WatajaxArray extends Watajax {
	
	protected $encoding = "UTF-8";
	
	public function __construct($encoding = "UTF-8") {
		$this->encoding = $encoding;
		parent::__construct();
	}
	
	public function getNumberOfPages() {
		return array("pages" => ceil(count($this->data) / $this->perPage), "items" => count($this->data));
	}
	
	public function getData($ignore_pages = false) {
		if ($ignore_pages == false) {
			foreach ($this->data as $real_key => $row) {
				$row = $this->encode($row);
				foreach ($row as $key => $value) {
					$fixed_row[$real_key][$key] = $this->transformColumn($key, $value, $row);
				}
			}
			return array_slice($this->data, (($this->page - 1) * $this->perPage), $this->perPage);
			
		} else {
			return $this->data;
		}
	}
	
	public function searchFilterData() {
		if($this->use_manual_search_filter !== true){
			if ($this->search != "") {
				$this->data = $this->arr_search($this->data, $this->search);
			}
			if ($filter = $this->getAppliedFilters()) {
				$this->data = $this->filter_data($this->data, $filter);
			}
		}
	}
	
	public function getGroupedColumnData($column) {
		$this->searchFilterData();
		$column_data[] = array();
		foreach ($this->data as $row) {
			$column_data[] = $row[$column];
		}
		return array_unique($column_data);
	}
	
	function filter_data($data, $filter) {
		$result = array();
		foreach ($data as $row_id => $row_data) {
			foreach ($filter as $col => $filtered_value) {
				if ($row_data[$col.'_id'] != $filtered_value && $row_data[$col] != $filtered_value) {
					continue 2;
				}
			}
			$result[$row_id] = $row_data;
		}
		return $result;
	}
	
	function arr_search($data, $query) {
		$result = array();
		foreach ($data as $row_id => $row_data) {
			foreach ($row_data as $column) {
				if (mb_stripos($column, $query, 0, $this->encoding) !== false) {
					$column = $this->checkEncoding($column);
					$result[$row_id] = $row_data;
				}
			}
		}
		return $result;
	}
	
	public function sortData() {
		if ($this->sortBy != NULL) {
			$f = 'strcasecmp';
			$arr = $this->data;
			$l = $this->sortBy;
			if (isset($this->columns[$l]['sort_type']) && $this->columns[$l]['sort_type'] == "numeric") {
				$non_numeric_pattern = '@[^0-9,]@';
				
				$function = "return (str_replace(',','.',preg_replace('".$non_numeric_pattern."','',trim(strip_tags(\$a[$l])))) > str_replace(',','.',preg_replace('".$non_numeric_pattern."','',trim(strip_tags(\$b[$l])))));";
			} else {
				$function = "return $f(trim(strip_tags(\$a['$l'])), trim(strip_tags(\$b['$l'])));";
			}
			usort($arr, create_function("\$a,\$b", $function));
			$this->data = (strtoupper($this->sortOrder) == "ASC")? $arr : array_reverse($arr);
		}
	}
	
	public function transformArray() {
		foreach ($this->data as $val) {
			$fixed_row = array();
			foreach ($this->columns as $key => $col) {
				$fixed_row[$key] = $this->transformColumn($key, $val[$key], $val);
			}
			$data[] = $fixed_row;
		}
		return $this->data = $data;
	}
	
}