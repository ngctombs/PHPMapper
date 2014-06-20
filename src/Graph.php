<?php
/**
 * Graph class file
 */
class Graph extends Functional {
	public $roads = array();
	public $nodes = array();

	public function __construct($graph_data) {
		$this->loadGraph($graph_data);
		$this->compileEdgeLength();
	}

	/**
	 * Loads graph's nodes and roads from an array
	 * @param  array $graph_data array of roads and nodes
	 */
	protected function loadGraph($graph_data) {
		$new_node = function($node) {
			return array(
				new Node(
					$node['key'],
					$node['coords']['x'],
					$node['coords']['y'],
					$node['related_nodes']
				));
		};

		$new_road = function($road) {
			return array(
				new Road(
					$road['name'], 
					$road['nodes']
				));
		};

		$this->roads = $this->functionalForeach($graph_data['roads'], $new_road);
		$this->nodes = $this->functionalForeach($graph_data['nodes'], $new_node);
	}

	/**
	 * Compiles the length of each node's edges using this and the relevant 
	 * node's coords
	 */
	protected function compileEdgeLength() {
		$weigh_nodes = function($node) use (&$weigh_nodes) {
			$k1 =& array_pop($node->related_nodes);
			$p0 =& $this->getNode($node->key)->coords;
			$p1 =& $this->getNode($k1)->coords;
			$distance = GraphCalc::getDistance($p0, $p1); 
			if (count($node->related_nodes) > 0) {
				return array_replace(array($k1 => $distance), $weigh_nodes($node));
			}
			return array($k1 => $distance);
		};

		$this->functionalFor(
			$this->nodes, 
			function($node) use (&$weigh_nodes){
				$weights =& $weigh_nodes($node);
				$node->related_nodes = $weights;
			});
	}

	/**
	 * An implementation of Dijkstra's graph traversal algorithm
	 * @param  string $start_node The node key from which to start
	 * @param  string $end_node   The node key at which we end
	 * @return array             The trace of the shortest path
	 */
	public function getShortestPath($start_node, $end_node) {	
		//Instantiate array of node keys to default distance (INF)
		foreach ($this->nodes as $key => $value){
			$node_stack[$key]['weight'] = INF;
		}

		//Set initial values
		$current_key = $start_node;
		$node_stack[$current_key]['weight'] = 0;
		$node_stack[$current_key]['trace'][$current_key] = 0;

		while (isset($node_stack[$end_node]) && $current_key != $end_node) {
			//Symlink because these paths are too damn long!
			$related_nodes =& $this->getNode($current_key)->related_nodes;

			//set new weights
			foreach($related_nodes as $linked_key => $linked_weight) {
				$new_weight = $node_stack[$current_key]['weight'] + $linked_weight;
				if (isset($node_stack[$linked_key]) && 
					$node_stack[$linked_key]['weight'] > $new_weight) {
					$node_stack[$linked_key]['weight'] = $new_weight;
					$node_stack[$linked_key]['trace'] = $node_stack[$current_key]['trace'];
					$node_stack[$linked_key]['trace'][$linked_key] = $linked_weight;
				}
			}
			unset($node_stack[$current_key]);

			//find next lowest weighed node
			$total_distance = NULL;
			foreach($node_stack as $key => $value) {
				if($value['weight'] < $total_distance || is_null($total_distance)) {
					$current_key = $key;
					$total_distance = $value['weight'];
				}
			}
		}

		//prepare string for transfer (must have an ordered key to avoid losing order)
		$i = 0;
		$json_array = array(
			'directions' => $this->getStreetDirections($node_stack[$end_node]['trace'])
			);
		foreach($node_stack[$end_node]['trace'] as $key => $trace) {
			$json_array['trace'][] = array($i, $key);
			$i++;
		}
		return $json_array;
	}

	/**
	 * Compiles a string of directions to go with the Shortest Path trace
	 * @param  array $trace array of node keys and distances
	 * @return string        returns a string containing the directions in the trace 
	 *                               in human-readable format
	 */
	protected function getStreetDirections($trace) {
		$last_road = "";
		$aggragate_distance = 0;
		$fin_str = "";
		$nodeA = NULL;
		$nodeB = NULL;
		$nodeC = NULL;
		foreach ($trace as $key => $distance) {
			if ($nodeA == NULL) {
				$nodeA = $key;
			} else {
				$nodeC = $nodeB;
				$nodeB = $nodeA;
				$nodeA = $key;
				$current_road =& $this->roads[$this->getRoad($nodeA, $nodeB)]->name;

				if ($last_road == "") {
					$last_road = $current_road;
				}

				if ($last_road != $current_road) {
					$fin_str .= $this->getDirection($last_road, $aggragate_distance);
					$aggragate_distance = $distance;
					$last_road = $current_road;
					if ($nodeC !== NULL) {
						$fin_str .= $this->getTurn(
							$this->getNode($nodeA)->coords,
							$this->getNode($nodeB)->coords,
							$this->getNode($nodeC)->coords,
							$current_road
							);
					}
				} else {
					$aggragate_distance += $distance;
				}
			}
		}
		$fin_str .= $this->getDirection($current_road, $aggragate_distance);

		return $fin_str;
	}

	/**
	 * [getTurn description]
	 * @param  Coordinates $p0   Coordinates of point 0
	 * @param  Coordinates $p1   Coordinates of point 1
	 * @param  Coordinates $p2   Coordinates of query point
	 * @param  string $road name of the road
	 * @return string       returns the display string
	 */
	private function getTurn($p0, $p1, $p2, $road) {
		if (GraphCalc::isRight($p0, $p1, $p2)) {
			return "Turn right on $road <br>";
		}
		return "Turn left on $road <br>";
	}

	/**
	 * Returns a formatted string of the name of the road & the distance
	 * @param  string $name     road name
	 * @param  float $distance  distance travelled on road
	 * @return string           returns the display string
	 */
	private function getDirection($name, $distance) {
		return 'Go down ' . $name . ' for ' . round($distance, 2) . 'km' . '<br><br>';
	}

	/**
	 * Looks for a road containing the two nodes
	 * @param  int $k0 key of the first node
	 * @param  int $k1 key of the second node
	 * @return int|null     either the key of the road or null
	 */
	protected function getRoad($k0, $k1) {
		if (count($this->roads) == 0) return NULL;
		foreach ($this->roads as $key => $road) {
			if (in_array($k0, $road->nodes) && in_array($k1, $road->nodes)) {
				return $key;
			}
		}
		return NULL;
	}

	/**
	 * Helper function to get a node from nodes based on its key
	 * @param  int|string $search_key the key
	 * @return Node             the node Object
	 */
	protected function getNode($search_key) {
		foreach ($this->nodes as $n0) {
			if ($n0->key == $search_key) {
				return $n0;
			}
		}
	}
}