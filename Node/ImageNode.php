<?php
class ImageNode
{
	const NODE_LABEL = 'UNDEFINED';

	protected $id;
	protected $x;
	protected $y;

	protected $neo4jClient;
	protected $dbNode = null;

	public function __construct($x, $y, Everyman\Neo4j\Client $neo4jClient)
	{
		$this->x = $x;
		$this->y = $y;
		$this->neo4jClient = $neo4jClient;
	}

	public static function initLabel($client)
	{
		static::$label = $client->makeLabel(static::NODE_LABEL);
		return static::$label;
	}

	public function __toString()
	{
		return static::NODE_LABEL . " (" . $this->x . ", " . $this->y .")";
	}

	public function getDbNode()
	{
		if (! empty($this->dbNode)) {
			return $this->dbNode;
		}

		$queryString = "
			MATCH (p)
			WHERE
				p:" . static::NODE_LABEL . "
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
				"Invalid number of " . static::NODE_LABEL . " nodes for the coordinates ({$this->x}, {$this->y}),
				number : " . $result->count());
		}
		foreach($result as $row) {
			$this->dbNode = $row['p'];
			$this->id = $this->dbNode->getId();
		}
		return $this->dbNode;
	}
}
