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
abstract class Watajax {

	public $columns = array();
	public $data = array();
	public $perPage = 10;
	public $page = 1;
	public $sortBy = NULL;
	public $sortOrder = "ASC";
	public $search = "";
	public $searchColumn = "";
	public $errorMsg = "Din s&ouml;kning gav inga resultat.";
	public $classes = array();
	public $fuzzy_search = true;
	public $contains_search = false;

	public $row_tag = "tr";
	public $column_tag = "td";
	public $header_column_tag = "th";

	/**
	 * Controls how numbers are formatted on export
	 * @var enum .|,
	 */
	public $decimal_export_standard = ".";

	public function __construct() {
		if(defined('DEFAULT_TABLE_ROWS') && is_numeric(DEFAULT_TABLE_ROWS)){
			$per_page = DEFAULT_TABLE_ROWS;
		}else{
			$per_page = 10;
		}
		$this->perPage = (isset($_GET["watajax_per_page"]) && is_numeric($_GET["watajax_per_page"]))? $_GET["watajax_per_page"] : $per_page;
		$this->page = (isset($_GET["watajax_page"]) && is_numeric($_GET["watajax_page"]))? $_GET["watajax_page"] : 1;
		$this->sortBy = (isset($_GET["watajax_sortBy"]) && $_GET["watajax_sortBy"] != "")? $_GET["watajax_sortBy"] : NULL;
		$this->sortOrder = (isset($_GET["watajax_sortOrder"]) && $_GET["watajax_sortOrder"] != "")? $_GET["watajax_sortOrder"] : "ASC";
		$this->search = (isset($_GET["watajax_search"]) && $_GET["watajax_search"] != "")? $_GET["watajax_search"] : "";
		$this->filter = (isset($_GET["watajax_filter"]) && $_GET["watajax_filter"] != "")? $_GET["watajax_filter"] : "";
		$this->searchColumn = (isset($_GET["watajax_searchColumn"]) && $_GET["watajax_searchColumn"] != "" && $_GET["watajax_searchColumn"] != "undefined")? $_GET["watajax_searchColumn"] : NULL;
	}

	public function run() {
		$this->checkForAction();
	}

	public function checkForAction() {
		switch ($_GET["action"]) {
			case "watajax_load_head":
				$this->sendHead();
				break;
			case "watajax_load_settings":
				echo json_encode($this->getSettings());
				break;
			case "watajax_load_body":
				$this->sendBody();
				break;
			case "watajax_load_csv":
				$this->sendCSV();
				break;
			case "watajax_load_xls":
				$this->sendXLS();
				break;
			default:
				break;
		}
	}

	public function getFilter() {
		$filter = array();
		if (is_array($this->columns)) {
			foreach ($this->columns as $id => $data) {
				if (isset($data["filter"]) && ($data["filter"] == "select" || $data["filter"] == "text")) {
					$filter[$id]["type"] = $data["filter"];
					if ($data["filter"] == "select") {
						if (is_array($data["select_filter_values"])) {
							$filter[$id]["contents"] = $data["select_filter_values"];
							$filter[$id]["contents_override"] = true;
						} else {
							$filter[$id]["contents"] = $this->getGroupedColumnData($id);
                            $filter[$id]["order"] = array_keys($filter[$id]["contents"]);
							if(get_class($this) == "MagicAjaxTable" || get_class($this) == "MagicListAjaxHandler") {
								$filter[$id]["contents_override"] = true;
							}
						}
					}
				}
			}
		}
		if (count($filter) > 0) {
			$filter["watajax_has_filters"] = true;
		}
		return $filter;
	}

	public function getSettings() {
		$page_count = $this->getNumberOfPages();
		return array("pages" => $page_count["pages"],
			"items" => $page_count["items"],
			"filters" => $this->getFilter(),
			"search_columns" => $this->getSearchColumns()
		);
	}

	abstract public function getGroupedColumnData($column);

	abstract public function sortData();

	abstract public function getNumberOfPages();

	abstract public function getData($ignore_pages = false);

	abstract public function searchFilterData();

	/**
	 * Adds a class to the TR if condition in $test is med
	 *
	 * @param string $class
	 * @param string $column
	 * @param eval_expression $test
	 */
	public function addClass($class, $column, $test) {
		$this->classes[$column][] = array("test" => $test, "class" => $class);
	}

	public function getRowClasses($row_data) {
		$class = array();
		foreach ($this->classes as $column => $data) {
			foreach ($data as $test) {
				$column_data = $row_data[$column];
				if (eval($test["test"])) {
					if (!in_array($test["class"], $class)) {
						$class[] = $test["class"];
					}
				}
			}
		}
		return $class;
	}

	public function renderClass($row_data) {
		$class = $this->getRowClasses($row_data);
		if (count($class) > 0) {
			return ' class="' . implode(" ", $class) . '"';
		}
	}

	public function sendBody() {
		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData();
		if (count($sorted_data) <= 0) {
			echo "no_rows_found";
		} else {
                        $outercol=0;
			foreach ($sorted_data as $row_id => $row_data) {
				$classes = " ".implode(" ",$this->getRowClasses($row_data));
				echo "<" . $this->row_tag . " class='row_".$outercol++."$classes' id='" . $_GET["watajax_table"] . "_row_$row_id'" . ">";
                                $innercol=0;
				foreach ($this->columns as $column_id => $column_data) {
					if ($this->columns[$column_id]["hide"] != true && $this->columns[$column_id]["load_and_hide"] != true) {
						echo "<" . $this->column_tag . " class='col_".$innercol++." ".$this->columns[$column_id]["class"]."' id='" . $column_id . "_data'>" . $row_data[$column_id] . "</" . $this->column_tag . ">";
					}
				}
				echo "</" . $this->row_tag . ">";
			}
		}
	}

	public function sendXLS($all_pages = true) {
		$filename = $_GET["watajax_table"] . "_" . date("Ymd_Hi") . ".xls";
		$Excel = new PHPExcel();
		$Excel->getProperties()->setCreator("Test");
		$Excel->getProperties()->setLastModifiedBy("Test");
		$Excel->getProperties()->setTitle("Office 2007 XLSX Test Document");
		$Excel->getProperties()->setSubject("Office 2007 XLSX Test Document");
		$Excel->getProperties()->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.");
		$Excel->setActiveSheetIndex(0);
		$Excel->getActiveSheet()->setTitle('Simple');
		$columns = range("A", "Z");
		$i=0;
		$column_count = 0;
		foreach ($this->columns as $id => $data) {
			if ($data["hide"] != true) {
				$Excel->getActiveSheet()->SetCellValue($columns[$i].'1', html_entity_decode($data["name"]));
				$column_count++;
				$i++;
			}
		}
		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData(true);
		if (count($sorted_data) > 0) {
			$j = 2;
			foreach ($sorted_data as $row_id => $row_data) {
				$row = array();
				foreach ($this->columns as $column_id => $column_data) {
					if ($this->columns[$column_id]["hide"] != true) {
						$column_data_holder = str_replace(array("\n", "\t", "\r"), "", $row_data[$column_id]);
						$row[] = html_entity_decode(strip_tags($column_data_holder));
					}
				}
				$i = 0;
				foreach($row as $item) {
					$Excel->getActiveSheet()->SetCellValue($columns[$i].$j, $item);
					$i++;
				}
				$j++;
			}
		}
		for($i=0;$i<$column_count;$i++) {
			$Excel->getActiveSheet()->getColumnDimension($columns[$i])->setAutoSize(true);
		}
		$Excel->getActiveSheet()->getStyle('A1:'.$columns[$column_count].'1')->getFont()->setBold(true);
		$Excel->getActiveSheet()->setAutoFilter($Excel->getActiveSheet()->calculateWorksheetDimension());
		$ExcelWriter = PHPExcel_IOFactory::createWriter($Excel, 'Excel5');
		// We'll be outputting an excel file
		header('Content-type: application/vnd.ms-excel');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		// Write file to the browser
		$ExcelWriter->save('php://output');
		die();

		/**
		 * OLD
		 */
		$Excel = new ExcelWriter('UTF-8', false, $filename);
		$export_data = array();
		$header_row = array();
		foreach ($this->columns as $id => $data) {
			if ($data["hide"] != true) {
				$header_row[] = html_entity_decode($data["name"]);
			}
		}
		$export_data[] = $header_row;

		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData(true);
		if (count($sorted_data) > 0) {
			foreach ($sorted_data as $row_id => $row_data) {
				$row = array();
				foreach ($this->columns as $column_id => $column_data) {
					if ($this->columns[$column_id]["hide"] != true) {
						$column_data_holder = str_replace(array("\n", "\t", "\r"), "", $row_data[$column_id]);
						$row[] = html_entity_decode(strip_tags($column_data_holder));
					}
				}
				$export_data[] = $row;
			}
		}
		$Excel->addArray($export_data);
		$Excel->generateXML($filename);
	}

	public function sendCSV($all_pages = true) {
		$delimiter = ";";
		$header = "";
		$row = "";
		$column_wrapper = '';

		// Header
		header("Content-type: application/vnd.ms-excel; charset=ISO-8859-1; encoding=ISO-8859-1");
		header("Content-Disposition: attachment; filename=" . $_GET["watajax_table"] . "_" . date("Ymd_Hi") . ".csv");
		header("Pragma: no-cache");
		header("Expires: 0");

		foreach ($this->columns as $id => $data) {
			if ($data["hide"] != true) {
				$header .= $column_wrapper . html_entity_decode($data["name"]) . $column_wrapper . $delimiter;
			}
		}
		echo mb_convert_encoding(rtrim($header, $delimiter), "ISO-8859-1") . PHP_EOL;

		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData(true);
		if (count($sorted_data) > 0) {
			foreach ($sorted_data as $row_id => $row_data) {
				$row = "";
				foreach ($this->columns as $column_id => $column_data) {
					if ($this->columns[$column_id]["hide"] != true) {
						if($this->decimal_export_standard == "," || (defined("WATAJAX_DECIMAL_EXPORT_STANDARD") && WATAJAX_DECIMAL_EXPORT_STANDARD == ",")) {
							$row_data[$column_id] = preg_replace("/^(\d*)\.(\d\d\d)$/", "$1$2", $row_data[$column_id]); // Remove thousand separator
							$row_data[$column_id] = preg_replace("/^(\d*)\.(\d\d?)$/", "$1,$2", $row_data[$column_id]); // Change to comma to be XLS compatible
						}
						$column_data_holder = str_replace(array($delimiter, $column_wrapper, "\n", "\t", "\r"), "", html_entity_decode($row_data[$column_id]));
						$row .= $column_wrapper . strip_tags($column_data_holder . $column_wrapper . $delimiter);
					}
				}
				echo mb_convert_encoding(rtrim($row, $delimiter), "ISO-8859-1") . PHP_EOL;
			}
		}
	}

	public function getSearchColumns() {
		$search_columns = array();
		foreach ($this->columns as $id => $data) {
			if ($data["virtual"] != true && $data["skip_in_search"] != true && $data['hide'] != true) {
				$search_columns[$id] = $data["name"];
			}
		}
		return $search_columns;
	}

	public function sendHead() {
		echo "<" . $this->row_tag . ">";
                $inner=0;
		foreach ($this->columns as $id => $data) {
			if ($data["hide"] != true) {
				$class = (isset($data["virtual"]) && $data["virtual"] == true)? "virtual" : "";
				echo "<" . $this->header_column_tag . " id='$id' class='$class col_".$inner++." ".$data["class"]."'>" . $data["name"] . "</" . $this->header_column_tag . ">";
			}
		}
		echo "</" . $this->row_tag . ">";
	}

	public function getAppliedFilters() {
		$appliedFilters = array();
		if ($this->filter != "") {
			$where = "";
			$filters = explode("|", rawurldecode($this->filter));

			if (is_array($filters) && count($filters) > 0) {
				foreach ($filters as $f) {
					list($column, $value) = explode(":", $f);
					$appliedFilters[$column] = $value;
				}
			}
		}
		return (count($appliedFilters) > 0)? $appliedFilters : false;
	}

	public function transformColumn($col, $data, $row) {
		$replace = array();
		$replacements = array();

		foreach (array_keys($this->columns) as $k) {
			if (isset($row[$k])) {
				$replace[] = "!" . $k;
				$replacements[] = $row[$k];
			}
		}

		if (isset($this->columns[$col]["transform"]) && $this->columns[$col]["transform"] != "") {
			$data = str_replace($replace, $replacements, $this->columns[$col]["transform"]);
			if ($this->columns[$col]["eval_transform"] == true || $this->columns[$col]["eval_transform"] == '1') {
				$data = eval($data);
			}
		}
		return $data;
	}

	public function encode($arr) {
		$encoded = array();
		if (isset($this->encoding) && $this->encoding === "UTF-8") {
			foreach ($arr as $key => $val) {
				if (!mb_detect_encoding($val, 'UTF-8', true)) {
					$encoded[$key] = utf8_encode($val);
				} else {
					$encoded[$key] = $val;
				}
			}
		} else {
			$encoded = $arr;
		}
		return $encoded;
	}

	function check_utf8($str) {
		$len = @strlen($str);
		for ($i = 0; $i < $len; $i++) {
			$c = ord($str[$i]);
			if ($c > 128) {
				if (($c > 247)) {
					return false;
				} elseif ($c > 239) {
					$bytes = 4;
				} elseif ($c > 223) {
					$bytes = 3;
				} elseif ($c > 191) {
					$bytes = 2;
				} else {
					return false;
				}
				if (($i + $bytes) > $len) {
					return false;
				}
				while ($bytes > 1) {
					$i++;
					$b = ord($str[$i]);
					if ($b < 128 || $b > 191) {
						return false;
					}
					$bytes--;
				}
			}
		}
		return true;
	}

	function checkEncoding($str) {
		if ($this->encoding == 'UTF-8' && !$this->check_utf8($str)) {
			$str = utf8_encode($str);
		} else {
			if ($this->encoding != 'UTF-8' && $this->check_utf8($str)) {
				$str = utf8_decode($str);
			}
		}
		return $str;
	}

}

class WatajaxSql extends Watajax {

	public $query = "";
	protected $database_table = "";
	protected $query_sort = "";
	protected $encoding = "UTF-8";
	protected $where = "";
	protected $groupBy = "";
	protected $sqlTables = "";
	protected $joins = "";
	protected $custom_sql = "";

	public function __construct($encoding = "UTF-8") {
		parent::__construct();
		$this->encoding = strtoupper($encoding);
		$this->table = $_GET["watajax_table"];
	}

	public function addWhere($where) {
		if ($this->where == "") {
			$this->where = " WHERE ";
		} else {
			$this->where .= " AND ";
		}
		$this->where .= $where . " ";
	}

	public function getWhere() {
		return $this->where;
	}

	public function addGroupBy($groupBy) {
		if ($this->groupBy == "") {
			$this->groupBy = " GROUP BY ";
		} else {
			$this->groupBy .= ", ";
		}
		$this->groupBy .= $groupBy . " ";
	}

	public function addSqlTable($table) {
		if ($this->sqlTables == "") {
			$this->sqlTables = ",";
		}
		$this->sqlTables .= Database::escape($table);
	}

	public function addSqlJoin($type, $table, $on) {
		$this->joins .= " ".$type." JOIN ".$table." ON ".$on." ";
		return true;
	}

	public function searchFilterData() {
		if ($this->search != "") {
			if ($this->searchColumn == NULL) {
				$where = "";
				foreach ($this->columns as $key => $value) {
					if ($value["virtual"] != true && stripos($key, " AS ") === false && $value["sub_query"] == "" && $value["skip_in_search"] != true) {
						$table = (isset($this->columns[$key]["table"]))? $this->columns[$key]["table"] : $this->table;
						$s = rawurldecode($this->checkEncoding($this->search));
						if ($this->fuzzy_search) {
							$where .= "`" . Database::escape($table) . "`.`".Database::escape($key)."` LIKE '" . Database::escape($s) . "%' OR ";
						} else if($this->contains_search) {
							$where .= "`" . Database::escape($table) . "`.`".Database::escape($key)."` LIKE '%" . Database::escape($s) . "%' OR ";
						} else {
							$where .= "`" . Database::escape($table) . "`.`".Database::escape($key)."` = '" . Database::escape($s) . "' OR ";
						}
					} else {
						if ($value["sub_query"] != "") {
							// $where .= "(".$value["sub_query"].") = '".$s."' OR "; // This was very slow, maybe someone has any better ideas? / CS
						}
					}
				}
				$where = "(" . rtrim($where, " OR ") . ")";
				$this->addWhere($where);
			} else {
				$table = (isset($this->columns[$this->searchColumn]["table"]))? $this->columns[$this->searchColumn]["table"] : $this->table;
				$s = rawurldecode($this->search);

				if ($this->fuzzy_search) {
					$where .= "`" . Database::escape($table) . "`.`".Database::escape($this->searchColumn)."` LIKE '" . Database::escape($s) . "%'";
				} elseif($this->contains_search) {
					$where .= "`" . Database::escape($table) . "`.`".Database::escape($this->searchColumn)."` LIKE '%" . Database::escape($s) . "%'";
				} else {
					$where .= "`" . Database::escape($table) . "`.`".Database::escape($this->searchColumn)."` = '" . Database::escape($s) . "'";
				}
				$this->addWhere($where);
			}
		}
		if ($appliedFilters = $this->getAppliedFilters()) {
			$where = "";
			foreach ($appliedFilters as $column => $value) {
				$table = (isset($this->columns[$column]["table"]))? $this->columns[$column]["table"] : $this->table;
				$s = $this->checkEncoding($value);
				$where .= "`" . Database::escape($table) . "`.`" . Database::escape($column) . "` = '" . Database::escape($s) . "' AND ";
			}
			$where = "(" . rtrim($where, " AND ") . ")";
			$this->addWhere($where);
		}
	}

	public function sortData() {
		if ($this->sortBy != "") {
			$order = (strtoupper($this->sortOrder) == "ASC")? "ASC" : "DESC";
			$table = (isset($this->columns[$this->sortBy]["table"]))? $this->columns[$this->sortBy]["table"] : $this->table;
			if($this->columns[$this->sortBy]["function"]) {
				$this->query_sort = " ORDER BY " . Database::escape($this->sortBy) . " " . $order;
			} else if (!isset($this->columns[$this->sortBy]["sub_query"])) {
				$this->query_sort = " ORDER BY `".Database::escape($table)."`.`" . Database::escape($this->sortBy) . "` " . $order;
			} else {
				$this->query_sort = " ORDER BY " . Database::escape($this->sortBy) . " " . $order;
			}
		}
	}

	public function getGroupedColumnData($column) {
		$column_data = array();
		$table = (isset($this->columns[$column]["table"]))? $this->columns[$column]["table"] : $this->table;
		$sql = "SELECT `".Database::escape($table)."`.`".Database::escape($column)."` FROM `" . Database::escape($this->table) . "`" . $this->sqlTables . $this->joins . $this->where . " GROUP BY `".Database::escape($column)."` ORDER BY `".Database::escape($column)."`";
		$this->filter_query = $sql;
		$r = mysql_query($sql);
		while ($data = mysql_fetch_row($r)) {
			$column_data[] = $this->checkEncoding($data[0]);
		}
		return $column_data;
	}

	public function assebleColumns($columns) {
		$columns_sql = "";
		foreach ($columns as $c) {
			$col_name = explode(".", $c);
			$col_name = array_pop($col_name);
			if ($this->columns[$col_name]["distinct"] == true) {
				$columns_sql .= "DISTINCT ";
			}
			if ($this->columns[$col_name]["sub_query"] != "") {
				$columns_sql .= "(" . $this->columns[$col_name]["sub_query"] . ") AS " . $col_name . ",";
			} else if ($this->columns[$col_name]["function"] != "") {
				$columns_sql .= $this->columns[$col_name]["function"] . " AS " . $col_name . ",";
			} else {
				if (stripos($c, ".") === false) {
					$columns_sql .= "`" . Database::escape($this->table) . "`.`" . Database::escape($c) . "`,";
				} else {
					//$columns_sql .= Database::escape($c) . ",";
					$columns_sql .= $c . ",";
				}
			}
		}
		return rtrim($columns_sql, ",");
	}

	/**
	 * Use a custom SQL query instead of the watajax generated one.
	 * BE AWARE! The SQL query must return columns that you select in the column configuration
	 * BE AWARE! Do NOT inlcude LIMIT, that will be added by paging automaticly
	 * @param string/function $sql
	 */
	public function useCustomSql($sql) {
		$this->custom_sql = $sql;
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

		if($this->custom_sql != "") {
			$sql = $this->custom_sql.$limit;
		} else {
			$sql = "SELECT " . $this->assebleColumns($columns) . " FROM `" . Database::escape($this->table) . "`" . $this->sqlTables . $this->joins . $this->where . $this->groupBy . $this->query_sort . $limit;
		}
		$this->query = $sql;
		$r = mysql_query($sql);
		if(mysql_error()) {
			error_log(mysql_error());
			error_log($sql);
			if(defined("DEBUG_MODE") && DEBUG_MODE == true) {
				echo "<strong>A database error occured</strong> - ".$sql." | ".mysql_error().".<br/>\n";
			} else {
				echo "<strong>A database error occured</strong> - Please contact the support.<br/>\n";
			}
		}
		while ($row = @mysql_fetch_assoc($r)) {
			$fixed_row = array();
			$row = $this->encode($row);
			foreach ($this->columns as $key => $value) {
				$fixed_row[$key] = $this->transformColumn($key, $row[$key], $row);
			}
			$data[] = $fixed_row;
		}

        if($this->columns[$this->sortBy]["virtual"] === true) { // Post sort if virtual
            $Presort = new ArrayList($data);
            $data = $Presort->yaGetSortedByColumn($this->sortBy, $this->sortOrder, $this->columns[$this->sortBy]["sort_type"]);
        }

		return $data;
	}

	function getNumberOfPages() {
		$this->searchFilterData();
		$count_column = "*";
		foreach ($this->columns as $key => $value) {
			if ($value["distinct"] == true) {
				if (stripos($key, ".") === false) {
					$columns_sql .= "`" . $this->table . "`.`" . $key . "`";
				} else {
					$columns_sql .= $key;
				}
				$count_column = "DISTINCT " . $columns_sql;
			}
		}
		if($this->custom_sql != "") {
			$result = mysql_query($this->custom_sql);
			$num = mysql_num_rows($result);
		} else {
			$sql = "SELECT COUNT(" . $count_column . ") FROM `" . mysql_real_escape_string($this->table) . "`" . $this->sqlTables . $this->joins . $this->where . $this->groupBy;
			$this->query = $sql;
			if($this->groupBy != "") {
				$num = @mysql_num_rows(mysql_query($sql));
			} else {
				$num = @mysql_result(mysql_query($sql), 0);
			}
		}
		$page_num = (is_numeric($num))? ceil($num / $this->perPage) : 0;
		return array("pages" => $page_num, "items" => $num);
	}

}

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

?>
