<?php
require("vendor/autoload.php");


$client = new Everyman\Neo4j\Client();


$queryString = "
	MATCH (n1)-[b:BORDER]-(n2)
	WHERE b.weight > {threshold}
	 AND (n1.x < n2.x OR n1.y < n2.y)
	RETURN n1, n2
	ORDER BY n1.x, n2.y
";

$query = new Everyman\Neo4j\Cypher\Query(
	$client,
	$queryString,
	array(
		'threshold' => 0.1
	)
);

$result = $query->getResultSet();

$nodes = array();
$arities = array();
$index = array();
$colors = array();

foreach($result as $row) {
	$node1 = $row['n1'];
	$node2 = $row['n2'];

	$node1Id = $node1->getId();
	if (isset($arities[$node1Id])) {
		$arities[$node1Id][]= $node2->getId();
	} else {
		$arities[$node1Id] = array($node2->getId());
		$nodes[] = $node1;
		$index[$node1Id] = $node1;
		$colors[$node1Id] = array();
	}

	$node2Id = $node2->getId();
	if (isset($arities[$node2Id])) {
		$arities[$node2Id][]= $node1->getId();
	} else {
		$arities[$node2Id] = array($node1->getId());
		$nodes[] = $node2;
		$index[$node2Id] = $node2;
		$colors[$node2Id] = array();
	}
}

list($nodes, $arities) = purgeUselessNodes($nodes, $arities, $index);

$polygons = searchPolygons($nodes, $arities, $index, $colors);
//var_dump(count($polygons));

$svg = traceSvg($polygons);

file_put_contents(__DIR__ . '/test.svg', $svg);

function traceSvg($polygons) {
	$svg = '';

	foreach($polygons as $polygon) {
		$svg .= polygonToSvg($polygon);
	}

	$header = '
	<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
	<svg width="4cm" height="4cm" viewBox="0 0 400 400"
     xmlns="http://www.w3.org/2000/svg" version="1.1">';
	return $header . $svg . '</svg>';
}

function polygonToSvg($polygon) {
	$path = '<path d="';
	$first = true;
	foreach($polygon as $node) {
		$x = $node->getProperty('x');
		$y = $node->getProperty('y');

		if ($first) {
			$path .= ' M ';
			$first = false;
		} else {
			$path .= ' L ';
		}
		$path .= $x . ' ' . $y;

	}

	$path .= 'z" style="stroke: black;fill:none;"/>';

	return $path;
}


function searchPolygons($nodes, $arities, $index, &$colors) {
	$polygons = array();

	foreach($nodes as $node) {
		$arity = count($arities[$node->getId()]);
		$colorsCount = count($colors[$node->getId()]);

		$isBorder = isBorder($node);

		//echo $node->getProperty('x') . ' - ' . $node->getProperty('y') . " : $arity - $colorsCount\n";
		if ((! $isBorder && $arity > $colorsCount) ||  ($isBorder && $arity - 1 > $colorsCount)) {

			$polygon = searchPolygon(array(), $node, $arities, $index, $colors, 1);
			//var_dump(count($polygon));
			$polygons[] = $polygon;
		}
	}

	return $polygons;
}

function isBorder($node) {
	$width = 20;
	$height = 20;
	$x = $node->getProperty('x');
	$y = $node->getProperty('y');

	return $x == 0 || $y == 0 || $x == $width || $y == $height;
}

function purgeUselessNodes($nodes, $arities)
{
	$result = array();
	$removed = array();
	foreach($nodes as $node) {
		$arity = $arities[$node->getId()];
		if (count($arity) > 1) {
			$result[] = $node;
			$removed[$node->getId()] = false;
		} else {
			$removed[$node->getId()] = true;
		}
	}

	foreach($arities as $id => $arity) {
		if ($removed[$id]) {
			unset($arities[$id]);
			continue;
		}
		foreach($arity as $n => $neighbour) {
			if ($removed[$neighbour]) {
				unset($arities[$id][$n]);
			}
		}
	}

	return array($result, $arities);
}

function searchPolygon($currentPolygon, $currentNode, $arities, $index, &$colors, $polygonId) {
	//echo $currentNode->getProperty('x') . ' - ' . $currentNode->getProperty('y') . "\n";
	if(empty($currentPolygon)) {
		// polygon beginning
		$lastNode = null;
		$nextNode = getNextNode($currentNode, $lastNode, $arities, $index);
		return searchPolygon(array($currentNode), $nextNode, $arities, $index, $colors, $polygonId);
	}

	if($currentPolygon[0] == $currentNode) {
		// the polygon is closed
		return $currentPolygon;
	}

	$lastNode = $currentPolygon[count($currentPolygon) - 1];
	$currentPolygon[] = $currentNode;
	$colors[$currentNode->getId()][] = $polygonId;

	$nextNode = getNextNode($currentNode, $lastNode, $arities, $index);
	return searchPolygon($currentPolygon, $nextNode, $arities, $index, $colors, $polygonId);
}

function getNextNode($currentNode, $lastNode, $arities, $index) {
	if (empty($lastNode)) {
		$lastDirection = array(0, -1);
	} else {
		$lastDirection = array(
			$currentNode->getProperty('x') - $lastNode->getProperty('x'),
			$currentNode->getProperty('y') - $lastNode->getProperty('y')
		);
	}


	$arity = $arities[$currentNode->getId()];

	$neighbours = array();
	foreach($arity as $neighbourId) {
		// we doesn't want to come back to the previous node
		if (! empty($lastNode) && $neighbourId == $lastNode->getId()) {
			continue;
		}
		$neighbour = $index[$neighbourId];
		$direction = array(
			$neighbour->getProperty('x') - $currentNode->getProperty('x'),
			$neighbour->getProperty('y') - $currentNode->getProperty('y')
		);

		$scalarProduct = doScalarProduct($lastDirection, $direction);
		if ($lastDirection[0] != 1) {
			$scalarProduct *= -1;
		}

		$neighbours[$scalarProduct] = $neighbour;
	}


	foreach(array(-1, 0, 1) as $priority) {
		if (isset($neighbours[$priority])) {
			return $neighbours[$priority];
		}
	}

	throw new Exception('No neighbour ??? for ' . $currentNode->getId());
}


function doScalarProduct($vector1, $vector2) {
	return $vector1[0] * $vector2[1] + $vector1[1] * $vector2[0];
}