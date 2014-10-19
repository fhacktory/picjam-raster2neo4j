<?php
require("vendor/autoload.php");
require("Svg/SvgBuilder.php");
require("Svg/NodeArity.php");
require("Svg/NodeArityCollection.php");
require("Svg/NodeColor.php");
require("Svg/NodeColorCollection.php");

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
$arities = new NodeArityCollection();
$index = array();
$colors = new NodeColorCollection();

foreach($result as $row) {
	$node1 = $row['n1'];
	$node2 = $row['n2'];

	$node1Id = $node1->getId();
	if ($arities->issetNodeArity($node1Id)) {
		$arities->getNodeArity($node1Id)->addNeighbour($node2->getId());
	} else {
		$arities->setNodeArity($node1Id, new NodeArity(array($node2->getId())));
		$nodes[] = $node1Id;
		$index[$node1Id] = $node1;
		$colors->initNodeColor($node1Id);
	}

	$node2Id = $node2->getId();
	if ($arities->issetNodeArity($node2Id)) {
		$arities->getNodeArity($node2Id)->addNeighbour($node1->getId());
	} else {
		$arities->setNodeArity($node2Id, new NodeArity(array($node1->getId())));
		$nodes[] = $node2Id;
		$index[$node2Id] = $node2;
		$colors->initNodeColor($node2Id);
	}
}

do {
	$previousCount = count($nodes);
	$nodes = purgeUselessNodes($nodes, $arities, $index);
	$currentCount = count($nodes);
} while($previousCount !== $currentCount);


$polygons = searchPolygons($nodes, $arities, $index, $colors);
//var_dump(count($polygons));
$svgBuilder = new SvgBuilder();

file_put_contents(__DIR__ . '/test.svg', $svgBuilder->traceSvg($polygons));

function searchPolygons($nodes, NodeArityCollection $arities, $index, NodeColorCollection $colors) {
	$polygons = array();

	foreach($nodes as $nodeId) {
		$node = $index[$nodeId];

		$arity = $arities->getNodeArity($node->getId())->count();
		$colorsCount = $colors->getNodeColor($node->getId())->count();

		$isBorder = isBorder($node);

		//echo $node->getProperty('x') . ' - ' . $node->getProperty('y') . " : $arity - $colorsCount\n";
		if ((! $isBorder && $arity > $colorsCount) ||  ($isBorder && $arity - 1 > $colorsCount))
		{
			echo "$nodeId - " . $node->getProperty('x') . ' : ' . $node->getProperty('y') . "\n";
			$polygon = searchPolygon(array(), $node, $arities, $index, $colors, 1);
			//var_dump(count($polygon));
			$polygons[] = $polygon;
		}
	}

	return $polygons;
}

function isBorder($node) {
	$width = 100;
	$height = 100;
	$x = $node->getProperty('x');
	$y = $node->getProperty('y');

	return $x == 0 || $y == 0 || $x == $width || $y == $height;
}

function purgeUselessNodes($nodes, NodeArityCollection $arities, $index)
{
	$result = array();
	$removed = array();
	foreach($nodes as $nodeId) {
		$node = $index[$nodeId];
		$arity = $arities->getNodeArity($node->getId());
		if ($arity->count() > 1) {
			$result[] = $nodeId;
			$removed[$node->getId()] = false;
		} else {
			$removed[$node->getId()] = true;
		}
	}

	foreach($arities->getAllNodeArity() as $id => $arity) {
		if ($removed[$id]) {
			$arities->removeNodeArity($id);
			continue;
		}
		foreach($arity->getNeighbours() as $n => $neighbour) {
			if ($removed[$neighbour]) {
				$arity->removeNeighbour($n);
			}
		}
	}

	return $result;
}

function searchPolygon($currentPolygon, $currentNode, NodeArityCollection $arities, $index, NodeColorCollection $colors, $polygonId) {
	echo "## - " . $currentNode->getProperty('x') . ' : ' . $currentNode->getProperty('y') . "\n";
	if(empty($currentPolygon)) {
		// polygon beginning
		$lastNode = null;
		try {
			$nextNode = getNextNode($currentNode, $lastNode, $arities, $index, $colors);
		} catch( Exception $e) {
			echo $e->getMessage() . "\n";
			return $currentPolygon;
		}
		$res = searchPolygon(array($currentNode), $nextNode, $arities, $index, $colors, $polygonId);
		$colors->getNodeColor($currentNode->getId())->addColor($polygonId);
		return $res;
	}

	if($currentPolygon[0] == $currentNode) {
		// the polygon is closed
		return $currentPolygon;
	}

	$lastNode = $currentPolygon[count($currentPolygon) - 1];
	$currentPolygon[] = $currentNode;
	$colors->getNodeColor($currentNode->getId())->addColor($polygonId);
	if($colors->getNodeColor($currentNode->getId())->count() > $arities->getNodeArity($currentNode->getId())->count()) {
		echo "erreur";
		return $currentPolygon;
	}

	try
	{
		$nextNode = getNextNode($currentNode, $lastNode, $arities, $index, $colors);
	} catch (Exception $e) {
		echo $e->getMessage() . "\n";
		return $currentPolygon;
	}

	return searchPolygon($currentPolygon, $nextNode, $arities, $index, $colors, $polygonId);
}

function getNextNode($currentNode, $lastNode, NodeArityCollection $arities, $index, NodeColorCollection $colors) {
	$arity = $arities->getNodeArity($currentNode->getId());
	$neighbours = $arity->getPriorizedNeighbours($currentNode, $lastNode, $index);

	foreach(array(-1, 0, 1) as $priority) {
		if (isset($neighbours[$priority])) {

			$isBorder = isBorder($neighbours[$priority]);
			$arity = $arities->getNodeArity($neighbours[$priority]->getId())->count();
			$colorsCount = $colors->getNodeColor($neighbours[$priority]->getId())->count();

			//echo $node->getProperty('x') . ' - ' . $node->getProperty('y') . " : $arity - $colorsCount\n";
			if ((! $isBorder && $arity > $colorsCount) ||  ($isBorder && $arity - 1 > $colorsCount))
			{
				return $neighbours[$priority];
			}
		}
	}

	throw new Exception('No neighbour ??? for ' . $currentNode->getId() . " - " . count($neighbours));
}
