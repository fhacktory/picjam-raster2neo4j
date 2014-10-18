<?php
class PixelNode
{
	private static $label;

	private $id;
	private $x;
	private $y;
	private $color = array("red" => null, "green" => null, "blue" => null, "alpha" => null);
	private $neo4jClient;
	private $dbNode = null;

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
		self::$label = $client->makeLabel(PixelNode::PIXEL_LABEL);
		return self::$label;
	}

	public function __toString()
	{
		return $this->x . " - " . $this->y ." : " . json_encode($this->color);
	}

	public function getDbNode()
	{
		if (! empty($this->dbNode)) {
			return $this->dbNode;
		}

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
			$this->dbNode = $row['p'];
			$this->id = $this->dbNode->getId();
		}
		return $this->dbNode;
	}

	public function createInBase()
	{
		$node = $this->getDbNode();
		if(! empty($node)) {
			return $this;
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

		return $this;
	}

	public function createNodePoints()
	{
		// 4 coins : haut-gauche, haut-droit, bas-gauche, bas-droit
		for($px = $this->x; $px <= $this->x + 1; $px++) {
			for($py = $this->y; $py <= $this->y; $py++) {
				$pointNode = new PointNode($px, $py, $this->neo4jClient);
				$pointNode->setPixelNode($this)
					->createInBase();
			}
		}
		// Pour chaque bordure (haut, bas, gauche, droite)
			// si n'existe pas
				// créer la relation avec poids de 2
			// si existe
				// calculer et mettre à jour le poids
	}
}
