<?php
/**
 * @info Watajax JQUERY plugin server side
 * @author West Art Communication AB
 * @version 1.0
 * @changelog
 * 		20100106 1.0 - First release
 */

class WatajaxSql extends Watajax {
	
	protected $query = "";
	protected $database_table = "";
	protected $query_sort = "";
	protected $encoding = "UTF-8";
	protected $where = "";
	
	public function __construct($encoding = "UTF-8") {
		parent::__construct();
		$this->encoding = $encoding;
		$this->table = $_GET["watajax_table"];
	}
	
	public function addWhere($where) {
		if ($this->where == "") {
			$this->where = " WHERE ";
		}
		$this->where .= $where." ";
	}
	
	public function searchFilterData() {
		if (!empty($this->search)) {
			$where = "";
			foreach($this->columns as $key => $value) {
				if (empty($value["virtual"])) {
					$where .= "`$key` LIKE '%".$this->search."%' OR ";
				}
			}
			$where = "(".rtrim($where, " OR ").")";
			$this->addWhere($where);
		}
	}
	
	public function sortData() {
		if (!empty($this->sortBy)) {
			$order = (strtoupper($this->sortOrder) == "ASC") ? "ASC" : "DESC";
			$this->query_sort = " ORDER BY `".mysql_real_escape_string($this->sortBy)."` ".$order;
		}
	}
	
	public function transformColumn($col, $data, $row) {
		$replace = array();
		foreach(array_keys($this->columns) as $k) {	$replace[] = "!".$k; }
		$data = ($this->encoding == "UTF-8") ? utf8_encode($data) : $data;
		if (!empty($this->columns[$col]["transform"])) {
			$data = str_replace($replace, $row, $this->columns[$col]["transform"]);
		}
		return $data;
	}
	
	public function getData() {
		$data = array();
		$limit_start = (($this->page-1)*$this->perPage);
		$columns = array();
		foreach($this->columns as $key => $value) {
			if (empty($value["virtual"])) { $columns[] = $key; }
		}
		$sql = "SELECT `".implode("`,`", $columns)."` FROM `".mysql_real_escape_string($this->table)."`".$this->where.$this->query_sort." LIMIT ".$limit_start.",".$this->perPage;
		$r = mysql_query($sql);
		while($row = mysql_fetch_assoc($r)) {
			$fixed_row = array();
			foreach($this->columns as $key => $value) {
				$fixed_row[$key] = $this->transformColumn($key, $row[$key], $row);
			}
			$data[] = $fixed_row;
		}
		return $data;
	}
	
	function getNumberOfResults() {
		$sql = "SELECT COUNT(*) FROM `".mysql_real_escape_string($this->table)."`".$this->where;
		$num = @mysql_result(mysql_query($sql),0);
		return $num;
	}
}