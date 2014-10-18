<?php
class NodePixel
{
	private static $label;

	private $id;
	private $x;
	private $y;
	private $color = array("red" => null, "green" => null, "blue" => null, "alpha" => null);
	private $neo4jClient;

	const PIXEL_LABEL = 'Pixel';

	public function __construct($x, $y, Everyman\Neo4j\Client $neo4jClient)
	{
		$this->x = $x;
		$this->y = $y;
		$this->neo4jClient = $neo4jClient;
	}

	public function setColor($rgb)
	{
		$this->color = $rgb;
		return $this;
	}

	public static function initLabel($client)
	{
		self::$label = $client->makeLabel(NodePixel::PIXEL_LABEL);
		return self::$label;
	}

	public function __toString()
	{
		return $this->x . " - " . $this->y ." : " . json_encode($this->color);
	}

	public function loadFromBase()
	{
		$queryString = "
			MATCH (p)
			WHERE
				p:Pixel
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
				"Invalid number of pixel nodes for the coordinates ({$this->x}, {$this->y}),
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
		if (! empty($id)) {
			return;
		}

		$this->neo4jClient->startBatch();
		$node = $this->neo4jClient->makeNode(
			array(
				'x' => $this->x,
				'y' => $this->y
			)
		);

		foreach($this->color as $key => $component) {
			$node->setProperty('color-' . substr($key, 0, 1), $component);
		}

		$node->save();
		$this->neo4jClient->commitBatch();
		$node->addLabels(array(self::$label));
	}
}
