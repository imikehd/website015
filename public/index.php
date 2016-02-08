<?php

require '../vendor/autoload.php';

use joernroeder\Pocomd\Config;
use joernroeder\Pocomd\Page;

/**
 * The AppConfig class acts as a middleware between the content loaders and the routing framework (http://flightphp.com/)
 */
class MyAppConfig extends Config {

	protected static $config = array(
		'debug'						=> true,
		'logErrors'					=> true,
		'showErrors'				=> true,
		'cache.enabled'				=> true,
		'cache.path'				=> '../cache/',
		'content.path'				=> '../content/',
		'navigation.sortreverse'	=> true,
		'template.data'				=> array(
			'MaxWidth'	=> '800px'
		)
	);

	public function get($key) {
		if ($val = parent::get($key)) {
			return $val;
		}

		return Flight::has($key) ? Flight::get($key) : null;
	}

	public function set($key, $value) {
		return Flight::set($key, $value);
	}

	public function pathFor($key, $urlSegements) {
		$urlSegements = !is_array($urlSegements) ? array($urlSegements) : $urlSegements;

		return '/' . join('/', $urlSegements);
	}

	public function render(Page $page) {
		return Flight::view()->display($page->getTemplate(), $page->getTemplateData());
	}

	public function notFound() {
		return Flight::notFound();
	}

	public function updateNavigationItem(&$navItem, $page) {
		$additionalKeys = array('Type');

		foreach ($additionalKeys as $key) {
			if ($val = $page->get($key)) {
				$navItem[$key] = $val;
			}
		}
	}
}

// ----------------------------------------

/**
 * Apply AppConfig and global settings to Flight.
 */
Flight::set('config', new MyAppConfig());
Flight::set('flight.log_errors', Flight::get('config')->get('logErrors'));

if (Flight::get('config')->get('showErrors')) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
}

/**
 * Initiate Twig, and register to Flight
 */
$twigLoader = new Twig_Loader_Filesystem('../templates'); 
$twigConfig = array(
	'cache'	=>	Flight::get('config')->get('cache.enabled') ? Flight::get('config')->get('cache.path') . 'twig/' : false,
	'debug'	=>	Flight::get('config')->get('debug'),
);
Flight::register('view', 'Twig_Environment', array($twigLoader, $twigConfig), function($twig) {
	$twig->addExtension(new Twig_Extension_Debug()); // Add the debug extension
	$twig->addGlobal('BaseUrl', Flight::get('flight.base_url'));
});

/**
 * Add navigation hook on start, get all links and store them in config -> 'app.navigation'
 */
Flight::before('start', function(&$params, &$output) {
	if (Flight::has('navigation.loader')) return;

	Flight::get('config')->initNavigation(Flight::request()->url);
});


// ! --- ROUTE: About ---------------------------

Flight::route('/', function() {
	return Flight::view()->display('index.php', Flight::get('config')->getNavigation());
});

// ! --- ROUTE: About ---------------------------

Flight::route('/we', function() {
	return Flight::get('config')->renderPage('about');
});


// ! --- ROUTE: Sitemap -------------------------

/**
 * Publishes all routes in the /sitemap.xml
 */
Flight::route('/sitemap.xml', function () {
	$items = Flight::get('navigation.loader')->getNavigationItems();
	$sitemap = array();

	foreach ($items as $item) {
		$sitemap[] = array(
			'Url' => $item->get('Url'),
			'DateTime' => $item->get('DateTime')
		);
	}

	Flight::response()->header('Content-Type', 'application/xml');
	Flight::view()->display('sitemap.php', array(
		'Sitemap' => $sitemap
	));
});


// ! --- ROUTE: Project -------------------------

Flight::route('/@name', function ($name) {
	return Flight::get('config')->renderPage($name);
});


// ! --- Kick things off! -----------------------

Flight::start();
