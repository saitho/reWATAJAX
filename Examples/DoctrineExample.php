<?php
/**
 * Use of WatajaxDoctrine
 * Not working in this state!
 * Example for implementation in a Symfony 3 controller that returns a json response
 */

use AppBundle\Entity\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

public function ajaxListAction(Request $request) {
	$isAjax = $request->isXmlHttpRequest();
	$response = ['success' => false];
	if($isAjax) {
		$w = new WatajaxDoctrine($this->get('doctrine')->getManager());
		$w->addTable(Product::class);
		$w->columns = [
			'id' => [
				'name' => '#',
				'sort_type' => 'numeric'
			],
			'name' => [
				'name' => $this->get('translator')->trans('label.product_name')
			],
			'productKeyNumber' => [
				'virtual' => true,
				'name' => $this->get('translator')->trans('label.product_keynumber'),
				'dqlSortValue' => 'productKeys',
				'dqlSortReference' => 'id',
				'dqlSortFunc' => 'COUNT'
			],
			'platform' => [
				'virtual' => true,
				'name' => $this->get('translator')->trans('label.platform'),
				'dqlModelValue' => ['platformName'=>'platform->name','LCplatformName'=>'platform->name:lower'],
				'transform' => '<a class="btn btn-!LCplatformName-inversed btn-xs btn-block"><i class="fa fa-!LCplatformName"></i> <small>!platformName</small></a>'
			],
			'tools' => [
				'name' => $this->get('translator')->trans('label.actions'),
				'virtual' => true,
				'transform' => '<a href="'.$this->generateUrl('admin_product_show', ['id' => '!id']).'" class="btn btn-default btn-xs">'.$this->get('translator')->trans('action.show').'</a>'
			]
		];
		// catch echo from watajax class in order to output it via the controller
		ob_start();
		$w->run();
		$content = ob_get_contents();
		ob_end_clean();
		$response = $content;
		return new Response($response);
	}
	return new JsonResponse($response);
}