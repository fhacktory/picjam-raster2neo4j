<?php
require("vendor/autoload.php");
require(__DIR__ . '/RasterImage.php');
require(__DIR__ . '/Node/PixelNode.php');
require(__DIR__ . '/Node/PointNode.php');

$client = new Everyman\Neo4j\Client();
PixelNode::initLabel($client);
PointNode::initLabel($client);

// open the image
$imageFilepath = __DIR__ . '/../assets/example-image1.png';
$rasterImage = new RasterImage($imageFilepath);


for($x = 0; $x < $rasterImage->getWidth(); $x++) {
	for($y = 0; $y < $rasterImage->getHeight(); $y++) {
		$nodePixel = new PixelNode($x, $y, $client);
		$nodePixel->setColor($rasterImage->getColorAt($x, $y));
		$nodePixel->createInBase()
			->createNodePoints();

		unset($nodePixel);
	}
}

