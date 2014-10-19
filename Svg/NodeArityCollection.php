<?php

class NodeArityCollection
{
	private $nodeArity = array();

	public function setNodeArity($nodeId, NodeArity $nodeArity)
	{
		$this->nodeArity[$nodeId] = $nodeArity;
	}

	public function issetNodeArity($nodeId)
	{
		return isset($this->nodeArity[$nodeId]);
	}

	/**
	 * @param $nodeId
	 * @return null|NodeArity
	 */
	public function getNodeArity($nodeId)
	{
		if ($this->issetNodeArity($nodeId)) {
			return $this->nodeArity[$nodeId];
		}
		return null;
	}

	public function getAllNodeArity()
	{
		return $this->nodeArity;
	}

	public function removeNodeArity($nodeId)
	{
		unset($this->nodeArity[$nodeId]);
		return $this;
	}
}
