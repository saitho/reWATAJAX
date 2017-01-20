<?php
/**
 * @info Watajax JQUERY plugin server side
 * @author West Art Communication AB
 * @version 1.0
 * @changelog
 * 		20100106 1.0 - First release
 * @usage
 * 		// SQL mode
 * 		$w = new WatajaxSql();
 *		$w->columns = array(
 *			"id" => array("name" => "#", "sort_type" => "numeric", "hide" => true),
 *			"firstname" => array("name" => "First name"),
 *			"lastname" => array("name" => "Last name"),
 *			"email" => array("name" => "E-mail adress", "transform" => "<a href=\"mailto:!email\">!email</a>"),
 *			"tools" => array("name" => "#", "virtual" => true, "transform" => "<a href=\"?edit=!id\">edit</a>")
 *		);
 *		$w->run();
 * 		// Array mode is similiar, just use WatajaxArray and populate the $watajax->data array with your data instead.
 */
abstract class Watajax {
	
	public $columns = array();
	public $data = array();
	public $perPage = 10;
	public $page = 1;
	public $sortBy = NULL;
	public $sortOrder = "ASC";
	public $search = "";
	
	public function __construct() {
		$this->perPage = (!empty($_GET['watajax_per_page']) && is_numeric($_GET['watajax_per_page'])) ? intval($_GET['watajax_per_page']) : 10;
		$this->page = (!empty($_GET['watajax_page']) && is_numeric($_GET['watajax_page'])) ? intval($_GET['watajax_page']) : 1;
		$this->sortBy = !empty($_GET['watajax_sortBy']) ? $_GET['watajax_sortBy'] : null;
		$this->sortOrder = !empty($_GET['watajax_sortOrder']) ? $_GET['watajax_sortOrder'] : 'ASC';
		$this->search = !empty($_GET['watajax_search']) ? $_GET['watajax_search'] : '';
	}
	
	public function run() {
		$this->checkForAction();
	}
	
	public function checkForAction() {
		switch($_GET["action"]) {
			case "watajax_load_head":
				echo json_encode(['html' => $this->getHead()]);
				break;
			case "watajax_load_body":
				echo json_encode(['html' => $this->getBody(), 'dataOptions' => $this->getSettings()]);
				break;
			default:
				break;
		}
	}
	
	public function getSettings() {
		$results = $this->getNumberOfResults();
		return ['results' => $results, 'pages' => $this->getNumberOfPages($results)];
	}
	
	abstract public function sortData();
	abstract public function getNumberOfResults();
	public function getNumberOfPages($results=null) {
		if($results == null) {
			$results = $this->getNumberOfResults();
		}
		return ceil($results / $this->perPage);
	}
	abstract public function getData();
	abstract public function searchFilterData();
	
	public function getBody() {
		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData();
		$html = '';
		foreach($sorted_data as $row_id => $row_data) {
			$html .= '<tr id="'.$row_id.'">';
			foreach($this->columns as $column_id => $column_data) {
				if (empty($this->columns[$column_id]["hide"])) {
					$html .= '<td id="'.$column_id.'_data">'.$row_data[$column_id].'</td>';
				}
			}
			$html .= '</tr>';
		}
		return $html;
	}
	
	public function getHead() {
		$html = '<tr>';
		foreach($this->columns as $id => $data) {
			if (empty($data['hide'])) {
				$html .= '<th id="'.$id.'">'.$data['name'].'</th>';
			}
		}
		$html .= '</tr>';
		return $html;
	}
	
}
