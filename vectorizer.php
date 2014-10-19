<?php
require("vendor/autoload.php");
require("Svg/SvgBuilder.php");
require("Svg/NodeArity.php");
require("Svg/NodeArityCollection.php");
require("Svg/NodeColor.php");
require("Svg/NodeColorCollection.php");
require("Svg/SvgNode.php");

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
	$node1 = new SvgNode($row['n1']);
	$node2 = new SvgNode($row['n2']);

	$node1Id = $node1->getDbNode()->getId();
	if ($arities->issetNodeArity($node1Id)) {
		$arities->getNodeArity($node1Id)->addNeighbour($node2->getDbNode()->getId());
	} else {
		$arities->setNodeArity($node1Id, new NodeArity(array($node2->getDbNode()->getId())));
		$nodes[] = $node1Id;
		$index[$node1Id] = $node1;
		$colors->initNodeColor($node1Id);
	}

	$node2Id = $node2->getDbNode()->getId();
	if ($arities->issetNodeArity($node2Id)) {
		$arities->getNodeArity($node2Id)->addNeighbour($node1->getDbNode()->getId());
	} else {
		$arities->setNodeArity($node2Id, new NodeArity(array($node1->getDbNode()->getId())));
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

		$arity = $arities->getNodeArity($node->getDbNode()->getId())->count();
		$colorsCount = $colors->getNodeColor($node->getDbNode()->getId())->count();

		$isBorder = $node->isBorder();

		//echo $node->getProperty('x') . ' - ' . $node->getProperty('y') . " : $arity - $colorsCount\n";
		if ((! $isBorder && $arity > $colorsCount) ||  ($isBorder && $arity - 1 > $colorsCount))
		{
			echo "$nodeId - " . $node->getDbNode()->getProperty('x') . ' : ' . $node->getDbNode()->getProperty('y') . "\n";
			$polygon = searchPolygon(array(), $node, $arities, $index, $colors, 1);
			//var_dump(count($polygon));
			$polygons[] = $polygon;
		}
	}

	return $polygons;
}

function purgeUselessNodes($nodes, NodeArityCollection $arities, $index)
{
	$result = array();
	$removed = array();
	foreach($nodes as $nodeId) {
		$node = $index[$nodeId];
		$arity = $arities->getNodeArity($node->getDbNode()->getId());
		if ($arity->count() > 1) {
			$result[] = $nodeId;
			$removed[$node->getDbNode()->getId()] = false;
		} else {
			$removed[$node->getDbNode()->getId()] = true;
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

function searchPolygon($currentPolygon, SvgNode $currentNode, NodeArityCollection $arities, $index, NodeColorCollection $colors, $polygonId) {
	echo "## - " . $currentNode->getDbNode()->getProperty('x') . ' : ' . $currentNode->getDbNode()->getProperty('y') . "\n";
	if(empty($currentPolygon)) {
		// polygon beginning
		$lastNode = $currentNode;
		try {
			$nextNode = getNextNode($currentNode, $lastNode, $arities, $index, $colors);
		} catch( Exception $e) {
			echo $e->getMessage() . "\n";
			return $currentPolygon;
		}
		$res = searchPolygon(array($currentNode), $nextNode, $arities, $index, $colors, $polygonId);
		$colors->getNodeColor($currentNode->getDbNode()->getId())->addColor($polygonId);
		return $res;
	}

	if($currentPolygon[0] == $currentNode) {
		// the polygon is closed
		return $currentPolygon;
	}

	$lastNode = $currentPolygon[count($currentPolygon) - 1];
	$currentPolygon[] = $currentNode;
	$colors->getNodeColor($currentNode->getDbNode()->getId())->addColor($polygonId);
	if($colors->getNodeColor($currentNode->getDbNode()->getId())->count() > $arities->getNodeArity($currentNode->getDbNode()->getId())->count()) {
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

function getNextNode(SvgNode $currentNode, SvgNode $lastNode, NodeArityCollection $arities, $index, NodeColorCollection $colors) {
	$arity = $arities->getNodeArity($currentNode->getDbNode()->getId());
	$neighbours = $arity->getPriorizedNeighbours($currentNode, $lastNode, $index);

	foreach(array(-1, 0, 1) as $priority) {
		if (isset($neighbours[$priority])) {

			$isBorder = $neighbours[$priority]->isBorder();
			$arity = $arities->getNodeArity($neighbours[$priority]->getDbNode()->getId())->count();
			$colorsCount = $colors->getNodeColor($neighbours[$priority]->getDbNode()->getId())->count();

			//echo $node->getProperty('x') . ' - ' . $node->getProperty('y') . " : $arity - $colorsCount\n";
			if ((! $isBorder && $arity > $colorsCount) ||  ($isBorder && $arity - 1 > $colorsCount))
			{
				return $neighbours[$priority];
			}
		}
	}

	throw new Exception('No neighbour ??? for ' . $currentNode->getDbNode()->getId() . " - " . count($neighbours));
}
