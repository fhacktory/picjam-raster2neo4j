<?php
require("vendor/autoload.php");
require("Svg/SvgBuilder.php");
require("Svg/NodeArity.php");

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
		$arities[$node1Id]->addNeighbour($node2->getId());
	} else {
		$arities[$node1Id] = new NodeArity(array($node2->getId()));
		$nodes[] = $node1Id;
		$index[$node1Id] = $node1;
		$colors[$node1Id] = array();
	}

	$node2Id = $node2->getId();
	if (isset($arities[$node2Id])) {
		$arities[$node2Id]->addNeighbour($node1->getId());
	} else {
		$arities[$node2Id] = new NodeArity(array($node1->getId()));
		$nodes[] = $node2Id;
		$index[$node2Id] = $node2;
		$colors[$node2Id] = array();
	}
}

do {
	$previousCount = count($nodes);
	list($nodes, $arities) = purgeUselessNodes($nodes, $arities, $index);
	$currentCount = count($nodes);
} while($previousCount !== $currentCount);


$polygons = searchPolygons($nodes, $arities, $index, $colors);
//var_dump(count($polygons));
$svgBuilder = new SvgBuilder();

file_put_contents(__DIR__ . '/test.svg', $svgBuilder->traceSvg($polygons));

function searchPolygons($nodes, $arities, $index, &$colors) {
	$polygons = array();

	foreach($nodes as $nodeId) {
		$node = $index[$nodeId];

		$arity = $arities[$node->getId()]->count();
		$colorsCount = count($colors[$node->getId()]);

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

function purgeUselessNodes($nodes, $arities, $index)
{
	$result = array();
	$removed = array();
	foreach($nodes as $nodeId) {
		$node = $index[$nodeId];
		$arity = $arities[$node->getId()];
		if ($arity->count() > 1) {
			$result[] = $nodeId;
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
		foreach($arity->getNeighbours() as $n => $neighbour) {
			if ($removed[$neighbour]) {
				$arity->removeNeighbour($n);
			}
		}
	}

	return array($result, $arities);
}

function searchPolygon($currentPolygon, $currentNode, $arities, $index, &$colors, $polygonId) {
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
		$colors[$currentNode->getId()][] = $polygonId;
		return $res;
	}

	if($currentPolygon[0] == $currentNode) {
		// the polygon is closed
		return $currentPolygon;
	}

	$lastNode = $currentPolygon[count($currentPolygon) - 1];
	$currentPolygon[] = $currentNode;
	$colors[$currentNode->getId()][] = $polygonId;
	if(count($colors[$currentNode->getId()]) > $arities[$currentNode->getId()]->count()) {
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

function getNextNode($currentNode, $lastNode, $arities, $index, $colors) {
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
	foreach($arity->getNeighbours() as $neighbourId) {
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

			$isBorder = isBorder($neighbours[$priority]);
			$arity = $arities[$neighbours[$priority]->getId()]->count();
			$colorsCount = count($colors[$neighbours[$priority]->getId()]);

			//echo $node->getProperty('x') . ' - ' . $node->getProperty('y') . " : $arity - $colorsCount\n";
			if ((! $isBorder && $arity > $colorsCount) ||  ($isBorder && $arity - 1 > $colorsCount))
			{
				return $neighbours[$priority];
			}
		}
	}

	throw new Exception('No neighbour ??? for ' . $currentNode->getId() . " - " . count($neighbours));
}


function doScalarProduct($vector1, $vector2) {
	return $vector1[0] * $vector2[1] + $vector1[1] * $vector2[0];
}
