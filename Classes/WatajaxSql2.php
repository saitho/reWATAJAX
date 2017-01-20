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

class WatajaxSql2 extends Watajax {
	
	public $query = "";
	protected $database_table = "";
	protected $query_sort = "";
	protected $encoding = "UTF-8";
	protected $where = "";
	protected $groupBy = "";
	protected $sqlTables = "";
	protected $dataHandler = null;
	
	public function __construct($encoding = "UTF-8") {
		parent::__construct();
		$this->encoding = $encoding;
	}
	
	public function getGroupedColumnData($column) {
		
	}
	
	public function sortData() {
		
	}
	
	public function getNumberOfPages() {
		$num = @mysql_result(mysql_query($this->count_query), 0);
		$page_num = (is_numeric($num))? ceil($num / $this->perPage) : 0;
		return array("pages" => $page_num, "items" => $num);
	}
	
	public function searchFilterData() {
		
	}
	
	public function setQuery($query) {
		$this->query = $query;
	}
	
	public function setDatahandler($datahandler) {
		$this->dataHandler = $datahandler;
	}
	
	public function getData($ignore_pages = false) {
		$data = array();
		$limit_start = (($this->page - 1) * $this->perPage);
		$columns = array();
		foreach ($this->columns as $key => $value) {
			if ($value["virtual"] != true) {
				$prefix = ($value["table"] != "")? "`" . $value["table"] . "`" . "." : "";
				$columns[] = $prefix . $key;
			}
		}
		if ($ignore_pages == false) {
			$limit = " LIMIT " . $limit_start . "," . $this->perPage;
		}
		
		$sql = $this->query . $limit;
		
		$r = mysql_query($sql);
		while ($row = @mysql_fetch_assoc($r)) {
			$fixed_row = array();
			$row = $this->encode($row);
			if (!is_null($this->dataHandler) && is_callable($this->dataHandler)) {
				$func_name = $this->dataHandler;
				$row = call_user_func($func_name, $row, $this->search, $this->searchColumn);
				if ($row === false) {
					continue;
				}
			}
			foreach ($this->columns as $key => $value) {
				$fixed_row[$key] = $this->transformColumn($key, $row[$key], $row);
			}
			$data[] = $fixed_row;
		}
		return $data;
	}
	
}