<?php
namespace de\RaumZeitLabor\PartDB2\Tests;
declare(encoding = 'UTF-8');

include("../src/de/RaumZeitLabor/PartDB2/PartDB2.php");

use de\RaumZeitLabor\PartDB2\Auth\User;
use de\RaumZeitLabor\PartDB2\Footprint\Footprint;
use de\RaumZeitLabor\PartDB2\Footprint\FootprintManager;
use de\RaumZeitLabor\PartDB2\PartDB2;

use de\RaumZeitLabor\PartDB2\Category\Category;
use de\RaumZeitLabor\PartDB2\Part\Part;
use de\RaumZeitLabor\PartDB2\StorageLocation\StorageLocation;
use de\RaumZeitLabor\PartDB2\Stock\StockEntry;
use de\RaumZeitLabor\PartDB2\Category\CategoryManager;
use de\RaumZeitLabor\PartDB2\Category\CategoryManagerService;

PartDB2::initialize();


echo "=)))==========================================\n";
echo "Actions performed by this script:\n";
echo "* Drop the old database INCLUDING ALL DATA\n";
echo "* Creates the schema\n";
echo "* Creates a test user (username/password test)\n";
echo "* Imports data from database partdb\n";
echo "=)))==========================================\n";

if (!($_SERVER["argc"] == 2 && $_SERVER["argv"][1] == "--yes")) {
	echo "\n\n";
	echo "If you are sure you want to do this, type YES and hit return.\n";

	$fp = fopen('php://stdin', 'r');
	$data = fgets($fp, 1024);

	if ($data !== "YES\n") {
		echo "Aborting.\n";
		die();
	}
	
}

echo "Performing actions...\n";

mkdir("../src/Proxies");
chmod("../src/Proxies", 0777);

$tool = new \Doctrine\ORM\Tools\SchemaTool(PartDB2::getEM());

$classes = PartDB2::getClassMetaData();

$tool->dropDatabase($classes);
$tool->createSchema($classes);

/* Create initial test user */
$user = new User();
$user->setUsername("test");
$user->setPassword("test");

PartDB2::getEM()->persist($user);

/* Create footprints */

$newFootprints = array();
$newCategories = array();

mysql_connect("localhost", "partdb", "partdb");
mysql_select_db("partdb");

echo "Creating footprints from SetupData/footprints.php\n";

$r = mysql_query("SELECT * FROM footprints");

while ($sFootprint = mysql_fetch_assoc($r)) {
	$footprint = new Footprint();
	$footprint->setFootprint(stripslashes($sFootprint["name"]));

	echo " Adding footprint ".$sFootprint["name"]."\r";
	PartDB2::getEM()->persist($footprint);
	
	$newFootprints[$sFootprint["id"]] = $footprint;
}

echo "\n\Creating categories from existing data\n";

$categoryManager = CategoryManager::getInstance();
$categoryManager->ensureRootExists();

/* Pass 1: Create numeric nodes */

$r = mysql_query("SELECT * FROM categories");

$categories = array();

while ($cat = mysql_fetch_assoc($r)) {
	$categories[] = $cat;
}

addCategoryRecursive($categories, 0, $categoryManager->getRootNode());

function addCategoryRecursive ($aCategories, $currentId, $parent) {
	global $newCategories;
	
	foreach ($aCategories as $aCategory) {
		if ($aCategory["parentnode"] == $currentId) {
			echo "Adding ".sprintf("%40s", $aCategory["name"])."\r";			
			$oCategory = new Category();
			$oCategory->setName(stripslashes($aCategory["name"]));
			$oCategory->setDescription("");
			$oCategory->setParent($parent->getId());
			$category = CategoryManager::getInstance()->addCategory($oCategory);
			
			addCategoryRecursive($aCategories, $aCategory["id"], $category);
			
			$newCategories[$aCategory["id"]] = $oCategory;
		}
	}
	
}

echo "\n";

$r = mysql_query("SELECT * FROM storeloc");

while ($store = mysql_fetch_assoc($r)) {
	$oStorageLocation = new StorageLocation();
	$oStorageLocation->setName(stripslashes($store["name"]));
	PartDB2::getEM()->persist($oStorageLocation);
	echo "Migrating storage location ".sprintf("%-40s", $store["name"])."\r";
	$newStorageLocations[$store["id"]] = $oStorageLocation;
}

echo "\n";

$r = mysql_query("SELECT * FROM parts");

while ($part = mysql_fetch_assoc($r)) {
	$oPart = new Part();
	$oPart->setName(stripslashes($part["name"]));
	$oPart->setComment(stripslashes($part["comment"]));
	$oPart->setFootprint($newFootprints[$part["id_footprint"]]);
	$oPart->setCategory($newCategories[$part["id_category"]]);
	$oPart->setStorageLocation($newStorageLocations[$part["id_storeloc"]]);
	$oPart->setMinStockLevel($part["mininstock"]);
	echo "Migrating part ".sprintf("%-40s", $part["name"])."\r";
	PartDB2::getEM()->persist($oPart);
	
	$oStock = new StockEntry($oPart, $part["instock"]);
	PartDB2::getEM()->persist($oStock);

}



PartDB2::getEM()->flush();

echo "All done.\n";

?>