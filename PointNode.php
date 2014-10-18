<?php
class PointNode
{
	private static $label;

	private $id;
	private $x;
	private $y;
	private $neo4jClient;
	/**
	 * @var PixelNode
	 */
	private $pixelNode;

	const POINT_LABEL = 'Point';

	public function __construct($x, $y, Everyman\Neo4j\Client $neo4jClient)
	{
		$this->x = $x;
		$this->y = $y;
		$this->neo4jClient = $neo4jClient;
	}

	public function setPixelNode(PixelNode $node)
	{
		$this->pixelNode = $node;
		return $this;
	}

	public static function initLabel($client)
	{
		self::$label = $client->makeLabel(PointNode::POINT_LABEL);
		return self::$label;
	}

	public function loadFromBase()
	{
		$queryString = "
			MATCH (p)
			WHERE
				p:Point
				AND p.x = {x}
				AND p.y = {y}
			RETURN p
		";

		$query = new Everyman\Neo4j\Cypher\Query(
			$this->neo4jClient,
			$queryString,
			array(
				'x' => $this->x,
				'y' => $this->y
			)
		);
		$result = $query->getResultSet();

		if ($result->count() > 1) {
			throw new Exception(
				"Invalid number of point nodes for the coordinates ({$this->x}, {$this->y}),
				number : " . $result->count());
		}
		foreach($result as $row) {
			$this->id = $row['p']->getId();
		}
		return $this->id;
	}

	public function createInBase()
	{
		$id = $this->loadFromBase();
		if(! empty($id)) {
			return $this;
		}

		if (empty($this->pixelNode)) {
			throw new Exception("The Pixel Node can't be null to insert a Point Node in DB");
		}

		$this->neo4jClient->startBatch();
		$node = $this->neo4jClient->makeNode(
			array(
				'x' => $this->x,
				'y' => $this->y
			)
		);

		$node->save();

		$pixelRelationship = $this->neo4jClient->makeRelationship();
		$pixelRelationship->setStartNode($node)
			->setEndNode($this->pixelNode->getDbNode())
			->setType('CORNER_OF')
			->save();

		$this->neo4jClient->commitBatch();
		$node->addLabels(array(self::$label));

		return $this;
	}
}
